<?php
// get_ultima_captura.php
require 'conexion.php';
date_default_timezone_set('Europe/Madrid');
require 'periodicos_mapping.php';

$periodico = $_GET['periodico'] ?? '';

if (!empty($periodico)) {
    $archivo_cache = __DIR__ . "/cache/cache_aemet_{$periodico}.txt";
    if (file_exists($archivo_cache)) {
        $timestamp = (int)file_get_contents($archivo_cache);
        echo date('Y-m-d H:i:s', $timestamp);
        exit;
    }
}

echo 'Ninguna';
?>
