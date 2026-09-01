<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once __DIR__ . '/fase-3_document_funcional_funcions.php';
function fase4Resposta(int $codi, string $missatge): never { http_response_code($codi); echo json_encode(['ok' => false, 'missatge' => $missatge]); exit; }
if (!esAlumno()) fase4Resposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase4Resposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$proyectoId = (int) ($_POST['proyecto_id'] ?? 0);
if ($proyectoId <= 0 || !esSuProyectoAlumno($proyectoId)) fase4Resposta(403, 'No tens autorització sobre aquest projecte.');
$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
$stmt->execute([':id' => $proyectoId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase3DocumentFuncionalObtenirEstat($pdo, $proyectoId)['completada']) fase4Resposta(403, 'Accés no permès.');
$tasca = is_string($_POST['tasca'] ?? null) ? trim($_POST['tasca']) : '';
if (!in_array($tasca, ['planificacio', 'gestio'], true)) fase4Resposta(422, 'Tasca no reconeguda.');
$url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
if ($url === '' || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) fase4Resposta(422, 'Introdueix una URL vàlida.');
$stmt = $tasca === 'planificacio'
    ? $pdo->prepare('UPDATE app.proyectos SET planificacion_url = :url WHERE id_proyecto = :id')
    : $pdo->prepare('UPDATE app.proyectos SET gestion_url = :url WHERE id_proyecto = :id');
$stmt->execute([':url' => $url, ':id' => $proyectoId]);
if ($stmt->rowCount() !== 1) fase4Resposta(409, 'No s’ha pogut desar l’enllaç.');
echo json_encode(['ok' => true, 'url' => $url]);
exit;
