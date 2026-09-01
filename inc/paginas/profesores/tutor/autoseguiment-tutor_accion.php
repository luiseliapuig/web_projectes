<?php
declare(strict_types=1);

// Ruta 'api' (vegeu index.php): respon en JSON i no renderitza el layout.
// Guarda la valoració o el comentari del tutor sobre una setmana ja tancada
// d'Autoseguiment. Mai crea ni toca el contingut escrit per l'alumnat.
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

$idSeguimiento = isset($_POST['id_seguimiento']) ? (int) $_POST['id_seguimiento'] : 0;
$accion = isset($_POST['accio']) && is_string($_POST['accio']) ? trim($_POST['accio']) : '';

if ($idSeguimiento <= 0 || !in_array($accion, ['valoracion', 'comentario'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Dades no vàlides.']);
    exit;
}

// El seguiment ha de correspondre a una setmana ja tancada (fecha_fin < avui,
// el mateix criteri que decideix què es mostra a la vista) i el professor
// autenticat ha de ser el tutor formal d'aquest projecte concret
// (rel_proyectos_profesores.rol = 'tutor', mitjançant la funció existent).
// Formar part del grup o constar com a cotutor no és suficient per escriure.
$stmt = $pdo->prepare("
    SELECT proyecto_id
    FROM app.seguimiento_alumnos
    WHERE id_seguimiento = :id AND fecha_fin < CURRENT_DATE
    LIMIT 1
");
$stmt->execute([':id' => $idSeguimiento]);
$proyectoId = $stmt->fetchColumn();

if ($proyectoId === false || !esTutorFormalDelProyecto((int) $proyectoId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'No tens autorització per modificar aquest seguiment.']);
    exit;
}

// -----------------------------------------------------------------------------
// Valoració: llista blanca estricta { "0", "1", "2", "3" }. Es compara com a
// cadena abans de convertir, perquè "0" no és buit ni fals i no s'ha de
// confondre amb una sol·licitud sense valor.
// -----------------------------------------------------------------------------

if ($accion === 'valoracion') {
    $valorPost = $_POST['valor'] ?? null;
    if (!in_array($valorPost, ['0', '1', '2', '3'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'La valoració no és vàlida.']);
        exit;
    }
    $valor = (int) $valorPost;

    try {
        $stmt = $pdo->prepare("
            UPDATE app.seguimiento_alumnos
            SET valoracion_tutor = :valor, updated_at = NOW()
            WHERE id_seguimiento = :id AND fecha_fin < CURRENT_DATE
        ");
        $stmt->execute([':valor' => $valor, ':id' => $idSeguimiento]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('no_actualitzat');
        }
        echo json_encode(['ok' => true, 'valor' => $valor]);
    } catch (Throwable $e) {
        error_log('Error guardant la valoració del tutor: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut guardar la valoració.']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// Comentari: text lliure opcional. Buit es guarda com NULL. Guardar o no
// guardar comentari no altera en absolut l'estat de revisió de la setmana.
// -----------------------------------------------------------------------------

$comentario = isset($_POST['comentario']) && is_string($_POST['comentario']) ? trim($_POST['comentario']) : '';
if (mb_strlen($comentario) > 4000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'El comentari és massa llarg.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE app.seguimiento_alumnos
        SET comentario_tutor = :comentario, updated_at = NOW()
        WHERE id_seguimiento = :id AND fecha_fin < CURRENT_DATE
    ");
    $stmt->execute([
        ':comentario' => $comentario !== '' ? $comentario : null,
        ':id' => $idSeguimiento,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('no_actualitzat');
    }
    echo json_encode(['ok' => true, 'comentario' => $comentario]);
} catch (Throwable $e) {
    error_log('Error guardant el comentari del tutor: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut guardar el comentari.']);
}
exit;
