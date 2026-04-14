<?php
declare(strict_types=1);

header('Content-Type: application/json');

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}



$idProyecto = (int)($_GET['proyecto_id'] ?? 0);
if (!$idProyecto) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, pr.nombre AS tutor_nombre, pr.apellidos AS tutor_apellidos
        FROM app.proyectos p
        LEFT JOIN app.profesores pr ON pr.id_profesor = p.tutor_id
        WHERE p.id_proyecto = ?
        LIMIT 1
    ");
    $stmt->execute([$idProyecto]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => $e->getMessage()]);
    exit;
}

if (!$proyecto) {
    echo json_encode(['ok' => false, 'missatge' => 'Projecte no trobat.']);
    exit;
}

// Capturar HTML del bloque
ob_start();
try {
    include('bloque-tribunal.php');
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'missatge' => 'Error al renderitzar: ' . $e->getMessage()]);
    exit;
}
$html = ob_get_clean();

// Recalcular nota final
try {
    $stmtNF = $pdo->prepare("
        SELECT AVG(nota_memoria) AS m, AVG(nota_proyecto) AS p, AVG(nota_defensa) AS d
        FROM app.evaluacion_tribunal WHERE proyecto_id = ?
    ");
    $stmtNF->execute([$idProyecto]);
    $t = $stmtNF->fetch(PDO::FETCH_ASSOC);

    $stmtTutor = $pdo->prepare("
        SELECT nota_tutor_planificacion, nota_tutor_gestion, nota_tutor_memoria,
               nota_tutor_proyecto, nota_tutor_compromiso
        FROM app.proyectos WHERE id_proyecto = ?
    ");
    $stmtTutor->execute([$idProyecto]);
    $pt = $stmtTutor->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => true, 'html' => $html]);
    exit;
}

$vals = array_filter([
    $pt['nota_tutor_planificacion'], $pt['nota_tutor_gestion'],
    $pt['nota_tutor_memoria'], $pt['nota_tutor_proyecto'], $pt['nota_tutor_compromiso'],
], fn($v) => $v !== null);
$notaTutor10 = count($vals) > 0 ? round(array_sum($vals) / count($vals) * 2, 2) : null;

$avgMem  = $t['m'] !== null ? round((float)$t['m'] * 2, 2) : null;
$avgProj = $t['p'] !== null ? round((float)$t['p'] * 2, 2) : null;
$avgDef  = $t['d'] !== null ? round((float)$t['d'] * 2, 2) : null;

$suma = 0; $pesos = 0;
if ($notaTutor10 !== null) { $suma += $notaTutor10 * 0.20; $pesos += 0.20; }
if ($avgMem      !== null) { $suma += $avgMem      * 0.30; $pesos += 0.30; }
if ($avgProj     !== null) { $suma += $avgProj     * 0.30; $pesos += 0.30; }
if ($avgDef      !== null) { $suma += $avgDef      * 0.20; $pesos += 0.20; }
$notaFinal = $pesos > 0 ? round($suma, 2) : null;

// ── Ajustos individuals ───────────────────────────────────────────
$ajustos = [];
try {
    $stmtAj = $pdo->prepare("
        SELECT alumno_id, ajuste
        FROM app.ajustes_nota_individual
        WHERE proyecto_id = ?
    ");
    $stmtAj->execute([$idProyecto]);
    foreach ($stmtAj->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ajustos[(int)$row['alumno_id']] = (float)$row['ajuste'];
    }
} catch (PDOException $e) {}

echo json_encode([
    'ok'      => true,
    'html'    => $html,
    'ajustos' => $ajustos,
    'nota'    => [
        'tutor'    => $notaTutor10 !== null ? round($notaTutor10 * 0.20, 2) : null,
        'memoria'  => $avgMem      !== null ? round($avgMem * 0.30, 2)      : null,
        'proyecto' => $avgProj     !== null ? round($avgProj * 0.30, 2)     : null,
        'defensa'  => $avgDef      !== null ? round($avgDef * 0.20, 2)      : null,
        'final'    => $notaFinal,
        'parcial'  => $pesos > 0 && $pesos < 1.0,
    ],
]);
