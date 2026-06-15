<?php
// capturar_resumen.php - Optimizado con curl_multi para peticiones paralelas
require 'conexion.php';

$archivo_cache = __DIR__ . "/cache/cache_resumen.txt";
$tiempo_espera = 1800; // 30 minutos

if (file_exists($archivo_cache)) {
    $ultima_captura = (int)file_get_contents($archivo_cache);
    if ((time() - $ultima_captura) < $tiempo_espera) {
        echo "Saltando captura Resumen: Datos recientes (menos de 30 min).\n";
        exit;
    }
}


function traducir_viento($dir) {
    if (!$dir) return 'Var.';
    $mapeo = [
        'N'   => 'N',
        'S'   => 'S',
        'E'   => 'E',
        'W'   => 'O',
        'NE'  => 'NE',
        'NW'  => 'NO',
        'SE'  => 'SE',
        'SW'  => 'SO',
        'NNE' => 'NNE',
        'NNW' => 'NNO',
        'SSE' => 'SSE',
        'SSW' => 'SSO',
        'ENE' => 'ENE',
        'ESE' => 'ESE',
        'WNW' => 'ONO',
        'WSW' => 'OSO',
        'Calm' => 'Calma',
    ];
    return $mapeo[strtoupper($dir)] ?? $dir;
}

// Municipios requeridos por cada periódico en su ficha "AYER"
// 'puerto' => ID harbor Portus (API móvil)
$municipios = [
    // === DIARIO SUR (MÁLAGA) ===
    '29067' => ['nombre' => 'Málaga',       'puerto' => '17110'],
    '29069' => ['nombre' => 'Marbella',     'puerto' => '17110'], // Same coast as Málaga
    '29015' => ['nombre' => 'Antequera',    'puerto' => null],
    '29094' => ['nombre' => 'Vélez-Málaga', 'puerto' => '17110'], // Same coast as Málaga
    '29084' => ['nombre' => 'Ronda',        'puerto' => null],

    // === EL NORTE DE CASTILLA ===
    '05019' => ['nombre' => 'Ávila',        'puerto' => null],
    '09059' => ['nombre' => 'Burgos',       'puerto' => null],
    '24089' => ['nombre' => 'León',         'puerto' => null],
    '34120' => ['nombre' => 'Palencia',     'puerto' => null],
    '37274' => ['nombre' => 'Salamanca',    'puerto' => null],
    '40194' => ['nombre' => 'Segovia',      'puerto' => null],
    '42173' => ['nombre' => 'Soria',        'puerto' => null],
    '47186' => ['nombre' => 'Valladolid',   'puerto' => null],
    '49275' => ['nombre' => 'Zamora',       'puerto' => null],

    // === HOY (EXTREMADURA) ===
    '06015' => ['nombre' => 'Badajoz',      'puerto' => null],
    '10037' => ['nombre' => 'Cáceres',      'puerto' => null],
    '06083' => ['nombre' => 'Mérida',       'puerto' => null],
    '10148' => ['nombre' => 'Plasencia',    'puerto' => null],

    // === LAS PROVINCIAS ===
    '46250' => ['nombre' => 'Valencia',     'puerto' => '26110'],
    '12040' => ['nombre' => 'Castellón',    'puerto' => '27110'],

    // === EL IDEAL ===
    '04013' => ['nombre' => 'Almería',      'puerto' => '23110'],
    '18087' => ['nombre' => 'Granada',      'puerto' => null],
    '23050' => ['nombre' => 'Jaén',         'puerto' => null],
    '18140' => ['nombre' => 'Motril',       'puerto' => '17110'], // Se mapea provisionalmente a Málaga

    // === LA VERDAD (MURCIA) ===
    '30030' => ['nombre' => 'Murcia',       'puerto' => null],
    '30016' => ['nombre' => 'Cartagena',    'puerto' => '21110'],
    '03014' => ['nombre' => 'Alicante',     'puerto' => '22110'],
    '03065' => ['nombre' => 'Elche',        'puerto' => null],


    // === LA RIOJA (LA RIOJA) ===
    '26089' => ['nombre' => 'Logroño',      'puerto' => null],
    '26102' => ['nombre' => 'Nájera',       'puerto' => null],
    '26011' => ['nombre' => 'Alfaro',       'puerto' => null],
    '26071' => ['nombre' => 'Haro',         'puerto' => null],

    // === DIARIO DE NAVARRA ===
    '31201' => ['nombre' => 'Pamplona',     'puerto' => null],
    
];

// ── FASE 1: PORTUS en paralelo (API móvil) ──────────────────────────────────
$portus_results = []; // [id_mun][param] => value

$mh_portus   = curl_multi_init();
$portus_handles = [];

foreach ($municipios as $id_mun => $info) {
    if (!$info['puerto']) continue;
    
    // La API móvil devuelve muchos parámetros en una sola llamada
    $url = "https://movil.puertos.es/simo/seastate/harbor/{$info['puerto']}/extended";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT,      "Mozilla/5.0");
    curl_multi_add_handle($mh_portus, $ch);
    $portus_handles[(int)$ch] = ['ch' => $ch, 'id' => $id_mun];
}

$running = null;
do { curl_multi_exec($mh_portus, $running); curl_multi_select($mh_portus, 0.5); } while ($running > 0);

foreach ($portus_handles as $hi) {
    $data = curl_multi_getcontent($hi['ch']);
    curl_multi_remove_handle($mh_portus, $hi['ch']);
    curl_close($hi['ch']);
    if ($data) {
        $js = json_decode($data, true);
        if (is_array($js)) {
            // Mapeo selectivo de parámetros
            if (isset($js['temperature'][0]['ts']) && $js['temperature'][0]['ts'] !== 'None') {
                $portus_results[$hi['id']]['Temperatura agua'] = $js['temperature'][0]['ts'];
            }
            if (isset($js['pressure'][0]['ps']) && $js['pressure'][0]['ps'] !== 'None') {
                $portus_results[$hi['id']]['Presión atmosférica'] = $js['pressure'][0]['ps'];
            }
            if (isset($js['AirTemp'][0]['ta']) && $js['AirTemp'][0]['ta'] !== 'None') {
                $portus_results[$hi['id']]['Temperatura del aire'] = $js['AirTemp'][0]['ta'];
            }
        }
    }
}
curl_multi_close($mh_portus);

// ── FASE 2: wttr.in en paralelo (todos los municipios) ────────────────────────
$mh_wttr   = curl_multi_init();
$wttr_handles = [];

foreach ($municipios as $id_mun => $info) {
    $url = "https://wttr.in/" . urlencode($info['nombre']) . "?format=j1";
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT,      "Mozilla/5.0");
    curl_multi_add_handle($mh_wttr, $ch);
    $wttr_handles[(int)$ch] = ['ch' => $ch, 'id' => $id_mun, 'nombre' => $info['nombre']];
}

$running = null;
do { curl_multi_exec($mh_wttr, $running); curl_multi_select($mh_wttr, 0.5); } while ($running > 0);

$wttr_results = [];
foreach ($wttr_handles as $hi) {
    $data = curl_multi_getcontent($hi['ch']);
    curl_multi_remove_handle($mh_wttr, $hi['ch']);
    curl_close($hi['ch']);
    if ($data) {
        $wttr = json_decode($data, true);
        if (isset($wttr['weather'][0])) {
            $wttr_results[$hi['id']] = $wttr;
        }
    }
}
curl_multi_close($mh_wttr);

// ── FASE 3: Combinar y guardar en BD ─────────────────────────────────────────
$stmt = $pdo->prepare("INSERT INTO datos_clima
    (id_municipio, nombre_municipio, fecha, humedad, precipitacion, viento_vel, viento_dir, presion, temp_agua, temp_max, temp_min)
    VALUES (:id, :nombre, :fecha, :hum, :prec, :v_vel, :v_dir, :pres, :t_agua, :t_max, :t_min)
    ON DUPLICATE KEY UPDATE
    humedad       = VALUES(humedad),
    precipitacion = VALUES(precipitacion),
    viento_vel    = VALUES(viento_vel),
    viento_dir    = VALUES(viento_dir),
    presion       = VALUES(presion),
    temp_agua     = VALUES(temp_agua),
    temp_max      = VALUES(temp_max),
    temp_min      = VALUES(temp_min)");

foreach ($municipios as $id_mun => $info) {
    $p = $portus_results[$id_mun] ?? [];
    $w = $wttr_results[$id_mun]   ?? [];

    if (!empty($w) && isset($w['weather'])) {
        foreach ($w['weather'] as $w_day) {
            $fecha_w = $w_day['date'] ?? date('Y-m-d');
            
            $temp_max  = $w_day['maxtempC'] ?? null;
            $temp_min  = $w_day['mintempC'] ?? null;
            
            // Intentar sacar humedad, presión y viento promedio de las horas centrales (12:00 o similar)
            $hourly    = $w_day['hourly'] ?? [];
            $mid       = isset($hourly[4]) ? $hourly[4] : ($hourly[0] ?? []);
            
            $precip = 0;
            foreach ($hourly as $hr) {
                $precip += (float)($hr['precipMM'] ?? 0);
            }
            $humedad    = $mid['humidity'] ?? null;
            $presion    = $mid['pressure'] ?? null;
            $viento_vel = $mid['windspeedKmph'] ?? null;
            $viento_dir = traducir_viento($mid['winddir16Point'] ?? null);
            
            $temp_agua = ($fecha_w === date('Y-m-d')) ? ($p['Temperatura agua'] ?? null) : null;

            $stmt->execute([
                'id'     => $id_mun,
                'nombre' => $info['nombre'],
                'fecha'  => $fecha_w,
                'hum'    => $humedad,
                'prec'   => $precip,
                'v_vel'  => $viento_vel,
                'v_dir'  => $viento_dir,
                'pres'   => $presion,
                't_agua' => $temp_agua,
                't_max'  => $temp_max,
                't_min'  => $temp_min,
            ]);
        }
    }

    echo "OK: {$info['nombre']} (hum={$humedad}%, T_max={$temp_max}, T_min={$temp_min}, viento={$viento_vel}km/h, presion={$presion}, agua={$temp_agua})\n";
}

file_put_contents($archivo_cache, time());
echo "\nCaptura completada.\n";
?>
