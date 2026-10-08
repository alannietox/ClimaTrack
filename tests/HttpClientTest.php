<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use ClimaTrack\Http\HttpClient;

$client = new HttpClient();
assert($client instanceof HttpClient);

echo "HttpClient: OK\n";
