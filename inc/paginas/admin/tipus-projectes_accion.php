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

// Un tipus amb projectes vinculats forma part del model i no es pot borrar;
// només es pot desactivar.
if ($modo === 'delete') {
    if ($id <= 0) {
        $_SESSION['tipus_projectes_error'] = 'Tipus no vàlid.';
        $redirigirTipus();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app.proyectos WHERE tipo_proyecto_id = :id");
    $stmt->execute([':id' => $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $_SESSION['tipus_projectes_error'] = 'No es pot borrar un tipus amb projectes associats. Pots desactivar-lo.';
        $redirigirTipus();
    }

    $stmt = $pdo->prepare("DELETE FROM app.proyecto_tipos WHERE id_tipo_proyecto = :id");
    $stmt->execute([':id' => $id]);
    $redirigirTipus('&msg=' . urlencode('Tipus borrat correctament.'));
}

// El tipo ya no pertenece directamente a una familia: la familia se obtiene
// siempre mediante tipo -> categoria -> familia, nunca se guarda aquí.
$categoriaId = isset($_POST['categoria_proyecto_id']) ? (int) $_POST['categoria_proyecto_id'] : 0;
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
$activo = isset($_POST['activo']);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM app.proyecto_categorias cp WHERE cp.id_categoria_proyecto = :id AND (cp.activo = true OR cp.id_categoria_proyecto = (SELECT categoria_proyecto_id FROM app.proyecto_tipos WHERE id_tipo_proyecto = :tipo_id))");
$stmt->execute([':id' => $categoriaId, ':tipo_id' => $id]);
$categoriaPermitida = (int) $stmt->fetchColumn() === 1;
if ($nombre === '' || mb_strlen($nombre) > 120 || $orden < 1 || $orden > 32767 || !$categoriaPermitida || ($modo === 'edit' && $id <= 0)) {
    $_SESSION['tipus_projectes_error'] = 'Revisa els camps obligatoris del tipus.';
    $redirigirTipus();
}

try {
    if ($modo === 'edit') {
        $stmt = $pdo->prepare("UPDATE app.proyecto_tipos SET categoria_proyecto_id=:categoria, nombre=:nombre, activo=:activo, orden=:orden WHERE id_tipo_proyecto=:id");
        $stmt->execute([':categoria' => $categoriaId, ':nombre' => $nombre, ':activo' => $activo, ':orden' => $orden, ':id' => $id]);
        $msg = 'Tipus actualitzat correctament.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO app.proyecto_tipos (categoria_proyecto_id, nombre, activo, orden) VALUES (:categoria, :nombre, :activo, :orden)");
        $stmt->execute([':categoria' => $categoriaId, ':nombre' => $nombre, ':activo' => $activo, ':orden' => $orden]);
        $msg = 'Tipus creat correctament.';
    }
} catch (PDOException) {
    $_SESSION['tipus_projectes_error'] = 'No s’han pogut guardar les dades del tipus.';
    $redirigirTipus();
}
$redirigirTipus('&msg=' . urlencode($msg));
