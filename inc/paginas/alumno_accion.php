<?php soloSuperadmin();

declare(strict_types=1);

$accion = $_POST['accion'] ?? '';

if ($accion === 'borrar') {
    $id = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;

    if ($id <= 0) {
        die('ID no vàlid');
    }

    $stmt = $pdo->prepare("
        DELETE FROM app.alumnos
        WHERE id_alumno = :id
    ");
    $stmt->execute(['id' => $id]);

    header('Location: index.php?main=alumno');
    exit;
}

if ($accion !== 'guardar') {
    die('Acció no permesa');
}

$id = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;

$nombre = trim($_POST['nombre'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$email = trim($_POST['email'] ?? '');
$ciclo = trim($_POST['ciclo'] ?? '');
$grupo = trim($_POST['grupo'] ?? '');
$cursoAcademico = trim($_POST['curso_academico'] ?? '');
$activo = isset($_POST['activo']) ? 1 : 0;

$ciclosValidos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
$gruposValidos = ['A', 'B', 'C' , 'D'];

if ($nombre === '' || $apellidos === '' || $email === '' || $ciclo === '' || $grupo === '' || $cursoAcademico === '') {
    die('Falten camps obligatoris');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Email no vàlid');
}

if (!in_array($ciclo, $ciclosValidos, true)) {
    die('Cicle no vàlid');
}

if (!in_array($grupo, $gruposValidos, true)) {
    die('Grup no vàlid');
}

if (!preg_match('/^\d{4}-\d{2}$/', $cursoAcademico)) {
    die('Format de curs acadèmic no vàlid');
}

if ($id > 0) {
    $stmt = $pdo->prepare("
        UPDATE app.alumnos
        SET
            nombre = :nombre,
            apellidos = :apellidos,
            email = :email,
            ciclo = :ciclo,
            grupo = :grupo,
            curso_academico = :curso_academico,
            activo = :activo
        WHERE id_alumno = :id
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'apellidos' => $apellidos,
        'email' => $email,
        'ciclo' => $ciclo,
        'grupo' => $grupo,
        'curso_academico' => $cursoAcademico,
        'activo' => $activo,
        'id' => $id,
    ]);
} else {
    $stmt = $pdo->prepare("
        INSERT INTO app.alumnos (
            nombre,
            apellidos,
            email,
            ciclo,
            grupo,
            curso_academico,
            activo
        ) VALUES (
            :nombre,
            :apellidos,
            :email,
            :ciclo,
            :grupo,
            :curso_academico,
            :activo
        )
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'apellidos' => $apellidos,
        'email' => $email,
        'ciclo' => $ciclo,
        'grupo' => $grupo,
        'curso_academico' => $cursoAcademico,
        'activo' => $activo,
    ]);
}

header('Location: index.php?main=alumno');
exit;