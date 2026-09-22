<?php

declare(strict_types=1);

use Hyperf\Di\Annotation\AnnotationReader;
use Hyperf\Di\Annotation\Scanner;

/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'scan' => [
        'enable' => false, //线上生产环境 关闭实时扫描
        'cacheable' => true, // 开启扫描缓存，读取预生成的注解元文件
        'paths' => [
            BASE_PATH . '/app',
        ],
        'exclude' => [
            BASE_PATH . '/app/Model',
        ],

        'ignore_annotations' => [
            'mixin',
        ],
    ],
    'inject' => [
        'enable' => true,
    ],
    'reader' => AnnotationReader::class,
    'scanner' => Scanner::class,
];
