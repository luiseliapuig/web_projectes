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
        RETURNING fecha_solicitud_revision
    ");
    $stmt->execute([':id' => $idSeguimiento, ':proyecto_id' => (int) $proyectoId]);
    $fechaSolicitudRevision = $stmt->fetchColumn();
    if ($fechaSolicitudRevision === false) {
        throw new RuntimeException('no_actualitzat');
    }

    $pdo->commit();

    // La sol·licitud funcional ja està confirmada. Qualsevol incidència del
    // correu es registra, però no altera ni fa fallar l'operació de l'alumnat.
    try {
        $stmtTutor = $pdo->prepare("
            SELECT pr.nombre, pr.apellidos, pr.email
            FROM app.rel_proyectos_profesores rpp
            INNER JOIN app.profesores pr ON pr.id_profesor = rpp.profesor_id
            WHERE rpp.proyecto_id = :proyecto_id
              AND rpp.rol = 'tutor'
              AND pr.activo = true
            LIMIT 1
        ");
        $stmtTutor->execute([':proyecto_id' => (int) $proyectoId]);
        $tutor = $stmtTutor->fetch(PDO::FETCH_ASSOC);

        if ($tutor && filter_var($tutor['email'], FILTER_VALIDATE_EMAIL)) {
            $stmtContext = $pdo->prepare("
                SELECT p.nombre AS projecte, me.titulo AS apartat,
                       STRING_AGG(TRIM(a.nombre || ' ' || a.apellidos), ' / ' ORDER BY a.nombre, a.apellidos) AS alumnat
                FROM app.memoria_seguimiento ms
                INNER JOIN app.memoria_estructura me ON me.id_memoria_estructura = ms.memoria_estructura_id
                INNER JOIN app.proyectos p ON p.id_proyecto = ms.proyecto_id
                INNER JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
                INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
                WHERE ms.id_memoria_seguimiento = :id
                  AND ms.proyecto_id = :proyecto_id
                GROUP BY p.nombre, me.titulo
            ");
            $stmtContext->execute([
                ':id' => $idSeguimiento,
                ':proyecto_id' => (int) $proyectoId,
            ]);
            $context = $stmtContext->fetch(PDO::FETCH_ASSOC);

            if ($context) {
                require_once dirname(__DIR__, 2) . '/email/bootstrap.php';
                require_once dirname(__DIR__, 2) . '/email/templates/memoria_apartat_revisio_solicitada.php';

                $baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
                if (filter_var($baseUrl, FILTER_VALIDATE_URL) && str_starts_with($baseUrl, 'https://')) {
                    $nombreTutor = trim((string) $tutor['nombre'] . ' ' . (string) $tutor['apellidos']);
                    $urlRevision = $baseUrl . '/revisio-memoria/projecte/' . (int) $proyectoId . '#apartat-' . $idSeguimiento;
                    $body = emailMemoriaApartatRevisioSolicitada(
                        $nombreTutor,
                        (string) $context['alumnat'],
                        (string) $context['projecte'],
                        (string) $context['apartat'],
                        $urlRevision
                    );

                    (new EmailQueue($pdo))->enqueue([
                        'destinatario' => (string) $tutor['email'],
                        'nombre_destinatario' => $nombreTutor,
                        'asunto' => 'Revisió d’un apartat de Memòria',
                        'cuerpo_html' => $body['html'],
                        'cuerpo_texto' => $body['text'],
                        'tipo' => 'memoria_apartat_revisio_solicitada',
                        'proyecto_id' => (int) $proyectoId,
                        'clave_idempotencia' => 'memoria_revisio:' . $idSeguimiento . ':' . hash('sha256', (string) $fechaSolicitudRevision),
                    ]);
                } else {
                    error_log('APP_URL no vàlida: no s’ha pogut encuar l’avís de revisió de memòria.');
                }
            } else {
                error_log('Sol·licitud de revisió de memòria sense context vàlid (seguiment ' . $idSeguimiento . '): no s’envia correu.');
            }
        } else {
            error_log('Sol·licitud de revisió de memòria sense tutor formal actiu amb email vàlid (projecte ' . (int) $proyectoId . '): no s’envia correu.');
        }
    } catch (Throwable $e) {
        error_log('Error encuant l’avís de revisió de memòria: ' . $e->getMessage());
    }

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
