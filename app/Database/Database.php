<?php
declare(strict_types=1);

namespace ClimaTrack\Database;

use PDO;

final class Database
{
    public static function fromEnvironment(): PDO
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $db = getenv('DB_NAME') ?: '';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASSWORD') ?: '';
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

        if ($db === '' || $user === '') {
            throw new \RuntimeException('Faltan DB_NAME o DB_USER en la configuración.');
        }

        return new PDO(
            "mysql:host={$host};dbname={$db};charset={$charset}",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
