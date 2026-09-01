<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once dirname(__DIR__, 3) . '/pdf/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';

function fase5EntornResposta(int $codi, string $missatge): never
{
    http_response_code($codi);
    echo json_encode(['ok' => false, 'missatge' => $missatge]);
    exit;
}

if (!esAlumno()) fase5EntornResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    fase5EntornResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
}

$accio = is_string($_POST['accio'] ?? null) ? trim($_POST['accio']) : '';
$proyectoId = (int) ($_POST['proyecto_id'] ?? 0);
if ($proyectoId <= 0 || !esSuProyectoAlumno($proyectoId)) fase5EntornResposta(403, 'No tens autorització sobre aquest projecte.');

$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');
$stmt->execute([':id' => $proyectoId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $proyectoId)['completada']) {
    fase5EntornResposta(403, 'Accés no permès.');
}

if ($accio === 'guardar_url') {
    $url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
    if ($url === '' || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) fase5EntornResposta(422, 'Introdueix una URL vàlida.');
    $stmt = $pdo->prepare('UPDATE app.proyectos SET entorno_desarrollo_url=:url WHERE id_proyecto=:id AND entorno_desarrollo_pdf IS NULL');
    $stmt->execute([':url' => $url, ':id' => $proyectoId]);
    if ($stmt->rowCount() !== 1) fase5EntornResposta(409, 'El document de preparació ja és definitiu.');
    echo json_encode(['ok' => true, 'url' => $url]);
    exit;
}

if ($accio === 'solicitar_revisio') {
    $stmt = $pdo->prepare('SELECT entorno_desarrollo_url,entorno_desarrollo_pdf,entorno_desarrollo_validado_en,nombre FROM app.proyectos WHERE id_proyecto=:id');
    $stmt->execute([':id' => $proyectoId]);
    $projecte = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (trim((string) ($projecte['entorno_desarrollo_url'] ?? '')) === '') fase5EntornResposta(422, 'Desa primer l’enllaç del document.');
    if (trim((string) ($projecte['entorno_desarrollo_pdf'] ?? '')) !== '') fase5EntornResposta(409, 'El document de preparació ja és definitiu.');
    if (($projecte['entorno_desarrollo_validado_en'] ?? null) !== null) fase5EntornResposta(409, 'El document de preparació ja està validat.');
    try {
        $stmt = $pdo->prepare("INSERT INTO app.revisiones_solicitudes(proyecto_id,tipo,referencia_id,titulo) VALUES(:id,'entorn_desenvolupament',NULL,'Preparació de l’entorn') ON CONFLICT (proyecto_id,tipo,COALESCE(referencia_id,0)) WHERE (resuelto_en IS NULL) DO NOTHING RETURNING id_revision_solicitud");
        $stmt->execute([':id' => $proyectoId]);
        $revisioId = $stmt->fetchColumn();
        if ($revisioId !== false) {
            $stmt = $pdo->prepare("SELECT pr.nombre,pr.apellidos,pr.email FROM app.rel_proyectos_profesores r INNER JOIN app.profesores pr ON pr.id_profesor=r.profesor_id WHERE r.proyecto_id=:id AND r.rol='tutor' AND pr.activo=true LIMIT 1");
            $stmt->execute([':id' => $proyectoId]);
            $tutor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tutor && filter_var($tutor['email'], FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare('SELECT a.nombre,a.apellidos FROM app.rel_proyectos_alumnos r INNER JOIN app.alumnos a ON a.id_alumno=r.alumno_id WHERE r.proyecto_id=:id ORDER BY a.nombre,a.apellidos');
                $stmt->execute([':id' => $proyectoId]);
                $alumnat = implode(' / ', array_map(static fn(array $a): string => trim($a['nombre'] . ' ' . $a['apellidos']), $stmt->fetchAll(PDO::FETCH_ASSOC)));
                try {
                    require_once dirname(__DIR__, 3) . '/email/bootstrap.php';
                    require_once dirname(__DIR__, 3) . '/email/templates/preparacio_entorn_revisio_solicitada.php';
                    $base = rtrim((string) (getenv('APP_URL') ?: ''), '/');
                    if (filter_var($base, FILTER_VALIDATE_URL) && str_starts_with($base, 'https://')) {
                        $nomTutor = trim($tutor['nombre'] . ' ' . $tutor['apellidos']);
                        $body = emailPreparacioEntornRevisioSolicitada($nomTutor, $alumnat, (string) $projecte['nombre'], $base . '/projecte/' . $proyectoId . '/fases/fase-5/preparacio-entorn');
                        (new EmailQueue($pdo))->enqueue(['destinatario' => $tutor['email'], 'nombre_destinatario' => $nomTutor, 'asunto' => 'Revisió de la Preparació de l’entorn', 'cuerpo_html' => $body['html'], 'cuerpo_texto' => $body['text'], 'tipo' => 'entorn_revisio_solicitada', 'proyecto_id' => $proyectoId, 'clave_idempotencia' => 'entorn_revisio:' . (int) $revisioId]);
                    }
                } catch (Throwable $e) { error_log($e->getMessage()); }
            }
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        fase5EntornResposta(500, 'No s’ha pogut sol·licitar la revisió.');
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($accio === 'pujar_pdf') {
    $stmt = $pdo->prepare('SELECT p.entorno_desarrollo_validado_en,p.curso_academico,c.abr ciclo FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');
    $stmt->execute([':id' => $proyectoId]);
    $projecte = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$projecte || $projecte['entorno_desarrollo_validado_en'] === null) fase5EntornResposta(409, 'Encara no s’ha validat el document de preparació.');
    $fitxer = $_FILES['pdf'] ?? null;
    if (!is_array($fitxer)) fase5EntornResposta(422, 'Error en la pujada del fitxer.');
    $resultat = pdfGuardarDefinitiu($fitxer, (string) $projecte['curso_academico'], (string) $projecte['ciclo'], $proyectoId, 'preparacio-entorn-desenvolupament.pdf');
    if (!$resultat['ok']) fase5EntornResposta(422, $resultat['error'] ?? 'No s’ha pogut guardar el fitxer.');
    $ruta = $resultat['ruta_rel'] . '?v=' . time();
    $stmt = $pdo->prepare('UPDATE app.proyectos SET entorno_desarrollo_pdf=:ruta WHERE id_proyecto=:id AND entorno_desarrollo_validado_en IS NOT NULL');
    $stmt->execute([':ruta' => $ruta, ':id' => $proyectoId]);
    if ($stmt->rowCount() !== 1) fase5EntornResposta(409, 'No s’ha pogut desar el document.');
    echo json_encode(['ok' => true, 'ruta' => $ruta]);
    exit;
}

fase5EntornResposta(422, 'Acció no reconeguda.');
