<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once dirname(__DIR__, 3) . '/pdf/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';

function fase6EntregaMemoriaResposta(int $codi, string $missatge, array $extra = []): never
{
    http_response_code($codi);
    echo json_encode(array_merge(['ok' => $codi < 400, 'missatge' => $missatge], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Si PHP descarta tot el multipart perquè supera post_max_size, $_POST i
// $_FILES arriben buits. Detectem aquest cas abans del CSRF per evitar un
// error ambigu o una resposta HTML del servidor.
$longitudPeticio = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $longitudPeticio > 0 && $_POST === [] && $_FILES === []) {
    fase6EntregaMemoriaResposta(413, 'El fitxer supera el límit de pujada configurat al servidor.');
}

if (!esAlumno()) fase6EntregaMemoriaResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase6EntregaMemoriaResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$projecteId = (int) ($_POST['proyecto_id'] ?? 0);
if ($projecteId <= 0 || !esSuProyectoAlumno($projecteId)) fase6EntregaMemoriaResposta(403, 'No tens autorització sobre aquest projecte.');

$stmt = $pdo->prepare('SELECT p.memoria_pdf, p.curso_academico, c.abr AS ciclo, c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
$stmt->execute([':id' => $projecteId]);
$projecte = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$projecte || !proyectoPerteneceArquitecturaFases(['fases_clave' => $projecte['fases_clave'] ?? null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $projecteId)['completada']) {
    fase6EntregaMemoriaResposta(403, 'Accés no permès.');
}

$fitxer = $_FILES['pdf'] ?? null;
if (!is_array($fitxer)) fase6EntregaMemoriaResposta(422, 'Seleccioneu un fitxer PDF.');
try {
    $sufix = date('Ymd-His') . '-' . bin2hex(random_bytes(6));
} catch (Throwable) {
    $sufix = date('Ymd-His') . '-' . str_replace('.', '', uniqid('', true));
}
$resultat = pdfGuardarDefinitiu(
    $fitxer,
    (string) $projecte['curso_academico'],
    (string) $projecte['ciclo'],
    $projecteId,
    'memoria-' . $sufix . '.pdf'
);
if (!$resultat['ok']) fase6EntregaMemoriaResposta(422, (string) ($resultat['error'] ?? 'No s’ha pogut guardar el PDF.'));

$rutaNova = (string) $resultat['ruta_rel'];
$fitxerNou = (string) $resultat['ruta_abs'];
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE app.proyectos SET memoria_pdf = :ruta WHERE id_proyecto = :id');
    $stmt->execute([':ruta' => $rutaNova, ':id' => $projecteId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Projecte no actualitzat');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($fitxerNou !== '' && is_file($fitxerNou)) @unlink($fitxerNou);
    error_log($e->getMessage());
    fase6EntregaMemoriaResposta(500, 'No s’ha pogut completar l’entrega de la memòria.');
}

$fitxerAnterior = pdfResoldreRutaLocalSegura((string) ($projecte['memoria_pdf'] ?? ''));
if ($fitxerAnterior !== null && $fitxerAnterior !== $fitxerNou && is_file($fitxerAnterior) && !@unlink($fitxerAnterior)) {
    error_log('No s’ha pogut eliminar la memòria anterior del projecte ' . $projecteId);
}

fase6EntregaMemoriaResposta(200, 'Memòria entregada.', ['ruta' => $rutaNova]);
