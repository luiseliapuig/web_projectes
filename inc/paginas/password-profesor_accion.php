<?php
declare(strict_types=1);

/**
 * ============================================================
 * ACCIÓN DE GUARDADO DE CONTRASEÑA DEL PROFESORADO
 * ============================================================
 *
 * Esta acción:
 * - valida que exista una sesión provisional o activa de profesor
 * - comprueba que el id y el token enviados coinciden con la sesión
 * - valida la contraseña
 * - guarda el hash en la tabla profesores
 * - eleva la sesión a 'professor'
 * - redirige de vuelta a /password con mensaje
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ------------------------------------------------------------
 * Redirección compatible con el flujo actual
 * ------------------------------------------------------------
 * Como este archivo se carga dentro de index.php mediante include,
 * evitamos usar header('Location: ...') y redirigimos con JS.
 */
function redirectTo(string $url): never
{
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

$authTipo = $_SESSION['auth_tipo'] ?? '';
$professorIdSesion = isset($_SESSION['professor_id']) ? (int) $_SESSION['professor_id'] : 0;
$professorUuidSesion = trim((string) ($_SESSION['professor_uuid'] ?? ''));

if (
    !in_array($authTipo, ['professor_pending', 'professor'], true) ||
    $professorIdSesion <= 0 ||
    $professorUuidSesion === ''
) {
    redirectTo('/password?msg=invalid');
}

$idProfesorPost = isset($_POST['id_profesor']) ? (int) $_POST['id_profesor'] : 0;
$tokenPost = trim((string) ($_POST['token'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordRepeat = (string) ($_POST['password_repeat'] ?? '');

/**
 * ------------------------------------------------------------
 * Validación de coherencia entre POST y sesión
 * ------------------------------------------------------------
 * Solo permitimos guardar la contraseña del profesor que
 * está realmente dentro de esta sesión.
 */
if (
    $idProfesorPost !== $professorIdSesion ||
    $tokenPost !== $professorUuidSesion
) {
    redirectTo('/password?msg=invalid');
}

if (mb_strlen($password) < 8) {
    redirectTo('/password?msg=short');
}

if ($password !== $passwordRepeat) {
    redirectTo('/password?msg=mismatch');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("
        UPDATE profesores
        SET password_hash = :password_hash
        WHERE id_profesor = :id_profesor
          AND uuid_acceso = :uuid_acceso
          AND activo = true
    ");
    $stmt->execute([
        'password_hash' => $passwordHash,
        'id_profesor' => $professorIdSesion,
        'uuid_acceso' => $professorUuidSesion
    ]);
} catch (PDOException $e) {
    redirectTo('/password?msg=error');
}

/**
 * ------------------------------------------------------------
 * Una vez guardada la contraseña, la sesión deja de ser
 * provisional y pasa a ser una sesión real de profesor.
 * ------------------------------------------------------------
 */
$_SESSION['auth_tipo'] = 'professor';

redirectTo('/password?msg=ok');