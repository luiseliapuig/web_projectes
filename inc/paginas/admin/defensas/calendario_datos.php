<?php
// calendari_drag_dades.php
// Endpoint de datos del calendario manual para el curso vigente.
// Retorna JSON amb dades dels tres dies per al drag & drop.

declare(strict_types=1);

soloSuperadmin();

$torn = trim($_GET['torn'] ?? 'tarda');
$cursoAcademico = isset($_GET['curso']) && is_string($_GET['curso']) ? trim($_GET['curso']) : '';
$familiaCicloId = isset($_GET['familia']) ? (int) $_GET['familia'] : 0;
if (
    !in_array($torn, ['mati', 'tarda'], true)
    || $cursoAcademico !== cursoAcademicoDefensas()
    || $familiaCicloId <= 0
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'Paràmetres incorrectes.']);
    exit;
}

$stmtFamilia = $pdo->prepare("SELECT 1 FROM app.familias_ciclos WHERE id_familia_ciclo = :id AND activo = true");
$stmtFamilia->execute([':id' => $familiaCicloId]);
if (!$stmtFamilia->fetchColumn()) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'La família professional no és vàlida.']);
    exit;
}

$hora_cond = $torn === 'mati'
    ? 'AND EXTRACT(HOUR FROM p.defensa_fecha) < 15'
    : 'AND EXTRACT(HOUR FROM p.defensa_fecha) >= 15';

// Dies disponibles
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(p.defensa_fecha) AS dia
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.defensa_fecha IS NOT NULL
          AND p.curso_academico = :curso_academico
          AND c.familia_ciclo_id = :familia_id
        ORDER BY dia
    ");
    $stmt->execute([
        ':curso_academico' => $cursoAcademico,
        ':familia_id' => $familiaCicloId,
    ]);
    $dies = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir dies.']);
    exit;
}

if (empty($dies)) {
    echo json_encode(['ok' => true, 'dies' => [], 'aules' => [], 'hores' => [], 'duracio_min' => 45, 'matriu' => [], 'torn' => $torn]);
    exit;
}

// El ajuste manual ofrece todas las aulas para resolver cualquier casuística.
try {
    $aules = $pdo->query("
        SELECT id_aula, codigo, nombre
        FROM app.aulas
        ORDER BY codigo
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir aules.']);
    exit;
}

// Tots els projectes del torn (tots els dies)
try {
    $stmt = $pdo->prepare("
        SELECT
            p.id_proyecto, p.nombre, c.abr AS ciclo, g.grupo,
            p.defensa_aula_id,
            DATE(p.defensa_fecha)                  AS dia,
            TO_CHAR(p.defensa_fecha, 'HH24:MI')    AS hora_inici
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.defensa_fecha IS NOT NULL
          AND p.curso_academico = :curso_academico
          AND c.familia_ciclo_id = :familia_id
          $hora_cond
        ORDER BY p.defensa_fecha, p.defensa_aula_id
    ");
    $stmt->execute([
        ':curso_academico' => $cursoAcademico,
        ':familia_id' => $familiaCicloId,
    ]);
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
    } catch (PDOException $e) {
        error_log('Error llegint alumnat del calendari de defenses: ' . $e->getMessage());
    }
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

// La duración se obtiene del menor intervalo regular observado. Los saltos del
// descanso no deben convertirse en la duración de todas las defensas.
$duracio_min = 45; // fallback
if (count($hores) >= 2) {
    $diferencies = [];
    for ($i = 1, $total = count($hores); $i < $total; $i++) {
        $t1 = strtotime('2000-01-01 ' . $hores[$i - 1]);
        $t2 = strtotime('2000-01-01 ' . $hores[$i]);
        $diferencia = (int) (($t2 - $t1) / 60);
        if ($diferencia >= 20 && $diferencia <= 90) {
            $diferencies[] = $diferencia;
        }
    }
    if ($diferencies !== []) {
        $duracio_min = min($diferencies);
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
