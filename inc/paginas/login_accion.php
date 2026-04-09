<?php
declare(strict_types=1);

/**
 * ============================================================
 * LOGIN CLÁSICO DEL PROFESORADO
 * ============================================================
 *
 * Esta acción:
 * - valida email y contraseña
 * - busca profesor activo por email
 * - comprueba password_hash
 * - abre sesión real de profesor
 * - redirige a la portada
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirectTo(string $url): never
{
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    redirectTo('/acces?msg=missing&email=' . urlencode($email));
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    redirectTo('/acces?msg=invalid&email=' . urlencode($email));
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id_profesor,
            nombre,
            apellidos,
            email,
            departamento,
            activo,
            uuid_acceso,
            rol,
            imagen,
            password_hash
        FROM app.profesores
        WHERE email = :email
          AND activo = true
        LIMIT 1
    ");
    $stmt->execute([
        'email' => $email
    ]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    redirectTo('/acces?msg=invalid&email=' . urlencode($email));
}

if (!$profesor) {
    redirectTo('/acces?msg=not-found&email=' . urlencode($email));
}

$passwordHash = (string) ($profesor['password_hash'] ?? '');

if ($passwordHash === '') {
    redirectTo('/acces?msg=no-password&email=' . urlencode($email));
}

if (!password_verify($password, $passwordHash)) {
    redirectTo('/acces?msg=wrong-password&email=' . urlencode($email));
}

/**
 * Si llega aquí, el login es correcto.
 * Convertimos la sesión en sesión real de profesor.
 */
$_SESSION['auth_tipo'] = 'professor';
$_SESSION['professor_id'] = (int) $profesor['id_profesor'];
$_SESSION['professor_uuid'] = (string) $profesor['uuid_acceso'];
$_SESSION['professor_nom'] = trim(
    ((string) $profesor['nombre']) . ' ' . ((string) $profesor['apellidos'])
);
$_SESSION['professor_email'] = (string) ($profesor['email'] ?? '');
$_SESSION['professor_imatge'] = (string) ($profesor['imagen'] ?? '');
$_SESSION['professor_rol'] = (string) ($profesor['rol'] ?? '');
$_SESSION['professor_departament'] = (string) ($profesor['departamento'] ?? '');

$returnUrl = $_SESSION['return_url'] ?? '/';
unset($_SESSION['return_url']);
header('Location: ' . $returnUrl);
exit;