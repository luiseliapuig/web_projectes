<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección fija al listado sin exponer detalles internos.
$redirigirCicles = static function (string $sufijo = ''): never {
    $url = '/index.php?main=cicles' . $sufijo;
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
    $_SESSION['cicles_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirCicles();
}

$id = isset($_POST['id_ciclo']) ? (int) $_POST['id_ciclo'] : 0;

// El borrado solo se permite cuando el ciclo no tiene grupos relacionados.
if ($modo === 'delete') {
    if ($id <= 0) {
        $_SESSION['cicles_error'] = 'Cicle no vàlid.';
        $redirigirCicles();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app.grupos WHERE id_ciclo = :id");
    $stmt->execute([':id' => $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $_SESSION['cicles_error'] = 'No es pot borrar un cicle que té grups associats. Pots desactivar-lo.';
        $redirigirCicles();
    }

    $stmt = $pdo->prepare("DELETE FROM app.ciclos WHERE id_ciclo = :id");
    $stmt->execute([':id' => $id]);
    $redirigirCicles('&msg=' . urlencode('Cicle borrat correctament.'));
}

// Validación de campos y valores controlados.
$abr = isset($_POST['abr']) && is_string($_POST['abr']) ? strtoupper(trim($_POST['abr'])) : '';
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$color = isset($_POST['color']) && is_string($_POST['color']) ? $_POST['color'] : '';
$orden = isset($_POST['orden']) ? (int) $_POST['orden'] : 0;
$familiaCicloId = isset($_POST['familia_ciclo_id']) ? (int) $_POST['familia_ciclo_id'] : 0;
$activo = isset($_POST['activo']);

// La familia debe estar activa, salvo la familia ya vinculada al ciclo editado.
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM app.familias_ciclos f
    WHERE f.id_familia_ciclo = :familia_id
      AND (
            f.activo = true
            OR EXISTS (
                SELECT 1 FROM app.ciclos c
                WHERE c.id_ciclo = :ciclo_id
                  AND c.familia_ciclo_id = f.id_familia_ciclo
            )
      )
");
$stmt->execute([':familia_id' => $familiaCicloId, ':ciclo_id' => $id]);
$familiaPermitida = (int) $stmt->fetchColumn() === 1;

if (
    $abr === ''
    || mb_strlen($abr) > 10
    || $nombre === ''
    || mb_strlen($nombre) > 120
    || !in_array($color, coloresCicloPermitidos(), true)
    || $orden < 1
    || $orden > 32767
    || !$familiaPermitida
    || ($modo === 'edit' && $id <= 0)
) {
    $_SESSION['cicles_error'] = 'Revisa els camps obligatoris del cicle.';
    $redirigirCicles();
}

// Alta o edición mediante consultas preparadas.
try {
    if ($modo === 'edit') {
        $stmt = $pdo->prepare("
            UPDATE app.ciclos
            SET abr = :abr, nombre = :nombre, activo = :activo,
                color = :color, orden = :orden,
                familia_ciclo_id = :familia_ciclo_id
            WHERE id_ciclo = :id
        ");
        $stmt->execute([
            ':abr' => $abr,
            ':nombre' => $nombre,
            ':activo' => $activo,
            ':color' => $color,
            ':orden' => $orden,
            ':familia_ciclo_id' => $familiaCicloId,
            ':id' => $id,
        ]);
        $msg = 'Cicle actualitzat correctament.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.ciclos (abr, nombre, activo, color, orden, familia_ciclo_id)
            VALUES (:abr, :nombre, :activo, :color, :orden, :familia_ciclo_id)
        ");
        $stmt->execute([
            ':abr' => $abr,
            ':nombre' => $nombre,
            ':activo' => $activo,
            ':color' => $color,
            ':orden' => $orden,
            ':familia_ciclo_id' => $familiaCicloId,
        ]);
        $msg = 'Cicle creat correctament.';
    }
} catch (PDOException) {
    $_SESSION['cicles_error'] = 'No s’han pogut guardar les dades. Comprova que l’abreviatura no estigui repetida.';
    $redirigirCicles();
}

$redirigirCicles('&msg=' . urlencode($msg));
