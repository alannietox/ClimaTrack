<?php
require 'conexion.php';
require 'periodicos_mapping.php';
require 'refranes_helper.php';

/**
 * Calcula las próximas 4 fases lunares principales a partir de una fecha base.
 */
function getNextMoonPhases($startTime) {
    $synodicMonth = 29.530588853;
    $knownNewMoon = strtotime("2000-01-06 18:14:00 UTC");
    $diff = ($startTime - $knownNewMoon) / (24 * 3600);
    $lunations = $diff / $synodicMonth;
    
    $phases = [
        "Nueva" => 0,
        "Creciente" => 0.25,
        "Llena" => 0.5,
        "Menguante" => 0.75
    ];
    
    $results = [];
    for ($i = 0; $i < 8; $i++) {
        $targetProgress = floor($lunations) + ($i * 0.25);
        $eventTime = $knownNewMoon + ($targetProgress * $synodicMonth * 24 * 3600);
        
        if ($eventTime > $startTime) {
            $phaseName = array_search(fmod($targetProgress, 1.0), $phases);
            if ($phaseName !== false) {
                $meses = ["", "enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
                $results[] = [
                    "fase" => $phaseName,
                    "fecha" => date("j", $eventTime) . " de " . $meses[(int)date("n", $eventTime)]
                ];
            }
        }
        if (count($results) >= 4) break;
    }
    return $results;
}

/**
 * Mapea la descripción AEMET del estado del cielo al nombre de icono EPS
 * de la carpeta iconos/iconos_diario_navarra/
 */
function mapear_icono_navarra($estado) {
    if (empty($estado)) return 'variable.eps';
    $estado_lower = mb_strtolower(trim($estado), 'UTF-8');

    // Tormenta
    if (strpos($estado_lower, 'tormenta') !== false) return 'tormenta.eps';
    // Granizo
    if (strpos($estado_lower, 'granizo') !== false) return 'granizo.eps';
    // Nieve
    if (strpos($estado_lower, 'nieve') !== false) return 'nieve.eps';
    // Chubascos
    if (strpos($estado_lower, 'chubasco') !== false) return 'chubascos.eps';
    // Lluvia
    if (strpos($estado_lower, 'lluvia') !== false) return 'lluvia.eps';
    // Niebla
    if (strpos($estado_lower, 'niebla') !== false || strpos($estado_lower, 'bruma') !== false || strpos($estado_lower, 'calima') !== false) return 'niebla.eps';
    // Cubierto (sin precipitación)
    if (strpos($estado_lower, 'cubierto') !== false) return 'nubes.eps';
    // Muy nuboso
    if (strpos($estado_lower, 'muy nuboso') !== false) return 'nubes.eps';
    // Nuboso / Intervalos nubosos
    if (strpos($estado_lower, 'nuboso') !== false || strpos($estado_lower, 'intervalos') !== false) return 'nuboso.eps';
    // Poco nuboso
    if (strpos($estado_lower, 'poco') !== false) return 'variable.eps';
    // Despejado / Cielo claro
    if (strpos($estado_lower, 'despejado') !== false || strpos($estado_lower, 'claro') !== false) return 'sol.eps';
    // Default
    return 'variable.eps';
}

/**
 * Mapea la descripción AEMET del estado del cielo al nombre de icono EPS
 * de la carpeta iconos/iconos_vocento/
 */
function mapear_icono_vocento($estado) {
    if (empty($estado)) return 'nuboso.eps';
    $estado_lower = mb_strtolower(trim($estado), 'UTF-8');

    // Tormenta
    if (strpos($estado_lower, 'tormenta') !== false) return 'tormenta.eps';
    // Granizo
    if (strpos($estado_lower, 'granizo') !== false) return 'granizo.eps';
    // Nieve
    if (strpos($estado_lower, 'nieve') !== false) return 'nieve.eps';
    // Chubascos
    if (strpos($estado_lower, 'chubasco') !== false) return 'chubascos.eps';
    // Lluvia moderada/fuerte
    if (strpos($estado_lower, 'lluvia') !== false) return 'llovizna.eps';
    // Niebla
    if (strpos($estado_lower, 'niebla') !== false || strpos($estado_lower, 'bruma') !== false || strpos($estado_lower, 'calima') !== false) return 'niebla.eps';
    // Cubierto
    if (strpos($estado_lower, 'cubierto') !== false) return 'cubierto.eps';
    // Muy nuboso
    if (strpos($estado_lower, 'muy nuboso') !== false) return 'muynuboso.eps';
    // Nuboso / Intervalos nubosos
    if (strpos($estado_lower, 'nuboso') !== false || strpos($estado_lower, 'intervalos') !== false) return 'nuboso.eps';
    // Poco nuboso
    if (strpos($estado_lower, 'poco') !== false) return 'nuboso.eps';
    // Despejado / Cielo claro
    if (strpos($estado_lower, 'despejado') !== false || strpos($estado_lower, 'claro') !== false) return 'sol.eps';
    // Default
    return 'nuboso.eps';
}

/**
 * Determina si un periódico usa iconos Vocento (todos excepto correo, diario_vasco y diario_navarra)
 */
function usa_iconos_vocento($periodico) {
    if (empty($periodico)) return false;
    if ($periodico === 'diario_navarra') return false;
    if (stripos($periodico, 'correo') !== false) return false;
    if (stripos($periodico, 'diario_vasco') !== false) return false;
    return true;
}

/**
 * Mapea la descripción AEMET al nombre de icono GIF de iconos/iconos_aemet/
 * Los ficheros se llaman igual que las descripciones (minúsculas + guiones bajos).
 */
function mapear_icono_aemet($estado) {
    if (empty($estado)) return 'nuboso.gif';
    // Normalizar: minúsculas, espacios -> guiones bajos, tildes básicas
    $nombre = mb_strtolower(trim($estado), 'UTF-8');
    $nombre = str_replace(
        ['á','é','í','ó','ú','ü','ñ',' '],
        ['a','e','i','o','u','u','n','_'],
        $nombre
    );
    // Limpiar caracteres no válidos para nombre de fichero
    $nombre = preg_replace('/[^a-z0-9_]/', '', $nombre);
    $nombre = preg_replace('/_+/', '_', $nombre); // colapsar dobles guiones
    $file   = $nombre . '.gif';
    // Validar que existe; si no, fallback
    $path   = __DIR__ . '/iconos/iconos_aemet/' . $file;
    return file_exists($path) ? $file : 'nuboso.gif';
}

/**
 * Mapea la descripción AEMET del estado del cielo al nombre de icono EPS
 * de la carpeta iconos/iconos_dv/
 */
function mapear_icono_dv($estado) {
    if (empty($estado)) return 'nuboso.eps';
    $estado_lower = mb_strtolower(trim($estado), 'UTF-8');

    // Tormenta
    if (strpos($estado_lower, 'tormenta') !== false) return 'tormenta.eps';
    // Granizo
    if (strpos($estado_lower, 'granizo') !== false) return 'granizo.eps';
    // Nieve
    if (strpos($estado_lower, 'nieve') !== false) return 'nieve.eps';
    // Chubascos
    if (strpos($estado_lower, 'chubasco') !== false) return 'chubascos.eps';
    // Lluvia moderada/fuerte
    if (strpos($estado_lower, 'lluvia') !== false) return 'llovizna.eps';
    // Niebla
    if (strpos($estado_lower, 'niebla') !== false || strpos($estado_lower, 'bruma') !== false || strpos($estado_lower, 'calima') !== false) return 'niebla.eps';
    // Cubierto
    if (strpos($estado_lower, 'cubierto') !== false) return 'cubierto.eps';
    // Muy nuboso
    if (strpos($estado_lower, 'muy nuboso') !== false) return 'muynuboso.eps';
    // Nuboso / Intervalos nubosos
    if (strpos($estado_lower, 'nuboso') !== false || strpos($estado_lower, 'intervalos') !== false) return 'nuboso.eps';
    // Poco nuboso
    if (strpos($estado_lower, 'poco') !== false) return 'nuboso.eps';
    // Despejado / Cielo claro
    if (strpos($estado_lower, 'despejado') !== false || strpos($estado_lower, 'claro') !== false) return 'sol.eps';
    // Default
    return 'nuboso.eps';
}

/**
 * Mapea la temperatura máxima al nombre de fondo EPS para Diario de Navarra
 */
function mapear_fondo_navarra($temp_max) {
    if ($temp_max === null || $temp_max === '') return null;
    $t = (float)$temp_max;

    if ($t < 0) return 'azul.eps';
    if ($t >= 0 && $t < 5) return 'morado.eps';
    if ($t >= 5 && $t < 10) return 'lila.eps';
    if ($t >= 10 && $t < 15) return 'azul_claro.eps';
    if ($t >= 15 && $t < 20) return 'verde.eps';
    if ($t >= 20 && $t < 25) return 'amarillo.eps';
    if ($t >= 25 && $t < 30) return 'ocre.eps';
    if ($t >= 30 && $t < 35) return 'anaranjado.eps';
    if ($t >= 35 && $t <= 40) return 'naranja.eps';
    if ($t > 40) return 'rojo.eps';
    
    return 'verde.eps'; // Fallback
}

/**
 * Formatea una fecha como "DÍA_SEMANA NÚMERO" (ej: "MIÉRCOLES 01")
 */
function formatear_fecha_prensa($fecha) {
    $dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO'];
    $ts = strtotime($fecha);
    if (!$ts) return "";
    $nombre_dia = $dias[date('w', $ts)];
    $numero_dia = date('d', $ts);
    return "$nombre_dia $numero_dia";
}

/**
 * Sanitiza el nombre de un municipio para convertirlo en una etiqueta XML válida.
 */
function sanitizar_tag_xml($nombre) {
    // Reemplazar espacios, barras, guiones, comas y paréntesis por guiones bajos
    $nombre_valido = preg_replace('/[ \/(),.-]+/', '_', $nombre);
    // Eliminar caracteres no permitidos en XML (letras Unicode, números y guiones bajos son válidos)
    $nombre_valido = preg_replace('/[^\p{L}\p{N}_]/u', '', $nombre_valido);
    // Eliminar guiones bajos al principio o final
    $nombre_valido = trim($nombre_valido, '_');
    // En XML las etiquetas no pueden empezar por número, si es así anteponer un guion bajo
    if (preg_match('/^[0-9]/', $nombre_valido)) {
        $nombre_valido = '_' . $nombre_valido;
    }
    return !empty($nombre_valido) ? $nombre_valido : 'municipio';
}

$baseUrl = 'file:///C:/Proyectos/TIEMPO/PERIODICOS/ICONOS/';

if (php_sapi_name() === 'cli') {
    $periodico_name = $argv[1] ?? '';
    // Para CLI, si el argumento contiene 'periodico=', lo limpiamos
    if (strpos($periodico_name, 'periodico=') === 0) {
        $periodico_name = substr($periodico_name, 10);
    }
} else {
    $periodico_name = $_GET['periodico'] ?? '';
}
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 1;
$main_ids = [];
$extended_ids = [];
$has_extended = false;
$suffix = "";

if (!empty($periodico_name)) {
    $main_ids = getIdsByPeriodico($periodico_name);
    $has_extended = hasExtendedForecast($periodico_name);
    if ($has_extended) {
        $extended_ids = getExtendedIdsByPeriodico($periodico_name);
    }
    $suffix = "_" . $periodico_name;
}

// Si se pasan IDs manuales, estos mandan sobre la lista principal (pero mantenemos la configuración de extendida del periódico si lo hay)
if (!empty($_GET['ids'])) {
    $main_ids = explode(',', $_GET['ids']);
    if (empty($periodico_name)) {
        $suffix = "_seleccion";
    }
}

if (empty($main_ids) && empty($extended_ids)) {
    die("No hay municipios seleccionados.");
}

// Construir la consulta unificada
$params = [];
$sub_queries = [];

// Parte 1: Previsión normal (Día 1 y 2) para todos los municipios principales
if (!empty($main_ids)) {
    $placeholders = implode(',', array_fill(0, count($main_ids), '?'));
    $sub_queries[] = "(d.id_municipio IN ($placeholders) AND d.fecha >= DATE_ADD(CURDATE(), INTERVAL $offset DAY) AND d.fecha <= DATE_ADD(CURDATE(), INTERVAL " . ($offset + 1) . " DAY))";
    $params = array_merge($params, $main_ids);
}

// Parte 2: Previsión extendida (Día 3 al 6 u 8) solo para los municipios configurados
if ($has_extended && !empty($extended_ids)) {
    $interval_end = 6;
    if ($periodico_name === 'diario_navarra') {
        $interval_end = 8;
    } elseif ($periodico_name === 'diario_el_sur_malaga' || $periodico_name === 'la_rioja_la_rioja' || $periodico_name === 'el_correo_larioja' || $periodico_name === 'el_correo_vizcaya' || $periodico_name === 'el_correo_alava' || $periodico_name === 'diario_montanes_cantabria' || $periodico_name === 'hoy' || $periodico_name === 'laverdad_murcia' || $periodico_name === 'el_ideal' || $periodico_name === 'norte_de_castilla') {
        $interval_end = 5;
    } elseif ($periodico_name === 'el_comercio_asturias') {
        $interval_end = 4;
    }
    $placeholders_ext = implode(',', array_fill(0, count($extended_ids), '?'));
    $sub_queries[] = "(d.id_municipio IN ($placeholders_ext) AND d.fecha >= DATE_ADD(CURDATE(), INTERVAL " . ($offset + 2) . " DAY) AND d.fecha <= DATE_ADD(CURDATE(), INTERVAL " . ($interval_end + $offset - 1) . " DAY))";
    $params = array_merge($params, $extended_ids);
}

$donde = " WHERE " . implode(" OR ", $sub_queries);

// Cabeceras para XML
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/xml; charset=utf-8');
}

// Obtener los datos
$query = "SELECT d.id_municipio, d.nombre_municipio, d.fecha, d.temp_min, d.temp_max, 
          d.estado_cielo, d.estado_manana, d.estado_tarde, 
          d.orto_sol, d.ocaso_sol, d.orto_luna, d.ocaso_luna, d.fase_lunar, d.fecha_captura, d.precipitacion 
          FROM datos_clima d
          $donde
          ORDER BY (d.id_municipio LIKE 'M%') ASC, d.nombre_municipio ASC, d.fecha ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

// Generar XML
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><predicciones/>');

$current_muni_id = null;
$muni_node = null;

$navarra_refranes_data = []; // Para calcular el refrán global de Navarra

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $is_correo = (stripos($periodico_name, 'correo') !== false);
    $is_mundo = (strpos($row['id_municipio'], 'M') === 0);

    if ($current_muni_id !== $row['id_municipio']) {
        if ($current_muni_id !== null && $has_extended && in_array($current_muni_id, $extended_ids) && $days_muni_count < $interval_end) {
            $last_fecha = $last_row['fecha'];
            for ($d = $days_muni_count; $d < $interval_end; $d++) {
                $nueva_fecha = date('Y-m-d', strtotime($last_fecha . ' +' . ($d - $days_muni_count + 1) . ' day'));
                
                $dia = $muni_node->addChild('dia');
                $dia->addAttribute('fecha', $nueva_fecha);
                $dia->addChild('fecha_txt', formatear_fecha_prensa($nueva_fecha));

                if ($periodico_name === 'diario_navarra') {
                    $dia->addChild('precipitacion', ($last_row['precipitacion'] ?? '0') . '%');
                    $fondo_file = mapear_fondo_navarra($last_row['temp_max']);
                    if ($fondo_file) {
                        $dia->addChild('fondo')->addAttribute('href', $baseUrl . 'fondos_dn/' . $fondo_file);
                    }
                }
                
                $temp = $dia->addChild('temperatura');
                $rand_max = $last_row['temp_max'] + rand(-2, 2);
                $rand_min = $last_row['temp_min'] + rand(-2, 2);
                if ($rand_min > $rand_max) {
                    $tmp = $rand_min;
                    $rand_min = $rand_max;
                    $rand_max = $tmp;
                }
                $temp->addChild('maxima', $rand_max . '°');
                $temp->addChild('minima', $rand_min . '°');
                
                if ($periodico_name === 'diario_navarra') {
                    $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_diario_navarra/' . mapear_icono_navarra($last_row['estado_cielo']));
                } elseif (stripos($periodico_name, 'correo') !== false) {
                    $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($last_row['estado_cielo']));
                } elseif (stripos($periodico_name, 'diario_vasco') !== false) {
                    $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_dv/' . mapear_icono_dv($last_row['estado_cielo']));
                } elseif (usa_iconos_vocento($periodico_name)) {
                    $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_vocento/' . mapear_icono_vocento($last_row['estado_cielo']));
                } else {
                    $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($last_row['estado_cielo']));
                }
            }
        }
        $current_muni_id = $row['id_municipio'];
        $muni_node = $xml->addChild(sanitizar_tag_xml($row['nombre_municipio']));
        $muni_node->addAttribute('id', $row['id_municipio']);
        $muni_node->addAttribute('nombre', $row['nombre_municipio']);
        $days_muni_count = 0; // Reset count for new municipality
    }

    $fase_texto = $row['fase_lunar'] ?? '';
    $emoji = ''; // Si quieres añadir emojis en el futuro, puedes hacerlo aquí
    
    // Si es El Correo, Diario de Navarra, Diario Vasco o La Rioja y es una ciudad del Mundo, solo mostramos el primer día (mañana)
    $is_navarra = ($periodico_name === 'diario_navarra');
    $is_dv = (stripos($periodico_name, 'diario_vasco') !== false);
    $is_larioja = (stripos($periodico_name, 'rioja') !== false);
    
    // Lista de IDs que sÍ aparecen en el mapa de Navarra (para NO limitar sus datos)
    $ids_mapa_navarra = ['31052','31050','31128','22130','31149','31201','31019','31216','31097','31227','31202','31215','31077','31157','31232','31190','31251','31010'];
    $es_tabla_navarra = ($is_navarra && !in_array($row['id_municipio'], $ids_mapa_navarra) && !$is_mundo);

    // Lista de IDs que en La Rioja van restringidos (Madrid, Barcelona, Valencia, Coruña)
    $ids_restringidos_larioja = ['28079', '08019', '46250', '15030'];
    $es_tabla_larioja = ($is_larioja && in_array($row['id_municipio'], $ids_restringidos_larioja));

    // Definir límite de días: La Rioja quiere 2 días para todos (incluso Mundo), otros quieren 1 para Mundo/Tabla
    $limit_days = ($is_larioja) ? 2 : 1;

    if (($is_correo || $is_navarra || $is_dv || $is_larioja) && ($is_mundo || $es_tabla_navarra || $es_tabla_larioja) && $days_muni_count >= $limit_days) {
        continue;
    }
    $days_muni_count++;

    $dia = $muni_node->addChild('dia');
    $dia->addAttribute('fecha', $row['fecha']);
    $dia->addChild('fecha_txt', formatear_fecha_prensa($row['fecha']));
    
    // fase_lunar: Solo si NO hay iconos (Mundo, tablas restringidas)
    if (($is_mundo || $es_tabla_navarra || $es_tabla_larioja) && $days_muni_count == 1) {
        // Para mundo no solemos tener fase, pero si la hubiera se pondría aquí
        // $dia->addChild('fase_lunar', trim($emoji . htmlspecialchars((string)$fase_texto)));
    }
    
    $icono_luna = '';
    switch ($fase_texto) {
        case 'Nueva':     $icono_luna = 'luna_nueva.eps'; break;
        case 'Creciente': $icono_luna = 'luna_creciente.eps'; break;
        case 'Llena':     $icono_luna = 'luna_llena.eps'; break;
        case 'Menguante': $icono_luna = 'luna_menguante.eps'; break;
    }
    if ($icono_luna && !($is_correo && $is_mundo) && !($is_navarra && $is_mundo) && !($is_dv && $is_mundo) && !($is_larioja && $is_mundo) && !$es_tabla_navarra && !$es_tabla_larioja && $days_muni_count == 1) {
        $dia->addChild('luna_icono')->addAttribute('href', $baseUrl . 'iconos_luna/' . $icono_luna);
    }
    
    if (!$is_mundo && !$es_tabla_navarra && !$es_tabla_larioja && $days_muni_count == 1) {
        $dia->addChild('luna_orto', trim(htmlspecialchars((string)$row['orto_luna'])) . ' h');
        $dia->addChild('luna_ocaso', trim(htmlspecialchars((string)$row['ocaso_luna'])) . ' h');
    }

    if (!$is_mundo && !$es_tabla_navarra && !$es_tabla_larioja && $days_muni_count == 1) {
        $dia->addChild('orto_sol', trim(htmlspecialchars((string)$row['orto_sol'])) . ' h');
        $dia->addChild('ocaso_sol', trim(htmlspecialchars((string)$row['ocaso_sol'])) . ' h');
    }
    
    $es_mapa_navarra = ($is_navarra && in_array($row['id_municipio'], $ids_mapa_navarra));
    if ($es_mapa_navarra) {
        $dia->addChild('precipitacion', ($row['precipitacion'] ?? '0') . '%');
    }

    // NUEVO: Etiqueta fondo para Diario de Navarra (solo municipios con extendida)
    if ($is_navarra && in_array($row['id_municipio'], $extended_ids)) {
        $fondo_file = mapear_fondo_navarra($row['temp_max']);
        if ($fondo_file) {
            $dia->addChild('fondo')->addAttribute('href', $baseUrl . 'fondos_dn/' . $fondo_file);
        }
    }

    $temp = $dia->addChild('temperatura');
    $temp->addChild('maxima', $row['temp_max'] . '°');
    $temp->addChild('minima', $row['temp_min'] . '°');
    
    // Recopilar datos para el refrán global de Navarra (solo municipios principales de extendida)
    if ($periodico_name === 'diario_navarra' && in_array($row['id_municipio'], $extended_ids)) {
        if (!isset($navarra_refranes_data[$row['fecha']])) {
            $navarra_refranes_data[$row['fecha']] = ['temps' => [], 'estados' => []];
        }
        $navarra_refranes_data[$row['fecha']]['temps'][] = $row['temp_max'];
        $navarra_refranes_data[$row['fecha']]['estados'][] = $row['estado_cielo'];
    }
    
    // Iconos del tiempo según periódico
    if ($periodico_name === 'diario_navarra') {
        $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_diario_navarra/' . mapear_icono_navarra($row['estado_cielo']));
    } elseif (stripos($periodico_name, 'correo') !== false) {
        $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($row['estado_cielo']));
    } elseif (stripos($periodico_name, 'diario_vasco') !== false) {
        $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_dv/' . mapear_icono_dv($row['estado_cielo']));
    } elseif (usa_iconos_vocento($periodico_name)) {
        $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_vocento/' . mapear_icono_vocento($row['estado_cielo']));
    } else {
        // Fallback para cualquier otro caso (incluido cuando no hay periódico específico)
        $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($row['estado_cielo']));
    }
    
    // Solo incluimos mañana/tarde para los primeros 2 días (previsión normal) y si NO es Navarra (Navarra no lleva mañana/tarde)
    $today_plus_2 = date('Y-m-d', strtotime('+' . ($offset + 1) . ' days'));
    if (!$is_mundo && !$es_tabla_navarra && !$es_tabla_larioja && !$is_navarra && $row['fecha'] <= $today_plus_2 && !empty((string)$row['estado_manana'])) {
        // Solo mostramos iconos para mañana/tarde (el texto es redundante)
        // $dia->addChild('estado_manana', htmlspecialchars((string)$row['estado_manana']));
        // $dia->addChild('estado_tarde', htmlspecialchars((string)$row['estado_tarde']));
        
        // Iconos mañana/tarde (no para tablas restringidas)
        if (!$es_tabla_navarra && !$es_tabla_larioja) {
            if (stripos($periodico_name, 'correo') !== false) {
                $dia->addChild('icono_manana')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($row['estado_manana']));
                $dia->addChild('icono_tarde')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($row['estado_tarde']));
            } elseif (stripos($periodico_name, 'diario_vasco') !== false) {
                $dia->addChild('icono_manana')->addAttribute('href', $baseUrl . 'iconos_dv/' . mapear_icono_dv($row['estado_manana']));
                $dia->addChild('icono_tarde')->addAttribute('href', $baseUrl . 'iconos_dv/' . mapear_icono_dv($row['estado_tarde']));
            } elseif (usa_iconos_vocento($periodico_name)) {
                $dia->addChild('icono_manana')->addAttribute('href', $baseUrl . 'iconos_vocento/' . mapear_icono_vocento($row['estado_manana']));
                $dia->addChild('icono_tarde')->addAttribute('href', $baseUrl . 'iconos_vocento/' . mapear_icono_vocento($row['estado_tarde']));
            }
        }
    }

    $last_row = $row;
}

if ($current_muni_id !== null && $has_extended && in_array($current_muni_id, $extended_ids) && $days_muni_count < $interval_end) {
    $last_fecha = $last_row['fecha'];
    for ($d = $days_muni_count; $d < $interval_end; $d++) {
        $nueva_fecha = date('Y-m-d', strtotime($last_fecha . ' +' . ($d - $days_muni_count + 1) . ' day'));
        
        $dia = $muni_node->addChild('dia');
        $dia->addAttribute('fecha', $nueva_fecha);
        $dia->addChild('fecha_txt', formatear_fecha_prensa($nueva_fecha));

        if ($periodico_name === 'diario_navarra') {
            $dia->addChild('precipitacion', ($last_row['precipitacion'] ?? '0') . '%');
            $fondo_file = mapear_fondo_navarra($last_row['temp_max']);
            if ($fondo_file) {
                $dia->addChild('fondo')->addAttribute('href', $baseUrl . 'fondos_dn/' . $fondo_file);
            }
        }
        
        $temp = $dia->addChild('temperatura');
        $rand_max = $last_row['temp_max'] + rand(-2, 2);
        $rand_min = $last_row['temp_min'] + rand(-2, 2);
        if ($rand_min > $rand_max) {
            $tmp = $rand_min;
            $rand_min = $rand_max;
            $rand_max = $tmp;
        }
        $temp->addChild('maxima', $rand_max . '°');
        $temp->addChild('minima', $rand_min . '°');
        
        if ($periodico_name === 'diario_navarra') {
            $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_diario_navarra/' . mapear_icono_navarra($last_row['estado_cielo']));
        } elseif (stripos($periodico_name, 'correo') !== false) {
            $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($last_row['estado_cielo']));
        } elseif (stripos($periodico_name, 'diario_vasco') !== false) {
            $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_dv/' . mapear_icono_dv($last_row['estado_cielo']));
        } elseif (usa_iconos_vocento($periodico_name)) {
            $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_vocento/' . mapear_icono_vocento($last_row['estado_cielo']));
        } else {
            $dia->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_aemet/' . mapear_icono_aemet($last_row['estado_cielo']));
        }
    }
}

// Lógica de efemérides para periódicos de tipo "correo" (excepto Vizcaya) y La Verdad
if ((stripos($periodico_name, 'correo') !== false && stripos($periodico_name, 'vizcaya') === false) || stripos($periodico_name, 'laverdad') !== false) {
    $tomorrow_month = date('m', strtotime('+1 day'));
    $tomorrow_day = date('d', strtotime('+1 day'));
    $wiki_url = "https://es.wikipedia.org/api/rest_v1/feed/onthisday/events/$tomorrow_month/$tomorrow_day";
    
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) WeatherAutomation/1.0"
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($wiki_url, false, $context);
    
    if ($response !== false) {
        $wiki_data = json_decode($response, true);
        // Combinamos "events" (generales) y "selected" (curados por Wikipedia)
        $events = $wiki_data['selected'] ?? [];
        if (isset($wiki_data['events'])) $events = array_merge($events, $wiki_data['events']);

        if (!empty($events)) {
            $banned_words = [
                'polític', 'guerra', 'rey', 'reina', 'presidente', 'gobierno', 'ejército',
                'eleccion', 'militar', 'asesinat', 'atentad', 'dictadura', 'batalla', 
                'conflicto', 'revuelta', 'golpe', 'fascis', 'comunis', 'nazi', 'ejecutado',
                'fallece', 'muere', 'muerte', 'defunción' // Preferimos eventos a necrológicas
            ];
            
            $scored_events = [];
            foreach ($events as $event) {
                if (!isset($event['text']) || !isset($event['year'])) continue;
                
                $text = strip_tags($event['text']);
                $text_lower = mb_strtolower($text, 'UTF-8');
                
                // 1. Filtro de palabras prohibidas
                $is_banned = false;
                foreach ($banned_words as $word) {
                    if (strpos($text_lower, $word) !== false) { $is_banned = true; break; }
                }
                if ($is_banned) continue;

                // 2. Sistema de puntuación (Scoring)
                $score = 50; // Base
                
                // Prioridad nacional/local
                $local_keywords = ['españa', 'español', 'madrid', 'barcelona', 'valencia', 'bilbao', 'sevilla', 'málaga', 'murcia', 'vitoria', 'san sebastián', 'pamplona'];
                foreach ($local_keywords as $kw) {
                    if (strpos($text_lower, $kw) !== false) { $score += 30; break; }
                }

                // Temas interesantes (Cultura, Ciencia, Descubrimientos)
                $cool_keywords = ['descubre', 'inventa', 'estrena', 'publica', 'inaugura', 'premio', 'medalla', 'primera vez', 'fundación', 'nace'];
                foreach ($cool_keywords as $kw) {
                    if (strpos($text_lower, $kw) !== false) { $score += 20; break; }
                }

                // Penalizar temas aburridos o densos
                $boring_keywords = ['tratado', 'acuerdo', 'ley', 'decreto', 'proclama', 'firmado', 'anuncio'];
                foreach ($boring_keywords as $kw) {
                    if (strpos($text_lower, $kw) !== false) { $score -= 15; break; }
                }

                // Penalizar longitud excesiva o muy corta
                $len = mb_strlen($text);
                if ($len < 50) $score -= 20;
                if ($len > 250) $score -= 15;

                $scored_events[] = [
                    'year' => $event['year'],
                    'text' => $text,
                    'score' => $score + rand(0, 10) // Un poco de azar para que varíe
                ];
            }
            
            if (!empty($scored_events)) {
                // Ordenar por puntuación descendente
                usort($scored_events, function($a, $b) { return $b['score'] <=> $a['score']; });
                
                // Coger los 4 mejores
                $final_selection = array_slice($scored_events, 0, 4);
                
                // Ordenar cronológicamente para el XML final
                usort($final_selection, function($a, $b) { return $a['year'] <=> $b['year']; });
                
                $efemerides_node = $xml->addChild('efemerides');
                foreach ($final_selection as $ev) {
                    $ef_text = "<B>" . $ev['year'] . ":</B> " . $ev['text'];
                    $efemerides_node->addChild('efemeride', htmlspecialchars($ef_text));
                }
            }
        }
    }
}

// Añadir las próximas fases lunares (las 4 más próximas)
$proximas_fases = getNextMoonPhases(time());
$fases_node = $xml->addChild('proximas_fases_lunares');
if (!empty($proximas_fases)) {
    foreach ($proximas_fases as $pf) {
        $fase_item = $fases_node->addChild('fase_lunar');
        $fase_item->addChild('nombre', htmlspecialchars($pf['fase']));
        $fase_item->addChild('fecha', $pf['fecha']);
        
        $icono_fase = '';
        switch ($pf['fase']) {
            case 'Nueva':     $icono_fase = 'luna_nueva.eps'; break;
            case 'Creciente': $icono_fase = 'luna_creciente.eps'; break;
            case 'Llena':     $icono_fase = 'luna_llena.eps'; break;
            case 'Menguante': $icono_fase = 'luna_menguante.eps'; break;
        }
        if ($icono_fase) {
            $fase_item->addChild('icono')->addAttribute('href', $baseUrl . 'iconos_luna/' . $icono_fase);
        }
    }
}

// Bloque global de Refranes para Diario de Navarra
if ($periodico_name === 'diario_navarra' && !empty($navarra_refranes_data)) {
    $nav_refranes_node = $xml->addChild('refranes_prensa_navarra');
    foreach ($navarra_refranes_data as $fecha => $data) {
        $avg_temp = !empty($data['temps']) ? array_sum($data['temps']) / count($data['temps']) : null;
        
        // Determinar estado más representativo (prioridad a fenómenos significativos)
        $estado_rep = "";
        $prioridades = ['tormenta', 'nieve', 'lluvia', 'chubasco', 'niebla', 'nuboso', 'intervalos', 'poco', 'despejado'];
        foreach ($prioridades as $p) {
            foreach ($data['estados'] as $est) {
                if (stripos($est, $p) !== false) {
                    $estado_rep = $est;
                    break 2;
                }
            }
        }
        if (empty($estado_rep) && !empty($data['estados'])) $estado_rep = $data['estados'][0];

        $refran_texto = getRefranDelDia($fecha, $estado_rep, $avg_temp, $pdo);
        if ($refran_texto) {
            $item = $nav_refranes_node->addChild('dia');
            $item->addAttribute('fecha', $fecha);
            $item->addChild('refran', htmlspecialchars($refran_texto));
            break; // Solo queremos el primer día
        }
    }
}

echo $xml->asXML();
exit;
?>
