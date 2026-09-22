<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'http' => [
        App\Middleware\VisitorCookieMiddleware::class, //访客身份Cookie（必须第一个）
        App\Middleware\RouteGuardMiddleware::class, //路由守卫中间件
    ],
];
