<?php

namespace App\Library;

/**
 * 滑动验证码轨迹行为验证器（专业风险评分引擎）
 */
class CaptchaTrackValidator
{
    /**
     * 服务端验证轨迹
     * @param array $track 轨迹点数组
     * @param float $targetX 服务端保存的目标绝对 X
     * @param float $targetY 服务端保存的目标绝对 Y
     * @param float $initX 滑块初始绝对 X
     * @param float $initY 滑块初始绝对 Y
     * @param array $options 校验选项参数
     * @return array
     * @author wave
     */
    public static function validate(
        array $track,
        float $targetX,
        float $targetY,
        float $initX = 0.0,
        float $initY = 0.0,
        array $options = []
    ): array {
        // 合并默认校验配置选项
        $options = array_merge([
            'min_points' => 8,
            'max_points' => 500,
            'min_duration' => 180,
            'max_duration' => 8000,
            'target_tolerance_x' => 5,
            'target_tolerance_y' => 20,
            'max_step' => 100,
            'max_speed' => 5.0,
            'risk_threshold' => 60,
        ], $options);

        // 1. 检查轨迹点数是否少于最小值
        if (count($track) < $options['min_points']) {
            return self::fail('轨迹点过少', 30);
        }

        // 检查轨迹点数是否超出最大值
        if (count($track) > $options['max_points']) {
            return self::fail('轨迹点过多', 50);
        }

        // 2. 循环检查每个轨迹点的格式与数据合法性
        foreach ($track as $point) {
            if (!isset($point['x'], $point['y'], $point['t'])) {
                return self::fail('轨迹数据格式错误', 100);
            }
            if (!is_numeric($point['x']) || !is_numeric($point['y']) || !is_numeric($point['t'])) {
                return self::fail('轨迹数据非法', 100);
            }
            if (!is_finite((float)$point['x']) || !is_finite((float)$point['y']) || !is_finite((float)$point['t'])) {
                return self::fail('轨迹数据异常', 100);
            }
        }

        // 初始化基础风险分与统计数据数组
        $baseRisk = 0;
        $dtList = [];
        $distanceList = [];
        $speedList = [];
        $yChanges = [];

        // 3. 循环校验时间单调性、单步位移及速度
        for ($i = 1; $i < count($track); $i++) {
            $prev = $track[$i - 1];
            $curr = $track[$i];

            $dt = (float)$curr['t'] - (float)$prev['t'];
            if ($dt <= 0) {
                return self::fail('轨迹时间戳异常', 100);
            }

            if ($dt < 2) {
                $baseRisk += 5;
            }

            $dx = (float)$curr['x'] - (float)$prev['x'];
            $dy = (float)$curr['y'] - (float)$prev['y'];
            $distance = sqrt($dx * $dx + $dy * $dy);

            if ($distance > $options['max_step']) {
                $baseRisk += 25;
            }

            $speed = $distance / $dt;
            if ($speed > $options['max_speed']) {
                $baseRisk += 15;
            }

            $dtList[] = $dt;
            $distanceList[] = $distance;
            $speedList[] = $speed;
            $yChanges[] = abs($dy);
        }

        // 4. 校验总耗时是否合规
        $duration = (float)$track[count($track) - 1]['t'] - (float)$track[0]['t'];
        if ($duration < $options['min_duration']) {
            $baseRisk += 35;
        }
        if ($duration > $options['max_duration']) {
            $baseRisk += 20;
        }

        // 5. 最终坐标误差校验（将前端相对滑动距离转换为画布绝对坐标）
        $last = $track[count($track) - 1];
        $lastX = $initX + (float)$last['x'];
        $lastY = $initY + (float)$last['y'];

        $errorX = abs($lastX - $targetX);
        $errorY = abs($lastY - $targetY);

        if ($errorX > $options['target_tolerance_x']) {
            return self::fail('X 坐标未到达目标', 100);
        }
        if ($errorY > $options['target_tolerance_y']) {
            return self::fail('Y 坐标异常', 100);
        }

        // 6. 计算物理动力学特征：加速度 (Acceleration) 与 急动度 (Jerk)
        $accelerationList = [];
        for ($i = 1; $i < count($speedList); $i++) {
            $dv = $speedList[$i] - $speedList[$i - 1];
            $dt_avg = ($dtList[$i] + $dtList[$i - 1]) / 2;
            $accelerationList[] = $dt_avg > 0 ? $dv / $dt_avg : 0;
        }

        $jerkList = [];
        for ($i = 1; $i < count($accelerationList); $i++) {
            $da = $accelerationList[$i] - $accelerationList[$i - 1];
            $dt_avg = $dtList[$i + 1] ?? $dtList[$i];
            $jerkList[] = $dt_avg > 0 ? $da / $dt_avg : 0;
        }

        // 7. 计算累计路径距离与路径效率 (Path Efficiency)
        $totalPathDistance = array_sum($distanceList);
        $startX = (float)$track[0]['x'];
        $startY = (float)$track[0]['y'];
        $lastX_local = (float)$track[count($track) - 1]['x'];
        $lastY_local = (float)$track[count($track) - 1]['y'];
        $straightDistance = sqrt(pow($lastX_local - $startX, 2) + pow($lastY_local - $startY, 2));
        
        // 路径效率 = 直线距离 / 实际总路径距离
        $pathEfficiency = $totalPathDistance > 0 ? ($straightDistance / $totalPathDistance) : 0;

        // 获取各项统计学指标
        $dtStats = self::statistics($dtList);
        $speedStats = self::statistics($speedList);
        $accelStats = count($accelerationList) > 0 ? self::statistics($accelerationList) : ['stddev' => 0];
        $jerkStats = count($jerkList) > 0 ? self::statistics($jerkList) : ['stddev' => 0];

        // 8. 检查运动方向是否有异常往复变化
        $directionChanges = 0;
        $lastDirection = null;
        for ($i = 1; $i < count($track); $i++) {
            $dx = (float)$track[$i]['x'] - (float)$track[$i - 1]['x'];
            if (abs($dx) < 0.01) {
                continue;
            }
            $direction = $dx > 0 ? 1 : -1;
            if ($lastDirection !== null && $direction !== $lastDirection) {
                $directionChanges++;
            }
            $lastDirection = $direction;
        }

        // 9. 检查 Y 轴抖动率特征
        $zeroYCount = 0;
        foreach ($yChanges as $change) {
            if ($change < 0.001) {
                $zeroYCount++;
            }
        }
        $yFlatRatio = count($yChanges) > 0 ? ($zeroYCount / count($yChanges)) : 0;

        // 10. 检查末端减速阶段的行为特征
        $decelerationAnomaly = false;
        if (count($speedList) >= 6) {
            $sliceCount = max(2, (int)floor(count($speedList) * 0.25));
            $firstSpeeds = array_slice($speedList, 0, $sliceCount);
            $lastSpeeds = array_slice($speedList, -$sliceCount);
            $firstAvg = array_sum($firstSpeeds) / count($firstSpeeds);
            $lastAvg = array_sum($lastSpeeds) / count($lastSpeeds);
            if ($firstAvg > 0 && $lastAvg > $firstAvg * 1.8) {
                $decelerationAnomaly = true;
            }
        }

        // ==========================================
        // 11. 组合风险特征引擎（多维特征联合判定）
        // ==========================================
        $compositeRisk = $baseRisk;

        // 组合特征 A：检查时间间隔规律性（防机械匀速脚本）
        if ($dtStats['count'] >= 5) {
            if ($dtStats['stddev'] < 1.0 && self::repeatRate($dtList) > 0.75) {
                $compositeRisk += 25;
            }
        }

        // 组合特征 B：完美直线挂特征（路径效率极其接近 1 且无方向变化及 Y 轴绝对平直）
        if ($pathEfficiency > 0.995 && $directionChanges === 0 && $yFlatRatio > 0.95) {
            $compositeRisk += 30;
        }

        // 组合特征 C：速度曲线过于平滑（匀速机械运动）
        if (count($speedList) >= 5 && $speedStats['stddev'] < 0.01) {
            $compositeRisk += 20;
        }

        // 组合特征 D：物理动力学异常（加速度过于死板或急动度突变剧烈）
        if ($accelStats['stddev'] < 0.005) {
            $compositeRisk += 15;
        }
        if ($jerkStats['stddev'] > 15.0) {
            $compositeRisk += 15;
        }

        // 组合特征 E：Y 轴僵硬无自然生物抖动
        if ($yFlatRatio > 0.98 && count($track) > 10) {
            $compositeRisk += 10;
        }

        // 组合特征 F：运动方向无往复或末端加速异常
        if ($directionChanges === 0) {
            $compositeRisk += 5;
        }
        if ($decelerationAnomaly) {
            $compositeRisk += 10;
        }

        // 限制最大风险分不超过 100
        $risk = min(100, (int)$compositeRisk);

        // 如果风险分超过阈值则判定为失败
        if ($risk >= $options['risk_threshold']) {
            return [
                'success' => false,
                'risk' => $risk,
                'reason' => '轨迹风险过高',
                'duration' => round($duration, 2),
                'points' => count($track),
                'path_efficiency' => round($pathEfficiency, 4),
                'error_x' => round($errorX, 2),
                'error_y' => round($errorY, 2),
            ];
        }

        // 轨迹校验通过返回成功
        return [
            'success' => true,
            'risk' => $risk,
            'duration' => round($duration, 2),
            'points' => count($track),
            'path_efficiency' => round($pathEfficiency, 4),
            'error_x' => round($errorX, 2),
            'error_y' => round($errorY, 2),
        ];
    }

    /**
     * 计算数组的统计学指标（平均值与标准差）
     * @param array $values 数值数组
     * @return array
     * @author wave
     */
    private static function statistics(array $values): array
    {
        $count = count($values);
        if ($count === 0) {
            return ['count' => 0, 'avg' => 0, 'stddev' => 0];
        }

        $avg = array_sum($values) / $count;
        $sum = 0;
        foreach ($values as $value) {
            $sum += pow($value - $avg, 2);
        }

        return [
            'count' => $count,
            'avg' => $avg,
            'stddev' => sqrt($sum / $count),
        ];
    }

    /**
     * 计算数组元素数值的重复率
     * @param array $values 数值数组
     * @return float
     * @author wave
     */
    private static function repeatRate(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $frequency = [];
        foreach ($values as $value) {
            $key = (string)round($value, 1);
            $frequency[$key] = ($frequency[$key] ?? 0) + 1;
        }

        return max($frequency) / count($values);
    }

    /**
     * 封装统一的失败返回格式
     * @param string $reason 失败原因
     * @param integer $risk 风险评分
     * @return array
     * @author wave
     */
    private static function fail(string $reason, int $risk): array
    {
        return [
            'success' => false,
            'risk' => min(100, $risk),
            'reason' => $reason,
        ];
    }
}