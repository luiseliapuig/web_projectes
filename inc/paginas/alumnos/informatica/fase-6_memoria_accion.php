<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';
require_once __DIR__ . '/fase-6_memoria_funcions.php';

function fase6MemoriaResposta(int $codi, string $missatge): never
{
    http_response_code($codi);
    echo json_encode(['ok' => $codi < 400, 'missatge' => $missatge], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!esAlumno()) fase6MemoriaResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase6MemoriaResposta(400, 'La sol·licitud no és vàlida o ha caducat.');

$projecteId = (int) ($_POST['proyecto_id'] ?? 0);
if ($projecteId <= 0 || !esSuProyectoAlumno($projecteId)) fase6MemoriaResposta(403, 'No tens autorització sobre aquest projecte.');

$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
$stmt->execute([':id' => $projecteId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $projecteId)['completada']) {
    fase6MemoriaResposta(403, 'Accés no permès.');
}

$url = $_POST['url'] ?? '';
if (!is_string($url)) fase6MemoriaResposta(422, 'L’enllaç no és vàlid.');
$url = trim($url);
if ($url !== '' && !fase6MemoriaUrlValida($url)) fase6MemoriaResposta(422, 'Introdueix una URL HTTP o HTTPS vàlida.');

$stmt = $pdo->prepare('UPDATE app.proyectos SET memoria_url = :url WHERE id_proyecto = :id');
$stmt->execute([
    ':url' => $url !== '' ? $url : null,
    ':id' => $projecteId,
]);
if ($stmt->rowCount() !== 1) fase6MemoriaResposta(409, 'No s’ha pogut desar l’enllaç.');

fase6MemoriaResposta(200, 'Enllaç desat.');
