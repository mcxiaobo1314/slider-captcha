<?php
namespace App\Exception;

use Hyperf\Server\Exception\ServerException;
use Throwable;

class ApiException extends ServerException
{

    /**
     * 自定义api抛出异常
     * @author wave
     */
    public function __construct(string $message, int $code = 0,?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}