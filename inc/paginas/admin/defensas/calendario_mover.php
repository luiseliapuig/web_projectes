<?php
declare(strict_types=1);

soloSuperadmin();

// El ajuste manual permite cualquier aula existente, pero mantiene el ámbito
// del curso vigente y evita aceptar identificadores o formatos inválidos.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'La sol·licitud no és vàlida.']);
    exit;
}

$proyectoId = isset($_POST['id_proyecto']) ? (int) $_POST['id_proyecto'] : 0;
$fecha = isset($_POST['nova_data']) && is_string($_POST['nova_data']) ? trim($_POST['nova_data']) : '';
$hora = isset($_POST['nova_hora']) && is_string($_POST['nova_hora']) ? trim($_POST['nova_hora']) : '';
$aulaId = isset($_POST['nova_aula_id']) ? (int) $_POST['nova_aula_id'] : 0;
$cursoAcademico = isset($_POST['curso_academico']) && is_string($_POST['curso_academico'])
    ? trim($_POST['curso_academico'])
    : '';

$fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
$horaValida = DateTimeImmutable::createFromFormat('!H:i', $hora);
if (
    $proyectoId <= 0
    || $aulaId <= 0
    || $cursoAcademico !== cursoAcademicoDefensas()
    || !$fechaValida
    || $fechaValida->format('Y-m-d') !== $fecha
    || !$horaValida
    || $horaValida->format('H:i') !== $hora
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Revisa el projecte, la data, l’hora i l’aula.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT 1
        FROM app.proyectos
        WHERE id_proyecto = :proyecto_id
          AND curso_academico = :curso_academico
        FOR UPDATE
    ");
    $stmt->execute([
        ':proyecto_id' => $proyectoId,
        ':curso_academico' => $cursoAcademico,
    ]);
    if (!$stmt->fetchColumn()) {
        throw new DomainException('projecte');
    }

    $stmt = $pdo->prepare("SELECT 1 FROM app.aulas WHERE id_aula = :aula_id");
    $stmt->execute([':aula_id' => $aulaId]);
    if (!$stmt->fetchColumn()) {
        throw new DomainException('aula');
    }

    $stmt = $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha = :defensa_fecha,
            defensa_aula_id = :aula_id
        WHERE id_proyecto = :proyecto_id
          AND curso_academico = :curso_academico
    ");
    $stmt->execute([
        ':defensa_fecha' => $fecha . ' ' . $hora . ':00',
        ':aula_id' => $aulaId,
        ':proyecto_id' => $proyectoId,
        ':curso_academico' => $cursoAcademico,
    ]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'missatge' => 'Defensa actualitzada correctament.']);
} catch (DomainException) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(404);
    echo json_encode(['ok' => false, 'missatge' => 'El projecte o l’aula no existeixen.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error movent defensa manualment: ' . $e->getMessage());
    $missatge = $e->getCode() === '23505'
        ? 'Aquesta aula ja està ocupada en la data i hora seleccionades.'
        : 'No s’ha pogut actualitzar la defensa.';
    echo json_encode(['ok' => false, 'missatge' => $missatge]);
}
exit;
