<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once dirname(__DIR__, 3) . '/imagenes/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';
require_once __DIR__ . '/fase-6_fitxa_publica_funcions.php';

function fase6FitxaResposta(int $codi, string $missatge): never
{
    http_response_code($codi);
    echo json_encode(['ok' => $codi < 400, 'missatge' => $missatge], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!esAlumno()) fase6FitxaResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase6FitxaResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$projecteId = (int) ($_POST['proyecto_id'] ?? 0);
if ($projecteId <= 0 || !esSuProyectoAlumno($projecteId)) fase6FitxaResposta(403, 'No tens autorització sobre aquest projecte.');

$stmt = $pdo->prepare('SELECT p.ruta_imagen, p.curso_academico, c.abr AS ciclo, c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
$stmt->execute([':id' => $projecteId]);
$projecte = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$projecte || !proyectoPerteneceArquitecturaFases(['fases_clave' => $projecte['fases_clave'] ?? null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $projecteId)['completada']) fase6FitxaResposta(403, 'Accés no permès.');

$nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
$resumen = is_string($_POST['resumen'] ?? null) ? trim($_POST['resumen']) : '';
$descripcion = is_string($_POST['descripcion'] ?? null) ? trim($_POST['descripcion']) : '';
if ($nombre === '') fase6FitxaResposta(422, 'Indiqueu el nom del projecte.');
if ($resumen === '' || mb_strlen($resumen) > FASE6_FITXA_RESUM_MAX) fase6FitxaResposta(422, 'El resum és obligatori i no pot superar els 220 caràcters.');
if (mb_strlen($descripcion) < FASE6_FITXA_DESCRIPCIO_MIN) fase6FitxaResposta(422, 'La descripció ha de tenir com a mínim 800 caràcters.');

$novaImatge = isset($_FILES['imagen']) && (int) ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$rutaNova = null;
$fitxerNou = null;
if ($novaImatge) {
    $curs = fase6FitxaPublicaPartRuta((string) ($projecte['curso_academico'] ?? ''));
    $cicle = fase6FitxaPublicaPartRuta((string) ($projecte['ciclo'] ?? ''));
    if ($curs === '' || $cicle === '') fase6FitxaResposta(422, 'El projecte no té un curs o cicle vàlid.');
    $directori = dirname(__DIR__, 4) . '/uploads/' . $curs . '/' . $cicle . '/' . $projecteId;
    if (!is_dir($directori) && !mkdir($directori, 0775, true) && !is_dir($directori)) fase6FitxaResposta(500, 'No s’ha pogut preparar la carpeta de la imatge.');
    try {
        $sufix = bin2hex(random_bytes(8));
    } catch (Throwable) {
        $sufix = str_replace('.', '', uniqid('', true));
    }
    $nomFitxer = 'imagen-' . $sufix . '.jpg';
    $fitxerNou = $directori . '/' . $nomFitxer;
    if (!extension_loaded('gd')) fase6FitxaResposta(500, 'El servidor no té activat el processament d’imatges GD.');
    if (!guardarImagenWeb($_FILES['imagen'], $fitxerNou)) fase6FitxaResposta(422, 'No s’ha pogut processar la imatge. Utilitzeu JPG, PNG o WEBP de fins a 20 MB.');
    $rutaNova = '/uploads/' . $curs . '/' . $cicle . '/' . $projecteId . '/' . $nomFitxer;
}

if (!$novaImatge && trim((string) ($projecte['ruta_imagen'] ?? '')) === '') fase6FitxaResposta(422, 'Seleccioneu una imatge del projecte.');
try {
    $sql = 'UPDATE app.proyectos SET nombre = :nombre, resumen = :resumen, descripcion = :descripcion';
    $params = [':nombre' => $nombre, ':resumen' => $resumen, ':descripcion' => $descripcion, ':id' => $projecteId];
    if ($rutaNova !== null) {
        $sql .= ', ruta_imagen = :ruta_imagen';
        $params[':ruta_imagen'] = $rutaNova;
    }
    $sql .= ' WHERE id_proyecto = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Projecte no actualitzat');
} catch (Throwable $e) {
    if ($fitxerNou !== null && is_file($fitxerNou)) @unlink($fitxerNou);
    error_log($e->getMessage());
    fase6FitxaResposta(500, 'No s’han pogut desar les dades.');
}

if ($rutaNova !== null) {
    $fitxerAnterior = fase6FitxaPublicaFitxerAnterior((string) ($projecte['ruta_imagen'] ?? ''));
    if ($fitxerAnterior !== null && $fitxerAnterior !== $fitxerNou && is_file($fitxerAnterior) && !@unlink($fitxerAnterior)) {
        error_log('No s’ha pogut eliminar la imatge anterior del projecte ' . $projecteId);
    }
}
fase6FitxaResposta(200, 'Dades desades.');
