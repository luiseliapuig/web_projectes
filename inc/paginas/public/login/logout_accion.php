<?php
declare(strict_types=1);

/**
 * ============================================================
 * CIERRE DE SESIÓN
 * ============================================================
 *
 * Este archivo destruye completamente la sesión activa,
 * tanto de alumno como de profesor, y redirige a la portada.
 *
 * Se accede mediante la ruta amigable:
 * /logout
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// El cierre de sesión se solicita por POST y se protege frente a CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

/**
 * Vaciar todas las variables de sesión.
 */
$_SESSION = [];

/**
 * Si la sesión usa cookie, se elimina también la cookie
 * en el navegador para cerrar la sesión de verdad.
 */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

/**
 * Destruir la sesión.
 */
session_destroy();

/**
 * Redirección final tras cerrar sesión.
 * Puedes cambiarla a otra ruta si luego prefieres
 * enviar al usuario al login de profesores.
 */
echo '<script>location.href="/";</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=/"></noscript>';
exit;
