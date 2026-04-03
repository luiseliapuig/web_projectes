<?php
declare(strict_types=1);
$_SESSION = [];
/**
 * ============================================================
 * LOGIN DE ACCESO POR ENLACE ÚNICO (PROFESORADO)
 * ============================================================
 *
 * Este archivo NO pinta HTML.
 * Solo hace de puerta de entrada técnica:
 *
 * - recibe el token UUID desde la URL
 * - valida que exista un profesor activo con ese uuid_acceso
 * - abre una sesión provisional de profesor
 * - redirige a /password
 *
 * La pantalla visual se renderiza dentro del sistema principal
 * mediante index.php?main=password-profesor
 * ============================================================
 */

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ------------------------------------------------------------
 * Función auxiliar para validar UUID
 * ------------------------------------------------------------
 */
function isValidUuid(string $uuid): bool
{
    return (bool) preg_match(
        '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
        $uuid
    );
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '' || !isValidUuid($token)) {
    header('Location: /?msg=login-invalid');
    exit;
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
        FROM profesores
        WHERE uuid_acceso = :uuid
          AND activo = true
        LIMIT 1
    ");
    $stmt->execute([
        'uuid' => $token
    ]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Location: /?msg=login-error');
    exit;
}

if (!$profesor) {
    header('Location: /?msg=login-not-found');
    exit;
}

/**
 * ------------------------------------------------------------
 * Guardamos una sesión provisional.
 * ------------------------------------------------------------
 * Esta sesión sirve solo para permitir al profesor generar
 * o cambiar su contraseña dentro de /password.
 *
 * Más adelante, cuando exista el login normal, el estado
 * 'professor' será el acceso completo estándar.
 * ------------------------------------------------------------
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

/**
 * Redirección a la pantalla integrada dentro del index principal.
 */
header('Location: /password');
exit;