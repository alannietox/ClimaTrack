<?php
// capturar_embalses.php
require 'conexion.php';

$archivo_cache = __DIR__ . "/cache/cache_embalses.txt";
$tiempo_espera = 1800; // 30 minutos

if (file_exists($archivo_cache)) {
    $ultima_captura = (int)file_get_contents($archivo_cache);
    if ((time() - $ultima_captura) < $tiempo_espera) {
        echo "Saltando captura Embalses: Datos recientes (menos de 30 min).\n";
        exit;
    }
}


$embalses = [
    'Alloz'   => 'https://embalses.info/embalse/alloz',
    'Ebro'    => 'https://embalses.info/embalse/ebro',
    'Eugui'   => 'https://embalses.info/embalse/eugui',
    'Irabia'  => 'https://embalses.info/embalse/irabia',
    'Itoiz'   => 'https://embalses.info/embalse/itoiz',
    'Urdalur' => 'https://embalses.info/embalse/urdalur',
    'Yesa'    => 'https://embalses.info/embalse/yesa',
];

$fecha = date('Y-m-d');

foreach ($embalses as $nombre => $url) {
    echo "Capturando $nombre... ";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        echo "Error al cargar la URL.\n";
        continue;
    }
    
    // Extraer datos usando regex más específicos
    // Porcentaje: XX.XX % o XX,XX %
    preg_match('/(\d+[,.]\d+|\d+)\s*%/', $html, $matches_perc);
    $porcentaje = isset($matches_perc[1]) ? (float)str_replace(',', '.', $matches_perc[1]) : null;
    
    // Intentar encontrar Capacidad
    $capacidad = null;
    if (preg_match('/Capacidad<\/p>\s*<p[^>]*>([\d\.,]+)\s*hm³/i', $html, $m)) {
        $capacidad = (float)str_replace(',', '.', $m[1]);
    }

    // Intentar encontrar Volumen
    $volumen = null;
    if (preg_match('/Volumen<\/p>\s*<p[^>]*>([\d\.,]+)\s*hm³/i', $html, $m)) {
        $volumen = (float)str_replace(',', '.', $m[1]);
    }

    // Búsqueda genérica si fallan los anteriores
    if ($capacidad === null) {
        if (preg_match('/Capacidad.*?([\d\.,]+)\s*hm³/is', $html, $m)) {
            $capacidad = (float)str_replace(',', '.', $m[1]);
        }
    }
    if ($volumen === null) {
        if (preg_match('/Volumen.*?([\d\.,]+)\s*hm³/is', $html, $m)) {
            $volumen = (float)str_replace(',', '.', $m[1]);
        }
    }
    
    // Si aún falta volumen pero hay capacidad y porcentaje, calcularlo (es lo más fiable si el scrape falla)
    if ($volumen === null && $capacidad !== null && $porcentaje !== null) {
        $volumen = round($capacidad * ($porcentaje / 100), 2);
    }
    // Caso especial: si volumen terminó siendo igual a capacidad pero porcentaje < 99, recalcular
    if ($volumen == $capacidad && $porcentaje < 98 && $capacidad > 0) {
        $volumen = round($capacidad * ($porcentaje / 100), 2);
    }

    if ($porcentaje !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO embalses (nombre, volumen_actual, capacidad_total, porcentaje, fecha) 
                                   VALUES (?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   volumen_actual = VALUES(volumen_actual),
                                   capacidad_total = VALUES(capacidad_total),
                                   porcentaje = VALUES(porcentaje)");
            $stmt->execute([$nombre, $volumen, $capacidad, $porcentaje, $fecha]);
            echo "OK ($porcentaje%)\n";
        } catch (Exception $e) {
            echo "Error DB: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No se encontraron datos.\n";
    }
}

file_put_contents($archivo_cache, time());
echo "Captura finalizada.\n";
?>
