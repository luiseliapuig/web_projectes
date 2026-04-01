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

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
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
header('Location: /');
exit;