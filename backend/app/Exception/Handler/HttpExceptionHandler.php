<?php

namespace App\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Logger\LoggerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpExceptionHandler extends ExceptionHandler
{
    /**
     * 日志实例（通过依赖注入获取）
     * @author wave
     */
    protected LoggerInterface $logger;

    /**
     * 构造函数
     * @author wave
     */
    public function __construct(LoggerFactory $loggerFactory)
    {
        // 绑定到自定义日志通道（默认使用 config/autoload/logger.php 中的 default 通道）
        $this->logger = $loggerFactory->get('exception','exception');
    }

    /**
     * http异常处理
     * @return ResponseInterface
     * @author wave
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $code = $throwable->getCode();
        $message =  isErrOput() ?
                SERVER_ERR_MSG : $throwable->getMessage();

        $data = json_encode([
            'Exception' => 'Http',
            'code' => $code,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        // 阻止异常冒泡
        $this->stopPropagation();

        // 2. 构建异常日志内容（包含关键信息，便于排查）
        $logContent = sprintf(
            "HttpExceptionHandler [%s] %s (文件：%s 行：%d) \n异常栈:%s",
            get_class($throwable), // 异常类名
            $throwable->getMessage(), // 异常消息
            $throwable->getFile(), // 异常文件
            $throwable->getLine(), // 异常行号
            $throwable->getTraceAsString() // 异常栈（关键！）
        );

        if ($code === HTTP_SERVER_ERROR) {
            // 系统异常：记录 error 级别（如数据库、服务器错误）
            $this->logger->error($logContent);
        }

        return $response->withStatus($code)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($data));
    }

    // 判断该异常类是否要对该异常进行处理
    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof \App\Exception\HttpException;
    }
}
