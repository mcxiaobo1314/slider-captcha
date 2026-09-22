<?php

namespace App\Controller;

use App\Library\CaptchaTrackValidator;
use GdImage;
use App\Library\VisitorIdentity;

/**
 * 滑动验证码控制器（集成高级行为轨迹校验）
 */
class CaptchaController extends BaseController
{

    /**
     * 生成动态规则多锁槽位安全滑动验证码（增强抗图像识别版）
     * @return array
     * @author wave
     */
    public function index()
    {
        // 获取客户端 IP 地址，若不存在则为 unknown
        $clientIp = $this->request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        // 获取请求中携带的访客唯一标识
        $visitorId = (string) ($this->request->getAttribute('visitor_id') ?? '');
        // 根据访客 ID 生成唯一的业务主体标识
        $visitorSubject = VisitorIdentity::subject($visitorId);

        // 构造当前客户端 IP 的访问限制 Redis 键
        $visitorLimitKey = 'captcha_visitor_limit_' . md5($clientIp);
        // 从 Redis 获取当前 IP 对应的历史访客记录
        $cachData = $this->redis->get($visitorLimitKey);
        // 如果缓存数据存在，则解码为数组，否则初始化为空数组
        $ipVisitorData = !empty($cachData) ? json_decode($cachData, true) : [];
        // 限制同一 IP 下的过多不同访客标识请求，防止刷验证码
        if (count($ipVisitorData) > 5) {
            // 超出限制时返回 429 状态码与提示信息
            return ['code' => 429, 'msg' => '刷新过于频繁，请稍后再试'];
        }

        // 如果当前访客主体不在记录中，则将其加入 IP 访客记录数组
        if (!in_array($visitorSubject, $ipVisitorData)) {
            $ipVisitorData[] = $visitorSubject;
        }

        // 构造高频刷新的限流 Redis 键
        $rateLimitKey = 'captcha_rate_limit_' . $visitorSubject;
        // 尝试设置 NX 锁并设置 3 秒过期时间，限制单用户高频刷新
        if (!$this->redis->set($rateLimitKey, 1, ['NX', 'EX' => 3])) {
            // 锁定失败说明刷新过快，返回 429 错误
            return ['code' => 429, 'msg' => '刷新过于频繁，请稍后再试'];
        }

        // 将更新后的 IP 访客记录存回 Redis，有效期 60 秒
        if (!$this->redis->set($visitorLimitKey, json_encode($ipVisitorData), ['EX' => 60])) {
            // 写入失败返回 429 错误
            return ['code' => 429, 'msg' => '刷新过于频繁，请稍后再试'];
        }

        // 构造安全锁 Redis 键，用于限制连续错误过多被封锁的访客
        $lockKey = 'captcha_lock_' . $visitorSubject;
        // 检查该安全锁是否存在
        if ($this->redis->exists($lockKey)) {
            // 如果存在则返回已被安全锁定 15 分钟的提示
            return ['code' => 429, 'msg' => '错误次数过多，已被安全锁定 15 分钟'];
        }

        // 定义画布总宽度
        $width = 320;
        // 定义画布总高度
        $height = 160;
        // 定义滑块标准宽度
        $w = 48;
        // 定义滑块标准高度
        $h = 48;

        // 可选的基础形状池
        $shapePool = ['circle', 'polygon', 'star', 'heart', 'diamond', 'ellipse'];

        $ruleType = random_int(0, 3);
        $realIdx = random_int(0, 2);
        $blockWidth = 48;

        // 1. 先确定右侧目标槽位的起始 X 坐标（整体靠右，避开左侧区域）
        $targetXs = [];
        $baseX = mt_rand(220, 250); // 目标区域整体右移
        $targetXs[] = $baseX;
        $targetXs[] = $targetXs[0] + ($blockWidth + mt_rand(15, 25)); // 槽位之间错开
        $targetXs[] = $targetXs[1] + ($blockWidth + mt_rand(15, 25));

        foreach ($targetXs as &$tx) {
            if ($tx + $w > $width - 5) {
                $tx = $width - $w - 5;
            }
        }
        unset($tx);

        $targetSlots = [];
        $sourceSlots = [];

        // 随机分配 3 个槽位的形状
        $slotShapes = [];
        for ($i = 0; $i < 3; $i++) {
            $slotShapes[$i] = $shapePool[array_rand($shapePool)];
        }

        // 初始化目标槽位
        for ($i = 0; $i < 3; $i++) {
            $targetSlots[$i] = [
                'l' => $targetXs[$i],
                't' => mt_rand(30, 90), // 目标凹槽的随机 Y 坐标
                'scale' => mt_rand(85, 115) / 100,
                'shape' => $slotShapes[$i],
                'is_real' => ($i === $realIdx)
            ];
        }

        // 【核心修复】使用动态递增间距生成左侧 3 个源切片坐标，确保绝对不重叠且不撞目标区
        $sourceXs = [];
        $maxSourceLimit = $targetXs[0] - $w - 20; // 左侧区域的最大右边界安全线

        // 初始起点
        $currentX = mt_rand(15, 30);
        for ($i = 0; $i < 3; $i++) {
            $sourceXs[$i] = $currentX;
            // 下一个切片的起点 = 当前起点 + 宽度 + 随机间距(10~25像素)
            $currentX += $w + mt_rand(10, 25);
        }

        // 如果超出左侧安全边界，进行整体压缩调整
        if ($sourceXs[2] + $w > $maxSourceLimit) {
            $availableSpace = max(60, $maxSourceLimit - 15);
            $step = (int)($availableSpace / 3);
            for ($i = 0; $i < 3; $i++) {
                $sourceXs[$i] = 15 + ($i * $step);
            }
        }

        for ($i = 0; $i < 3; $i++) {
            if ($i === $realIdx) {
                // 真实源切片的 Y 坐标、形状、缩放必须与目标槽位完全一致
                $sourceSlots[$i] = [
                    'l' => $sourceXs[$i],
                    't' => $targetSlots[$realIdx]['t'], // 强制 Y 轴相同！
                    'scale' => $targetSlots[$realIdx]['scale'],
                    'shape' => $targetSlots[$realIdx]['shape'],
                    'is_real' => true
                ];
            } else {
                // 虚假切片可以各自随机
                $sourceSlots[$i] = [
                    'l' => $sourceXs[$i],
                    't' => mt_rand(30, 90),
                    'scale' => mt_rand(85, 115) / 100,
                    'shape' => $slotShapes[$i],
                    'is_real' => false
                ];
            }
        }

        // 记录真实目标的绝对坐标供后端校验
        $initX = (float)$sourceSlots[$realIdx]['l'];
        $initY = (float)$sourceSlots[$realIdx]['t'];
        $targetX = (float)$targetSlots[$realIdx]['l'];
        $targetY = (float)$targetSlots[$realIdx]['t'];

        // 定义服务端加密密钥混淆串
        $secretKey = '#12@Ac&qwer$%' . uniqid();
        // 生成服务端会话 Token
        $serverToken = hash_hmac('sha256', $visitorSubject . '-' . getTimestamp(), $secretKey);

        // 构造验证码缓存 Redis 键
        $captchaCacheKey = 'captcha_cache_' . $visitorSubject;
        // 将验证码核心校验数据存入 Redis，有效期 180 秒
        if (!$this->redis->set($captchaCacheKey, json_encode([
            'token' => $serverToken,
            'init_x' => $initX,
            'init_y' => $initY,
            'target_x' => $targetX,
            'target_y' => $targetY,
            'real_idx' => $realIdx,
            'rule_type' => $ruleType
        ]), ['EX' => 180])) {
            // 写入失败返回错误
            return ['code' => 500, 'msg' => '验证码初始化缓存失败'];
        }

        // 创建真彩色画布用于绘制底图
        $srcImage = imagecreatetruecolor($width, $height);
        // 循环为背景填充渐变噪点行
        // 使用 challenge seed，保证同一验证码的纹理可以复现
        $seed = random_int(1, PHP_INT_MAX);

        mt_srand($seed);

        // 背景基础颜色
        $baseR = mt_rand(90, 170);
        $baseG = mt_rand(90, 170);
        $baseB = mt_rand(100, 190);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {

                $wave1 = sin($x / 23.0 + $seed * 0.0001);
                $wave2 = cos($y / 19.0 + $seed * 0.0002);
                $wave3 = sin(($x + $y) / 31.0);

                $lowFreq = (
                    $wave1 * 10 +
                    $wave2 * 9 +
                    $wave3 * 7
                );

                $noise = mt_rand(-14, 14);
                $local = sin($x / 7.0 + $y / 11.0) * 5;

                $r = (int) round($baseR + $lowFreq + $local + $noise);
                $g = (int) round($baseG + $lowFreq * 0.85 + $noise);
                $b = (int) round($baseB + $lowFreq * 1.15 + $local + $noise);

                $r = max(0, min(255, $r));
                $g = max(0, min(255, $g));
                $b = max(0, min(255, $b));

                $color = imagecolorallocate($srcImage, $r, $g, $b);
                imagesetpixel($srcImage, $x, $y, $color);
            }
        }

        // 1. 绘制少量结构干扰线（有效破坏 AI 的边缘检测与轮廓提取）
        for ($k = 0; $k < 6; $k++) {
            $lineColor = imagecolorallocatealpha(
                $srcImage,
                rand(70, 190),
                rand(70, 190),
                rand(70, 190),
                rand(65, 90)
            );

            imageline(
                $srcImage,
                rand(0, $width - 1),
                rand(0, $height - 1),
                rand(0, $width - 1),
                rand(0, $height - 1),
                $lineColor
            );
        }

        // 2. 绘制高密度的微小噪点（破坏局部纹理连续性，防止 AI 通过图像分割去除）
        for ($k = 0; $k < 20; $k++) {
            $dotColor = imagecolorallocatealpha(
                $srcImage,
                rand(60, 220),
                rand(60, 220),
                rand(60, 220),
                rand(70, 100)
            );

            imagefilledellipse(
                $srcImage,
                rand(0, $width - 1),
                rand(0, $height - 1),
                rand(1, 3),
                rand(1, 3),
                $dotColor
            );
        }

        // 遍历所有目标槽位，在底图上应用形状挖空蒙版效果
        foreach ($targetSlots as $slot) {
            $srcImage = $this->applyShapeMask($srcImage, $slot['l'], $slot['t'], $w, $h, $slot['shape'], $slot['scale'], true);
        }

        // 先对整张背景图应用波浪扭曲干扰特效（必须在挖空和切片之前或正确时机完成）
        // 注意：如果你希望滑块能对上，最稳妥的方式是从已经挖空并扭曲好的 $srcImage 中裁剪并做独立纹理融合！
        $srcImage = $this->applyWaveDistortion($srcImage, $width, $height, $ruleType);

        // 初始化滑块图片数组
        $blockImages = [];
        // 初始化滑块关系数据数组
        $blockData = [];
        // 循环生成 3 个滑块的切片图像
        for ($i = 0; $i < 3; $i++) {
            // 获取当前源切片槽位
            $slot = $sourceSlots[$i];
            // 【关键修复】如果是真实滑块，应该以其对应的目标槽位（$targetSlots[$realIdx]）的坐标去扭曲后的背景中提取轮廓基底，
            // 确保滑块的形态和背景凹槽完全契合
            $targetSlot = $targetSlots[$i];

            // 创建临时滑块图像资源
            $tempBlock = imagecreatetruecolor($w, $h);
            imagealphablending($tempBlock, false);
            imagesavealpha($tempBlock, true);
            $transparentColor = imagecolorallocatealpha($tempBlock, 0, 0, 0, 127);
            imagefill($tempBlock, 0, 0, $transparentColor);
            imagealphablending($tempBlock, true);

            // 从已经完成挖空和波浪扭曲的主图中，按目标槽位坐标精准提取像素作为基底
            $extractX = max(0, min((int)$targetSlot['l'], $width - $w));
            $extractY = max(0, min((int)$targetSlot['t'], $height - $h));

            // 复制背景对应区域到滑块
            imagecopy($tempBlock, $srcImage, 0, 0, $extractX, $extractY, $w, $h);

            // 独立颜色基数，用于破坏纯粹的模板匹配，但保留边缘几何契合度
            $baseR = mt_rand(30, 180);
            $baseG = mt_rand(30, 180);
            $baseB = mt_rand(30, 180);

            // 1. 【核心优化】在遍历前预生成该形状尺寸的“真随机”噪点矩阵与透明度矩阵
            // 这样可以确保：① 每次生成完全不同（真随机）；② 保证源切片与目标槽位的噪点绝对一致（能对上凹槽）
            $noiseMatrix = [];
            $alphaMatrix = [];
            for ($px = 0; $px < $w; $px++) {
                for ($py = 0; $py < $h; $py++) {
                    $noiseMatrix[$px][$py] = mt_rand(-15, 15) / 100.0;
                    // 将 alpha 阶梯化（比如 4 档：0, 5, 10, 15），大幅减少 GD 库颜色分配数量，防止颜色表爆满
                    $alphaMatrix[$px][$py] = (int)(mt_rand(0, 3) * 5);
                }
            }

            // 2. 确保图像正确初始化为支持 Alpha 通道的真彩色画布
            imagesavealpha($tempBlock, true);
            $transparentColor = imagecolorallocatealpha($tempBlock, 0, 0, 0, 127);
            imagefill($tempBlock, 0, 0, $transparentColor);

            // 3. 遍历像素进行混色处理
            for ($px = 0; $px < $w; $px++) {
                for ($py = 0; $py < $h; $py++) {
                    if (!$this->isInsideShape($px, $py, $w, $h, $slot['shape'], $slot['scale'])) {
                        imagesetpixel($tempBlock, $px, $py, $transparentColor);
                    } else {
                        // 获取当前背景切片的像素颜色进行色彩重映射混色
                        $rgb = imagecolorat($tempBlock, $px, $py);
                        $origR = ($rgb >> 16) & 0xFF;
                        $origG = ($rgb >> 8) & 0xFF;
                        $origB = $rgb & 0xFF;

                        // 使用预生成的真随机矩阵值
                        $noiseFactor = $noiseMatrix[$px][$py];
                        $r = max(0, min(255, (int)(($origR * 0.4 + $baseR * 0.6) * (1.0 + $noiseFactor))));
                        $g = max(0, min(255, (int)(($origG * 0.4 + $baseG * 0.6) * (1.0 + $noiseFactor))));
                        $b = max(0, min(255, (int)(($origB * 0.4 + $baseB * 0.6) * (1.0 + $noiseFactor))));

                        $alpha = $alphaMatrix[$px][$py];
                        $pixelColor = imagecolorallocatealpha($tempBlock, $r, $g, $b, $alpha);
                        imagesetpixel($tempBlock, $px, $py, $pixelColor);
                    }
                }
            }

            // 输出缓冲区转 Base64
            ob_start();
            imagepng($tempBlock);
            $blockBase64 = 'data:image/png;base64,' . base64_encode(ob_get_clean());
            imagedestroy($tempBlock);

            $key = md5($blockBase64 . $slot['l'] . $slot['t'] . getTimestamp());
            if ($slot['is_real'] === true) {
                $blockData['is_real'][$realIdx] = $key;
            }
            $blockData['lists'][] = $key;

            $blockImages[$key] = [
                'base64' => $blockBase64,
                'x' => $slot['l'],
                'y' => $slot['t']
            ];
        }


        // 将滑块映射关系存入 Redis，有效期 180 秒
        $this->redis->set(
            'captcha_blocks_' . $visitorSubject,
            json_encode($blockData),
            ['EX' => 180]
        );

        // 循环绘制干扰弧线，进一步增加图片识别难度
        for ($i = 0; $i < 25; $i++) {
            $arcColor = imagecolorallocatealpha($srcImage, rand(0, 255), rand(0, 255), rand(0, 255), rand(20, 60));
            imagearc($srcImage, rand(0, $width), rand(0, $height), rand(30, 120), rand(30, 120), 0, 360, $arcColor);
        }

        // 定义九宫格拼图碎片切片列数
        $cols = 3;
        // 定义九宫格拼图碎片切片行数
        $rows = 3;
        // 计算每个碎片切片的宽度
        $pieceWidth = (int)($width / $cols);
        // 计算每个碎片切片的高度
        $pieceHeight = (int)($height / $rows);

        // 初始化背景拼图碎片数组
        $bgPieces = [];
        // 生成 0 到 8 的数字索引数组
        $indices = range(0, 8);
        // 如果规则类型为 2 或 3，则打乱背景切片顺序实现拼图还原效果
        if ($ruleType === 2 || $ruleType === 3) {
            shuffle($indices);
        }

        // 遍历打乱后的索引生成背景碎片
        foreach ($indices as $visualPos => $origIndex) {
            $origRow = (int)($origIndex / $cols);
            $origCol = $origIndex % $cols;

            $srcX = $origCol * $pieceWidth;
            $srcY = $origRow * $pieceHeight;
            $curW = ($origCol == $cols - 1) ? ($width - $srcX) : $pieceWidth;
            $curH = ($origRow == $rows - 1) ? ($height - $srcY) : $pieceHeight;

            // 创建碎片图像资源
            $pieceImg = imagecreatetruecolor($curW, $curH);
            // 从主图复制对应区域
            imagecopy($pieceImg, $srcImage, 0, 0, $srcX, $srcY, $curW, $curH);

            // 开启缓冲区并转为 Base64
            ob_start();
            imagepng($pieceImg);
            $pieceData = 'data:image/png;base64,' . base64_encode(ob_get_clean());
            // 销毁碎片资源
            imagedestroy($pieceImg);

            // 将碎片数据存入数组
            $bgPieces[] = [
                'index' => $origIndex,
                'base64' => $pieceData
            ];
        }


        // 销毁主背景图像资源
        imagedestroy($srcImage);

        // 返回成功响应，包含客户端渲染所需的所有数据
        return [
            'code' => 200,
            'token' => $serverToken,
            'bgPieces' => $bgPieces,
            'blockImages' => $blockImages
        ];
    }

    /**
     * 对背景图像应用形状蒙版处理（优化凹槽视觉与边缘过渡）
     *
     * 特点：
     * 1. 修复边界越界问题
     * 2. 修复 <= 导致的 off-by-one 偏差
     * 3. 增加完整坐标检查
     * 4. 形状边缘采用平滑软过渡（Smoothstep）
     * 5. 背景凹槽增加明显度（通过独立亮度因子及压暗处理）
     * 6. 保留原始背景纹理细节
     * 7. 使用确定性扰动（SHA-256 哈希），避免每次生成完全不同的随机噪声
     * 8. 不依赖 GD 库的 Alpha 混合来表现凹槽，直接写入固定的 RGB 以防凹槽被冲淡
     *
     * @param \GdImage $img          目标 GD 图像资源
     * @param int      $l            左侧绝对坐标 (Left)
     * @param int      $t            顶部绝对坐标 (Top)
     * @param int      $w            形状宽度
     * @param int      $h            形状高度
     * @param string   $shapeType    形状类型（如 circle, polygon 等）
     * @param float    $scale        缩放比例
     * @param bool     $isBackground 是否为背景凹槽（true: 背景凹槽，false: 滑块本身）
     * @param int|null $challengeSeed 挑战验证码种子（用于生成确定性噪声）
     * @return \GdImage 处理后的 GD 图像资源
     */
    private function applyShapeMask(
        $img,
        int $l,
        int $t,
        int $w,
        int $h,
        string $shapeType,
        float $scale,
        bool $isBackground = true,
        ?int $challengeSeed = null
    ) {
        // 1. 验证传入的图像资源是否合法
        if (!($img instanceof \GdImage)) {
            throw new \InvalidArgumentException('Invalid GD image resource.');
        }

        // 2. 防止异常的宽高参数引发无限循环或死循环
        if ($w <= 0 || $h <= 0) {
            return $img;
        }

        // 3. 严格限制 scale 缩放比例在安全范围内 (0.50 ~ 1.50)
        $scale = max(0.50, min(1.50, $scale));

        $imageWidth  = imagesx($img);
        $imageHeight = imagesy($img);

        // 4. 边界裁剪检查：若切片与目标画布完全没有交集，则直接返回原图
        if (
            $l >= $imageWidth ||
            $t >= $imageHeight ||
            ($l + $w) <= 0 ||
            ($t + $h) <= 0
        ) {
            return $img;
        }

        // 5. 开启 Alpha 混合与透明通道保存支持
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // 6. 计算实际安全的像素遍历范围（防止越界溢出画布）
        $startX = max(0, $l);
        $startY = max(0, $t);
        $endX   = min($imageWidth, $l + $w);
        $endY   = min($imageHeight, $t + $h);

        // 7. 定义边缘过渡范围（3~5 像素最佳，过大会导致凹槽边缘模糊发虚）
        $edgeSize = max(
            2.0,
            min(5.0, min($w, $h) * 0.10)
        );

        // 8. 若未提供 challengeSeed，则自动生成一个安全的随机种子
        if ($challengeSeed === null) {
            $challengeSeed = random_int(1, PHP_INT_MAX);
        }

        // 9. 双层循环遍历图像区域中的每一个像素点
        for ($y = $startY; $y < $endY; $y++) {
            for ($x = $startX; $x < $endX; $x++) {

                // 计算当前像素相对于形状局部的相对坐标
                $relX = $x - $l;
                $relY = $y - $t;

                // 判断当前坐标点是否处于几何形状内部，若在外部则跳过处理
                if (
                    !$this->isInsideShape(
                        $relX,
                        $relY,
                        $w,
                        $h,
                        $shapeType,
                        $scale
                    )
                ) {
                    continue;
                }

                // 获取当前像素点的原始 RGB 颜色值
                $rgb = imagecolorat($img, $x, $y);
                $origR = ($rgb >> 16) & 0xFF;
                $origG = ($rgb >> 8) & 0xFF;
                $origB = $rgb & 0xFF;

                // 10. 计算确定性伪随机扰动（基于 Seed 与相对坐标生成哈希，保持抗 AI 特征但可复现）
                $hash = hash(
                    'sha256',
                    $challengeSeed . ':' . $relX . ':' . $relY
                );
                $noiseInt = hexdec(substr($hash, 0, 4));
                // 将哈希值映射为 -3% ~ +3% 的微小噪声浮动
                $noise = (($noiseInt / 65535) * 0.06) - 0.03;

                // 11. 根据是“背景凹槽”还是“滑块本身”执行不同的亮度因子策略
                if ($isBackground) {
                    // 背景凹槽基础压暗因子（核心暗化：0.72 ~ 0.78 左右，使凹槽具有明显深度）
                    $baseFactor = 0.75;

                    // 估算当前像素到形状边缘的距离，用于实现边缘软化
                    $distance = $this->estimateShapeEdgeDistance(
                        $relX,
                        $relY,
                        $w,
                        $h,
                        $shapeType,
                        $scale
                    );

                    // 边缘软过渡处理
                    if ($distance < $edgeSize) {
                        $ratio = max(
                            0.0,
                            min(1.0, $distance / $edgeSize)
                        );
                        // 使用 Smoothstep 公式使过渡更加平滑自然
                        $smooth = $ratio * $ratio * (3.0 - 2.0 * $ratio);

                        // 边缘处逐渐过渡回原图亮度，中心保持明显的暗化深度
                        $factor = 1.0 - ((1.0 - $baseFactor) * $smooth);
                    } else {
                        // 形状中心区域保持固定的暗化系数
                        $factor = $baseFactor;
                    }

                    // 叠加轻微的确定性扰动噪声
                    $factor += $noise;

                    // 严格限制亮度因子上下限，防止过度曝光或死黑
                    $factor = max(
                        0.68,
                        min(0.82, $factor)
                    );

                } else {
                    // 滑块本身：基本保持原始纹理，仅叠加微弱噪声防止 AI 纯图特征识别
                    $factor = 1.0 + ($noise * 0.5);
                }

                // 12. 计算最终的 RGB 颜色通道值
                $r = max(0, min(255, (int)round($origR * $factor)));
                $g = max(0, min(255, (int)round($origG * $factor)));
                $b = max(0, min(255, (int)round($origB * $factor)));

                // 13. 分配固定 RGB 颜色（此处不使用带 Alpha 的混合，避免 GD 透明混合把凹槽视觉冲淡）
                $pixelColor = imagecolorallocate(
                    $img,
                    $r,
                    $g,
                    $b
                );

                // 将处理后的像素写回目标画布
                imagesetpixel(
                    $img,
                    $x,
                    $y,
                    $pixelColor
                );
            }
        }

        return $img;
    }

    /**
     * 估算当前像素到几何形状边缘的距离
     *
     * 该方法用于实现边缘软化与平滑过渡效果，不用于安全校验。
     * 通过向四个方向进行高效的局部搜索，寻找最近的形状边界或画布边缘。
     *
     * @param float  $x         当前像素相对于形状局部的 X 坐标
     * @param float  $y         当前像素相对于形状局部的 Y 坐标
     * @param int    $w         形状的总宽度
     * @param int    $h         形状的总高度
     * @param string $shapeType 形状类型（如 circle, polygon 等）
     * @param float  $scale     形状缩放比例
     * @return float 返回估算的边缘距离（单位：像素），若在外部或达到最大搜索距离则返回相应阈值
     */
    private function estimateShapeEdgeDistance(
        float $x,
        float $y,
        int $w,
        int $h,
        string $shapeType,
        float $scale
    ): float {

        // 1. 前置检查：当前像素必须首先确认位于形状内部，否则距离视为 0
        if (!$this->isInsideShape($x, $y, $w, $h, $shapeType, $scale)) {
            return 0.0;
        }

        // 2. 定义简单局部搜索的最大探测半径（1~6 像素范围），避免全图计算带来的性能开销
        $maxDistance = 6;

        // 3. 从半径 1 开始向外逐层扩散搜索
        for ($d = 1; $d <= $maxDistance; $d++) {
            // 定义上下左右四个正交方向的探测点坐标
            $points = [
                [$x + $d, $y], // 右
                [$x - $d, $y], // 左
                [$x, $y + $d], // 下
                [$x, $y - $d], // 上
            ];

            foreach ($points as $point) {
                $px = $point[0];
                $py = $point[1];

                // 如果探测点超出了形状切片的边界，说明已经到达边缘
                if ($px < 0 || $py < 0 || $px >= $w || $py >= $h) {
                    return (float)$d;
                }

                // 如果探测点落在了形状外部，说明当前点靠近边界，返回当前的步长作为距离
                if (!$this->isInsideShape($px, $py, $w, $h, $shapeType, $scale)) {
                    return (float)$d;
                }
            }
        }

        // 4. 若在最大搜索范围内未碰到边缘，则判定该点处于形状中心深处，返回最大距离
        return (float)$maxDistance;
    }

    /**
     * 判断坐标点是否在指定的几何形状内部
     * @param float|int $x 相对横坐标
     * @param float|int $y 相对纵坐标
     * @param int $w 宽度
     * @param int $h 高度
     * @param string $shapeType 形状类型
     * @param float $scale 缩放比例
     * @return bool
     * @author wave
     */
    private function isInsideShape($x, $y, $w, $h, $shapeType, $scale = 1.0)
    {
        $cx = $w / 2;
        $cy = $h / 2;
        $adjX = ($x - $cx) / max(0.4, $scale) + $cx;
        $adjY = ($y - $cy) / max(0.4, $scale) + $cy;

        switch ($shapeType) {
            case 'circle':
                return pow($adjX - $cx, 2) + pow($adjY - $cy, 2) <= pow(min($w, $h) / 2, 2);
            case 'rectangle':
                return $adjX >= 2 && $adjX <= $w - 2 && $adjY >= 2 && $adjY <= $h - 2;
            case 'triangle':
                return $adjY >= 2 && $adjY <= $h - 2 && abs($adjX - $cx) <= (($w / 2) * ($adjY / $h));
            case 'polygon':
                return abs($adjX - $cx) + abs($adjY - $cy) <= min($w, $h) / 2;
            case 'star':
                $angle = atan2($adjY - $cy, $adjX - $cx);
                $rad = (min($w, $h) / 2) * (0.5 + 0.5 * abs(cos(5 * $angle)));
                return pow($adjX - $cx, 2) + pow($adjY - $cy, 2) <= pow($rad, 2);
            case 'heart':
                $nx = ($adjX - $cx) / ($w / 4);
                $ny = ($cy - $adjY) / ($h / 4);
                return pow($nx, 2) + pow($ny - pow(abs($nx), 2 / 3), 2) <= 1.2;
            case 'ellipse':
                return (pow($adjX - $cx, 2) / pow($w / 2, 2) + pow($adjY - $cy, 2) / pow($h / 3, 2)) <= 1;
            case 'diamond':
                return abs($adjX - $cx) + abs($adjY - $cy) <= $w / 2.2;
            default:
                return true;
        }
    }

    /**
     * 对背景图像应用正弦波浪扭曲特效
     * @param GdImage $img 图像资源
     * @param integer $width 图像宽度
     * @param integer $height 图像高度
     * @param integer $ruleType 规则类型
     * @return GdImage
     * @author wave
     */
    private function applyWaveDistortion($img, $width, $height, $ruleType)
    {
        // 创建目标扭曲画布
        $dest = imagecreatetruecolor($width, $height);
        // 分配白色背景色
        $backgroundColor = imagecolorallocate($dest, 255, 255, 255);
        // 填充背景
        imagefill($dest, 0, 0, $backgroundColor);

        // 随机生成波相
        $phase = mt_rand(0, 628) / 100.0;
        // 根据规则类型设定周期
        $period = mt_rand(20, 40);
        // 根据规则类型设定振幅
        $amplitude = ($ruleType === 3) ? mt_rand(3, 7) : mt_rand(1, 3);

        // 循环双坐标应用正弦波像素位移
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $offset = (int)(sin(($y / $period) + $phase) * $amplitude + cos($x / ($period * 1.5)) * ($amplitude / 2));
                $newX = $x + $offset;
                if ($newX >= 0 && $newX < $width) {
                    $rgb = imagecolorat($img, $x, $y);
                    imagesetpixel($dest, $newX, $y, $rgb);
                }
            }
        }
        // 销毁原图资源
        imagedestroy($img);
        // 返回扭曲后的图像资源
        return $dest;
    }

    /**
     * 校验滑动轨迹、耗时与绝对拼图位置
     * @return array
     * @author wave
     */
    public function verify()
    {
        // 获取前端提交的解析请求体数据
        $data = $this->request->getParsedBody();
        // 获取前端提交的滑块相对移动距离
        $userMoveDelta = isset($data['x']) ? floatval($data['x']) : 0.0;
        // 获取客户端 Token
        $clientToken = isset($data['token']) ? (string)$data['token'] : '';
        // 获取用户选择的滑块索引
        $selectIdx = isset($data['select_idx']) ? intval($data['select_idx']) : 0;
        // 获取用户选择的滑块哈希键
        $selectKey = isset($data['select_key']) ? (string)$data['select_key'] : '';
        // 获取用户移动的轨迹点数组
        $track = isset($data['track']) && is_array($data['track']) ? $data['track'] : [];

        // 获取访客 Cookie 身份标识
        $visitorId = (string) ($this->request->cookie(VisitorIdentity::cookieName()) ?? '');
        // 校验访客身份是否存在且有效
        if (empty($visitorId) || !VisitorIdentity::verify($visitorId)) {
            return ['code' => 429, 'msg' => '访问身份缺失或无效'];
        }
        // 生成业务主体标识
        $visitorSubject = VisitorIdentity::subject($visitorId);

        // 定义安全锁与失败计数键
        $lockKey = 'captcha_lock_' . $visitorSubject;
        $failCountKey = 'captcha_fail_count_' . $visitorSubject;

        // 检查当前用户是否已被安全锁定
        if ($this->redis->exists($lockKey)) {
            return ['code' => 429, 'msg' => '错误次数过多，已被安全锁定 15 分钟'];
        }

        // 获取验证码缓存键
        $captchaCacheKey = 'captcha_cache_' . $visitorSubject;
        // 读取并解码缓存的验证码数据
        $captchaData = json_decode($this->redis->get($captchaCacheKey), true);

        // 获取滑块映射缓存键
        $captchaBlocksKey = 'captcha_blocks_' . $visitorSubject;
        // 读取并解码滑块映射数据
        $blockData = json_decode($this->redis->get($captchaBlocksKey), true);

        // 验证完成后立即销毁 Redis 中的缓存，防止重复利用
        $this->redis->del($captchaCacheKey);
        $this->redis->del($captchaBlocksKey);

        // 检查验证码缓存是否失效
        if (empty($captchaData) || !isset($captchaData['token'])) {
            return ['code' => 500, 'msg' => '验证码已过期或已被作废，请刷新重试'];
        }

        // 检查滑块数据是否失效
        if (empty($blockData) || !isset($blockData['is_real']) || empty($blockData['lists'])) {
            return ['code' => 500, 'msg' => '验证码已过期或已被作废，请刷新重试'];
        }

        // 校验用户选择的滑块是否合法以及是否匹配真实滑块
        if (
            !in_array($selectKey, $blockData['lists']) ||
            !isset($blockData['is_real'][$selectIdx]) ||
            $blockData['is_real'][$selectIdx] !== $selectKey
        ) {
            return ['code' => 500, 'msg' => '验证失败，拼图位置不对'];
        }

        // 校验客户端 Token 是否与服务端匹配
        if (!hash_equals($captchaData['token'], $clientToken)) {
            return ['code' => 500, 'msg' => '验证失败：会话令牌不匹配'];
        }

        // 校验选择的索引是否和真实的索引一致
        if ($selectIdx !== intval($captchaData['real_idx'])) {
            return ['code' => 500, 'msg' => '验证失败，拼图位置不对'];
        }

        // 获取初始坐标与目标绝对坐标
        $initX = (float)$captchaData['init_x'];
        $initY = (float)$captchaData['init_y'];
        $targetX = (float)$captchaData['target_x'];
        $targetY = (float)$captchaData['target_y'];

        // 使用专业的行为轨迹验证器进行深度校验
        $validationResult = CaptchaTrackValidator::validate(
            $track,
            $targetX,
            $targetY,
            $initX,
            $initY,
            [
                'target_tolerance_x' => 6.0,
                'target_tolerance_y' => 20.0,
                'risk_threshold' => 60,
            ]
        );

        // 如果轨迹验证未通过
        if (!$validationResult['success']) {
            // 记录失败次数
            $this->recordFailure($failCountKey, $lockKey);
            return [
                'code' => 500,
                'msg' => '验证失败：' . ($validationResult['reason'] ?? '轨迹行为异常')
            ];
        }

        // 双重保险：终点坐标绝对值校验
        $finalUserX = $initX + $userMoveDelta;
        if (abs($finalUserX - $targetX) > 6.0) {
            $this->recordFailure($failCountKey, $lockKey);
            return ['code' => 500, 'msg' => '验证失败，拼图位置不对'];
        }

        // 生成一次性验证通过凭证
        $ticket = md5(uniqid() . mt_rand(1000, 9999));
        return ['code' => 200, 'msg' => '验证成功', 'ticket' => $ticket];
    }

    /**
     * 记录验证失败次数并在超限时触发安全锁定
     * @param string $failCountKey 失败计数键
     * @param string $lockKey 锁定键
     * @return void
     * @author wave
     */
    private function recordFailure($failCountKey, $lockKey)
    {
        // 失败次数自增 1
        $fails = $this->redis->incr($failCountKey);
        // 设置失败计数缓存过期时间 10 分钟
        $this->redis->expire($failCountKey, 600);
        // 如果失败次数达到 5 次
        if ($fails >= 5) {
            // 触发锁定 15 分钟
            $this->redis->set($lockKey, 1, ['EX' => 900]);
        }
    }
}

