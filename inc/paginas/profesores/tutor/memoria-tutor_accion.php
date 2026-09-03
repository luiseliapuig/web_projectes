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
        RETURNING fecha_ultima_revision
    ");
    $stmt->execute([':estado' => $estado, ':id' => $idSeguimiento]);
    $fechaUltimaRevision = $stmt->fetchColumn();
    if ($fechaUltimaRevision === false) {
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

    // La revisió i el comentari ja estan confirmats. El correu és només una
    // notificació: qualsevol incidència posterior queda aïllada de l'acció.
    try {
        $stmtContext = $pdo->prepare("
            SELECT p.nombre AS projecte, me.titulo AS apartat
            FROM app.memoria_seguimiento ms
            INNER JOIN app.memoria_estructura me ON me.id_memoria_estructura = ms.memoria_estructura_id
            INNER JOIN app.proyectos p ON p.id_proyecto = ms.proyecto_id
            WHERE ms.id_memoria_seguimiento = :id
              AND ms.proyecto_id = :proyecto_id
        ");
        $stmtContext->execute([
            ':id' => $idSeguimiento,
            ':proyecto_id' => (int) $seguimiento['proyecto_id'],
        ]);
        $context = $stmtContext->fetch(PDO::FETCH_ASSOC);

        $stmtAlumnes = $pdo->prepare("
            SELECT a.id_alumno, a.nombre, a.apellidos, a.email
            FROM app.rel_proyectos_alumnos rpa
            INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
            WHERE rpa.proyecto_id = :proyecto_id
              AND a.activo = true
            ORDER BY a.id_alumno
        ");
        $stmtAlumnes->execute([':proyecto_id' => (int) $seguimiento['proyecto_id']]);
        $alumnes = $stmtAlumnes->fetchAll(PDO::FETCH_ASSOC);

        if ($context && $alumnes !== []) {
            require_once dirname(__DIR__, 3) . '/email/bootstrap.php';
            require_once dirname(__DIR__, 3) . '/email/templates/memoria_apartat_revisat.php';

            $baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
            if (filter_var($baseUrl, FILTER_VALIDATE_URL) && str_starts_with($baseUrl, 'https://')) {
                $urlRevision = $baseUrl . '/memoria';
                $revisionKey = hash('sha256', (string) $fechaUltimaRevision);
                $queue = new EmailQueue($pdo);

                foreach ($alumnes as $alumne) {
                    if (!filter_var($alumne['email'], FILTER_VALIDATE_EMAIL)) {
                        error_log('Revisió de memòria: alumne actiu sense email vàlid (alumne ' . (int) $alumne['id_alumno'] . '), no s’envia correu.');
                        continue;
                    }

                    try {
                        $nombreAlumne = trim((string) $alumne['nombre'] . ' ' . (string) $alumne['apellidos']);
                        $body = emailMemoriaApartatRevisat(
                            $nombreAlumne,
                            (string) $context['projecte'],
                            (string) $context['apartat'],
                            $estado,
                            $comentario,
                            $urlRevision
                        );

                        $queue->enqueue([
                            'destinatario' => (string) $alumne['email'],
                            'nombre_destinatario' => $nombreAlumne,
                            'asunto' => 'Revisió d’un apartat de Memòria',
                            'cuerpo_html' => $body['html'],
                            'cuerpo_texto' => $body['text'],
                            'tipo' => 'memoria_apartat_revisat',
                            'proyecto_id' => (int) $seguimiento['proyecto_id'],
                            'creado_por' => (int) $_SESSION['professor_id'],
                            'clave_idempotencia' => 'memoria_revisio_resultat:' . $idSeguimiento . ':' . $revisionKey . ':' . (int) $alumne['id_alumno'],
                        ]);
                    } catch (Throwable $e) {
                        error_log('Error encuant el resultat de revisió de memòria per a l’alumne ' . (int) $alumne['id_alumno'] . ': ' . $e->getMessage());
                    }
                }
            } else {
                error_log('APP_URL no vàlida: no s’han pogut encuar els resultats de revisió de memòria.');
            }
        } elseif (!$context) {
            error_log('Revisió de memòria sense context vàlid (seguiment ' . $idSeguimiento . '): no s’envien correus.');
        }
    } catch (Throwable $e) {
        error_log('Error preparant els avisos del resultat de revisió de memòria: ' . $e->getMessage());
    }

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
