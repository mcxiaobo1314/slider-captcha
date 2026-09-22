<?php

declare(strict_types=1);

namespace App\Bootstrap;

use ErrorException;

class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler(function (
            int $severity,
            string $message,
            string $file,
            int $line
        ) {

            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException(
                $message,
                0,
                $severity,
                $file,
                $line
            );
        });
    }
}