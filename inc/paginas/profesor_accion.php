<?php soloSuperadmin();

$id    = isset($_POST['id_profesor']) ? (int)$_POST['id_profesor'] : 0;
$accio = trim($_POST['accio'] ?? '');

// ── ELIMINAR ──────────────────────────────────────────────────────────────────
if ($accio === 'eliminar') {
    if ($id <= 0) {
        die('ID no vàlid');
    }

    $errors = [];

    // 1. Projectes on és tutor
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM app.proyectos WHERE tutor_id = :id
    ");
    $stmt->execute(['id' => $id]);
    $n = (int)$stmt->fetchColumn();
    if ($n > 0) {
        $errors[] = "És tutor de {$n} projecte(s).";
    }

    // 2. Avaluacions de tribunal
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM app.evaluacion_tribunal WHERE profesor_id = :id
    ");
    $stmt->execute(['id' => $id]);
    $n = (int)$stmt->fetchColumn();
    if ($n > 0) {
        $errors[] = "Té {$n} avaluació(ns) de tribunal registrades.";
    }

    // 3. Membre de tribunals assignats
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE profesor_id = :id
    ");
    $stmt->execute(['id' => $id]);
    $n = (int)$stmt->fetchColumn();
    if ($n > 0) {
        $errors[] = "Pertany al tribunal de {$n} projecte(s).";
    }

    // 4. Ajustos de nota individuals creats per ell
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM app.ajustes_nota_individual WHERE creado_por_profesor_id = :id
    ");
    $stmt->execute(['id' => $id]);
    $n = (int)$stmt->fetchColumn();
    if ($n > 0) {
        $errors[] = "Ha creat {$n} ajust(os) de nota individual.";
    }

    if ($errors) {
        $stmt = $pdo->prepare("SELECT nombre, apellidos FROM app.profesores WHERE id_profesor = :id");
        $stmt->execute(['id' => $id]);
        $prof = $stmt->fetch(PDO::FETCH_ASSOC);
        $nomProf = $prof ? ($prof['nombre'] . ' ' . $prof['apellidos']) : "ID {$id}";

        $llistat = implode('', array_map(fn($e) => "<li>{$e}</li>", $errors));
        $msg = urlencode("No es pot eliminar <strong>{$nomProf}</strong> perquè té dades associades:<ul class=\"mb-0 mt-1\">{$llistat}</ul>");
        header("Location: index.php?main=profesor&error={$msg}");
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM app.profesores WHERE id_profesor = :id");
    $stmt->execute(['id' => $id]);

    header('Location: index.php?main=profesor&msg=eliminat');
    exit;
}

// ── CREAR / EDITAR ────────────────────────────────────────────────────────────
$nombre       = trim($_POST['nombre']       ?? '');
$apellidos    = trim($_POST['apellidos']    ?? '');
$email        = trim($_POST['email']        ?? '');
$departamento = trim($_POST['departamento'] ?? '');
$activo       = isset($_POST['activo'])     ? 1 : 0;
$rol          = isset($_POST['superadmin']) ? 'superadmin' : null;

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
            nombre        = :nombre,
            apellidos     = :apellidos,
            email         = :email,
            departamento  = :departamento,
            activo        = :activo,
            rol           = :rol
        WHERE id_profesor = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'nombre'       => $nombre,
        'apellidos'    => $apellidos,
        'email'        => $email,
        'departamento' => $departamento !== '' ? $departamento : null,
        'activo'       => $activo,
        'rol'          => $rol,
        'id'           => $id,
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
        'nombre'       => $nombre,
        'apellidos'    => $apellidos,
        'email'        => $email,
        'departamento' => $departamento !== '' ? $departamento : null,
        'activo'       => $activo,
        'rol'          => $rol,
    ]);
}

header('Location: index.php?main=profesor');
exit;
