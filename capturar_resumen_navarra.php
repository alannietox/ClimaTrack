<?php
// capturar_resumen_navarra.php - Con fallback a Open-Meteo Archive
require 'conexion.php';

$archivo_cache = __DIR__ . "/cache/cache_resumen_navarra.txt";
$tiempo_espera = 1800; // 30 minutos

if (file_exists($archivo_cache)) {
    $ultima_captura = (int)file_get_contents($archivo_cache);
    if ((time() - $ultima_captura) < $tiempo_espera) {
        echo "Saltando captura Resumen Navarra: Datos recientes (menos de 30 min).\n";
        exit;
    }
}


// API Key de AEMET (no se usa en este archivo, se mantiene por si se necesita en el futuro)
$apiKey = getenv('AEMET_API_KEY') ?: 'TU_API_KEY_AQUI';

$estaciones = [
    '9263D' => ['nombre' => 'Pamplona', 'lat' => 42.8125, 'lon' => -1.6458]
];

$fechas = [
    'hoy'  => date('Y-m-d'),
    'ayer' => date('Y-m-d', strtotime('-1 day'))
];

$sql_insert = "INSERT INTO datos_clima 
               (id_municipio, nombre_municipio, fecha, humedad, presion, radiacion_solar, racha_viento, temp_max, temp_min) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE 
               humedad = VALUES(humedad),
               presion = VALUES(presion),
               radiacion_solar = VALUES(radiacion_solar),
               racha_viento = VALUES(racha_viento),
               temp_max = VALUES(temp_max),
               temp_min = VALUES(temp_min)";

$stmt = $pdo->prepare($sql_insert);

foreach ($estaciones as $id_est => $info) {
    foreach ($fechas as $label => $fecha) {
        $success = false;
        
        $is_today = ($fecha === date('Y-m-d'));
        $api_type = $is_today ? "forecast" : "archive";
        $daily_params = "temperature_2m_max,temperature_2m_min,wind_gusts_10m_max,shortwave_radiation_sum";
        $hourly_params = "relative_humidity_2m,surface_pressure";
        
        $url_om = "https://{$api_type}-api.open-meteo.com/v1/{$api_type}?latitude={$info['lat']}&longitude={$info['lon']}&start_date={$fecha}&end_date={$fecha}&daily={$daily_params}&hourly={$hourly_params}&timezone=Europe%2FMadrid";
        
        if ($is_today) {
             $url_om = "https://api.open-meteo.com/v1/forecast?latitude={$info['lat']}&longitude={$info['lon']}&daily={$daily_params}&hourly={$hourly_params}&timezone=Europe%2FMadrid&forecast_days=1";
        }

        $res_om = @file_get_contents($url_om);
        if ($res_om) {
            $om = json_decode($res_om, true);
            if (isset($om['daily']['time'][0])) {
                $t_max = $om['daily']['temperature_2m_max'][0];
                $t_min = $om['daily']['temperature_2m_min'][0];
                $racha = $om['daily']['wind_gusts_10m_max'][0];
                $radiacion = $om['daily']['shortwave_radiation_sum'][0]; 

                // Procesar horarios para Humedad y Presión
                $humedades = $om['hourly']['relative_humidity_2m'] ?? [];
                $presiones = $om['hourly']['surface_pressure'] ?? [];
                
                $hum_media = !empty($humedades) ? round(array_sum($humedades) / count($humedades)) : null;
                $pres_media = !empty($presiones) ? round(array_sum($presiones) / count($presiones)) : null;
                
                $stmt->execute([
                    $id_est, $info['nombre'], $om['daily']['time'][0], $hum_media, $pres_media, $radiacion, $racha, $t_max, $t_min
                ]);
                echo "Capturado resumen Navarra para {$info['nombre']} ($fecha): Racha=$racha km/h, Hum=$hum_media%, Pres=$pres_media hPa\n";
                $success = true;
            }
        }
    }
}

file_put_contents($archivo_cache, time());
echo "\nCaptura de resumen histórico Navarra completada.\n";
?>
