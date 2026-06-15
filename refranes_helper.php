<?php
/**
 * Helper para la selección de refranes según fecha y clima desde la Base de Datos.
 */

function getRefranDelDia($fecha, $estado_cielo, $temp_max, $pdo) {
    if (!$pdo) return "";

    $date = new DateTime($fecha);
    $dia_num = (int)$date->format('j');
    $mes_num = (int)$date->format('n');

    // 1. Categorizar clima de hoy
    $climas_hoy = categorizarClimaAemet($estado_cielo, $temp_max);

    try {
        // Prioridad 1: Día exacto + Mes
        $stmt = $pdo->prepare("SELECT id, texto FROM refranes WHERE dia_exacto = ? AND FIND_IN_SET(?, meses_validos) > 0 ORDER BY veces_usado ASC, ultimo_uso ASC LIMIT 1");
        $stmt->execute([$dia_num, $mes_num]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            marcarRefranUsado($res['id'], $fecha, $pdo);
            return $res['texto'];
        }

        // Prioridad 2: Mes + Clima coincidente
        if (!empty($climas_hoy)) {
            $clima_conds = [];
            foreach ($climas_hoy as $c) {
                $clima_conds[] = "FIND_IN_SET('$c', clima_requerido) > 0";
            }
            $where_clima = "(" . implode(" OR ", $clima_conds) . ")";
            
            $stmt = $pdo->query("SELECT id, texto FROM refranes WHERE FIND_IN_SET('$mes_num', meses_validos) > 0 AND $where_clima AND dia_exacto IS NULL ORDER BY veces_usado ASC, ultimo_uso ASC");
            while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (esRefranValidoParaDia($res['texto'], $dia_num)) {
                    marcarRefranUsado($res['id'], $fecha, $pdo);
                    return $res['texto'];
                }
            }
        }

        // Prioridad 3: Solo Mes
        $stmt = $pdo->query("SELECT id, texto FROM refranes WHERE FIND_IN_SET('$mes_num', meses_validos) > 0 AND (clima_requerido IS NULL OR clima_requerido = '') AND dia_exacto IS NULL ORDER BY veces_usado ASC, ultimo_uso ASC");
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (esRefranValidoParaDia($res['texto'], $dia_num)) {
                marcarRefranUsado($res['id'], $fecha, $pdo);
                return $res['texto'];
            }
        }

        // Fallback: Cualquier refrán del mes
        $stmt = $pdo->query("SELECT id, texto FROM refranes WHERE FIND_IN_SET('$mes_num', meses_validos) > 0 ORDER BY veces_usado ASC, ultimo_uso ASC LIMIT 1");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            marcarRefranUsado($res['id'], $fecha, $pdo);
            return $res['texto'];
        }

    } catch (Exception $e) {
        // Fallback silencioso
    }

    return "";
}

function marcarRefranUsado($id, $fecha, $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE refranes SET veces_usado = veces_usado + 1, ultimo_uso = ? WHERE id = ?");
        $stmt->execute([$fecha, $id]);
    } catch (Exception $e) {}
}

function esRefranValidoParaDia($texto, $dia_actual) {
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    
    // 1. Buscar números explícitos (ej: "A tres de abril", "El 12 de...")
    // Buscamos patrones de números que parezcan ser un día del mes
    if (preg_match_all('/\b([\d]{1,2})\b/u', $texto_lower, $matches)) {
        foreach ($matches[1] as $num) {
            $n = (int)$num;
            if ($n >= 1 && $n <= 31) {
                // Si el número encontrado NO es el día de hoy, y parece ser una fecha, lo descartamos
                if ($n !== $dia_actual) {
                    // Verificamos si va seguido de "de [mes]" para estar más seguros
                    if (preg_match('/\b' . $n . '\s+de\s+(?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/ui', $texto_lower)) {
                        return false;
                    }
                    // Si el número está muy al principio, también es sospechoso
                    if (mb_strpos($texto_lower, (string)$n) < 10) {
                        return false;
                    }
                }
            }
        }
    }

    // 2. Buscar números escritos en texto (para los más comunes: uno a diez)
    $num_letras = [
        1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco', 
        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez'
    ];
    foreach ($num_letras as $n => $palabra) {
        if ($n !== $dia_actual) {
            if (preg_match('/\b' . $palabra . '\s+de\s+(?:enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/ui', $texto_lower)) {
                return false;
            }
        }
    }

    return true;
}

function categorizarClimaAemet($estado, $temp_max) {
    $categorias = [];
    if (empty($estado)) return $categorias;
    $estado_lower = mb_strtolower($estado, 'UTF-8');

    // Lluvia / Frío / Tormenta (Correspondiente a VERDE en el Excel)
    if (strpos($estado_lower, 'tormenta') !== false) $categorias[] = 'tormenta';
    if (strpos($estado_lower, 'nieve') !== false) $categorias[] = 'nieve';
    if (strpos($estado_lower, 'lluvia') !== false || strpos($estado_lower, 'chubasco') !== false || strpos($estado_lower, 'llovizna') !== false) {
        $categorias[] = 'lluvia';
    }
    
    // Despejado / Calor (Correspondiente a ROJO en el Excel)
    if (strpos($estado_lower, 'despejado') !== false || strpos($estado_lower, 'claro') !== false) {
        $categorias[] = 'despejado';
    }

    if ($temp_max !== null) {
        if ($temp_max < 15) $categorias[] = 'frio';
        if ($temp_max > 25) $categorias[] = 'calor';
    }

    return array_unique($categorias);
}
