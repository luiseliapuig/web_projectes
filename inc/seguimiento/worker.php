<?php
declare(strict_types=1);

// Generador semanal idempotente. Se conserva esta ruta de invocación CLI.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = require dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__) . '/funciones.php';
require_once __DIR__ . '/funciones.php';

try {
    $resultado = seguimientoReconciliarPeriodoActual($pdo, 'cron');
} catch (Throwable $e) {
    fwrite(STDERR, 'Error generant la setmana d’autoseguiment: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Període {$resultado['fecha_inicio']} - {$resultado['fecha_fin']}: "
    . "{$resultado['creados']} creats, {$resultado['ya_existentes']} ja existents i "
    . "{$resultado['errores']} errors de {$resultado['candidatos']} candidats "
    . "(execució {$resultado['numero_ejecucion']})." . PHP_EOL;
exit($resultado['errores'] > 0 ? 1 : 0);
