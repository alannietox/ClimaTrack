<?php
// capturar_mundo.php - Optimizado con curl_multi para peticiones paralelas
require 'conexion.php';

$isAjax = isset($_GET['ajax']);

$periodico_cache = $_GET['periodico'] ?? 'general';
$archivo_cache = __DIR__ . "/cache/cache_mundo_{$periodico_cache}.txt";
$tiempo_espera = 1800; // 30 minutos

if (file_exists($archivo_cache)) {
    $ultima_captura = (int)file_get_contents($archivo_cache);
    if ((time() - $ultima_captura) < $tiempo_espera) {
        if ($isAjax) {
            echo "OK";
        } else {
            echo "Saltando captura Mundo para $periodico_cache: Datos recientes (menos de 30 min).\n";
        }
        exit;
    }
}


$donde  = " WHERE 1=1 ";
$params = [];

if (!empty($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $ids_m = array_filter($ids, function($id) { return strpos($id, 'M') === 0; });
    if (!empty($ids_m)) {
        $ids_num = array_map(function($id) { return (int)substr($id, 1); }, $ids_m);
        $placeholders = implode(',', array_fill(0, count($ids_num), '?'));
        $donde .= " AND Id_Mundo IN ($placeholders) ";
        $params = array_merge($params, $ids_num);
    } else {
        if ($isAjax) { echo "OK"; exit; }
        echo "No hay ciudades de mundo en la selección.\n"; exit;
    }
} elseif (!empty($_GET['periodico'])) {
    require 'periodicos_mapping.php';
    if (isset($periodicos[$_GET['periodico']])) {
        $ids_p = $periodicos[$_GET['periodico']];
        $ids_m = array_filter($ids_p, function($id) { return strpos($id, 'M') === 0; });
        if (!empty($ids_m)) {
            $ids_num = array_map(function($id) { return (int)substr($id, 1); }, $ids_m);
            $placeholders = implode(',', array_fill(0, count($ids_num), '?'));
            $donde .= " AND Id_Mundo IN ($placeholders) ";
            $params = array_merge($params, $ids_num);
        } else {
            if ($isAjax) { echo "OK"; exit; }
            echo "El periódico {$_GET['periodico']} no tiene ciudades de mundo.\n"; exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT Id_Mundo, Nombre FROM ciudades_mundo" . $donde);
$stmt->execute($params);
$ciudades = $stmt->fetchAll();

if (empty($ciudades)) {
    if ($isAjax) { echo "OK"; exit; }
    echo "No hay ciudades que procesar.\n"; exit;
}

// ── PETICIONES PARALELAS CON curl_multi ──────────────────────────────────────
$mh      = curl_multi_init();
$handles = [];

foreach ($ciudades as $ciudad) {
    $nombre      = $ciudad['Nombre'];
    $search_name = str_replace('?', 'n', $nombre);
    $url         = "https://wttr.in/" . urlencode($search_name) . "?format=j1&lang=es";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT,      "Mozilla/5.0");
    curl_multi_add_handle($mh, $ch);
    $handles[(int)$ch] = ['ch' => $ch, 'ciudad' => $ciudad];
}

// Ejecutar todas las peticiones en paralelo
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 0.5);
} while ($running > 0);

// ── PROCESAR RESPUESTAS ───────────────────────────────────────────────────────
$sql_insert = "INSERT INTO datos_clima
               (id_municipio, nombre_municipio, fecha, temp_min, temp_max, estado_cielo, estado_manana, estado_tarde)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE
               temp_min      = VALUES(temp_min),
               temp_max      = VALUES(temp_max),
               estado_cielo  = VALUES(estado_cielo),
               estado_manana = VALUES(estado_manana),
               estado_tarde  = VALUES(estado_tarde)";
$stmt_insert = $pdo->prepare($sql_insert);

foreach ($handles as $info) {
    $ch     = $info['ch'];
    $ciudad = $info['ciudad'];
    $nombre = $ciudad['Nombre'];

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $respuesta = curl_multi_getcontent($ch);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);

    if ($http_code == 200 && $respuesta) {
        $datos = json_decode($respuesta, true);
        if (isset($datos['weather'][1])) {
            for ($i = 1; $i <= 2; $i++) {
                if (!isset($datos['weather'][$i])) continue;
                $dia      = $datos['weather'][$i];
                $fecha    = $dia['date'];
                $temp_max = $dia['maxtempC'];
                $temp_min = $dia['mintempC'];

                $get_val = function($slot) use ($dia) {
                    return $dia['hourly'][$slot]['lang_es'][0]['value']
                        ?? $dia['hourly'][$slot]['weatherDesc'][0]['value']
                        ?? null;
                };
                $estado_cielo  = $get_val(4);
                $estado_manana = $get_val(3) ?? $estado_cielo;
                $estado_tarde  = $get_val(6) ?? $estado_cielo;

                $id_municipio = "M" . str_pad($ciudad['Id_Mundo'], 4, '0', STR_PAD_LEFT);

                $stmt_insert->execute([
                    $id_municipio,
                    $nombre,
                    $fecha,
                    $temp_min,
                    $temp_max,
                    mapear_estado_aemet($estado_cielo),
                    mapear_estado_aemet($estado_manana),
                    mapear_estado_aemet($estado_tarde),
                ]);
            }
            if (!$isAjax) echo "OK: $nombre\n";
        } else {
            if (!$isAjax) echo "Sin datos suficientes: $nombre\n";
        }
    } else {
        if (!$isAjax) echo "Error HTTP $http_code: $nombre\n";
    }
}

curl_multi_close($mh);

file_put_contents($archivo_cache, time());
if ($isAjax) { echo "OK"; exit; }
echo "\nCaptura de ciudades del mundo finalizada.\n";

// ── MAPEO ESTADOS ─────────────────────────────────────────────────────────────
function mapear_estado_aemet($estado_wttr) {
    if (empty($estado_wttr)) return null;
    $estado_wttr = trim($estado_wttr);
    $mapping = [
        'Soleado'                          => 'Despejado',
        'Sunny'                            => 'Despejado',
        'Cielo despejado'                  => 'Despejado',
        'Parcialmente nublado'             => 'Poco nuboso',
        'Partly Cloudy'                    => 'Poco nuboso',
        'Partly cloudy'                    => 'Poco nuboso',
        'Nublado'                          => 'Nuboso',
        'Cloudy'                           => 'Nuboso',
        'Nubes dispersas'                  => 'Nuboso',
        'Neblina'                          => 'Nuboso',
        'Niebla'                           => 'Nuboso',
        'Mist'                             => 'Nuboso',
        'Fog'                              => 'Nuboso',
        'Muy nublado'                      => 'Cubierto',
        'Cielo cubierto'                   => 'Cubierto',
        'Overcast'                         => 'Cubierto',
        'Muy nuboso'                       => 'Muy nuboso',
        'Lluvia  moderada a intervalos'    => 'Intervalos nubosos con lluvia',
        'Patchy rain nearby'               => 'Intervalos nubosos con lluvia',
        'Lluvia moderada'                  => 'Cubierto con lluvia',
        'Lluvias fuertes o moderadas'      => 'Cubierto con lluvia',
        'Periodos de lluvia moderada'      => 'Cubierto con lluvia',
        'Ligeras precipitaciones'          => 'Nuboso con lluvia escasa',
        'Ligeras lluvias'                  => 'Nuboso con lluvia escasa',
        'Llovizna'                         => 'Nuboso con lluvia escasa',
        'Light drizzle'                    => 'Nuboso con lluvia escasa',
        'Fuertes nevadas'                  => 'Muy nuboso con nieve',
        'Heavy snow'                       => 'Muy nuboso con nieve',
        'Nieve moderada'                   => 'Intervalos nubosos con nieve',
        'Nieve moderada a intervalos'      => 'Intervalos nubosos con nieve',
        'Ligeras ráfagas de nieve'         => 'Intervalos nubosos con nieve',
        'Cielos tormentosos en las aproximaciones' => 'Tormenta',
        'Thundery outbreaks nearby'        => 'Tormenta',
    ];
    foreach ($mapping as $key => $val) {
        if (mb_stripos($estado_wttr, $key) !== false) return $val;
    }
    if (mb_stripos($estado_wttr, 'lluvia') !== false || mb_stripos($estado_wttr, 'precipita') !== false)
        return 'Nuboso con lluvia';
    if (mb_stripos($estado_wttr, 'nieve') !== false)
        return 'Muy nuboso con nieve';
    if (mb_stripos($estado_wttr, 'nublado') !== false || mb_stripos($estado_wttr, 'nubes') !== false)
        return 'Nuboso';
    return $estado_wttr;
}
?>
