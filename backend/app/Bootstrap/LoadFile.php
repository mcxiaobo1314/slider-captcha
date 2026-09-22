<?php

namespace App\Bootstrap;

class LoadFile
{
    public static function load()
    {
        $files = [
            BASE_PATH . '/app/Common/Code.php',
            BASE_PATH . '/app/Common/ErrorMessage.php',
            BASE_PATH . '/app/Common/Function.php',
        ];
        foreach ($files as $file) {
            if (file_exists($file)) {
                include $file;
            }
        }
    }
}
