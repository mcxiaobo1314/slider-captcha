<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;

class AutoReopenStreamHandler extends StreamHandler
{
    /**
     * 当前日志文件路径
     */
    private string $currentFile = '';

    public function __construct(
        string $stream,
        int|string $level = 200,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
        string $fileOpenMode = 'a'
    ) {
        $this->currentFile = $stream;

        parent::__construct(
            $stream,
            $level,
            $bubble,
            $filePermission,
            $useLocking,
            $fileOpenMode
        );
    }

    protected function write(LogRecord $record): void
    {
        // 1. 清除指定文件的状态缓存
        clearstatcache(true, $this->currentFile);

        $dir = dirname($this->currentFile);

        // 2. 如果日志目录被整体删除，重新创建目录
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // 3. 检查文件是否存在，或者底层 stream 资源是否已经失效
        // 如果文件被外部删除，或者句柄异常，则主动关闭并重置状态让 Monolog 重新打开
        if (!is_file($this->currentFile) || !is_resource($this->stream)) {
            if (is_resource($this->stream)) {
                @fclose($this->stream);
            }
            // 将 stream 置为 null，并调用 Monolog 的 close 逻辑清空内部缓存状态
            $this->stream = null;
            
            // 如果父类有定义 close 方法，可安全触发一次
            if (method_exists(parent::class, 'close')) {
                parent::close();
            }
        }

        parent::write($record);
    }
}