<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use ClimaTrack\Cache\FileCache;

$dir = sys_get_temp_dir() . '/climatrack-test-' . bin2hex(random_bytes(4));
$cache = new FileCache($dir);
$cache->remember('test');
assert($cache->isFresh('test', 60) === true);
$cache->clear();

echo "FileCache: OK\n";
