<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = require dirname(__DIR__, 2) . '/config/db.php';
require_once __DIR__ . '/bootstrap.php';

$config = EmailConfig::fromEnvironment();
if (!$config->isReady()) {
    fwrite(STDERR, 'Configuración de correo incompleta: ' . implode(', ', $config->validationErrors()) . PHP_EOL);
    exit(2);
}

$queue = new EmailQueue($pdo);
$service = new EmailService($config);
$messages = $queue->claimBatch($config->batchSize);
$sent = 0;
$failed = 0;

foreach ($messages as $message) {
    try {
        $service->send($message);
        $queue->markSent((int) $message['id_email']);
        $sent++;
    } catch (Throwable $e) {
        $queue->markFailed(
            (int) $message['id_email'],
            (int) $message['intentos'] + 1,
            (int) $message['max_intentos'],
            $e->getMessage()
        );
        error_log('Email #' . (int) $message['id_email'] . ' no enviado: ' . $e->getMessage());
        $failed++;
    }
}

echo "Procesados: " . count($messages) . "; enviados: $sent; fallidos: $failed" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
