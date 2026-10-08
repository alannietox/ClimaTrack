<?php
declare(strict_types=1);

$tests = [
    __DIR__ . '/HttpClientTest.php',
    __DIR__ . '/CacheTest.php',
];

foreach ($tests as $test) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($test), $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

echo "All tests passed.\n";
