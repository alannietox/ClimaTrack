<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use ClimaTrack\Database\Database;

try {
    $pdo = Database::fromEnvironment();
} catch (Throwable $e) {
    $isCli = PHP_SAPI === 'cli';
    $message = $isCli
        ? 'No se pudo conectar con la base de datos: ' . $e->getMessage()
        : 'No se pudo conectar con la base de datos.';

    http_response_code(500);
    exit($message);
}
