<?php
declare(strict_types=1);

soloSuperadmin();
$redirect = static function (): never {
    $url = '/index.php?main=autoseguiment-control';
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
$periodo = isset($_POST['periodo']) && is_string($_POST['periodo']) ? $_POST['periodo'] : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || !in_array($periodo, ['actual', 'siguiente'], true)) {
    $_SESSION['autoseguiment_control_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirect();
}
require_once dirname(__DIR__, 2) . '/seguimiento/funciones.php';
try {
    $_SESSION['autoseguiment_control_resultat'] = seguimientoReconciliarPeriodoCanonico($pdo, 'manual', $periodo);
} catch (Throwable $e) {
    error_log('Error en la reconciliació manual d’autoseguiment: ' . $e->getMessage());
    $_SESSION['autoseguiment_control_error'] = 'No s’ha pogut completar la comprovació. Consulta l’última execució registrada.';
}
$redirect();
