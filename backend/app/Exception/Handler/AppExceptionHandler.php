<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Logger\LoggerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
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
        $this->logger = $loggerFactory->get('exception','exception');
    }

    /**
     * http异常处理
     * @return ResponseInterface
     * @author wave
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $code = CODE_ERR;
        $message =  isErrOput() ?
                SERVER_ERR_MSG : $throwable->getMessage();

        $data = json_encode([
            'code' => $code,
            'message' => $message,
            'Exception' => 'App',
        ], JSON_UNESCAPED_UNICODE);
        // 阻止异常冒泡
        $this->stopPropagation();

        // 2. 构建异常日志内容（包含关键信息，便于排查）
        $logContent = sprintf(
            "AppExceptionHandler [%s] %s (文件：%s 行：%d) \n异常栈:%s",
            get_class($throwable), // 异常类名
            $throwable->getMessage(), // 异常消息
            $throwable->getFile(), // 异常文件
            $throwable->getLine(), // 异常行号
            $throwable->getTraceAsString() // 异常栈（关键！）
        );

        $this->logger->error($logContent);

        return $response->withStatus(HTTP_CODE_OK)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($data));
    }


    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}