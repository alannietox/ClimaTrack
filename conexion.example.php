<?php
/**
 * Plantilla de conexión a MySQL/MariaDB.
 *
 * Copia este archivo como conexion.php y configura las variables
 * de entorno correspondientes.
 */
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'tu_base_de_datos';
$user = getenv('DB_USER') ?: 'tu_usuario';
$pass = getenv('DB_PASSWORD') ?: 'tu_contraseña';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // No exponer detalles internos en producción.
    $isCli = PHP_SAPI === 'cli';
    $message = $isCli
        ? 'No se pudo conectar con la base de datos: ' . $e->getMessage()
        : 'No se pudo conectar con la base de datos.';

    http_response_code(500);
    exit($message);
}
