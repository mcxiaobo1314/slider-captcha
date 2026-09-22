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

use Hyperf\HttpServer\Router\Router;

use function Hyperf\Config\config;

// 1. 读取映射配置文件
$routeMap = config('router', []);
if (!empty($routeMap)) {
    foreach ($routeMap as $item) {
        $method = strtoupper($item['method']);
        $path = $item['path'];
        $handler = $item['handler'];
        // 根据请求方法动态调用 Router 对应的方法
        match ($method) {
            'GET' => Router::get($path, $handler),
            'POST' => Router::post($path, $handler)
        };
    }
}







