<?php

declare(strict_types=1);

namespace App\Listener;

use DateTimeInterface;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Stringable\Str;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

#[Listener]
class DbQueryExecutedListener implements ListenerInterface
{
    /**
     * 慢查询时间（毫秒）
     * 0 = 全部记录
     */
    private const SLOW_SQL = 0;

    private LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container
            ->get(LoggerFactory::class)
            ->get('sql', 'sql');
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof QueryExecuted) {
            return;
        }

        // 只记录慢SQL
        if (self::SLOW_SQL > 0 && $event->time < self::SLOW_SQL) {
            return;
        }

        try {
            $sql = Str::replaceArray(
                '?',
                array_map([$this, 'formatBinding'], $event->bindings),
                $event->sql
            );

            $this->logger->info(sprintf(
                '[%s ms] %s',
                number_format($event->time, 2),
                $sql
            ));
        } catch (Throwable $e) {
            // 即使SQL日志记录失败，也不能影响业务
            $this->logger->error(sprintf(
                'SQL日志记录失败:%s',
                $e->getMessage()
            ));
        }
    }

    /**
     * 格式化SQL绑定参数
     */
    private function formatBinding(mixed $value): string
    {
        return match (true) {

            $value === null
                => 'NULL',

            is_bool($value)
                => $value ? '1' : '0',

            is_int($value),
            is_float($value)
                => (string)$value,

            $value instanceof DateTimeInterface
                => "'" . $value->format('Y-m-d H:i:s') . "'",

            default
                => "'" . str_replace("'", "''", (string)$value) . "'",
        };
    }
}