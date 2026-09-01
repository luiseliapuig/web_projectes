<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';
require_once __DIR__ . '/fase-5_autoavaluacio_funcions.php';

function fase5AutoavaluacioResposta(int $codi, string $missatge): never
{
    http_response_code($codi);
    echo json_encode(['ok' => $codi < 400, 'missatge' => $missatge], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!esAlumno()) fase5AutoavaluacioResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase5AutoavaluacioResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$projecteId = (int) ($_POST['proyecto_id'] ?? 0);
if ($projecteId <= 0 || !esSuProyectoAlumno($projecteId)) fase5AutoavaluacioResposta(403, 'No tens autorització sobre aquest projecte.');
$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');
$stmt->execute([':id' => $projecteId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $projecteId)['completada']) fase5AutoavaluacioResposta(403, 'Accés no permès.');
$respostes = [];
foreach (fase5AutoavaluacioPreguntes() as $camp => $_) {
    $valor = $_POST[$camp] ?? '';
    if (!is_string($valor)) fase5AutoavaluacioResposta(422, 'Les respostes no són vàlides.');
    $respostes[$camp] = trim($valor);
}
$stmt = $pdo->prepare('UPDATE app.proyectos SET autoev1=:autoev1, autoev2=:autoev2, autoev3=:autoev3, autoev4=:autoev4 WHERE id_proyecto=:id');
$stmt->execute([
    ':autoev1' => $respostes['autoev1'] !== '' ? $respostes['autoev1'] : null,
    ':autoev2' => $respostes['autoev2'] !== '' ? $respostes['autoev2'] : null,
    ':autoev3' => $respostes['autoev3'] !== '' ? $respostes['autoev3'] : null,
    ':autoev4' => $respostes['autoev4'] !== '' ? $respostes['autoev4'] : null,
    ':id' => $projecteId,
]);
if ($stmt->rowCount() !== 1) fase5AutoavaluacioResposta(409, 'No s’ha pogut desar l’autoavaluació.');
fase5AutoavaluacioResposta(200, 'Autoavaluació desada.');
