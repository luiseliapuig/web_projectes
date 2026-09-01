<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';
require_once __DIR__ . '/fase-5_produccio_funcions.php';

function fase5ProduccioResposta(int $codi, string $missatge, array $extra = []): never
{
    http_response_code($codi);
    echo json_encode(array_merge(['ok' => $codi < 400, 'missatge' => $missatge], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!esAlumno()) fase5ProduccioResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase5ProduccioResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$projecteId = (int) ($_POST['proyecto_id'] ?? 0);
if ($projecteId <= 0 || !esSuProyectoAlumno($projecteId)) fase5ProduccioResposta(403, 'No tens autorització sobre aquest projecte.');
$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');
$stmt->execute([':id' => $projecteId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $projecteId)['completada']) fase5ProduccioResposta(403, 'Accés no permès.');
$url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
if ($url !== '' && !fase5ProduccioUrlValida($url)) fase5ProduccioResposta(422, 'Introdueix una URL http o https vàlida.');
$stmt = $pdo->prepare('UPDATE app.proyectos SET url_proyecto=:url WHERE id_proyecto=:id');
$stmt->execute([':url' => $url !== '' ? $url : null, ':id' => $projecteId]);
if ($stmt->rowCount() !== 1) fase5ProduccioResposta(409, 'No s’ha pogut desar l’enllaç.');
fase5ProduccioResposta(200, $url !== '' ? 'Enllaç desat.' : 'Enllaç eliminat.', ['url' => $url]);
