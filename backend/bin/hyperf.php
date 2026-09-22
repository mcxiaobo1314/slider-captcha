#!/usr/bin/env php
<?php
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('memory_limit', '1G');

error_reporting(E_ALL);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', Hyperf\Engine\DefaultOption::hookFlags());

$runTimePath = BASE_PATH . '/runtime';

$logsPath =  $runTimePath . '/logs';
if (!is_dir($logsPath)) {
    mkdir($logsPath, 0777, true);
}

$pathArr = ['/error', '/exception', '/operate', '/sql'];
foreach ($pathArr as $path) {
    if (!is_dir($logsPath . $path)) {
        mkdir($logsPath . $path, 0777, true);
    }
}

$uploadPath = $runTimePath . '/uploads';
if (!is_dir($uploadPath)) {
    mkdir($uploadPath, 0777, true);
}

// Self-called anonymous function that creates its own scope and keep the global namespace clean.
(function () {
    \App\Bootstrap\ErrorHandler::register();
    Hyperf\Di\ClassLoader::init();
    /** @var Psr\Container\ContainerInterface $container */
    $container = require BASE_PATH . '/config/container.php';
    $application = $container->get(Hyperf\Contract\ApplicationInterface::class);
    if (method_exists($application, 'run')) {
        $application->run();
    }
})();
