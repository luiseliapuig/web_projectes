<?php soloSuperadmin();



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

    header('Location: index.php?main=aula');
    exit;
}

if ($accion !== 'guardar') {
    die('Acció no permesa');
}

$id = isset($_POST['id_aula']) ? (int)$_POST['id_aula'] : 0;

$codigo = trim($_POST['codigo'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$piso = trim($_POST['piso'] ?? '');

if ($codigo === '' || $nombre === '') {
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

header('Location: index.php?main=aula');
exit;