<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/conexion.php';
require __DIR__ . '/periodicos_mapping.php';

use ClimaTrack\Cache\FileCache;
use ClimaTrack\Http\HttpClient;
use ClimaTrack\Services\WeatherStateMapper;
use ClimaTrack\Services\WttrService;

$isAjax = isset($_GET['ajax']);
$periodico = $_GET['periodico'] ?? 'general';
$cache = new FileCache(__DIR__ . '/cache');

if ($cache->isFresh("mundo_{$periodico}", 1800)) {
    echo $isAjax ? 'OK' : "Saltando captura Mundo para {$periodico}: Datos recientes (menos de 30 min).\n";
    exit;
}

$donde = ' WHERE 1=1 ';
$params = [];

if (!empty($_GET['ids'])) {
    $ids = array_filter(explode(',', (string) $_GET['ids']), static fn($id) => str_starts_with($id, 'M'));
    if (!$ids) {
        echo $isAjax ? 'OK' : "No hay ciudades de mundo en la selección.\n";
        exit;
    }
    $idsNum = array_map(static fn($id) => (int) substr($id, 1), $ids);
    $placeholders = implode(',', array_fill(0, count($idsNum), '?'));
    $donde .= " AND Id_Mundo IN ({$placeholders}) ";
    $params = $idsNum;
} elseif (!empty($_GET['periodico']) && isset($periodicos[$periodico])) {
    $ids = array_filter($periodicos[$periodico], static fn($id) => str_starts_with($id, 'M'));
    if (!$ids) {
        echo $isAjax ? 'OK' : "El periódico {$periodico} no tiene ciudades de mundo.\n";
        exit;
    }
    $idsNum = array_map(static fn($id) => (int) substr($id, 1), $ids);
    $placeholders = implode(',', array_fill(0, count($idsNum), '?'));
    $donde .= " AND Id_Mundo IN ({$placeholders}) ";
    $params = $idsNum;
}

$stmt = $pdo->prepare("SELECT Id_Mundo, Nombre FROM ciudades_mundo{$donde}");
$stmt->execute($params);
$ciudades = $stmt->fetchAll();

if (!$ciudades) {
    echo $isAjax ? 'OK' : "No hay ciudades que procesar.\n";
    exit;
}

$service = new WttrService(new HttpClient(timeout: 15, connectTimeout: 5));
$insert = $pdo->prepare(
    "INSERT INTO datos_clima
    (id_municipio, nombre_municipio, fecha, temp_min, temp_max, estado_cielo, estado_manana, estado_tarde)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    temp_min=VALUES(temp_min), temp_max=VALUES(temp_max),
    estado_cielo=VALUES(estado_cielo), estado_manana=VALUES(estado_manana),
    estado_tarde=VALUES(estado_tarde)"
);

foreach ($ciudades as $ciudad) {
    $nombre = $ciudad['Nombre'];
    try {
        $datos = $service->forecast($nombre);
        if (!isset($datos['weather'][1])) {
            if (!$isAjax) echo "Sin datos suficientes: {$nombre}\n";
            continue;
        }

        for ($i = 1; $i <= 2; $i++) {
            if (!isset($datos['weather'][$i])) continue;
            $dia = $datos['weather'][$i];
            $hourly = $dia['hourly'] ?? [];
            $value = static function (array $slots, int $slot): ?string {
                return $slots[$slot]['lang_es'][0]['value']
                    ?? $slots[$slot]['weatherDesc'][0]['value']
                    ?? null;
            };

            $cielo = $value($hourly, 4);
            $manana = $value($hourly, 3) ?? $cielo;
            $tarde = $value($hourly, 6) ?? $cielo;

            $insert->execute([
                'M' . str_pad((string) $ciudad['Id_Mundo'], 4, '0', STR_PAD_LEFT),
                $nombre,
                $dia['date'],
                $dia['mintempC'] ?? null,
                $dia['maxtempC'] ?? null,
                WeatherStateMapper::map($cielo),
                WeatherStateMapper::map($manana),
                WeatherStateMapper::map($tarde),
            ]);
        }

        if (!$isAjax) echo "OK: {$nombre}\n";
    } catch (Throwable $e) {
        if (!$isAjax) echo "Error consultando {$nombre}: {$e->getMessage()}\n";
    }
}

$cache->remember("mundo_{$periodico}");
echo $isAjax ? 'OK' : "\nCaptura de ciudades del mundo finalizada.\n";
