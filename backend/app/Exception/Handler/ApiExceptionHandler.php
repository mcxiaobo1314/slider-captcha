<?php

namespace App\Exception\Handler;

use App\Exception\ApiException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Throwable;


class ApiExceptionHandler extends ExceptionHandler
{

    /**
     * 接口异常处理
     * @return ResponseInterface
     * @author wave
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $data = json_encode([
            'Exception' => 'Api',
            'code'    => $throwable->getCode(),
            'message' => $throwable->getMessage(),
        ], JSON_UNESCAPED_UNICODE);

        // 阻止异常冒泡
        $this->stopPropagation();
        
        return $response->withStatus(HTTP_CODE_OK)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($data));
    }

    // 判断该异常类是否要对该异常进行处理
    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ApiException;
    }
}
