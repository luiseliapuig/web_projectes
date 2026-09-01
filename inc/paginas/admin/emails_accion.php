<?php
declare(strict_types=1);

soloSuperadmin();

$redirect = static function (string $suffix = ''): never {
    $url = '/index.php?main=emails' . $suffix;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['emails_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirect();
}

// Botó "Enviar cua": processa manualment un lot pendent, exactament igual
// que ho fa el worker CLI (mateixa funció compartida, cap lògica nova).
if (isset($_POST['accio']) && $_POST['accio'] === 'enviar_cola') {
    require_once dirname(__DIR__, 2) . '/email/bootstrap.php';
    require_once dirname(__DIR__, 2) . '/email/procesar_cola.php';

    $config = EmailConfig::fromEnvironment();
    if (!$config->isReady()) {
        $_SESSION['emails_error'] = 'El servei de correu no està configurat: falten ' . implode(', ', $config->validationErrors()) . '.';
        $redirect();
    }

    $resultado = emailProcesarColaPendent(new EmailQueue($pdo), new EmailService($config), $config->batchSize);
    $pendientes = (int) $pdo->query("SELECT COUNT(*) FROM app.email_outbox WHERE estado = 'pendiente'")->fetchColumn();

    $redirect('&msg=cua_processada&enviats=' . $resultado['enviados'] . '&fallits=' . $resultado['fallidos'] . '&pendents=' . $pendientes);
}

$destinatario = isset($_POST['destinatario']) && is_string($_POST['destinatario']) ? strtolower(trim($_POST['destinatario'])) : '';
$asunto = isset($_POST['asunto']) && is_string($_POST['asunto']) ? trim($_POST['asunto']) : '';
$contenido = isset($_POST['contenido']) && is_string($_POST['contenido']) ? trim($_POST['contenido']) : '';
if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL) || mb_strlen($destinatario) > 320 || $asunto === '' || mb_strlen($asunto) > 255 || $contenido === '' || mb_strlen($contenido) > 10000) {
    $_SESSION['emails_error'] = 'Revisa el destinatari, l’assumpte i el contingut.';
    $redirect();
}

require_once dirname(__DIR__, 2) . '/email/bootstrap.php';

try {
    $queue = new EmailQueue($pdo);
    $queue->enqueue([
        'destinatario' => $destinatario,
        'asunto' => $asunto,
        'cuerpo_html' => '<div style="font-family:Arial,sans-serif;line-height:1.5">' . nl2br(htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8')) . '</div>',
        'cuerpo_texto' => $contenido,
        'tipo' => 'manual_admin',
        'creado_por' => (int) ($_SESSION['professor_id'] ?? 0),
    ]);
    $redirect('&msg=encolat');
} catch (Throwable $e) {
    error_log('No se pudo encolar el email: ' . $e->getMessage());
    $_SESSION['emails_error'] = 'No s’ha pogut afegir el missatge a la cua.';
    $redirect();
}
