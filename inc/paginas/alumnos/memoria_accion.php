<?php
declare(strict_types=1);

// Ruta 'api' (vegeu index.php): respon en JSON i no renderitza el layout.
// Únicament permet sol·licitar revisió d'un apartat de memòria. Mai crea ni
// modifica cap altre camp; el contingut de l'apartat el gestiona el
// professorat des de la seva pròpia vista (encara no implementada).
header('Content-Type: application/json; charset=utf-8');

if (!esAlumno()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'Accés no permès.']);
    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'La sol·licitud no és vàlida o ha caducat.']);
    exit;
}

$accio = isset($_POST['accio']) && is_string($_POST['accio']) ? trim($_POST['accio']) : '';
$idSeguimiento = isset($_POST['id_seguimiento']) ? (int) $_POST['id_seguimiento'] : 0;

if ($accio !== 'solicitar_revisio' || $idSeguimiento <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Dades no vàlides.']);
    exit;
}

// El seguiment és per projecte, no per alumne: cal comprovar que el
// projecte del seguiment és realment el de l'alumne autenticat (mateixa
// funció que ja fa servir la resta de l'àrea d'alumnat), no només que
// l'apartat existeixi.
$stmt = $pdo->prepare("SELECT proyecto_id FROM app.memoria_seguimiento WHERE id_memoria_seguimiento = :id");
$stmt->execute([':id' => $idSeguimiento]);
$proyectoId = $stmt->fetchColumn();

if ($proyectoId === false || !esSuProyectoAlumno((int) $proyectoId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'No tens autorització per modificar aquest apartat.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Només es pot sol·licitar revisió si l'apartat encara no en té cap de
    // pendent i no està ja complet (pendiente/corregir → revision_solicitada).
    $stmt = $pdo->prepare("
        UPDATE app.memoria_seguimiento
        SET estado = 'revision_solicitada', fecha_solicitud_revision = NOW(), actualizado_en = NOW()
        WHERE id_memoria_seguimiento = :id
          AND proyecto_id = :proyecto_id
          AND estado IN ('pendiente', 'corregir')
    ");
    $stmt->execute([':id' => $idSeguimiento, ':proyecto_id' => (int) $proyectoId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('no_actualitzat');
    }

    $pdo->commit();

    // TODO(memoria-notificacions): un cop confirmada la sol·licitud (aquí
    // mateix, després del commit), caldrà encolar via EmailQueue un avís
    // per correu al tutor del projecte. Encara no implementat perquè falta
    // la vista de revisió del professorat on hauria d'enllaçar el correu.

    echo json_encode([
        'ok' => true,
        'estado' => 'revision_solicitada',
        'etiqueta' => 'Revisió sol·licitada',
        'data_solicitud' => dataCatalanaNatural(date('Y-m-d')),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!($e instanceof RuntimeException && $e->getMessage() === 'no_actualitzat')) {
        error_log('Error sol·licitant revisió de memòria: ' . $e->getMessage());
    }
    http_response_code(409);
    echo json_encode(['ok' => false, 'missatge' => 'No es pot sol·licitar la revisió d’aquest apartat ara mateix.']);
}
