<?php 
declare(strict_types=1);

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(0);
function jsonOut(bool $ok, array $extra = [], string $missatge = ''): never {
    
    echo json_encode(array_merge(['ok' => $ok, 'missatge' => $missatge], $extra));
    exit;
}

$professorId = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;
if (!$professorId) jsonOut(false, missatge: 'No autenticat.');

$proyectoId  = (int)($_POST['proyecto_id'] ?? 0);
$profesorObj = (int)($_POST['profesor_id'] ?? 0);
if (!$proyectoId || !$profesorObj) jsonOut(false, missatge: 'Dades no vàlides.');

// Verificar que és el seu propi registre o superadmin
if ($professorId !== $profesorObj && !esSuperadmin()) {
    jsonOut(false, missatge: 'No tens permís per editar aquesta valoració.');
}

// Verificar que és membre del tribunal
try {
    $stmt = $pdo->prepare("
        SELECT 1 FROM app.rel_profesores_tribunal
        WHERE id_proyecto = ? AND profesor_id = ?
    ");
    $stmt->execute([$proyectoId, $profesorObj]);
    if (!$stmt->fetch()) jsonOut(false, missatge: 'No ets membre del tribunal d\'aquest projecte.');
} catch (PDOException $e) {
    jsonOut(false, missatge: 'Error en verificar permisos.');
}

$accio = trim($_POST['accio'] ?? '');

// ── Guardar estrella ──────────────────────────────────────────────
if ($accio === 'nota') {
    $camp  = trim($_POST['camp'] ?? '');
    $valor = isset($_POST['valor']) ? (int)$_POST['valor'] : -1;

    $campsPermesos = ['memoria', 'proyecto', 'defensa'];
    if (!in_array($camp, $campsPermesos, true)) jsonOut(false, missatge: 'Camp no vàlid.');
    if ($valor < 1 || $valor > 5) jsonOut(false, missatge: 'Valor no vàlid.');

    try {
        // Upsert: inserir o actualitzar
        $pdo->prepare("
            INSERT INTO app.evaluacion_tribunal (proyecto_id, profesor_id, nota_{$camp}, fecha_valoracion)
            VALUES (?, ?, ?, NOW())
            ON CONFLICT (proyecto_id, profesor_id)
            DO UPDATE SET nota_{$camp} = EXCLUDED.nota_{$camp}, fecha_valoracion = NOW()
        ")->execute([$proyectoId, $profesorObj, $valor]);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en guardar la nota: ' . $e->getMessage());
    }

    jsonOut(true, ['nota_html' => calcularNotaFinal($pdo, $proyectoId)]);
}

// ── Guardar comentari ─────────────────────────────────────────────
if ($accio === 'comentari') {
    $comentari = trim($_POST['comentari'] ?? '');

    try {
        $pdo->prepare("
            INSERT INTO app.evaluacion_tribunal (proyecto_id, profesor_id, comentario, fecha_valoracion)
            VALUES (?, ?, ?, NOW())
            ON CONFLICT (proyecto_id, profesor_id)
            DO UPDATE SET comentario = EXCLUDED.comentario, fecha_valoracion = NOW()
        ")->execute([$proyectoId, $profesorObj, $comentari !== '' ? $comentari : null]);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en guardar el comentari.');
    }

    jsonOut(true, ['comentari' => $comentari]);
}

jsonOut(false, missatge: 'Acció no reconeguda.');

// ── Càlcul nota final ─────────────────────────────────────────────
function calcularNotaFinal(PDO $pdo, int $proyectoId): string {
    $proj = $pdo->prepare("
        SELECT nota_tutor_planificacion, nota_tutor_gestion, nota_tutor_memoria,
               nota_tutor_proyecto, nota_tutor_compromiso
        FROM app.proyectos WHERE id_proyecto = ?
    ");
    $proj->execute([$proyectoId]);
    $p = $proj->fetch(PDO::FETCH_ASSOC);

    $vals = array_filter([
        $p['nota_tutor_planificacion'], $p['nota_tutor_gestion'],
        $p['nota_tutor_memoria'], $p['nota_tutor_proyecto'], $p['nota_tutor_compromiso'],
    ], fn($v) => $v !== null);
    $notaTutor10 = count($vals) > 0 ? round(array_sum($vals) / count($vals) * 2, 2) : null;

    $stmt = $pdo->prepare("
        SELECT AVG(nota_memoria) AS m, AVG(nota_proyecto) AS p, AVG(nota_defensa) AS d
        FROM app.evaluacion_tribunal WHERE proyecto_id = ?
    ");
    $stmt->execute([$proyectoId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    $avgMem  = $t['m'] !== null ? round((float)$t['m'] * 2, 2) : null;
    $avgProj = $t['p'] !== null ? round((float)$t['p'] * 2, 2) : null;
    $avgDef  = $t['d'] !== null ? round((float)$t['d'] * 2, 2) : null;

    $suma = 0; $pesos = 0;
    if ($notaTutor10 !== null) { $suma += $notaTutor10 * 0.20; $pesos += 0.20; }
    if ($avgMem      !== null) { $suma += $avgMem      * 0.30; $pesos += 0.30; }
    if ($avgProj     !== null) { $suma += $avgProj     * 0.30; $pesos += 0.30; }
    if ($avgDef      !== null) { $suma += $avgDef      * 0.20; $pesos += 0.20; }
    $notaFinal = $pesos > 0 ? round($suma, 2) : null;

    return json_encode([
        'tutor'    => $notaTutor10 !== null ? round($notaTutor10 * 0.20, 2) : null,
        'memoria'  => $avgMem      !== null ? round($avgMem * 0.30, 2)      : null,
        'proyecto' => $avgProj     !== null ? round($avgProj * 0.30, 2)     : null,
        'defensa'  => $avgDef      !== null ? round($avgDef * 0.20, 2)      : null,
        'final'    => $notaFinal,
    ]);
}
