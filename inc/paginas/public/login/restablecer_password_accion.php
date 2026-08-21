<?php
declare(strict_types=1);

$redirect = static function (string $url): never {
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) $redirect('/acces?msg=invalid');
$token = isset($_POST['token']) && is_string($_POST['token']) ? trim($_POST['token']) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$repeat = isset($_POST['password_repeat']) && is_string($_POST['password_repeat']) ? $_POST['password_repeat'] : '';
$returnUrl = '/restablir-contrasenya?token=' . rawurlencode($token);
if (!preg_match('/^[a-f0-9]{64}$/', $token)) $redirect('/acces?msg=invalid');
if (mb_strlen($password) < 10 || mb_strlen($password) > 255) $redirect($returnUrl . '&msg=weak');
if ($password !== $repeat) $redirect($returnUrl . '&msg=mismatch');

try {
    $pdo->beginTransaction();
    $hash = hash('sha256', $token);
    // Igual que en el login, el token docente se comprueba primero.
    $stmt = $pdo->prepare("
        SELECT r.profesor_id AS actor_id
        FROM app.profesor_password_reset r INNER JOIN app.profesores p ON p.id_profesor=r.profesor_id
        WHERE r.token_hash=:hash AND r.usado_en IS NULL AND r.expira_en>CURRENT_TIMESTAMP AND p.activo=true
        LIMIT 1 FOR UPDATE OF r
    ");
    $stmt->execute([':hash' => $hash]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    $actorTipo = $reset ? 'professor' : '';
    if (!$reset) {
        $stmt = $pdo->prepare("
            SELECT r.alumno_id AS actor_id
            FROM app.alumno_password_reset r INNER JOIN app.alumnos a ON a.id_alumno=r.alumno_id
            WHERE r.token_hash=:hash AND r.usado_en IS NULL AND r.expira_en>CURRENT_TIMESTAMP AND a.activo=true
            LIMIT 1 FOR UPDATE OF r
        ");
        $stmt->execute([':hash' => $hash]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        $actorTipo = $reset ? 'alumne' : '';
    }
    if (!$reset) {
        $pdo->rollBack();
        $redirect($returnUrl . '&msg=invalid');
    }
    $tabla = $actorTipo === 'professor' ? 'profesores' : 'alumnos';
    $idColumn = $actorTipo === 'professor' ? 'id_profesor' : 'id_alumno';
    $resetTable = $actorTipo === 'professor' ? 'profesor_password_reset' : 'alumno_password_reset';
    $resetId = $actorTipo === 'professor' ? 'profesor_id' : 'alumno_id';
    $pdo->prepare("UPDATE app.$tabla SET password_hash=:hash WHERE $idColumn=:id")
        ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $reset['actor_id']]);
    $pdo->prepare("UPDATE app.$resetTable SET usado_en=CURRENT_TIMESTAMP WHERE $resetId=:id AND usado_en IS NULL")
        ->execute([':id' => (int) $reset['actor_id']]);
    $pdo->commit();
    $redirect('/acces?msg=password-reset');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error restableciendo contraseña: ' . $e->getMessage());
    $redirect($returnUrl . '&msg=invalid');
}
