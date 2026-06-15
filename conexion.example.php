<?php
// conexion.php
// Copia este archivo como "conexion.php" y rellena con tus credenciales
$host = 'localhost';
$db   = 'tu_base_de_datos';
$user = 'tu_usuario';
$pass = 'tu_contraseña';
$charset = 'utf8mb4';


$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>
