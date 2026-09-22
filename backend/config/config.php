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

use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;

use function Hyperf\Support\env;

return [
    'app_name' => env('APP_NAME', 'skeleton'),
    'app_env' => env('APP_ENV', 'dev'),
    'exception_err_oput' => env('EXCEPTION_ERR_OPUT', true), //是否页面输出异常信息
    'scan_cacheable' => env('SCAN_CACHEABLE', false),
    'visitor_id_secret_key' => env('VISITOR_ID_SECRET_KEY',''),//访客身份标识签名密钥
    'visitor_id_lifetime' => intval(env('VISITOR_ID_LIFETIME', 604800)),//访客身份标识默认有效期（秒），默认 7 天

    StdoutLoggerInterface::class => [
        'log_level' => [
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::DEBUG,
            LogLevel::EMERGENCY,
            LogLevel::ERROR,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
        ],
    ],
];
