<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Library\VisitorIdentity;
use Hyperf\Context\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Exception\HttpException;
use App\Exception\ApiException;

/**
 * 访客身份 Cookie 中间件 —— 必须注册在全局中间件链【最前】
 *
 * 职责：
 * 1. 请求进入时读取 Cookie(tax_vid)，验签通过则视为有效访客身份；
 * 2. 缺失/被篡改 → 生成新身份，注入 request attribute（visitor_id），
 *    并在响应时通过 Set-Cookie 回写浏览器；
 * 3. 供后续 CsrfMiddleware（令牌-身份绑定校验）与
 *    HeartbeatController（令牌签发时写入绑定关系）使用。
 *
 * Cookie 属性：HttpOnly（防 XSS 读取）+ SameSite=Lax（直接阻断跨站请求）
 * + Max-Age=604800（7天持久，过期自动换新身份）。
 *
 * @author wave
 */
class VisitorCookieMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $ua = $request->getHeaderLine('User-Agent');
        $this->checkUserAgent($ua);
        // 1. 读取并校验已有身份
        $cookies = $request->getCookieParams();
        $vid = (string) ($cookies[VisitorIdentity::cookieName()] ?? '');
        $needSetCookie = false;

        if (! VisitorIdentity::verify($vid)) {
            // 首次访问 / Cookie 被篡改 / 已过期 → 重新签发（主体更换，旧绑定随之作废）
            $vid = VisitorIdentity::create();
            $needSetCookie = true;
        } elseif ((VisitorIdentity::remaining($vid) ?? 0) < VisitorIdentity::getRefreshThreshold()) {
            // 滑动续签：剩余寿命低于阈值 → 同主体重签（只更新时间戳与签名）
            // 主体不变 → CSRF 令牌等按主体绑定的数据继续有效，活跃用户永不过期，
            // 从根上消除「令牌还在 120 秒有效期内、身份却先到期」的竞态
            $renewed = VisitorIdentity::renew($vid);
            if ($renewed !== null) {
                $vid = $renewed;
                $needSetCookie = true;
            }
        }

        // 2. 注入 attribute，并同步到协程上下文（保证控制器 $this->request->getAttribute() 可读）
        $request = $request->withAttribute('visitor_id', $vid);
        RequestContext::set($request);

        // 3. 放行（CsrfMiddleware 等后续中间件可读取 visitor_id 做绑定校验）
        $response = $handler->handle($request);

        // 4. 响应回写 Cookie（仅当本次新签发了身份）
        if ($needSetCookie) {
            $response = $response->withAddedHeader(
                'Set-Cookie',
                VisitorIdentity::cookieName() . '=' . $vid
                    . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . VisitorIdentity::getLifetime()

            );
        }

        return $response;
    }

    /**
     * 校验 User-Agent
     * @param string $ua User-Agent
     * @author wave
     */
    protected function checkUserAgent($ua)
    {
        if (empty($ua) || preg_match('/curl|python|postman|wget/i', $ua)) {
            throw new HttpException("403 Forbidden", FORBIDDEN);
        }

        // 检查是否包含基础的浏览器引擎/架构标识
        $isBrowserLike = preg_match('/(Mozilla\/5\.0|AppleWebKit|Gecko|Trident|Edge|Chrome|Safari|Firefox)/i', $ua);
        if (!$isBrowserLike) {
            throw new ApiException("请勿非法请求", CODE_ERR);
        }
    }
}
