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
    'default' => [
        'handler' => [
            'class' => App\Logger\AutoReopenStreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/error/' . date('Y-m-d') . '.log',
                'level' => Monolog\Logger::DEBUG,
                'bubble' => true,
                'filePermission' => 0666,
                'useLocking' => true,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %level_name% %message%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => false,
                'ignoreEmptyContextAndExtra' => true,
            ],
        ],
    ],
    'sql' => [
        'handler' => [
            'class' => App\Logger\AutoReopenStreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/sql/' . date('Y-m-d') . '.log',
                'level' => Monolog\Logger::INFO,
                'bubble' => true,
                'filePermission' => 0666,
                'useLocking' => true,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %message%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => false,
                'ignoreEmptyContextAndExtra' => true,
            ],
        ],
    ],
    'exception' => [
        'handler' => [
            'class' => App\Logger\AutoReopenStreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/exception/' . date('Y-m-d') . '.log',
                'level' => Monolog\Logger::DEBUG,
                'bubble' => true,
                'filePermission' => 0666,
                'useLocking' => true,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] %level_name% %message%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
                'ignoreEmptyContextAndExtra' => true,
            ],
        ],
    ],
];
