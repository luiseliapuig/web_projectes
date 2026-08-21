<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección interna después de cualquier escritura sobre configuración.
$redirigirConfiguracion = static function (string $sufijo = ''): never {
    $url = '/index.php?main=configuracion' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$accion = isset($_POST['accio']) && is_string($_POST['accio'])
    ? trim($_POST['accio'])
    : '';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !in_array($accion, ['toggle', 'crear', 'actualizar', 'eliminar'], true)
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    $_SESSION['configuracion_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirConfiguracion();
}

$clave = isset($_POST['clave']) && is_string($_POST['clave'])
    ? strtolower(trim($_POST['clave']))
    : '';
$claveOriginal = isset($_POST['clave_original']) && is_string($_POST['clave_original'])
    ? trim($_POST['clave_original'])
    : '';
$valor = isset($_POST['valor']) && (string) $_POST['valor'] === '1' ? '1' : '0';

// El interruptor rápido solo puede modificar una regla que ya existe.
if ($accion === 'toggle') {
    if (!preg_match('/^[a-z0-9_]{1,100}$/', $clave)) {
        $_SESSION['configuracion_error'] = 'La regla indicada no és vàlida.';
        $redirigirConfiguracion();
    }

    try {
        $stmt = $pdo->prepare("UPDATE app.config SET valor = :valor WHERE clave = :clave");
        $stmt->execute([':valor' => $valor, ':clave' => $clave]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('no_encontrada');
        }
        $redirigirConfiguracion('&msg=guardada');
    } catch (Throwable $e) {
        error_log('Error canviant configuració: ' . $e->getMessage());
        $_SESSION['configuracion_error'] = 'No s’ha pogut actualitzar la regla.';
        $redirigirConfiguracion();
    }
}

// La eliminación usa la clave original y requiere confirmación en el formulario.
if ($accion === 'eliminar') {
    if (!preg_match('/^[a-z0-9_]{1,100}$/', $claveOriginal)) {
        $_SESSION['configuracion_error'] = 'La regla indicada no és vàlida.';
        $redirigirConfiguracion();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM app.config WHERE clave = :clave");
        $stmt->execute([':clave' => $claveOriginal]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('no_encontrada');
        }
        $redirigirConfiguracion('&msg=eliminada');
    } catch (Throwable $e) {
        error_log('Error eliminant configuració: ' . $e->getMessage());
        $_SESSION['configuracion_error'] = 'No s’ha pogut eliminar la regla.';
        $redirigirConfiguracion();
    }
}

// Alta y edición comparten las mismas reglas de validación.
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
    ? trim($_POST['nombre'])
    : '';
$descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion'])
    ? trim($_POST['descripcion'])
    : '';

if (
    !preg_match('/^[a-z0-9_]{1,100}$/', $clave)
    || $nombre === ''
    || mb_strlen($nombre) > 150
    || mb_strlen($descripcion) > 255
    || ($accion === 'actualizar' && !preg_match('/^[a-z0-9_]{1,100}$/', $claveOriginal))
) {
    $_SESSION['configuracion_error'] = 'Revisa la clau, el nom i la descripció de la regla.';
    $redirigirConfiguracion();
}

try {
    if ($accion === 'crear') {
        $stmt = $pdo->prepare("
            INSERT INTO app.config (clave, nombre, descripcion, valor)
            VALUES (:clave, :nombre, :descripcion, :valor)
        ");
        $stmt->execute([
            ':clave' => $clave,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':valor' => $valor,
        ]);
        $redirigirConfiguracion('&msg=creada');
    }

    $stmt = $pdo->prepare("
        UPDATE app.config
        SET clave = :clave,
            nombre = :nombre,
            descripcion = :descripcion,
            valor = :valor
        WHERE clave = :clave_original
    ");
    $stmt->execute([
        ':clave' => $clave,
        ':nombre' => $nombre,
        ':descripcion' => $descripcion !== '' ? $descripcion : null,
        ':valor' => $valor,
        ':clave_original' => $claveOriginal,
    ]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('no_encontrada');
    }
    $redirigirConfiguracion('&msg=guardada');
} catch (PDOException $e) {
    error_log('Error guardant configuració: ' . $e->getMessage());
    $_SESSION['configuracion_error'] = $e->getCode() === '23505'
        ? 'Ja existeix una regla amb aquesta clau.'
        : 'No s’ha pogut guardar la regla.';
    $redirigirConfiguracion();
} catch (Throwable $e) {
    error_log('Error guardant configuració: ' . $e->getMessage());
    $_SESSION['configuracion_error'] = 'No s’ha pogut guardar la regla.';
    $redirigirConfiguracion();
}
