<?php

declare(strict_types=1);

namespace App\Library;

use App\Exception\ApiException;

use function Hyperf\Config\config;

/**
 * 访客身份标识（匿名会话 ID）
 *
 * 结构：32位hex随机主体 + "." + 10位过期时间戳 + "." + 16位HMAC-SHA256签名
 * 例：c4a1f0e2b9d87c6352a4b1e0f9d8c7a6.1756310400.ab12cd34ef567890
 *
 * 无状态校验：服务端不落库，每次请求靠 HMAC 签名验真，
 * 防伪造、防篡改；随机主体 128bit 熵（random_bytes(16)），不可枚举。
 *
 * 过期校验（v2）：
 * 过期时间戳嵌入身份本体并纳入签名，verify() 时同时校验
 * 「签名有效」+「未过期」。即使 Cookie 被复制或手动延长有效期，
 * 身份本身也会在服务端校验时过期失效——不再依赖浏览器对
 * Cookie Max-Age 的执行行为。
 *
 * 兼容性：旧版两段式 ID（主体.签名）无法通过三段解析，一律视为
 * 无效并重新签发，前端无需任何改动。
 *
 * 续签机制（v3）：
 * 绑定（如 CSRF 令牌）只使用稳定的「主体」段（随机部分），续签仅
 * 更新过期时间戳与签名、主体不变 → 绑定关系跨续签持续有效；
 * 中间件在剩余寿命低于阈值时自动滑动续签，活跃用户永不过期，
 * 从根上消除「CSRF 令牌还在有效期、身份却先到期」的竞态。
 *
 * @author wave
 */
class VisitorIdentity
{
    /**
     * Cookie 名称
     */
    private const COOKIE_NAME = 'uuid';

    /**
     * 身份默认有效期（秒），默认 7 天；可用 visitor_id_lifetime 配置覆盖
     */
    private const DEFAULT_LIFETIME = 604800;

    /**
     * 身份分割符号
     */
    private const SPLIT_DOT = '.';

    /**
     * 签名分割符号
     */
    private const SIGN_SPLIT_DOT = '-';



    /**
     * 签名密钥兜底（生产环境务必用 env 覆盖）
     */
    protected static string $secretKey = '';

    /**
     * 生成新的访客身份标识
     * @param array|null $identity 可选的自定义身份信息数组（如用户属性、多维标识等），不传则系统完全随机生成
     * @return string
     * @throws \App\Exception\ApiException 签名密钥未配置时拒绝签发，防止弱密钥
     */
    public static function create(?array $identity = null): string
    {
        $secretKey = static::getSecretKey();
        if ($secretKey === '') {
            throw new ApiException('签名密钥未配置时拒绝签发，防止弱密钥', CODE_ERR);
        }

        if ($identity !== null && $identity !== []) {
            // 根据传入的身份信息数组生成固定的 32 位 hex 主体（确保符合 32 位 hex 格式约束）
            $random = substr(hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE)), 0, 32);
        } else {
            // 系统完全随机生成：32位hex，128bit CSPRNG 熵
            $random = bin2hex(random_bytes(16));
        }

        $expireTs = (string) (time() + static::getLifetime()); // 过期时间戳（10位）
        $sign = substr(hash_hmac('sha256', $random . self::SIGN_SPLIT_DOT . $expireTs, $secretKey), 0, 16);
        return $random . self::SPLIT_DOT . $expireTs . self::SPLIT_DOT . $sign;
    }

    /**
     * 校验访客身份标识（格式 + 过期 + 验签 + 黑名单拦截，hash_equals 防御时序攻击）
     * @param string|null $vid Cookie 中的身份值
     * @param string|null $ip 可选传入请求IP
     * @return bool
     */
    public static function verify(?string $vid, ?string $ip = null): bool
    {
        if (! is_string($vid) || $vid === '') {
            return false;
        }
        
        // 黑名单拦截检查（支持访客唯一标识或IP拦截）
        if (static::isBlacklisted($vid, $ip)) {
            return false;
        }

        $secretKey = static::getSecretKey();
        if ($secretKey === '') {
            return false;
        }
        $parts = explode(self::SPLIT_DOT, $vid);
        if (count($parts) !== 3) {
            return false;
        }
        [$random, $expireTs, $sign] = $parts;
        // 严格格式校验：主体32位hex + 过期时间戳10位数字 + 签名16位hex
        if (
            ! preg_match('/^[0-9a-f]{32}$/', $random)
            || ! preg_match('/^[0-9]{10}$/', $expireTs)
            || ! preg_match('/^[0-9a-f]{16}$/', $sign)
        ) {
            return false;
        }
        // 过期校验：身份本体已过期 → 直接失效（防 Cookie 复制/手动延期）
        if ((int) $expireTs < time()) {
            return false;
        }
        $expect = substr(hash_hmac('sha256', $random . self::SIGN_SPLIT_DOT . $expireTs, $secretKey), 0, 16);
        return hash_equals($expect, $sign);
    }

    /**
     * 提取身份主体（随机段）—— 绑定用的稳定标识
     *
     * 主体在续签（renew）过程中保持不变，因此以主体为键的绑定
     * 关系（如 Redis 中的 CSRF 令牌）不会因续签而失效。
     * 兼容两种存量格式：完整三段式 ID / 仅主体。
     * @param string|null $vid
     * @return string|null 32位hex主体；无法解析返回 null
     */
    public static function subject(?string $vid): ?string
    {
        if (! is_string($vid) || $vid === '') {
            return null;
        }
        $parts = explode(self::SPLIT_DOT, $vid);
        if (count($parts) === 3) {
            [$random, $expireTs, $sign] = $parts;
            if (
                preg_match('/^[0-9a-f]{32}$/', $random)
                && preg_match('/^[0-9]{10}$/', $expireTs)
                && preg_match('/^[0-9a-f]{16}$/', $sign)
            ) {
                return $random;
            }
            return null;
        }
        // 兼容：绑定记录中仅存主体的格式
        if (count($parts) === 1 && preg_match('/^[0-9a-f]{32}$/', $parts[0])) {
            return $parts[0];
        }
        return null;
    }

    /**
     * 剩余有效期（秒）
     * 注意：本方法只做格式解析，不做验签；请先 verify() 再调用
     * @param string|null $vid
     * @return int|null 剩余秒数；格式非法返回 null
     */
    public static function remaining(?string $vid): ?int
    {
        if (! is_string($vid) || $vid === '') {
            return null;
        }
        $parts = explode('.', $vid);
        if (
            count($parts) !== 3 || ! preg_match('/^[0-9a-f]{32}$/', $parts[0])
            || ! preg_match('/^[0-9]{10}$/', $parts[1])
        ) {
            return null;
        }
        return max(0, (int) $parts[1] - time());
    }

    /**
     * 滑动续签：同一主体 + 新过期时间戳 + 新签名
     *
     * 前提：身份当前仍有效（未过期且签名正确）——服务端持有密钥，
     * 可以对有效身份重签以延长寿命；过期身份只能走 create() 重建。
     * 主体不变 → 按主体绑定的数据（CSRF 令牌等）继续有效。
     * @param string|null $vid
     * @param string|null $ip 可选传入请求IP
     * @return string|null 新的三段式 ID；无效/已过期/验签失败返回 null
     */
    public static function renew(?string $vid, ?string $ip = null): ?string
    {
        if (! is_string($vid) || $vid === '') {
            return null;
        }

        // 续签前同样校验黑名单拦截
        if (static::isBlacklisted($vid, $ip)) {
            return null;
        }

        $secretKey = static::getSecretKey();
        if ($secretKey === '') {
            return null;
        }
        $parts = explode(self::SPLIT_DOT, $vid);
        if (count($parts) !== 3) {
            return null;
        }
        [$random, $expireTs, $sign] = $parts;
        if (
            ! preg_match('/^[0-9a-f]{32}$/', $random)
            || ! preg_match('/^[0-9]{10}$/', $expireTs)
            || ! preg_match('/^[0-9a-f]{16}$/', $sign)
        ) {
            return null;
        }
        // 已过期的身份不续签（防 Cookie 复制后无限续命）
        if ((int) $expireTs < time()) {
            return null;
        }
        $expect = substr(hash_hmac('sha256', $random . self::SIGN_SPLIT_DOT . $expireTs, $secretKey), 0, 16);
        if (! hash_equals($expect, $sign)) {
            return null;
        }
        $newExpireTs = (string) (time() + static::getLifetime());
        $newSign = substr(hash_hmac('sha256', $random . self::SIGN_SPLIT_DOT . $newExpireTs, $secretKey), 0, 16);
        return $random . self::SPLIT_DOT . $newExpireTs . self::SPLIT_DOT . $newSign;
    }

    /**
     * 检查访客身份或IP是否在黑名单中
     * @param string|null $vid
     * @param string|null $ip
     * @return bool
     */
    public static function isBlacklisted(?string $vid, ?string $ip = null): bool
    {
        $subject = static::subject($vid);
        $targetIp = $ip ?? null;

        // 1. 全局配置规则拦截（支持配置数组）
        $configVids = config('visitor_blacklist_vids', []);
        $configIps = config('visitor_blacklist_ips', []);

        if (!empty($subject) && in_array($subject, $configVids, true)) {
            return true;
        }
        if (!empty($targetIp) && in_array($targetIp, $configIps, true)) {
            return true;
        }

        return false;
    }

    /**
     * 自动续签阈值：剩余寿命低于该值时中间件触发续签
     * 取「有效期的一半」与 300 秒的较大者——300 秒远大于 CSRF 令牌
     * 的 120 秒 TTL，保证令牌签发时身份剩余寿命必定覆盖令牌整个
     * 生命周期，杜绝边界竞态。
     * @return int
     */
    public static function getRefreshThreshold(): int
    {
        return max(300, intdiv(static::getLifetime(), 2));
    }

    /**
     * 获取 Cookie 名称
     * @return string
     */
    public static function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    /**
     * 获取身份有效期（秒），默认 7 天，可用配置覆盖
     * @return int
     */
    public static function getLifetime(): int
    {
        return max(1, (int) config('visitor_id_lifetime', self::DEFAULT_LIFETIME));
    }

    /**
     * 获取签名密钥（环境变量优先）
     * @return string
     */
    protected static function getSecretKey(): string
    {
        return (string) config('visitor_id_secret_key', static::$secretKey);
    }
}
