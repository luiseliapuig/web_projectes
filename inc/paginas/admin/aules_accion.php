<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección interna compatible con el layout renderizado por index.php.
$redirigirAules = static function (): never {
    $url = '/index.php?main=aules';
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

// La acción solo acepta formularios POST con un token válido.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    $redirigirAules();
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'borrar') {
    $id = isset($_POST['id_aula']) ? (int)$_POST['id_aula'] : 0;

    if ($id <= 0) {
        die('ID no vàlid');
    }

    $stmt = $pdo->prepare("
        DELETE FROM app.aulas
        WHERE id_aula = :id
    ");
    $stmt->execute(['id' => $id]);

    $redirigirAules();
}

if ($accion !== 'guardar') {
    die('Acció no permesa');
}

$id = isset($_POST['id_aula']) ? (int)$_POST['id_aula'] : 0;

$codigo = trim($_POST['codigo'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$piso = trim($_POST['piso'] ?? '');

if ($codigo === '' ) {
    die('Falten camps obligatoris');
}

if ($id > 0) {
    $stmt = $pdo->prepare("
        UPDATE app.aulas
        SET
            codigo = :codigo,
            nombre = :nombre,
            piso = :piso
        WHERE id_aula = :id
    ");

    $stmt->execute([
        'codigo' => $codigo,
        'nombre' => $nombre,
        'piso' => $piso !== '' ? $piso : null,
        'id' => $id,
    ]);
} else {
    $stmt = $pdo->prepare("
        INSERT INTO app.aulas (
            codigo,
            nombre,
            piso
        ) VALUES (
            :codigo,
            :nombre,
            :piso
        )
    ");

    $stmt->execute([
        'codigo' => $codigo,
        'nombre' => $nombre,
        'piso' => $piso !== '' ? $piso : null,
    ]);
}

$redirigirAules();
