<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use ClimaTrack\Http\HttpClient;

if (!(new HttpClient() instanceof HttpClient)) {
    throw new RuntimeException('No se pudo crear HttpClient.');
}

echo "HttpClient: OK\n";
