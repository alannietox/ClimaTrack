<?php
// capturar_aemet.php
require 'conexion.php';

/**
 * Clase para el cálculo geométrico de la Luna (Rise/Set)
 * Basado en algoritmos astronómicos adaptados a PHP
 */
class MoonCalc {
    const RAD = M_PI / 180;

    /**
     * Calcula los tiempos de salida y puesta de la Luna.
     */
    public static function getTimes($dateStr, $lat, $lng) {
        $t = strtotime($dateStr . " 00:00:00 UTC");
        $hc = 0.133 * self::RAD;
        $h0 = self::getAltitude($t, $lat, $lng);
        $rise = null; $set = null;
        
        // Buscamos cambios de altitud en intervalos de 2 horas
        for ($i = 1; $i <= 24; $i += 2) {
            $h1 = self::getAltitude($t + $i * 3600, $lat, $lng);
            $h2 = self::getAltitude($t + ($i + 1) * 3600, $lat, $lng);
            $a = ($h0 + $h2) / 2 - $h1;
            $b = ($h2 - $h0) / 2;
            $xe = -$b / (2 * $a);
            $ye = ($a * $xe + $b) * $xe + $h1;
            $d = $b * $b - 4 * $a * ($h1 - $hc);
            $roots = 0;
            if ($d >= 0) {
                $dx = sqrt($d) / (abs($a) * 2);
                $x1 = $xe - $dx; $x2 = $xe + $dx;
                if (abs($x1) <= 1) $roots++;
                if (abs($x2) <= 1) $roots++;
                if (abs($x1) > 1) $x1 = $x2;
                if ($roots > 0) {
                    $res = $i + $x1;
                    if ($h0 < $h2) $rise = $res; else $set = $res;
                }
            }
            $h0 = $h2;
        }
        return [
            'rise' => $rise ? date("H:i", $t + $rise * 3600) : '--:--',
            'set' => $set ? date("H:i", $t + $set * 3600) : '--:--'
        ];
    }

    /**
     * Calcula la altitud de la Luna en un momento dado.
     */
    private static function getAltitude($t, $lat, $lng) {
        $d = ($t / 86400) - 10957.5; // Días desde J2000
        
        // Coordenadas lunares (simplificadas pero robustas)
        $L = (218.316 + 13.176396 * $d) * self::RAD; // Longitud media
        $M = (134.963 + 13.064993 * $d) * self::RAD; // Anomalía media
        $F = (93.272 + 13.229350 * $d) * self::RAD;  // Distancia media

        $long = $L + 6.289 * self::RAD * sin($M);
        $lat_m = 5.128 * self::RAD * sin($F);
        
        $ra = atan2(sin($long) * cos(23.439 * self::RAD) - tan($lat_m) * sin(23.439 * self::RAD), cos($long));
        $dec = asin(sin($lat_m) * cos(23.439 * self::RAD) + cos($lat_m) * sin(23.439 * self::RAD) * sin($long));
        
        // Tiempo sidéreo local
        $sidereal = (280.4606 + 360.985647 * $d) * self::RAD + $lng * self::RAD;
        
        return asin(sin($lat * self::RAD) * sin($dec) + cos($lat * self::RAD) * cos($dec) * cos($sidereal - $ra));
    }
    
    /**
     * Calcula la fase lunar actual.
     */
    public static function getPhase($dateStr) {
        $t = strtotime($dateStr);
        $diff = $t - strtotime("2000-01-06 18:14:00 UTC");
        $cycle = 29.530588853;
        $phase = ($diff / (86400 * $cycle)) - floor($diff / (86400 * $cycle));
        if ($phase < 0) $phase += 1;
        if ($phase < 0.0625 || $phase > 0.9375) return 'Nueva';
        if ($phase < 0.1875) return 'Creciente';
        if ($phase < 0.3125) return 'Cuarto Creciente';
        if ($phase < 0.4375) return 'Gibosa Creciente';
        if ($phase < 0.5625) return 'Llena';
        if ($phase < 0.6875) return 'Gibosa Menguante';
        if ($phase < 0.8125) return 'Cuarto Menguante';
        return 'Menguante';
    }
}


// API Key de AEMET OpenData (obtener en https://opendata.aemet.es/centrodedescargas/altaUsuario)
$apiKey = getenv('AEMET_API_KEY') ?: 'TU_API_KEY_AQUI';
$isAjax = isset($_GET['ajax']);
$limit = $isAjax ? " LIMIT 10 " : ""; // Solo actualizamos 10 si es desde la UI para evitar timeouts
set_time_limit(0); // Evitar que el proceso se muera a los 30 segundos

// Solo ejecutar si se llama directamente
if (basename($_SERVER['PHP_SELF']) == 'capturar_aemet.php') {
    // 1. Obtener las localidades marcadas con un 1 en "Activado"
    $donde = " WHERE Activado = 1 ";
    $params = [];
    
    // 1. Determinar filtros (Soporte para CLI y Web)
if (php_sapi_name() === 'cli') {
    $arg = $argv[1] ?? '';
    if (strpos($arg, 'ids=') === 0) {
        $_GET['ids'] = substr($arg, 4);
    } elseif (strpos($arg, 'periodico=') === 0) {
        $_GET['periodico'] = substr($arg, 10);
    }
}

    $periodico_cache = $_GET['periodico'] ?? 'general';
    if (!empty($_GET['ids']) && empty($_GET['periodico'])) {
        $periodico_cache = 'seleccion';
    }
    $archivo_cache = __DIR__ . "/cache/cache_aemet_{$periodico_cache}.txt";
    $tiempo_espera = 1800; // 30 minutos

    if (file_exists($archivo_cache)) {
        $ultima_captura = (int)file_get_contents($archivo_cache);
        if ((time() - $ultima_captura) < $tiempo_espera) {
            if ($isAjax) {
                echo "OK";
            } else {
                echo "Saltando captura AEMET para $periodico_cache: Datos recientes (menos de 30 min).\n";
            }
            exit;
        }
    }


if (!empty($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $donde .= " AND Id_Loc_Aemet IN ($placeholders) ";
    $params = array_merge($params, $ids);
} elseif (!empty($_GET['periodico'])) {
    require 'periodicos_mapping.php';
    if (isset($periodicos[$_GET['periodico']])) {
        $ids_p = $periodicos[$_GET['periodico']];
        if (!empty($ids_p)) {
            $placeholders = implode(',', array_fill(0, count($ids_p), '?'));
            $donde .= " AND Id_Loc_Aemet IN ($placeholders) ";
            $params = array_merge($params, $ids_p);
        }
    }
} elseif ($isAjax) {
    // Si es AJAX y no hay filtros, no hacemos nada por seguridad
    echo "No se han especificado filtros.";
    exit;
}

$stmt = $pdo->prepare("SELECT Id_Loc_Aemet, Nombre, Latitud, Longitud FROM localidades " . $donde);
$stmt->execute($params);
$localidades = $stmt->fetchAll();

$sql_insert = "INSERT INTO datos_clima 
               (id_municipio, nombre_municipio, fecha, temp_min, temp_max, estado_cielo, estado_manana, estado_tarde, orto_sol, ocaso_sol, orto_luna, ocaso_luna, fase_lunar, precipitacion) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE 
               temp_min = VALUES(temp_min), 
               temp_max = VALUES(temp_max), 
               estado_cielo = VALUES(estado_cielo), 
               estado_manana = VALUES(estado_manana),
               estado_tarde = VALUES(estado_tarde),
               orto_sol = VALUES(orto_sol), 
               ocaso_sol = VALUES(ocaso_sol),
               orto_luna = VALUES(orto_luna),
               ocaso_luna = VALUES(ocaso_luna),
               fase_lunar = VALUES(fase_lunar),
               precipitacion = VALUES(precipitacion)";

$stmt_insert = $pdo->prepare($sql_insert);

foreach ($localidades as $loc) {
    $codigo_municipio = $loc['Id_Loc_Aemet'];
    $nombre_municipio = $loc['Nombre'];
    
    // Identificar si es Canarias por el código de provincia (35 o 38)
    $is_canarias = (strpos($codigo_municipio, '35') === 0 || strpos($codigo_municipio, '38') === 0);

    $url = "https://opendata.aemet.es/opendata/api/prediccion/especifica/municipio/diaria/{$codigo_municipio}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
        "api_key: " . $apiKey, 
        "Accept: application/json",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $respuesta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $datos_url = json_decode($respuesta, true);
        
        if (isset($datos_url['datos'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $datos_url['datos']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36"]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $json_clima = curl_exec($ch);
            curl_close($ch);
            
            $json_clima = mb_convert_encoding($json_clima, 'UTF-8', 'ISO-8859-15');
            $datos_prediccion = json_decode($json_clima, true);
            
            if (isset($datos_prediccion[0]['prediccion']['dia'])) {
                $dias = $datos_prediccion[0]['prediccion']['dia'];
                $lat_city = (float)($loc['Latitud'] ?? 40.41);
                $lng_city = (float)($loc['Longitud'] ?? -3.7);

                foreach ($dias as $dia) {
                    $fecha_aemet = substr($dia['fecha'], 0, 10);
                    
                    // Ajuste de zona horaria según municipio
                    $dt = new DateTime($fecha_aemet, new DateTimeZone('Europe/Madrid'));
                    if ($is_canarias) $dt->setTimezone(new DateTimeZone('Atlantic/Canary'));
                    $offset_s = $dt->getOffset();

                    $sun_info = date_sun_info(strtotime($fecha_aemet), $lat_city, $lng_city);
                    $orto = date("H:i", $sun_info['sunrise'] + $offset_s);
                    $ocaso = date("H:i", $sun_info['sunset'] + $offset_s);
                    
                    $moonTimes = MoonCalc::getTimes($fecha_aemet, $lat_city, $lng_city);
                    $fixT = function($tStr, $off) {
                        if ($tStr == '--:--') return $tStr;
                        return date("H:i", strtotime("1970-01-01 " . $tStr . " UTC") + $off);
                    };
                    $orto_luna = $fixT($moonTimes['rise'], $offset_s);
                    $ocaso_luna = $fixT($moonTimes['set'], $offset_s);
                    $fase_lunar = MoonCalc::getPhase($fecha_aemet);

                    $temp_max = $dia['temperatura']['maxima'] ?? null;
                    $temp_min = $dia['temperatura']['minima'] ?? null;
                    
                    $estado_cielo = null;
                    $estado_manana = null;
                    $estado_tarde = null;

                    if (isset($dia['estadoCielo'])) {
                        foreach ($dia['estadoCielo'] as $estado) {
                            if (!empty($estado['descripcion'])) {
                                if ($estado_cielo === null) $estado_cielo = $estado['descripcion'];
                                if (isset($estado['periodo'])) {
                                    if ($estado['periodo'] == '06-12') $estado_manana = $estado['descripcion'];
                                    if ($estado['periodo'] == '12-18') $estado_tarde = $estado['descripcion'];
                                }
                            }
                        }
                    }
                    
                    if ($estado_manana === null) $estado_manana = $estado_cielo;
                    if ($estado_tarde === null) $estado_tarde = $estado_cielo;
                    
                    // Precipitación (Probabilidad máx del día)
                    $prob_precip = null;
                    if (isset($dia['probPrecipitacion'])) {
                        foreach ($dia['probPrecipitacion'] as $pp) {
                            $val = (int)$pp['value'];
                            if ($prob_precip === null || $val > $prob_precip) {
                                $prob_precip = $val;
                            }
                        }
                    }

                    $stmt_insert->execute([
                        $codigo_municipio, 
                        $nombre_municipio, 
                        $fecha_aemet, 
                        $temp_min, 
                        $temp_max, 
                        $estado_cielo, 
                        $estado_manana,
                        $estado_tarde,
                        $orto, 
                        $ocaso,
                        $orto_luna,
                        $ocaso_luna,
                        $fase_lunar,
                        $prob_precip
                    ]);
                }
                if (!$isAjax) echo "✅ $nombre_municipio OK\n";
            }
        }
    } else {
        if (!$isAjax) echo "⚠️ Error $http_code en $nombre_municipio. Intentando fallback (AEMET XML Público)...\n";
        
        $id_corto = str_pad(substr($codigo_municipio, 0, 5), 5, '0', STR_PAD_LEFT);
        $url_f = "https://www.aemet.es/xml/municipios/localidad_{$id_corto}.xml";
        
        $ch_f = curl_init();
        curl_setopt($ch_f, CURLOPT_URL, $url_f);
        curl_setopt($ch_f, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_f, CURLOPT_TIMEOUT, 15);
        $res_f = curl_exec($ch_f);
        $code_f = curl_getinfo($ch_f, CURLINFO_HTTP_CODE);
        curl_close($ch_f);

        if ($code_f == 200 && $res_f) {
            $xml_f = @simplexml_load_string($res_f);
            if ($xml_f && isset($xml_f->prediccion->dia)) {
                foreach ($xml_f->prediccion->dia as $dia_f) {
                    $fecha_f = (string)$dia_f['fecha'];
                    // if ($fecha_f < date('Y-m-d')) continue; // Saltamos solo si es pasado

                    $temp_max = (string)$dia_f->temperatura->maxima;
                    $temp_min = (string)$dia_f->temperatura->minima;
                    
                    $estado_cielo = ""; $estado_m = ""; $estado_t = "";
                    foreach ($dia_f->estado_cielo as $ec) {
                        $p = (string)$ec['periodo'];
                        $desc = (string)$ec['descripcion'];
                        if ($p == '00-24' || empty($estado_cielo)) $estado_cielo = $desc;
                        if ($p == '06-12') $estado_m = $desc;
                        if ($p == '12-18' || $p == '12-24') $estado_t = $desc;
                    }
                    if (empty($estado_m)) $estado_m = $estado_cielo;
                    if (empty($estado_t)) $estado_t = $estado_cielo;

                    // Precipitación (Fallback XML)
                    $prob_p = null;
                    if (isset($dia_f->prob_precipitacion)) {
                        foreach ($dia_f->prob_precipitacion as $pp) {
                            $val = (int)$pp;
                            if ($prob_p === null || $val > $prob_p) $prob_p = $val;
                        }
                    }

                    // Datos astronómicos locales
                    $lat_f = (float)($loc['Latitud'] ?? 40.41);
                    $lng_f = (float)($loc['Longitud'] ?? -3.7);
                    
                    $dt_f = new DateTime($fecha_f, new DateTimeZone('Europe/Madrid'));
                    if ($is_canarias) $dt_f->setTimezone(new DateTimeZone('Atlantic/Canary'));
                    $off_f = $dt_f->getOffset();

                    $sun_f = date_sun_info(strtotime($fecha_f), $lat_f, $lng_f);
                    $orto_s = date("H:i", $sun_f['sunrise'] + $off_f);
                    $ocaso_s = date("H:i", $sun_f['sunset'] + $off_f);

                    $moon_f = MoonCalc::getTimes($fecha_f, $lat_f, $lng_f);
                    $fixT_f = function($tStr, $off) {
                        if ($tStr == '--:--') return $tStr;
                        return date("H:i", strtotime("1970-01-01 " . $tStr . " UTC") + $off);
                    };
                    $orto_l = $fixT_f($moon_f['rise'], $off_f);
                    $ocaso_l = $fixT_f($moon_f['set'], $off_f);
                    $fase_l = MoonCalc::getPhase($fecha_f);

                    $stmt_insert->execute([
                        $codigo_municipio, $nombre_municipio, $fecha_f, 
                        $temp_min, $temp_max, $estado_cielo, $estado_m, $estado_t,
                        $orto_s, $ocaso_s, $orto_l, $ocaso_l, $fase_l, $prob_p
                    ]);
                }
                if (!$isAjax) echo "✅ $nombre_municipio OK (Fallback AEMET XML)\n";
                continue; // Pasamos a la siguiente localidad
            }
        }
        
        // Si el fallback de AEMET XML falla, intentamos wttr.in como ULTIMO recurso
        if (!$isAjax) echo "⚠️ Fallback AEMET XML fallido para $nombre_municipio. Intentando wttr.in...\n";
        $url_w = "https://wttr.in/" . urlencode($nombre_municipio) . "?format=j1&lang=es";
        $ch_w = curl_init();
        curl_setopt($ch_w, CURLOPT_URL, $url_w);
        curl_setopt($ch_w, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_w, CURLOPT_TIMEOUT, 10);
        $res_w = curl_exec($ch_w);
        curl_close($ch_w);

        if ($res_w) {
            $datos_w = json_decode($res_w, true);
            if (isset($datos_w['weather'])) {
                foreach ($datos_w['weather'] as $i => $w_f) {
                    if ($i == 0) continue;
                    $fecha_f = $w_f['date'];
                    $stmt_insert->execute([
                        $codigo_municipio, $nombre_municipio, $fecha_f,
                        $w_f['mintempC'], $w_f['maxtempC'],
                        $w_f['hourly'][4]['lang_es'][0]['value'] ?? 'Despejado',
                        $w_f['hourly'][2]['lang_es'][0]['value'] ?? null,
                        $w_f['hourly'][5]['lang_es'][0]['value'] ?? null,
                        null, null, '--:--', '--:--', null, null
                    ]);
                }
                if (!$isAjax) echo "✅ $nombre_municipio OK (Fallback wttr.in final)\n";
            }
        } else {
            if (!$isAjax) echo "❌ Todos los fallbacks fallaron para $nombre_municipio\n";
        }
    }
}

    file_put_contents($archivo_cache, time());
    if ($isAjax) {
        echo "OK";
        exit;
    }
    echo "\n¡Proceso finalizado!";
}
?>
