<?php
// calendari_drag_dades.php
// Endpoint raw. Rep ?torn=mati|tarda
// Retorna JSON amb dades dels tres dies per al drag & drop.

declare(strict_types=1);

$torn = trim($_GET['torn'] ?? 'tarda');
if (!in_array($torn, ['mati', 'tarda'], true)) {
    echo json_encode(['ok' => false, 'missatge' => 'Paràmetres incorrectes.']);
    exit;
}

$hora_cond = $torn === 'mati'
    ? 'AND EXTRACT(HOUR FROM p.defensa_fecha) < 15'
    : 'AND EXTRACT(HOUR FROM p.defensa_fecha) >= 15';

// Dies disponibles
try {
    $dies = $pdo->query("
        SELECT DISTINCT DATE(defensa_fecha) AS dia
        FROM app.proyectos WHERE defensa_fecha IS NOT NULL ORDER BY dia
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir dies.']);
    exit;
}

if (empty($dies)) {
    echo json_encode(['ok' => true, 'dies' => [], 'aules' => [], 'torn' => $torn]);
    exit;
}

// Totes les aules que apareixen en defensas (qualsevol dia, qualsevol torn)
// filtrades per les que tenen defensas en aquest torn
try {
    $aules = $pdo->prepare("
        SELECT DISTINCT a.id_aula, a.codigo, a.nombre
        FROM app.proyectos p
        JOIN app.aulas a ON a.id_aula = p.defensa_aula_id
        WHERE p.defensa_fecha IS NOT NULL $hora_cond
        ORDER BY a.codigo
    ");
    $aules->execute();
    $aules = $aules->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir aules.']);
    exit;
}

// Tots els projectes del torn (tots els dies)
try {
    $stmt = $pdo->prepare("
        SELECT
            p.id_proyecto, p.nombre, p.ciclo, p.grupo,
            p.defensa_aula_id,
            DATE(p.defensa_fecha)                  AS dia,
            TO_CHAR(p.defensa_fecha, 'HH24:MI')    AS hora_inici
        FROM app.proyectos p
        WHERE p.defensa_fecha IS NOT NULL $hora_cond
        ORDER BY p.defensa_fecha, p.defensa_aula_id
    ");
    $stmt->execute();
    $projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir projectes.']);
    exit;
}

// Alumnes
$alumnes_per_proj = [];
if (!empty($projectes)) {
    $ids = array_column($projectes, 'id_proyecto');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT r.proyecto_id, a.nombre, a.apellidos
            FROM app.rel_proyectos_alumnos r
            JOIN app.alumnos a ON a.id_alumno = r.alumno_id
            WHERE r.proyecto_id IN ($ph) ORDER BY a.apellidos
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $alumnes_per_proj[$a['proyecto_id']][] = trim($a['nombre'] . ' ' . $a['apellidos']);
        }
    } catch (PDOException $e) {}
}

// Hores úniques del torn (per fer les files de la taula)
$hores_set = [];
foreach ($projectes as $p) { $hores_set[$p['hora_inici']] = true; }
ksort($hores_set);
$hores = array_keys($hores_set);

// Matriu: dia → hora → aula_id → projecte|null
$matriu = [];
foreach ($dies as $dia) {
    foreach ($hores as $hora) {
        foreach ($aules as $aula) {
            $matriu[$dia][$hora][$aula['id_aula']] = null;
        }
    }
}
foreach ($projectes as $p) {
    $matriu[$p['dia']][$p['hora_inici']][$p['defensa_aula_id']] = [
        'id'      => (int)$p['id_proyecto'],
        'nom'     => $p['nombre'] ?? '',
        'cicle'   => strtoupper($p['ciclo'] ?? ''),
        'grup'    => strtoupper($p['grupo'] ?? ''),
        'alumnes' => $alumnes_per_proj[$p['id_proyecto']] ?? [],
    ];
}

// Duració real de franja (diferència entre hores consecutives del mateix dia+aula)
$duracio_min = 45; // fallback
if (count($hores) >= 2) {
    // Buscar dues hores consecutives que siguin del mateix dia i aula
    // per no confondre hores de grups diferents
    $hores_per_dia_aula = [];
    foreach ($projectes as $p) {
        $hores_per_dia_aula[$p['dia']][$p['defensa_aula_id']][] = $p['hora_inici'];
    }
    foreach ($hores_per_dia_aula as $dh) {
        foreach ($dh as $hh) {
            sort($hh);
            if (count($hh) >= 2) {
                $t1 = strtotime('2000-01-01 ' . $hh[0]);
                $t2 = strtotime('2000-01-01 ' . $hh[1]);
                $duracio_min = (int)(($t2 - $t1) / 60);
                break 2;
            }
        }
    }
}

echo json_encode([
    'ok'         => true,
    'torn'       => $torn,
    'dies'       => $dies,
    'aules'      => $aules,
    'hores'      => $hores,
    'duracio_min' => $duracio_min,
    'matriu'     => $matriu,
]);
exit;
