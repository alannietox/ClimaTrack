<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use ClimaTrack\Cache\FileCache;

$dir = sys_get_temp_dir() . '/climatrack-test-' . bin2hex(random_bytes(4));
$cache = new FileCache($dir);
$cache->remember('test');

if (!$cache->isFresh('test', 60)) {
    throw new RuntimeException('El caché debería estar fresco.');
}

$cache->clear();
if (is_dir($dir)) {
    @rmdir($dir);
}

echo "FileCache: OK\n";
