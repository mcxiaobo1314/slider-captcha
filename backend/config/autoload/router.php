<?php

declare(strict_types=1);

return [
    [
        'method' => 'GET',
        'path' => '/api/captcha',
        'handler' => [App\Controller\CaptchaController::class, 'index'],
        'name' => '验证码',
        'alias' => 'sys:captcha:index',
        'group' => 'common',
        'is_auth' => false,
        'is_csrf' => false,
        'is_permission' => false,
    ],
    [
        'method' => 'POST',
        'path' => '/api/captcha/verify',
        'handler' => [App\Controller\CaptchaController::class, 'verify'],
        'name' => '验证码验证',
        'alias' => 'sys:captcha:verify',
        'group' => 'common',
        'is_auth' => false,
        'is_csrf' => false,
        'is_permission' => false,
    ],
];
