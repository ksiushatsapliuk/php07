<?php
class Profile
{
    private static float $started;

    public static function start(): void
    {
        self::$started = microtime(true);
    }

    public static function finish(?PDO $pdo): void
    {
        $metrics = [
            'elapsed_ms' => round((microtime(true) - self::$started) * 1000, 4),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 4),
            'sql_queries' => $pdo instanceof MeasuredPDO ? $pdo->queryCount : 0,
        ];

        header('X-Execution-Ms: ' . $metrics['elapsed_ms']);
        header('X-Peak-Memory-Mb: ' . $metrics['peak_mb']);
        header('X-SQL-Queries: ' . $metrics['sql_queries']);
    }
}
