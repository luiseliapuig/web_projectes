<?php soloSuperadmin();

declare(strict_types=1);

$id = isset($_POST['id_profesor']) ? (int)$_POST['id_profesor'] : 0;

$nombre = trim($_POST['nombre'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$email = trim($_POST['email'] ?? '');
$departamento = trim($_POST['departamento'] ?? '');
$activo = isset($_POST['activo']) ? 1 : 0;
$rol = isset($_POST['superadmin']) ? 'superadmin' : null;

if ($nombre === '' || $apellidos === '' || $email === '') {
    die('Falten camps obligatoris');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Email no vàlid');
}

if ($id > 0) {
    $sql = "
        UPDATE app.profesores
        SET
            nombre = :nombre,
            apellidos = :apellidos,
            email = :email,
            departamento = :departamento,
            activo = :activo,
            rol = :rol
        WHERE id_profesor = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'nombre' => $nombre,
        'apellidos' => $apellidos,
        'email' => $email,
        'departamento' => $departamento !== '' ? $departamento : null,
        'activo' => $activo,
        'rol' => $rol,
        'id' => $id,
    ]);
} else {
    $sql = "
        INSERT INTO app.profesores (
            nombre,
            apellidos,
            email,
            departamento,
            activo,
            uuid_acceso,
            rol
        ) VALUES (
            :nombre,
            :apellidos,
            :email,
            :departamento,
            :activo,
            gen_random_uuid(),
            :rol
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'nombre' => $nombre,
        'apellidos' => $apellidos,
        'email' => $email,
        'departamento' => $departamento !== '' ? $departamento : null,
        'activo' => $activo,
        'rol' => $rol,
    ]);
}

header('Location: index.php?main=profesor');
exit;