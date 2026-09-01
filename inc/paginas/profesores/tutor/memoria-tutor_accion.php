<?php
declare(strict_types=1);

// Ruta 'api' (vegeu index.php): respon en JSON i no renderitza el layout.
// Únicament permet al tutor formal d'un projecte canviar l'estat d'un
// apartat de memòria i, opcionalment, afegir-hi un comentari nou. Els
// comentaris no es sobreescriuen mai: cada comentari és una fila nova a
// memoria_comentarios, es conserva l'historial complet.
header('Content-Type: application/json; charset=utf-8');

if (!esProfesor()) {
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
$estado = isset($_POST['estado']) && is_string($_POST['estado']) ? trim($_POST['estado']) : '';
$comentario = isset($_POST['comentario']) && is_string($_POST['comentario']) ? trim($_POST['comentario']) : '';

$estadosValidos = ['corregir', 'completo'];
if (
    $accio !== 'revisar'
    || $idSeguimiento <= 0
    || mb_strlen($comentario) > 4000
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Dades no vàlides.']);
    exit;
}

if (!in_array($estado, $estadosValidos, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Selecciona un resultat de revisió vàlid.']);
    exit;
}

// El seguiment és per projecte, no per alumne: el professor autenticat ha de
// ser el tutor formal d'aquest projecte concret. Formar part del grup o
// constar com a cotutor no és suficient per escriure.
$stmt = $pdo->prepare("SELECT proyecto_id, estado FROM app.memoria_seguimiento WHERE id_memoria_seguimiento = :id");
$stmt->execute([':id' => $idSeguimiento]);
$seguimiento = $stmt->fetch(PDO::FETCH_ASSOC);

if ($seguimiento === false || !esTutorFormalDelProyecto((int) $seguimiento['proyecto_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'No tens autorització per revisar aquest apartat.']);
    exit;
}

if ((string) $seguimiento['estado'] !== 'revision_solicitada') {
    http_response_code(409);
    echo json_encode(['ok' => false, 'missatge' => 'Aquest apartat ja no està pendent de revisió.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // fecha_solicitud_revision es conserva sempre: és el registre de quan es
    // va demanar la revisió, no es toca en canviar l'estat. fecha_ultima_revision
    // sí que s'actualitza: és el moment d'aquesta revisió del tutor.
    $stmt = $pdo->prepare("
        UPDATE app.memoria_seguimiento
        SET estado = :estado, fecha_ultima_revision = NOW(), actualizado_en = NOW()
        WHERE id_memoria_seguimiento = :id
          AND estado = 'revision_solicitada'
    ");
    $stmt->execute([':estado' => $estado, ':id' => $idSeguimiento]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('no_actualitzat');
    }

    // El comentari és opcional: canviar l'estat mai obliga a escriure'n un.
    if ($comentario !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO app.memoria_comentarios (memoria_seguimiento_id, profesor_id, comentario)
            VALUES (:seguimiento_id, :profesor_id, :comentario)
        ");
        $stmt->execute([
            ':seguimiento_id' => $idSeguimiento,
            ':profesor_id' => (int) $_SESSION['professor_id'],
            ':comentario' => $comentario,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'estado' => $estado,
        'fecha_ultima_revision' => dataCatalanaNatural(date('Y-m-d')),
        'comentario' => $comentario !== '' ? $comentario : null,
        'comentario_fecha' => $comentario !== '' ? dataCatalanaNatural(date('Y-m-d')) : null,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error guardant la revisió de memòria: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut guardar la revisió.']);
}
