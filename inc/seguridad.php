<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Sesión y cookie
// La sesión se inicia aquí para que toda petición comparta una configuración
// segura antes de ejecutar el router o renderizar contenido.
// -----------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    $httpsActivo = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $httpsActivo,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// -----------------------------------------------------------------------------
// Identidad de la sesión
// Un rol aislado o un identificador sin el tipo de autenticación correcto no
// concede permisos.
// -----------------------------------------------------------------------------

function esAlumno(): bool
{
    return ($_SESSION['auth_tipo'] ?? '') === 'alumne'
        && (int) ($_SESSION['alumno_id'] ?? 0) > 0;
}

function esProfesor(): bool
{
    return ($_SESSION['auth_tipo'] ?? '') === 'professor'
        && (int) ($_SESSION['professor_id'] ?? 0) > 0;
}

function esSuperadmin(): bool
{
    return esProfesor() && ($_SESSION['professor_rol'] ?? '') === 'superadmin';
}

function soloSuperadmin(): void
{
    if (!esSuperadmin()) {
        http_response_code(403);
        die('Accés no permès');
    }
}

// -----------------------------------------------------------------------------
// Autorización sobre proyectos
// La pertenencia del alumno y la asignación del profesor son comprobaciones
// distintas. Ninguna incluye atajos implícitos por rol administrativo.
// -----------------------------------------------------------------------------

function esSuProyectoAlumno(int $idProjecte): bool
{
    if ($idProjecte <= 0 || !esAlumno()) {
        return false;
    }
    global $pdo;
    if (!$pdo instanceof PDO) {
        return false;
    }
    static $cache = [];
    $alumnoId = (int) $_SESSION['alumno_id'];
    $key = $alumnoId . ':' . $idProjecte;
    if (!array_key_exists($key, $cache)) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM app.rel_proyectos_alumnos
            WHERE alumno_id = :alumno_id AND proyecto_id = :proyecto_id
            LIMIT 1
        ");
        $stmt->execute([':alumno_id' => $alumnoId, ':proyecto_id' => $idProjecte]);
        $cache[$key] = (bool) $stmt->fetchColumn();
    }
    return $cache[$key];
}

function esTutorDelProyecto(int $idProjecte): bool
{
    if ($idProjecte <= 0 || !esProfesor()) {
        return false;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        return false;
    }

    static $cache = [];
    $idProfessor = (int) $_SESSION['professor_id'];
    $claveCache = $idProfessor . ':' . $idProjecte;

    if (array_key_exists($claveCache, $cache)) {
        return $cache[$claveCache];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM app.rel_proyectos_profesores
            WHERE proyecto_id = :id_proyecto
              AND profesor_id = :id_profesor
            LIMIT 1
        ");
        $stmt->execute([
            ':id_proyecto' => $idProjecte,
            ':id_profesor' => $idProfessor,
        ]);
        $cache[$claveCache] = (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $cache[$claveCache] = false;
    }

    return $cache[$claveCache];
}

// -----------------------------------------------------------------------------
// Función contextual de tutor
// Un profesor dispone de herramientas de tutoría cuando imparte en algún grupo
// durante el curso vigente. El resultado se conserva durante la petición.
// -----------------------------------------------------------------------------

function esTutor(): bool
{
    if (!esProfesor()) {
        return false;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        return false;
    }

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM app.rel_profesores_grupos
            WHERE profesor_id = :id_profesor
              AND curso_academico = :curso_academico
            LIMIT 1
        ");
        $stmt->execute([
            ':id_profesor' => (int) $_SESSION['professor_id'],
            ':curso_academico' => cursoAcademicoActual(),
        ]);
        $cache = (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $cache = false;
    }

    return $cache;
}

// -----------------------------------------------------------------------------
// Reglas configurables
// Se reutiliza el PDO principal y se cachean tanto los valores activos como los
// inactivos. Una clave inexistente o un error de base de datos deniega el acceso.
// -----------------------------------------------------------------------------

function configuracion(string $clave): bool
{
    static $cache = [];

    if (array_key_exists($clave, $cache)) {
        return $cache[$clave];
    }

    global $pdo;
    if (!$pdo instanceof PDO || $clave === '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT valor
            FROM app.config
            WHERE clave = :clave
            LIMIT 1
        ");
        $stmt->execute([':clave' => $clave]);
        $valor = $stmt->fetchColumn();
        $cache[$clave] = in_array(strtolower((string) $valor), ['1', 'true', 'on'], true);
    } catch (PDOException $e) {
        $cache[$clave] = false;
    }

    return $cache[$clave];
}
