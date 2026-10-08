<?php
declare(strict_types=1);

namespace ClimaTrack\Cache;

final class FileCache
{
    public function __construct(private readonly string $directory) {}

    public function isFresh(string $key, int $ttl): bool
    {
        $file = $this->path($key);
        return is_file($file) && (time() - (int) file_get_contents($file)) < $ttl;
    }

    public function remember(string $key): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
        file_put_contents($this->path($key), (string) time(), LOCK_EX);
    }

    public function clear(): void
    {
        if (!is_dir($this->directory)) return;
        foreach (glob($this->directory . '/*.txt') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function path(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . '/cache_' . $safeKey . '.txt';
    }
}
