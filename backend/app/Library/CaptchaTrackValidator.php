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

        // 初始化风险分与统计数据数组
        $risk = 0;
        $dtList = [];
        $distanceList = [];
        $speedList = [];

        // 3. 循环校验时间单调性、单步位移及速度
        for ($i = 1; $i < count($track); $i++) {
            $prev = $track[$i - 1];
            $curr = $track[$i];

            $dt = (float)$curr['t'] - (float)$prev['t'];
            if ($dt <= 0) {
                return self::fail('轨迹时间戳异常', 100);
            }

            if ($dt < 2) {
                $risk += 10;
            }

            $dx = (float)$curr['x'] - (float)$prev['x'];
            $dy = (float)$curr['y'] - (float)$prev['y'];
            $distance = sqrt($dx * $dx + $dy * $dy);

            if ($distance > $options['max_step']) {
                $risk += 30;
            }

            $speed = $distance / $dt;
            if ($speed > $options['max_speed']) {
                $risk += 15;
            }

            $dtList[] = $dt;
            $distanceList[] = $distance;
            $speedList[] = $speed;
        }

        // 4. 校验总耗时是否合规
        $duration = (float)$track[count($track) - 1]['t'] - (float)$track[0]['t'];
        if ($duration < $options['min_duration']) {
            $risk += 35;
        }
        if ($duration > $options['max_duration']) {
            $risk += 20;
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

        // 6. 检查时间间隔规律性（防机械匀速脚本）
        $dtStats = self::statistics($dtList);
        if ($dtStats['count'] >= 5) {
            if ($dtStats['stddev'] < 1.0) {
                $risk += 15;
            }
            if (self::repeatRate($dtList) > 0.75) {
                $risk += 15;
            }
        }

        // 7 & 8. 检查速度曲线平滑度与加速度剧烈变化
        if (count($speedList) >= 5) {
            $speedStats = self::statistics($speedList);
            if ($speedStats['stddev'] < 0.01) {
                $risk += 10;
            }

            for ($i = 1; $i < count($speedList); $i++) {
                if (abs($speedList[$i] - $speedList[$i - 1]) > 3) {
                    $risk += 5;
                }
            }
        }

        // 9. 检查末端减速阶段的行为特征
        if (count($speedList) >= 6) {
            $count = count($speedList);
            $sliceCount = max(2, (int)floor($count * 0.25));

            $firstSpeeds = array_slice($speedList, 0, $sliceCount);
            $lastSpeeds = array_slice($speedList, -$sliceCount);

            $firstAvg = array_sum($firstSpeeds) / count($firstSpeeds);
            $lastAvg = array_sum($lastSpeeds) / count($lastSpeeds);

            if ($firstAvg > 0 && $lastAvg > $firstAvg * 1.8) {
                $risk += 10;
            }
        }

        // 10. 检查 Y 轴抖动率特征
        $yChanges = [];
        for ($i = 1; $i < count($track); $i++) {
            $yChanges[] = abs((float)$track[$i]['y'] - (float)$track[$i - 1]['y']);
        }
        if (count($yChanges) > 5) {
            $zeroCount = 0;
            foreach ($yChanges as $change) {
                if ($change < 0.001) {
                    $zeroCount++;
                }
            }
            if (($zeroCount / count($yChanges)) > 0.98) {
                $risk += 5;
            }
        }

        // 11. 检查运动方向是否有异常往复变化
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
        if ($directionChanges === 0) {
            $risk += 3;
        }

        // 限制最大风险分不超过 100
        $risk = min(100, $risk);

        // 如果风险分超过阈值则判定为失败
        if ($risk >= $options['risk_threshold']) {
            return [
                'success' => false,
                'risk' => $risk,
                'reason' => '轨迹风险过高',
                'duration' => round($duration, 2),
                'points' => count($track),
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
