<?php
// Elimina la planificación únicamente del curso académico vigente.

declare(strict_types=1);

soloSuperadmin();

$input = json_decode(file_get_contents('php://input'), true);
$cursoAcademico = is_array($input) && isset($input['curso_academico']) && is_string($input['curso_academico'])
    ? trim($input['curso_academico'])
    : '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !is_array($input)
    || !validarTokenCsrf($input['csrf_token'] ?? null)
    || $cursoAcademico !== cursoAcademicoDefensas()
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'La sol·licitud no és vàlida.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha = NULL, defensa_aula_id = NULL
        WHERE curso_academico = :curso_academico
          AND (defensa_fecha IS NOT NULL OR defensa_aula_id IS NOT NULL)
    ");
    $stmt->execute([':curso_academico' => $cursoAcademico]);
    $afectats = $stmt->rowCount();

    echo json_encode(['ok' => true, 'projectes' => $afectats]);
} catch (PDOException $e) {
    error_log('Error eliminant planificació de defenses: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'missatge' => 'No s’han pogut eliminar les dates.']);
}
exit;
