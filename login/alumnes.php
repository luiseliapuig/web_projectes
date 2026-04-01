<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

session_start();

function isValidUuid(string $uuid): bool
{
    return (bool) preg_match(
        '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
        $uuid
    );
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    http_response_code(400);
    exit('Falta el token d’accés.');
}

if (!isValidUuid($token)) {
    http_response_code(400);
    exit('Token no vàlid.');
}

$sql = "
    SELECT id_proyecto, uuid, nombre
    FROM proyectos
    WHERE uuid = :uuid
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['uuid' => $token]);
$projecte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$projecte) {
    http_response_code(404);
    exit('No s’ha trobat cap projecte per aquest enllaç.');
}

$_SESSION['auth_tipo'] = 'alumne';
$_SESSION['projecte_id'] = (int) $projecte['id_proyecto'];
$_SESSION['projecte_uuid'] = (string) $projecte['uuid'];
$_SESSION['projecte_nom'] = (string) $projecte['nombre'];
$_SESSION['last_access'] = time();

header('Location: /projecte/' . (int) $projecte['id_proyecto'] . '/editar');
exit;