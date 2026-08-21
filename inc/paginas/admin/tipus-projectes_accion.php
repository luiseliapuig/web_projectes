<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección fija y validación común del CRUD.
$redirigirTipus = static function (string $sufijo = ''): never {
    $url = '/index.php?main=tipus-projectes' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
$modo = isset($_POST['modo']) && is_string($_POST['modo']) ? $_POST['modo'] : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null) || !in_array($modo, ['new', 'edit', 'delete'], true)) {
    $_SESSION['tipus_projectes_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirTipus();
}
$id = isset($_POST['id_tipo_proyecto']) ? (int) $_POST['id_tipo_proyecto'] : 0;
if ($modo === 'delete') {
    if ($id <= 0) {
        $_SESSION['tipus_projectes_error'] = 'Tipus no vàlid.';
        $redirigirTipus();
    }
    $stmt = $pdo->prepare("DELETE FROM app.tipos_proyectos WHERE id_tipo_proyecto = :id");
    $stmt->execute([':id' => $id]);
    $redirigirTipus('&msg=' . urlencode('Tipus borrat correctament.'));
}

$familiaId = isset($_POST['familia_ciclo_id']) ? (int) $_POST['familia_ciclo_id'] : 0;
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
$activo = isset($_POST['activo']);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM app.familias_ciclos WHERE id_familia_ciclo = :id AND (activo = true OR id_familia_ciclo = (SELECT familia_ciclo_id FROM app.tipos_proyectos WHERE id_tipo_proyecto = :tipo_id))");
$stmt->execute([':id' => $familiaId, ':tipo_id' => $id]);
$familiaPermitida = (int) $stmt->fetchColumn() === 1;
if ($nombre === '' || mb_strlen($nombre) > 120 || $orden < 1 || $orden > 32767 || !$familiaPermitida || ($modo === 'edit' && $id <= 0)) {
    $_SESSION['tipus_projectes_error'] = 'Revisa els camps obligatoris del tipus.';
    $redirigirTipus();
}

try {
    if ($modo === 'edit') {
        $stmt = $pdo->prepare("UPDATE app.tipos_proyectos SET familia_ciclo_id=:familia, nombre=:nombre, activo=:activo, orden=:orden WHERE id_tipo_proyecto=:id");
        $stmt->execute([':familia' => $familiaId, ':nombre' => $nombre, ':activo' => $activo, ':orden' => $orden, ':id' => $id]);
        $msg = 'Tipus actualitzat correctament.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO app.tipos_proyectos (familia_ciclo_id, nombre, activo, orden) VALUES (:familia, :nombre, :activo, :orden)");
        $stmt->execute([':familia' => $familiaId, ':nombre' => $nombre, ':activo' => $activo, ':orden' => $orden]);
        $msg = 'Tipus creat correctament.';
    }
} catch (PDOException) {
    $_SESSION['tipus_projectes_error'] = 'No s’han pogut guardar les dades. Comprova que el nom no estigui repetit dins la família.';
    $redirigirTipus();
}
$redirigirTipus('&msg=' . urlencode($msg));
