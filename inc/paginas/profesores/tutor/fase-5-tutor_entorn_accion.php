<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/fases/funciones.php';

function fase5TutorEntornResposta(int $codi, string $missatge): never
{
    http_response_code($codi);
    echo json_encode(['ok' => false, 'missatge' => $missatge]);
    exit;
}

if (!esProfesor()) fase5TutorEntornResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase5TutorEntornResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$id = (int) ($_POST['proyecto_id'] ?? 0);
$accio = is_string($_POST['accio'] ?? null) ? trim($_POST['accio']) : '';
if ($id <= 0 || !esTutorFormalDelProyecto($id)) fase5TutorEntornResposta(403, 'No tens autorització per intervenir en aquest projecte.');
$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');
$stmt->execute([':id' => $id]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica')) fase5TutorEntornResposta(403, 'Accés no permès.');

if ($accio === 'tancar_solicitud') {
    try {
        $stmt = $pdo->prepare("UPDATE app.revisiones_solicitudes rs SET resuelto_en=NOW() WHERE rs.proyecto_id=:id AND rs.tipo='entorn_desenvolupament' AND rs.resuelto_en IS NULL AND EXISTS (SELECT 1 FROM app.proyectos p WHERE p.id_proyecto=rs.proyecto_id AND p.entorno_desarrollo_validado_en IS NULL)");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) fase5TutorEntornResposta(409, 'La sol·licitud ja no està oberta.');
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('Error tancant la sol·licitud de revisió de la preparació de l’entorn: ' . $e->getMessage());
        fase5TutorEntornResposta(500, 'No s’ha pogut tancar la sol·licitud.');
    }
    exit;
}

if ($accio !== 'validar') fase5TutorEntornResposta(422, 'Acció no reconeguda.');
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE app.proyectos SET entorno_desarrollo_validado_en=NOW() WHERE id_proyecto=:id AND entorno_desarrollo_url IS NOT NULL AND BTRIM(entorno_desarrollo_url)<>'' AND entorno_desarrollo_validado_en IS NULL");
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        fase5TutorEntornResposta(409, 'El document no es pot validar en l’estat actual.');
    }
    $pdo->prepare("UPDATE app.revisiones_solicitudes SET resuelto_en=NOW() WHERE proyecto_id=:id AND tipo='entorn_desenvolupament' AND resuelto_en IS NULL")->execute([':id' => $id]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    fase5TutorEntornResposta(500, 'No s’ha pogut validar el document.');
}
echo json_encode(['ok' => true]);
