<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = require dirname(__DIR__, 2) . '/config/db.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/procesar_cola.php';

$config = EmailConfig::fromEnvironment();
if (!$config->isReady()) {
    fwrite(STDERR, 'Configuración de correo incompleta: ' . implode(', ', $config->validationErrors()) . PHP_EOL);
    exit(2);
}

$resultado = emailProcesarColaPendent(new EmailQueue($pdo), new EmailService($config), $config->batchSize);

echo "Procesados: {$resultado['procesados']}; enviados: {$resultado['enviados']}; fallidos: {$resultado['fallidos']}" . PHP_EOL;
exit($resultado['fallidos'] > 0 ? 1 : 0);
