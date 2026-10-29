<?php
class FileCache
{
    private string $file;
    public string $status = 'BYPASS';

    public function __construct(string $namespace, private int $ttl = 60)
    {
        $this->file = sys_get_temp_dir() . '/floracare07_' . hash('sha256', $namespace) . '.json';
    }

    public function remember(callable $load): array
    {
        $lock = @fopen($this->file . '.lock', 'c');

        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) fclose($lock);

            return $load();
        }

        try {
            $raw = is_file($this->file) ? @file_get_contents($this->file) : false;
            $cached = $raw === false ? null : json_decode($raw, true);

            if (is_array($cached) && ($cached['day'] ?? '') === date('Y-m-d')
                && ($cached['expires'] ?? 0) > time() && is_array($cached['data'] ?? null)) {
                $this->status = 'HIT';

                return $cached['data'];
            }

            $this->status = 'MISS';
            $data = $load();
            $json = json_encode([
                'day' => date('Y-m-d'),
                'expires' => time() + $this->ttl,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if (@file_put_contents($this->file, $json) === false) {
                $this->status = 'BYPASS';
            }

            return $data;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function clear(): void
    {
        $lock = @fopen($this->file . '.lock', 'c');

        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) fclose($lock);

            return;
        }

        try {
            if (is_file($this->file)) {
                @unlink($this->file);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
