<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Contexto de la petición
$main = $_GET['main'] ?? '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Bloque alumnos: detectar sesión válida
$isAlumne = (
    isset($_SESSION['auth_tipo'], $_SESSION['projecte_id'], $_SESSION['projecte_uuid']) &&
    $_SESSION['auth_tipo'] === 'alumne'
);

// Bloque alumnos, restricciones:
// Un alumno solo puede ver su propio proyecto

if ($isAlumne && $id > 0) {
    $idSesion = (int) $_SESSION['projecte_id'];

   
    /* ANULADA LO DE VER
    if ($main === 'ficha' && $id !== $idSesion) {
        header('Location: /projecte/' . $idSesion);
        exit;
    }*/

    if ($main === 'ficha_form' && $id !== $idSesion) {
        header('Location: /projecte/' . $idSesion . '/editar');
        exit;
    }
}

/// Bloque alumnos, restricciones:
// Solo puede guardar su propio proyecto
if ($main === 'ficha_accion' && $method === 'POST') {
    if (!$isAlumne && !esSuperadmin()) {
        exit;
    }
    // Además, la edición debe estar permitida globalmente
    if (!configuracion('permitir_editar') && !esSuperadmin()) {
        exit;
    }
    // El superadmin no necesita validar proyecto propio
    if (!esSuperadmin()) {
        $idSesion       = (int)$_SESSION['projecte_id'];
        $uuidSesion     = (string)$_SESSION['projecte_uuid'];
        $idProyectoPost = isset($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : 0;
        $uuidPost       = trim((string)($_POST['uuid'] ?? ''));
        if ($idProyectoPost !== $idSesion || $uuidPost !== $uuidSesion) {
            exit;
        }
    }
}

// Futuro:
// Profesores, fases del sistema y permisos avanzados



// superadmin, acceso total
function esProfesor(): bool
{
    return (
        isset($_SESSION['auth_tipo']) &&
        $_SESSION['auth_tipo'] === 'professor'
    );
}

// superadmin, acceso total
function esSuperadmin(): bool
{
    return (
        isset($_SESSION['professor_rol']) &&
        $_SESSION['professor_rol'] === 'superadmin'
    );
}


// solo superadmin
function soloSuperadmin(): void
{
    esSuperadmin() || die();
}



// para que los alumnos solo editen su proyecto
function esSuProyectoAlumno(int $idProjecte): bool
{
    // Superadmin → acceso total
    if (esSuperadmin()) {
        return true;
    }

    // Debe ser alumno autenticado
    if (
        !isset($_SESSION['auth_tipo'], $_SESSION['projecte_id']) ||
        $_SESSION['auth_tipo'] !== 'alumne'
    ) {
        return false;
    }

    // Comparar con su proyecto
    return (int) $_SESSION['projecte_id'] === $idProjecte;
}

// para que los alumnos y los profesores vean la valoracion
function esSuProyectoProfesor(int $idProjecte): bool
{
    // Profesor → puede ver
    if (esProfesor()) {
        return true;
    }

    // Debe ser alumno autenticado
    if (
        !isset($_SESSION['auth_tipo'], $_SESSION['projecte_id']) ||
        $_SESSION['auth_tipo'] !== 'alumne'
    ) {
        return false;
    }

    // Comparar con su proyecto
    return (int) $_SESSION['projecte_id'] === $idProjecte;
}


// para permitir trozos segun configuración

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = require __DIR__ . '/../config/db.php';
    }

    return $pdo;
}

function configuracion(string $clave): bool
{
    static $cache = [];

    if (isset($cache[$clave])) {
        return $cache[$clave];
    }

    $stmt = db()->prepare("SELECT valor FROM config WHERE clave = :clave LIMIT 1");
    $stmt->execute(['clave' => $clave]);

    $valor = $stmt->fetchColumn();

    $cache[$clave] = in_array((string)$valor, ['1', 'true', 'on'], true);

    return $cache[$clave];
}

function require_config(string $clave): void
{
    if (!configuracion($clave)) {
        http_response_code(403);
        die('Accés no permès');
    }
}