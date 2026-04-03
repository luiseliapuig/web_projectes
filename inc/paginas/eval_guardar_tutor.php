<?php
declare(strict_types=1);

header('Content-Type: application/json');

function jsonOut(bool $ok, array $extra = [], string $missatge = ''): never {
   
    echo json_encode(array_merge(['ok' => $ok, 'missatge' => $missatge], $extra));
    exit;
}

// Autenticació
$professorId = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;
if (!$professorId) jsonOut(false, missatge: 'No autenticat.');

$proyectoId = (int)($_POST['proyecto_id'] ?? 0);
if (!$proyectoId) jsonOut(false, missatge: 'Projecte no vàlid.');

// Verificar que és el tutor o superadmin
try {
    $stmt = $pdo->prepare("SELECT tutor_id FROM app.proyectos WHERE id_proyecto = ?");
    $stmt->execute([$proyectoId]);
    $proj = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    jsonOut(false, missatge: 'Error en carregar el projecte.');
}

if (!$proj) jsonOut(false, missatge: 'Projecte no trobat.');

$esTutor = (int)$proj['tutor_id'] === $professorId;
if (!$esTutor && !esSuperadmin()) jsonOut(false, missatge: 'No tens permís per editar aquesta valoració.');

$accio = trim($_POST['accio'] ?? '');

// ── Guardar estrella ──────────────────────────────────────────────
if ($accio === 'nota') {
    $camp  = trim($_POST['camp'] ?? '');
    $valor = isset($_POST['valor']) ? (int)$_POST['valor'] : -1;

    $campsPermesos = ['planificacion', 'gestion', 'memoria', 'proyecto', 'compromiso'];
    if (!in_array($camp, $campsPermesos, true)) jsonOut(false, missatge: 'Camp no vàlid.');
    if ($valor < 1 || $valor > 5) jsonOut(false, missatge: 'Valor no vàlid.');

    try {
        $pdo->prepare("
            UPDATE app.proyectos
            SET nota_tutor_{$camp} = ?
            WHERE id_proyecto = ?
        ")->execute([$valor, $proyectoId]);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en guardar la nota.');
    }

    jsonOut(true, ['nota_html' => renderNotaFinal($pdo, $proyectoId)]);
}

// ── Guardar comentari ─────────────────────────────────────────────
if ($accio === 'comentari') {
    $comentari = trim($_POST['comentari'] ?? '');

    try {
        $pdo->prepare("
            UPDATE app.proyectos
            SET comentario_tutor = ?
            WHERE id_proyecto = ?
        ")->execute([$comentari !== '' ? $comentari : null, $proyectoId]);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en guardar el comentari.');
    }

    jsonOut(true, ['comentari' => $comentari]);
}

jsonOut(false, missatge: 'Acció no reconeguda.');

// ── Funció de càlcul nota final ───────────────────────────────────
function renderNotaFinal(PDO $pdo, int $proyectoId): string {
    $proj = $pdo->prepare("
        SELECT nota_tutor_planificacion, nota_tutor_gestion, nota_tutor_memoria,
               nota_tutor_proyecto, nota_tutor_compromiso
        FROM app.proyectos WHERE id_proyecto = ?
    ");
    $proj->execute([$proyectoId]);
    $p = $proj->fetch(PDO::FETCH_ASSOC);

    $vals = array_filter([
        $p['nota_tutor_planificacion'],
        $p['nota_tutor_gestion'],
        $p['nota_tutor_memoria'],
        $p['nota_tutor_proyecto'],
        $p['nota_tutor_compromiso'],
    ], fn($v) => $v !== null);
    $notaTutor10 = count($vals) > 0 ? round(array_sum($vals) / count($vals) * 2, 2) : null;

    $stmt = $pdo->prepare("
        SELECT AVG(nota_memoria) AS avg_memoria, AVG(nota_proyecto) AS avg_proyecto, AVG(nota_defensa) AS avg_defensa
        FROM app.evaluacion_tribunal WHERE proyecto_id = ?
    ");
    $stmt->execute([$proyectoId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    $avgMem  = $t['avg_memoria']  !== null ? round((float)$t['avg_memoria']  * 2, 2) : null;
    $avgProj = $t['avg_proyecto'] !== null ? round((float)$t['avg_proyecto'] * 2, 2) : null;
    $avgDef  = $t['avg_defensa']  !== null ? round((float)$t['avg_defensa']  * 2, 2) : null;

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
