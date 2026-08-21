<?php
declare(strict_types=1);

soloSuperadmin();
$redirect = static function (string $suffix = ''): never {
    $url = '/index.php?main=professorat' . $suffix;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['professorat_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirect();
}
$id = isset($_POST['id_profesor']) ? (int) $_POST['id_profesor'] : 0;
if ($id <= 0) {
    $_SESSION['professorat_error'] = 'Professor no vàlid.';
    $redirect();
}
try {
    require_once dirname(__DIR__, 2) . '/email/bootstrap.php';
    (new ProfessorInvitation($pdo, new EmailService(EmailConfig::fromEnvironment())))->send($id);
    $redirect('&msg=invitacio-enviada');
} catch (Throwable $e) {
    error_log('No se pudo reenviar la invitación al profesor #' . $id . ': ' . $e->getMessage());
    $_SESSION['professorat_error'] = 'No s’ha pogut enviar la invitació. Revisa que el professor estiga actiu i la configuració SMTP.';
    $redirect();
}
