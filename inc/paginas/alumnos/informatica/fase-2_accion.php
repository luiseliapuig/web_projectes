<?php
declare(strict_types=1);

// Ruta 'api' (vegeu index.php): respon en JSON i no renderitza el layout.
// Accions de l'alumnat sobre la Proposta de projecte (Fase 2, arquitectura
// 'informatica'): classificar el projecte (Pas 1), desar l'enllaç viu,
// sol·licitar revisió i pujar el PDF definitiu (Pas 2/Pas 3). Mai estableix
// propuesta_validada_en: això és responsabilitat exclusiva del tutor (vegeu
// fase-2-tutor_accion.php).
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once dirname(__DIR__, 3) . '/pdf/funciones.php';

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
$proyectoId = isset($_POST['proyecto_id']) ? (int) $_POST['proyecto_id'] : 0;

// El projecte sempre es torna a verificar contra la sessió de l'alumnat, mai
// es confia en l'ID rebut per POST.
if ($proyectoId <= 0 || !esSuProyectoAlumno($proyectoId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'No tens autorització sobre aquest projecte.']);
    exit;
}

// Aquesta acció és específica de la Fase 2 d'informatica': el cicle real del
// projecte ha de pertànyer a aquesta arquitectura (mai n'hi ha prou amb
// amagar el control a la interfície).
$stmtCiclo = $pdo->prepare("
    SELECT c.fases_clave
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE p.id_proyecto = :id
");
$stmtCiclo->execute([':id' => $proyectoId]);
$fasesClaveProyecto = $stmtCiclo->fetchColumn();
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $fasesClaveProyecto !== false ? $fasesClaveProyecto : null], 'informatica')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'Accés no permès.']);
    exit;
}

// Fase 2 exigeix Fase 1 completada. El bloqueig visual no és suficient: cap
// d'aquestes accions (desar URL, sol·licitar revisió, pujar PDF) pot
// executar-se si Fase 1 encara no està completada, encara que s'invoqui
// directament per POST. Gate abans de qualsevol escriptura.
require_once __DIR__ . '/fase-1_funcions.php';
if (!fase1CompletadaAlumnoProyecto($pdo, (int) ($_SESSION['alumno_id'] ?? 0), $proyectoId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'Accés no permès.']);
    exit;
}

// Pas 2 (enllaç, sol·licitud) i Pas 3 (PDF) exigeixen Pas 1 (classificació
// del projecte) ja completat. El bloqueig visual no és suficient: cap
// d'aquestes accions pot executar-se sense una classificació vàlida i
// completa, encara que s'invoqui directament per POST.
require_once __DIR__ . '/fase-2_proposta_funcions.php';
if (in_array($accio, ['guardar_url', 'solicitar_revisio', 'pujar_pdf'], true)) {
    $classificacio = fase2ClassificacioObtenirEstat($pdo, $proyectoId);
    if (!$classificacio['completat']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'missatge' => 'Primer defineix el tipus de projecte.']);
        exit;
    }
}

// -----------------------------------------------------------------------------
// Pas 1: classificar el projecte (categoria +, si escau, subtipus). Cada
// selecció vàlida es desa immediatament; no hi ha botó "Desar" general.
// Reutilitza el catàleg ja existent (app.proyecto_categorias /
// app.proyecto_tipos): mai es confia en l'<option> enviat pel navegador.
// -----------------------------------------------------------------------------

if ($accio === 'guardar_categoria') {
    $categoriaId = isset($_POST['categoria_proyecto_id']) ? (int) $_POST['categoria_proyecto_id'] : 0;

    $stmtFamilia = $pdo->prepare("
        SELECT c.familia_ciclo_id
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.id_proyecto = :id
    ");
    $stmtFamilia->execute([':id' => $proyectoId]);
    $familiaId = (int) $stmtFamilia->fetchColumn();

    $stmtValid = $pdo->prepare("
        SELECT COUNT(*) FROM app.proyecto_categorias
        WHERE id_categoria_proyecto = :id AND familia_ciclo_id = :familia AND activo = true
    ");
    $stmtValid->execute([':id' => $categoriaId, ':familia' => $familiaId]);
    if ($categoriaId <= 0 || $familiaId <= 0 || (int) $stmtValid->fetchColumn() !== 1) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Selecciona un tipus de projecte vàlid.']);
        exit;
    }

    // Canviar de categoria invalida qualsevol subtipus anterior: mai pot
    // quedar un projecte classificat amb una categoria i el subtipus d'una
    // altra (per exemple, Investigació amb un subtipus de Desenvolupament).
    $pdo->prepare("
        UPDATE app.proyectos SET categoria_proyecto_id = :categoria, tipo_proyecto_id = NULL
        WHERE id_proyecto = :id
    ")->execute([':categoria' => $categoriaId, ':id' => $proyectoId]);

    echo json_encode(['ok' => true]);
    exit;
}

if ($accio === 'guardar_tipo') {
    $categoriaId = isset($_POST['categoria_proyecto_id']) ? (int) $_POST['categoria_proyecto_id'] : 0;
    $tipoId = isset($_POST['tipo_proyecto_id']) ? (int) $_POST['tipo_proyecto_id'] : 0;

    // La categoria s'envia també aquí (i es torna a validar sencera, mateix
    // criteri que guardar_categoria) perquè el <select> de categoria pot no
    // haver disparat mai un "change": si el valor per defecte que mostrava
    // la interfície ja coincidia amb la selecció de l'alumnat, el navegador
    // no notifica cap canvi i categoria_proyecto_id pot seguir sense desar a
    // BD. Mai es dona per fet que ja hi és: es revalida i es desa sencera
    // en aquest mateix pas.
    $stmtFamilia = $pdo->prepare("
        SELECT c.familia_ciclo_id
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.id_proyecto = :id
    ");
    $stmtFamilia->execute([':id' => $proyectoId]);
    $familiaId = (int) $stmtFamilia->fetchColumn();

    $stmtCategoria = $pdo->prepare("
        SELECT COUNT(*) FROM app.proyecto_categorias
        WHERE id_categoria_proyecto = :id AND familia_ciclo_id = :familia AND activo = true
    ");
    $stmtCategoria->execute([':id' => $categoriaId, ':familia' => $familiaId]);
    if ($categoriaId <= 0 || $familiaId <= 0 || (int) $stmtCategoria->fetchColumn() !== 1) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Selecciona primer un tipus de projecte vàlid.']);
        exit;
    }

    // El subtipus ha de pertànyer realment a AQUESTA categoria (la que s'ha
    // validat just a sobre, no una prèviament desada) i estar actiu.
    $stmtTipo = $pdo->prepare("
        SELECT COUNT(*) FROM app.proyecto_tipos
        WHERE id_tipo_proyecto = :id AND categoria_proyecto_id = :categoria AND activo = true
    ");
    $stmtTipo->execute([':id' => $tipoId, ':categoria' => $categoriaId]);
    if ($tipoId <= 0 || (int) $stmtTipo->fetchColumn() !== 1) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Selecciona un subtipus vàlid.']);
        exit;
    }

    $pdo->prepare("UPDATE app.proyectos SET categoria_proyecto_id = :categoria, tipo_proyecto_id = :tipo WHERE id_proyecto = :id")
        ->execute([':categoria' => $categoriaId, ':tipo' => $tipoId, ':id' => $proyectoId]);

    echo json_encode(['ok' => true]);
    exit;
}

// -----------------------------------------------------------------------------
// Desar l'enllaç viu de la proposta. Només mentre encara no hi ha PDF
// definitiu: un cop la proposta és definitiva, l'enllaç viu deixa de ser
// l'element principal (però mai s'esborra).
// -----------------------------------------------------------------------------

if ($accio === 'guardar_url') {
    $url = isset($_POST['url']) && is_string($_POST['url']) ? trim($_POST['url']) : '';
    if ($url === '' || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Introdueix una URL vàlida.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE app.proyectos
        SET propuesta_url = :url
        WHERE id_proyecto = :id AND propuesta_pdf IS NULL
    ");
    $stmt->execute([':url' => $url, ':id' => $proyectoId]);
    if ($stmt->rowCount() !== 1) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'missatge' => 'La proposta ja és definitiva i l’enllaç viu no es pot modificar.']);
        exit;
    }

    echo json_encode(['ok' => true, 'url' => $url]);
    exit;
}

// -----------------------------------------------------------------------------
// Sol·licitar revisió: crea una fila a la taula genèrica de revisions. La
// pròpia BD garanteix la idempotència (índex únic parcial sobre files
// obertes); si ja n'hi havia una d'oberta, no es duplica ni s'envia un altre
// correu.
// -----------------------------------------------------------------------------

if ($accio === 'solicitar_revisio') {
    $stmt = $pdo->prepare("SELECT propuesta_url, propuesta_pdf FROM app.proyectos WHERE id_proyecto = :id");
    $stmt->execute([':id' => $proyectoId]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

    $urlViva = trim((string) ($proyecto['propuesta_url'] ?? ''));
    if ($urlViva === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Desa primer l’enllaç del document abans de sol·licitar revisió.']);
        exit;
    }
    if (trim((string) ($proyecto['propuesta_pdf'] ?? '')) !== '') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'missatge' => 'La proposta ja és definitiva.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO app.revisiones_solicitudes (proyecto_id, tipo, referencia_id, titulo)
            VALUES (:proyecto_id, 'proposta', NULL, 'Proposta de projecte')
            ON CONFLICT (proyecto_id, tipo, COALESCE(referencia_id, 0)) WHERE (resuelto_en IS NULL) DO NOTHING
            RETURNING id_revision_solicitud
        ");
        $stmt->execute([':proyecto_id' => $proyectoId]);
        $idSolicitud = $stmt->fetchColumn();

        if ($idSolicitud !== false) {
            // Nova sol·licitud real (no una duplicada): s'encua l'avís al
            // tutor actual del projecte. Si no n'hi ha cap amb rol 'tutor'
            // assignat encara, no s'envia res (no hi ha destinatari formal).
            $stmtTutor = $pdo->prepare("
                SELECT pr.nombre, pr.apellidos, pr.email
                FROM app.rel_proyectos_profesores rpp
                INNER JOIN app.profesores pr ON pr.id_profesor = rpp.profesor_id
                WHERE rpp.proyecto_id = :proyecto_id AND rpp.rol = 'tutor' AND pr.activo = true
                LIMIT 1
            ");
            $stmtTutor->execute([':proyecto_id' => $proyectoId]);
            $tutor = $stmtTutor->fetch(PDO::FETCH_ASSOC);

            if ($tutor && filter_var($tutor['email'], FILTER_VALIDATE_EMAIL)) {
                $stmtAlumnes = $pdo->prepare("
                    SELECT a.nombre, a.apellidos
                    FROM app.rel_proyectos_alumnos rpa
                    INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
                    WHERE rpa.proyecto_id = :proyecto_id
                    ORDER BY a.nombre, a.apellidos
                ");
                $stmtAlumnes->execute([':proyecto_id' => $proyectoId]);
                $nomsAlumnes = array_map(
                    static fn (array $a): string => trim((string) $a['nombre'] . ' ' . (string) $a['apellidos']),
                    $stmtAlumnes->fetchAll(PDO::FETCH_ASSOC)
                );

                $stmtProjecte = $pdo->prepare("SELECT nombre FROM app.proyectos WHERE id_proyecto = :id");
                $stmtProjecte->execute([':id' => $proyectoId]);
                $nombreProjecte = trim((string) ($stmtProjecte->fetchColumn() ?: ''));

                try {
                    require_once dirname(__DIR__, 3) . '/email/bootstrap.php';
                    require_once dirname(__DIR__, 3) . '/email/templates/proposta_revisio_solicitada.php';

                    $baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
                    if (filter_var($baseUrl, FILTER_VALIDATE_URL) && str_starts_with($baseUrl, 'https://')) {
                        $nombreTutor = trim((string) $tutor['nombre'] . ' ' . (string) $tutor['apellidos']);
                        $urlFase = $baseUrl . '/projecte/' . $proyectoId . '/fases/fase-2/proposta';
                        $body = emailPropostaRevisioSolicitada($nombreTutor, implode(' / ', $nomsAlumnes), $nombreProjecte, $urlFase);

                        $queue = new EmailQueue($pdo);
                        $queue->enqueue([
                            'destinatario' => (string) $tutor['email'],
                            'nombre_destinatario' => $nombreTutor,
                            'asunto' => 'Revisió de la Proposta de projecte',
                            'cuerpo_html' => $body['html'],
                            'cuerpo_texto' => $body['text'],
                            'tipo' => 'proposta_revisio_solicitada',
                            'proyecto_id' => $proyectoId,
                            'clave_idempotencia' => 'proposta_revisio:' . (int) $idSolicitud,
                        ]);
                    } else {
                        error_log('APP_URL no vàlida: no s’ha pogut encuar l’avís de revisió de proposta.');
                    }
                } catch (Throwable $e) {
                    error_log('Error encuant l’avís de revisió de proposta: ' . $e->getMessage());
                }
            } else {
                error_log('Sol·licitud de revisió de proposta sense tutor assignat (projecte ' . $proyectoId . '): no s’envia correu.');
            }
        }
    } catch (Throwable $e) {
        error_log('Error creant la sol·licitud de revisió de proposta: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut sol·licitar la revisió.']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

// -----------------------------------------------------------------------------
// Pujar el PDF definitiu: només un cop el tutor ha validat la proposta.
// Passa per la capa única pdfGuardarDefinitiu() (inc/pdf/funciones.php):
// mateixa validació, mateixa estructura de carpetes
// uploads/{curs}/{cicle}/{id_projecte}/ i, a més, la mateixa optimització
// que ja aplicava el mecanisme V1 a document funcional/memòria — abans
// aquesta pujada la saltava.
// -----------------------------------------------------------------------------

if ($accio === 'pujar_pdf') {
    $stmt = $pdo->prepare("
        SELECT p.propuesta_validada_en, p.curso_academico, c.abr AS ciclo
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.id_proyecto = :id
    ");
    $stmt->execute([':id' => $proyectoId]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proyecto || $proyecto['propuesta_validada_en'] === null) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'missatge' => 'Encara no s’ha validat la proposta.']);
        exit;
    }

    $file = $_FILES['pdf'] ?? null;
    if (!is_array($file)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Error en la pujada del fitxer.']);
        exit;
    }

    // Nom fix i estable (no derivat d'input de l'usuari): només hi ha un PDF
    // definitiu de proposta per projecte, i una pujada nova en substitueix
    // l'anterior (pdfGuardarDefinitiu() ja garanteix que mai queda a mitges).
    $resultat = pdfGuardarDefinitiu(
        $file,
        (string) $proyecto['curso_academico'],
        (string) $proyecto['ciclo'],
        $proyectoId,
        'proposta.pdf'
    );

    if (!$resultat['ok']) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => $resultat['error'] ?? 'No s’ha pogut guardar el fitxer.']);
        exit;
    }

    $rutaRel = $resultat['ruta_rel'] . '?v=' . time();
    $stmt = $pdo->prepare("UPDATE app.proyectos SET propuesta_pdf = :ruta WHERE id_proyecto = :id AND propuesta_validada_en IS NOT NULL");
    $stmt->execute([':ruta' => $rutaRel, ':id' => $proyectoId]);
    if ($stmt->rowCount() !== 1) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut desar el document.']);
        exit;
    }

    echo json_encode(['ok' => true, 'ruta' => $rutaRel]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'missatge' => 'Acció no reconeguda.']);
