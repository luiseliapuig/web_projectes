<?php
declare(strict_types=1);

require_once __DIR__ . '/destinos.php';

$redirect = static function (string $url): never {
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) $redirect('/acces?msg=invalid');
$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
if ($email === '' || $password === '') $redirect('/acces?msg=missing&email=' . urlencode($email));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) $redirect('/acces?msg=invalid');

$emailHash = hash('sha256', $email);
$dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
$registrarFallo = static function (PDO $pdo, string $hash): void {
    $stmt = $pdo->prepare("
        INSERT INTO app.login_intentos (email_hash, intentos, ventana_inicio, bloqueado_hasta)
        VALUES (:hash, 1, CURRENT_TIMESTAMP, NULL)
        ON CONFLICT (email_hash) DO UPDATE SET
            intentos = CASE WHEN login_intentos.ventana_inicio < CURRENT_TIMESTAMP - INTERVAL '15 minutes' THEN 1 ELSE login_intentos.intentos + 1 END,
            ventana_inicio = CASE WHEN login_intentos.ventana_inicio < CURRENT_TIMESTAMP - INTERVAL '15 minutes' THEN CURRENT_TIMESTAMP ELSE login_intentos.ventana_inicio END,
            bloqueado_hasta = CASE WHEN login_intentos.ventana_inicio >= CURRENT_TIMESTAMP - INTERVAL '15 minutes' AND login_intentos.intentos >= 4 THEN CURRENT_TIMESTAMP + INTERVAL '15 minutes' ELSE NULL END,
            actualizado_en = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':hash' => $hash]);
};
$stmt = $pdo->prepare("SELECT 1 FROM app.login_intentos WHERE email_hash = :hash AND bloqueado_hasta > CURRENT_TIMESTAMP");
$stmt->execute([':hash' => $emailHash]);
if ($stmt->fetchColumn()) {
    password_verify($password, $dummyHash);
    $redirect('/acces?msg=invalid&email=' . urlencode($email));
}

try {
    // Si el email aparece en ambas tablas, la identidad docente tiene precedencia.
    $stmt = $pdo->prepare("SELECT id_profesor AS id, nombre, apellidos, email, departamento, rol, imagen, password_hash FROM app.profesores WHERE lower(email) = :email AND activo = true LIMIT 1");
    $stmt->execute([':email' => $email]);
    $actor = $stmt->fetch(PDO::FETCH_ASSOC);
    $actorTipo = $actor ? 'professor' : '';
    if (!$actor) {
        $stmt = $pdo->prepare("SELECT id_alumno AS id, nombre, apellidos, email, password_hash FROM app.alumnos WHERE lower(email) = :email AND activo = true LIMIT 1");
        $stmt->execute([':email' => $email]);
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        $actorTipo = $actor ? 'alumne' : '';
    }
} catch (PDOException $e) {
    error_log('Error consultando identidad de acceso: ' . $e->getMessage());
    $redirect('/acces?msg=invalid');
}

$passwordHash = (string) ($actor['password_hash'] ?? '');
if (!$actor || $passwordHash === '' || !password_verify($password, $passwordHash)) {
    if (!$actor || $passwordHash === '') password_verify($password, $dummyHash);
    $registrarFallo($pdo, $emailHash);
    $redirect('/acces?msg=invalid&email=' . urlencode($email));
}
$pdo->prepare('DELETE FROM app.login_intentos WHERE email_hash = :hash')->execute([':hash' => $emailHash]);
if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
    $tabla = $actorTipo === 'professor' ? 'profesores' : 'alumnos';
    $columnaId = $actorTipo === 'professor' ? 'id_profesor' : 'id_alumno';
    $stmt = $pdo->prepare("UPDATE app.$tabla SET password_hash = :hash WHERE $columnaId = :id");
    $stmt->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $actor['id']]);
}

$_SESSION = [];
session_regenerate_id(true);
$_SESSION['auth_tipo'] = $actorTipo;
if ($actorTipo === 'professor') {
    $_SESSION['professor_id'] = (int) $actor['id'];
    $_SESSION['professor_nom'] = trim((string) $actor['nombre'] . ' ' . (string) $actor['apellidos']);
    $_SESSION['professor_email'] = (string) $actor['email'];
    $_SESSION['professor_imatge'] = (string) ($actor['imagen'] ?? '');
    $_SESSION['professor_rol'] = (string) ($actor['rol'] ?? '');
    $_SESSION['professor_departament'] = (string) ($actor['departamento'] ?? '');
    $redirect(loginDestinoPostAutenticacion($actorTipo, $_SESSION['professor_rol']));
}

$_SESSION['alumno_id'] = (int) $actor['id'];
$_SESSION['alumno_nom'] = trim((string) $actor['nombre'] . ' ' . (string) $actor['apellidos']);
$_SESSION['alumno_email'] = (string) $actor['email'];
$stmt = $pdo->prepare("
    SELECT p.id_proyecto, p.nombre
    FROM app.rel_proyectos_alumnos rpa
    INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
    WHERE rpa.alumno_id = :id AND p.estado = 'activo'
    ORDER BY p.curso_academico DESC, p.id_proyecto DESC LIMIT 1
");
$stmt->execute([':id' => (int) $actor['id']]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if ($project) {
    $_SESSION['projecte_id'] = (int) $project['id_proyecto'];
    $_SESSION['projecte_nom'] = (string) ($project['nombre'] ?? '');
}
$redirect(loginDestinoPostAutenticacion($actorTipo));
