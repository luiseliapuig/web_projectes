<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección fija al listado de familias.
$redirigirFamilies = static function (string $sufijo = ''): never {
    $url = '/index.php?main=families-cicles' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

// Validación general de petición, token y modo.
$modo = isset($_POST['modo']) && is_string($_POST['modo']) ? $_POST['modo'] : '';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || !in_array($modo, ['new', 'edit', 'delete'], true)
) {
    $_SESSION['families_cicles_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirFamilies();
}

$id = isset($_POST['id_familia_ciclo']) ? (int) $_POST['id_familia_ciclo'] : 0;

// Una familia con ciclos forma parte del modelo y no puede borrarse.
if ($modo === 'delete') {
    if ($id <= 0) {
        $_SESSION['families_cicles_error'] = 'Família no vàlida.';
        $redirigirFamilies();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app.ciclos WHERE familia_ciclo_id = :id");
    $stmt->execute([':id' => $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $_SESSION['families_cicles_error'] = 'No es pot borrar una família amb cicles associats. Pots desactivar-la.';
        $redirigirFamilies();
    }

    $stmt = $pdo->prepare("DELETE FROM app.familias_ciclos WHERE id_familia_ciclo = :id");
    $stmt->execute([':id' => $id]);
    $redirigirFamilies('&msg=' . urlencode('Família borrada correctament.'));
}

// Validación y persistencia de los campos editables.
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
$activo = isset($_POST['activo']);

if (
    $nombre === ''
    || mb_strlen($nombre) > 120
    || $orden < 1
    || $orden > 32767
    || ($modo === 'edit' && $id <= 0)
) {
    $_SESSION['families_cicles_error'] = 'Revisa els camps obligatoris de la família.';
    $redirigirFamilies();
}

try {
    if ($modo === 'edit') {
        $stmt = $pdo->prepare("
            UPDATE app.familias_ciclos
            SET nombre = :nombre, activo = :activo, orden = :orden
            WHERE id_familia_ciclo = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':activo' => $activo,
            ':orden' => $orden,
            ':id' => $id,
        ]);
        $msg = 'Família actualitzada correctament.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.familias_ciclos (nombre, activo, orden)
            VALUES (:nombre, :activo, :orden)
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':activo' => $activo,
            ':orden' => $orden,
        ]);
        $msg = 'Família creada correctament.';
    }
} catch (PDOException) {
    $_SESSION['families_cicles_error'] = 'No s’han pogut guardar les dades. Comprova que el nom no estigui repetit.';
    $redirigirFamilies();
}

$redirigirFamilies('&msg=' . urlencode($msg));
