<?php
declare(strict_types=1);

$grupoRetorn = isset($_POST['grupo_id']) ? max(0, (int) $_POST['grupo_id']) : 0;
$modeTutors = isset($_POST['mode_tutors']) && (string) $_POST['mode_tutors'] === 'manual';
$redirigirResumTutors = static function (bool $correcte = false) use ($grupoRetorn, $modeTutors): never {
    $url = '/resum?grupo_id=' . $grupoRetorn;
    if ($modeTutors) {
        $url .= '&tutors=1';
    }
    $url .= $correcte ? '&tutor_actualitzat=1' : '&tutor_error=1';
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$profesorActualId = (int) ($_SESSION['professor_id'] ?? 0);
$proyectoId = isset($_POST['proyecto_id']) ? (int) $_POST['proyecto_id'] : 0;
$nuevoTutorId = isset($_POST['profesor_id']) ? (int) $_POST['profesor_id'] : 0;

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || $profesorActualId <= 0
    || !esTutor()
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || $proyectoId <= 0
    || $nuevoTutorId <= 0
) {
    $_SESSION['resum_tutors_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirResumTutors();
}

try {
    $pdo->beginTransaction();

    // El bloqueig del projecte serialitza dues possibles reassignacions
    // concurrents i la JOIN repeteix l'autorització per grup i curs.
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.grupo_id
        FROM app.proyectos p
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_actual_id
        WHERE p.id_proyecto = :proyecto_id
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
        FOR UPDATE OF p
    ");
    $stmt->execute([
        ':profesor_actual_id' => $profesorActualId,
        ':proyecto_id' => $proyectoId,
        ':curso_academico' => cursoAcademicoActual(),
    ]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proyecto) {
        throw new RuntimeException('Projecte fora de l\'\u00e0mbit autoritzat.');
    }

    // El candidat ha de ser professor actiu del mateix grup/curs i, a més,
    // tenir ja la relació tutor/cotutor que aquesta funcionalitat reutilitza.
    $stmt = $pdo->prepare("
        SELECT rpp.profesor_id
        FROM app.rel_proyectos_profesores rpp
        INNER JOIN app.profesores pr
            ON pr.id_profesor = rpp.profesor_id
           AND pr.activo = true
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.profesor_id = rpp.profesor_id
           AND rpg.grupo_id = :grupo_id
           AND rpg.curso_academico = :curso_academico
        WHERE rpp.proyecto_id = :proyecto_id
          AND rpp.profesor_id = :nuevo_tutor_id
          AND rpp.rol IN ('tutor', 'cotutor')
        FOR UPDATE OF rpp
    ");
    $stmt->execute([
        ':grupo_id' => (int) $proyecto['grupo_id'],
        ':curso_academico' => cursoAcademicoActual(),
        ':proyecto_id' => $proyectoId,
        ':nuevo_tutor_id' => $nuevoTutorId,
    ]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Professor no disponible per a aquest projecte.');
    }

    $stmt = $pdo->prepare("
        UPDATE app.rel_proyectos_profesores
        SET rol = 'cotutor'
        WHERE proyecto_id = :proyecto_id
          AND rol = 'tutor'
          AND profesor_id <> :nuevo_tutor_id
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':nuevo_tutor_id' => $nuevoTutorId]);

    $stmt = $pdo->prepare("
        UPDATE app.rel_proyectos_profesores
        SET rol = 'tutor'
        WHERE proyecto_id = :proyecto_id
          AND profesor_id = :nuevo_tutor_id
          AND rol IN ('tutor', 'cotutor')
        RETURNING profesor_id
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':nuevo_tutor_id' => $nuevoTutorId]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('No s\'ha pogut promoure el tutor seleccionat.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app.rel_proyectos_profesores WHERE proyecto_id = :proyecto_id AND rol = 'tutor'");
    $stmt->execute([':proyecto_id' => $proyectoId]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('La reassignació no ha deixat un únic tutor formal.');
    }

    $pdo->commit();
    unset($_SESSION['resum_tutors_error']);
    $redirigirResumTutors(true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error assignant tutor des de Resum: ' . $e->getMessage());
    $_SESSION['resum_tutors_error'] = 'No s’ha pogut actualitzar el tutor.';
    $redirigirResumTutors();
}
