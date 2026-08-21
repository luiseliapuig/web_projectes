<?php
declare(strict_types=1);

$redirect = static function (string $message): never {
    $url = '/canviar-contrasenya?msg=' . rawurlencode($message);
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};
if ((!esProfesor() && !esAlumno()) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) $redirect('error');
$actual = isset($_POST['password_actual']) && is_string($_POST['password_actual']) ? $_POST['password_actual'] : '';
$password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
$repeat = isset($_POST['password_repeat']) && is_string($_POST['password_repeat']) ? $_POST['password_repeat'] : '';
if (mb_strlen($password) < 10 || mb_strlen($password) > 255) $redirect('weak');
if ($password !== $repeat) $redirect('mismatch');

$profesor = esProfesor();
$tabla = $profesor ? 'profesores' : 'alumnos';
$idColumn = $profesor ? 'id_profesor' : 'id_alumno';
$id = $profesor ? (int) $_SESSION['professor_id'] : (int) $_SESSION['alumno_id'];
$resetTable = $profesor ? 'profesor_password_reset' : 'alumno_password_reset';
$resetId = $profesor ? 'profesor_id' : 'alumno_id';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT password_hash FROM app.$tabla WHERE $idColumn = :id AND activo = true LIMIT 1 FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $hash = (string) ($stmt->fetchColumn() ?: '');
    if ($hash === '' || !password_verify($actual, $hash)) {
        $pdo->rollBack();
        $redirect('current');
    }
    $pdo->prepare("UPDATE app.$tabla SET password_hash = :hash WHERE $idColumn = :id")
        ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
    $pdo->prepare("UPDATE app.$resetTable SET usado_en = CURRENT_TIMESTAMP WHERE $resetId = :id AND usado_en IS NULL")
        ->execute([':id' => $id]);
    $pdo->commit();
    session_regenerate_id(true);
    $redirect('ok');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error cambiando contraseña: ' . $e->getMessage());
    $redirect('error');
}
