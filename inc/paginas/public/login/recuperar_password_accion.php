<?php
declare(strict_types=1);

$redirect = static function (string $suffix = ''): never {
    $url = '/recuperar-contrasenya' . $suffix;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) $redirect('?msg=invalid');
$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) $redirect('?msg=invalid');

$inicioSolicitud = microtime(true);
$tokenHashCreado = null;
$resetTable = null;
try {
    // La recuperación sigue la misma precedencia que el login.
    $stmt = $pdo->prepare("SELECT id_profesor AS id, nombre, apellidos, email FROM app.profesores WHERE lower(email) = :email AND activo = true LIMIT 1");
    $stmt->execute([':email' => $email]);
    $actor = $stmt->fetch(PDO::FETCH_ASSOC);
    $actorTipo = $actor ? 'professor' : '';
    if (!$actor) {
        $stmt = $pdo->prepare("SELECT id_alumno AS id, nombre, apellidos, email FROM app.alumnos WHERE lower(email) = :email AND activo = true LIMIT 1");
        $stmt->execute([':email' => $email]);
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        $actorTipo = $actor ? 'alumne' : '';
    }

    if ($actor) {
        $resetTable = $actorTipo === 'professor' ? 'profesor_password_reset' : 'alumno_password_reset';
        $actorColumn = $actorTipo === 'professor' ? 'profesor_id' : 'alumno_id';
        $cooldown = $pdo->prepare("SELECT 1 FROM app.$resetTable WHERE $actorColumn = :id AND solicitado_en > CURRENT_TIMESTAMP - INTERVAL '2 minutes' LIMIT 1");
        $cooldown->execute([':id' => (int) $actor['id']]);
        if (!$cooldown->fetchColumn()) {
            $token = bin2hex(random_bytes(32));
            $tokenHashCreado = hash('sha256', $token);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE app.$resetTable SET usado_en = CURRENT_TIMESTAMP WHERE $actorColumn = :id AND usado_en IS NULL")
                ->execute([':id' => (int) $actor['id']]);
            $pdo->prepare("INSERT INTO app.$resetTable ($actorColumn, token_hash, expira_en) VALUES (:id, :hash, CURRENT_TIMESTAMP + INTERVAL '30 minutes')")
                ->execute([':id' => (int) $actor['id'], ':hash' => $tokenHashCreado]);
            $pdo->commit();

            require_once dirname(__DIR__, 3) . '/email/bootstrap.php';
            require_once dirname(__DIR__, 3) . '/email/templates/password_reset.php';
            $baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
            if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) throw new RuntimeException('APP_URL debe ser una URL HTTPS válida.');
            $resetUrl = $baseUrl . '/restablir-contrasenya?token=' . rawurlencode($token);
            $nombre = trim((string) $actor['nombre'] . ' ' . (string) $actor['apellidos']);
            $body = emailPasswordReset($nombre, $resetUrl, 30);
            (new EmailService(EmailConfig::fromEnvironment()))->send([
                'destinatario' => (string) $actor['email'],
                'nombre_destinatario' => $nombre,
                'asunto' => 'Restableix la contrasenya · Web Projectes',
                'cuerpo_html' => $body['html'],
                'cuerpo_texto' => $body['text'],
            ]);
        }
    } else {
        password_verify('dummy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($tokenHashCreado !== null && $resetTable !== null) {
        try {
            $pdo->prepare("DELETE FROM app.$resetTable WHERE token_hash = :hash")->execute([':hash' => $tokenHashCreado]);
        } catch (Throwable) {
        }
    }
    error_log('Error en recuperación de contraseña: ' . $e->getMessage());
}
$esperaRestante = 1.2 - (microtime(true) - $inicioSolicitud);
if ($esperaRestante > 0) usleep((int) ($esperaRestante * 1_000_000));
$redirect('?msg=sent');
