<?php
declare(strict_types=1);

header('Content-Type: application/json');

function jsonOut(bool $ok, array $extra = [], string $missatge = ''): never {
    echo json_encode(array_merge(['ok' => $ok, 'missatge' => $missatge], $extra));
    exit;
}

// ── Permisos (han de coincidir amb bloque-nota-final.php) ─────────
$permiso_tutor    = false;
$permiso_tribunal = true;

$professorId = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;
if (!$professorId) jsonOut(false, missatge: 'No autenticat.');

$proyectoId = (int)($_POST['proyecto_id'] ?? 0);
$alumnoId   = (int)($_POST['alumno_id']   ?? 0);
$ajuste     = isset($_POST['ajuste']) ? round((float)$_POST['ajuste'], 1) : null;
$accio      = trim($_POST['accio'] ?? 'guardar');

if (!$proyectoId || !$alumnoId) {
    jsonOut(false, missatge: 'Dades incorrectes.');
}

// ── Verificar permisos ────────────────────────────────────────────
$potAjustar = false;

if (esSuperadmin()) {
    $potAjustar = true;
} else {
    if ($permiso_tutor) {
        try {
            $stmt = $pdo->prepare("SELECT tutor_id FROM app.proyectos WHERE id_proyecto = ?");
            $stmt->execute([$proyectoId]);
            $proj = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($proj && (int)$proj['tutor_id'] === $professorId) $potAjustar = true;
        } catch (PDOException $e) {}
    }
    if ($permiso_tribunal && !$potAjustar) {
        try {
            $stmt = $pdo->prepare("
                SELECT 1 FROM app.rel_profesores_tribunal
                WHERE id_proyecto = ? AND profesor_id = ?
            ");
            $stmt->execute([$proyectoId, $professorId]);
            if ($stmt->fetch()) $potAjustar = true;
        } catch (PDOException $e) {}
    }
}

if (!$potAjustar) jsonOut(false, missatge: 'No tens permís per ajustar aquesta nota.');

// ── Verificar que l'alumne pertany al projecte ────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT 1 FROM app.rel_proyectos_alumnos
        WHERE proyecto_id = ? AND alumno_id = ?
    ");
    $stmt->execute([$proyectoId, $alumnoId]);
    if (!$stmt->fetch()) jsonOut(false, missatge: 'Alumne no pertany a aquest projecte.');
} catch (PDOException $e) {
    jsonOut(false, missatge: 'Error en verificar l\'alumne.');
}

// ── Reset: eliminar el registre ───────────────────────────────────
if ($accio === 'reset') {
    try {
        $pdo->prepare("
            DELETE FROM app.ajustes_nota_individual
            WHERE proyecto_id = ? AND alumno_id = ?
        ")->execute([$proyectoId, $alumnoId]);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en reset: ' . $e->getMessage());
    }
    jsonOut(true, ['ajuste' => 0]);
}

// ── Guardar upsert ────────────────────────────────────────────────
if ($ajuste === null) jsonOut(false, missatge: 'Ajust no vàlid.');
if ($ajuste < -10 || $ajuste > 10) jsonOut(false, missatge: 'Ajust fora de rang.');

// ── Upsert ────────────────────────────────────────────────────────
try {
    $pdo->prepare("
        INSERT INTO app.ajustes_nota_individual
            (proyecto_id, alumno_id, ajuste, creado_por_profesor_id, fecha_creacion, fecha_actualizacion)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON CONFLICT (proyecto_id, alumno_id)
        DO UPDATE SET
            ajuste                  = EXCLUDED.ajuste,
            creado_por_profesor_id  = EXCLUDED.creado_por_profesor_id,
            fecha_actualizacion     = NOW()
    ")->execute([$proyectoId, $alumnoId, $ajuste, $professorId]);
} catch (PDOException $e) {
    jsonOut(false, missatge: 'Error en guardar: ' . $e->getMessage());
}

jsonOut(true, ['ajuste' => $ajuste]);
