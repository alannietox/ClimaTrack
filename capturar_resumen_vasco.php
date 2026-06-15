<?php
// capturar_resumen_vasco.php
require 'conexion.php';

$archivo_cache = __DIR__ . "/cache/cache_resumen_vasco.txt";
$tiempo_espera = 1800; // 30 minutos

if (file_exists($archivo_cache)) {
    $ultima_captura = (int)file_get_contents($archivo_cache);
    if ((time() - $ultima_captura) < $tiempo_espera) {
        echo "Saltando captura Resumen Vasco: Datos recientes (menos de 30 min).\n";
        exit;
    }
}


function getVascoData() {
    global $pdo;
    
    // Fechas: hoy y mañana
    $fechas = [
        date('Y-m-d', strtotime('+1 day')),
        date('Y-m-d', strtotime('+2 day'))
    ];

    // 1. Obtener datos de wttr.in (Forecast)
    $url_wttr = "https://wttr.in/Donostia?format=j1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_wttr);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $res_wttr = curl_exec($ch);
    curl_close($ch);

    $wttr = json_decode($res_wttr, true);
    
    // 2. Obtener Temperatura del Agua de Portus (API móvil de Puertos del Estado)
    // Usamos el ID de puerto 11110 (Pasaia)
    $url_portus = "https://movil.puertos.es/simo/seastate/harbor/11110/extended";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_portus);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
    $res_portus = curl_exec($ch);
    curl_close($ch);
    
    $portus_json = json_decode($res_portus, true);
    $water_temp_now = isset($portus_json['temperature'][0]['ts']) ? $portus_json['temperature'][0]['ts'] : null;
    // Datos de oleaje en tiempo real de Portus (buoy 1101) - clave: 'swell'
    $ola_alt_real  = isset($portus_json['swell'][0]['hm0'])  ? $portus_json['swell'][0]['hm0']  : null;
    $ola_per_real  = isset($portus_json['swell'][0]['tp'])   ? $portus_json['swell'][0]['tp']   : null;
    $ola_grd_real  = isset($portus_json['swell'][0]['dmd'])  ? $portus_json['swell'][0]['dmd']  : null;

    $stmt = $pdo->prepare("INSERT INTO resumen_vasco 
        (fecha, viento_vel, viento_dir, olas_altura, olas_periodo, olas_dir, temp_ambiente, nubosidad, temp_agua)
        VALUES (:fecha, :v_vel, :v_dir, :o_alt, :o_per, :o_dir, :t_amb, :nub, :t_agua)
        ON DUPLICATE KEY UPDATE
        viento_vel = VALUES(viento_vel),
        viento_dir = VALUES(viento_dir),
        olas_altura = VALUES(olas_altura),
        olas_periodo = VALUES(olas_periodo),
        olas_dir = VALUES(olas_dir),
        temp_ambiente = VALUES(temp_ambiente),
        nubosidad = VALUES(nubosidad),
        temp_agua = VALUES(temp_agua)");

    // Convierte grados a punto cardinal en español
    function grados2dir($deg) {
        $dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
        return $dirs[round($deg / 22.5) % 16];
    }

    // Traducción de punto cardinal inglés a español
    function traducirDir($dir) {
        $map = [
            'N' => 'N', 'NNE' => 'NNE', 'NE' => 'NE', 'ENE' => 'ENE',
            'E' => 'E', 'ESE' => 'ESE', 'SE' => 'SE', 'SSE' => 'SSE',
            'S' => 'S', 'SSW' => 'SSO', 'SW' => 'SO', 'WSW' => 'OSO',
            'W' => 'O', 'WNW' => 'ONO', 'NW' => 'NO', 'NNW' => 'NNO'
        ];
        return $map[$dir] ?? $dir;
    }

    foreach ($fechas as $index => $fecha) {
        if (!isset($wttr['weather'][$index])) continue;
        
        $day_data = $wttr['weather'][$index];
        // Tomamos el mediodía (12:00) como referencia (índice 4 en hourly de 3h)
        $noon = $day_data['hourly'][4] ?? end($day_data['hourly']);
        
        $v_vel = $noon['windspeedKmph'] ?? '0';
        $v_dir = traducirDir($noon['winddir16Point'] ?? 'N');
        // Oleaje: usamos datos reales de Portus (misma boya para ambos días)
        $o_alt = $ola_alt_real !== null ? number_format((float)$ola_alt_real, 1) : 'N/D';
        $o_per = $ola_per_real !== null ? number_format((float)$ola_per_real, 0) : 'N/D';
        $o_dir = $ola_grd_real !== null ? grados2dir((float)$ola_grd_real) : 'N/D';
        $t_amb = $noon['tempC'] ?? $day_data['maxtempC'];
        $nub   = $noon['cloudcover'] ?? '0';
        
        // El agua de wttr.in suele ser más fiable si aparece, sino usamos Portus como estimación
        $t_agua = $noon['waterTemp_C'] ?? $water_temp_now;
        
        $stmt->execute([
            'fecha'  => $fecha,
            'v_vel'  => $v_vel,
            'v_dir'  => $v_dir,
            'o_alt'  => $o_alt,
            'o_per'  => $o_per,
            'o_dir'  => $o_dir,
            't_amb'  => $t_amb,
            'nub'    => $nub,
            't_agua' => $t_agua
        ]);
        
        echo "Capturado DV ($fecha): Viento={$v_vel}km/h, Olas={$o_alt}m, T_amb={$t_amb}, Agua={$t_agua}\n";
    }
}

getVascoData();
file_put_contents($archivo_cache, time());
echo "Captura de resumen Vasco completada.\n";
?>
