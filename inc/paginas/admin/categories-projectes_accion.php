<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección fija y validación común del CRUD.
$redirigirCategories = static function (string $sufijo = ''): never {
    $url = '/index.php?main=categories-projectes' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
$modo = isset($_POST['modo']) && is_string($_POST['modo']) ? $_POST['modo'] : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null) || !in_array($modo, ['new', 'edit', 'delete'], true)) {
    $_SESSION['categories_projectes_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirCategories();
}
$id = isset($_POST['id_categoria_proyecto']) ? (int) $_POST['id_categoria_proyecto'] : 0;

// Una categoria amb tipus o projectes vinculats forma part del model i no es
// pot borrar; només es pot desactivar.
if ($modo === 'delete') {
    if ($id <= 0) {
        $_SESSION['categories_projectes_error'] = 'Categoria no vàlida.';
        $redirigirCategories();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app.proyecto_tipos WHERE categoria_proyecto_id = :id");
    $stmt->execute([':id' => $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $_SESSION['categories_projectes_error'] = 'No es pot borrar una categoria amb tipus associats. Pots desactivar-la.';
        $redirigirCategories();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app.proyectos WHERE categoria_proyecto_id = :id");
    $stmt->execute([':id' => $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $_SESSION['categories_projectes_error'] = 'No es pot borrar una categoria amb projectes associats. Pots desactivar-la.';
        $redirigirCategories();
    }

    $stmt = $pdo->prepare("DELETE FROM app.proyecto_categorias WHERE id_categoria_proyecto = :id");
    $stmt->execute([':id' => $id]);
    $redirigirCategories('&msg=' . urlencode('Categoria borrada correctament.'));
}

$familiaId = isset($_POST['familia_ciclo_id']) ? (int) $_POST['familia_ciclo_id'] : 0;
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
$activo = isset($_POST['activo']);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM app.familias_ciclos WHERE id_familia_ciclo = :id AND (activo = true OR id_familia_ciclo = (SELECT familia_ciclo_id FROM app.proyecto_categorias WHERE id_categoria_proyecto = :categoria_id))");
$stmt->execute([':id' => $familiaId, ':categoria_id' => $id]);
$familiaPermitida = (int) $stmt->fetchColumn() === 1;

if ($nombre === '' || mb_strlen($nombre) > 120 || $orden < 1 || $orden > 32767 || !$familiaPermitida || ($modo === 'edit' && $id <= 0)) {
    $_SESSION['categories_projectes_error'] = 'Revisa els camps obligatoris de la categoria.';
    $redirigirCategories();
}

try {
    if ($modo === 'edit') {
        $stmt = $pdo->prepare("UPDATE app.proyecto_categorias SET familia_ciclo_id=:familia, nombre=:nombre, activo=:activo, orden=:orden WHERE id_categoria_proyecto=:id");
        $stmt->execute([':familia' => $familiaId, ':nombre' => $nombre, ':activo' => $activo, ':orden' => $orden, ':id' => $id]);
        $msg = 'Categoria actualitzada correctament.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO app.proyecto_categorias (familia_ciclo_id, nombre, activo, orden) VALUES (:familia, :nombre, :activo, :orden)");
        $stmt->execute([':familia' => $familiaId, ':nombre' => $nombre, ':activo' => $activo, ':orden' => $orden]);
        $msg = 'Categoria creada correctament.';
    }
} catch (PDOException) {
    $_SESSION['categories_projectes_error'] = 'No s’han pogut guardar les dades. Comprova que el nom no estigui repetit dins la família.';
    $redirigirCategories();
}
$redirigirCategories('&msg=' . urlencode($msg));
