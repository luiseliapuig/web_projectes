<?php soloSuperadmin();



/**
 * ============================================================
 * CONFIGURACION_ACCION
 * ============================================================
 * Acciones:
 * - toggle   (AJAX)
 * - insert
 * - update
 * - delete
 */

if (
    ($_SESSION['auth_tipo'] ?? '') !== 'professor' ||
    ($_SESSION['professor_rol'] ?? '') !== 'superadmin'
) {
    if (($_POST['accion'] ?? '') === 'toggle') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'msg' => 'Acceso no permitido.'
        ]);
        exit;
    }

    echo '<div class="container py-4"><div class="alert alert-danger">Acceso no permitido.</div></div>';
    exit;
}

$accion = trim((string)($_POST['accion'] ?? ''));

/**
 * ------------------------------------------------------------
 * TOGGLE AJAX
 * ------------------------------------------------------------
 */
if ($accion === 'toggle') {
    $clave = trim((string)($_POST['clave'] ?? ''));
    $valor = ((string)($_POST['valor'] ?? '0') === '1') ? '1' : '0';

    header('Content-Type: application/json; charset=utf-8');

    if ($clave === '') {
        echo json_encode([
            'ok' => false,
            'msg' => 'Falta la clave.'
        ]);
        exit;
    }

    $sql = "
        UPDATE app.config
        SET valor = :valor
        WHERE clave = :clave
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':valor' => $valor,
        ':clave' => $clave
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'ok' => false,
            'msg' => 'La regla no existe.'
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'msg' => 'OK'
    ]);
    exit;
}

/**
 * ------------------------------------------------------------
 * INSERT / UPDATE / DELETE
 * ------------------------------------------------------------
 */
$clave = trim((string)($_POST['clave'] ?? ''));
$claveOriginal = trim((string)($_POST['clave_original'] ?? ''));
$nombre = trim((string)($_POST['nombre'] ?? ''));
$descripcion = trim((string)($_POST['descripcion'] ?? ''));
$valor = isset($_POST['valor']) && (string)$_POST['valor'] === '1' ? '1' : '0';

$clave = mb_strtolower($clave);
$clave = preg_replace('/\s+/', '_', $clave);

$msg = 'Operación completada.';
$returnMain = 'configuracion';

if ($accion === 'insert') {
    if ($clave === '' || $nombre === '') {
        $msg = 'La clave y el nombre son obligatorios.';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $clave)) {
        $msg = 'La clave solo puede contener minúsculas, números y guion bajo.';
    } else {
        $sql = "
            SELECT 1
            FROM app.config
            WHERE clave = :clave
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':clave' => $clave
        ]);

        if ($stmt->fetch()) {
            $msg = 'Ya existe una regla con esa clave.';
            $to = '/index.php?main=configuracion_form&msg=' . urlencode($msg);
            echo '<script>location.href=' . json_encode($to) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '"></noscript>';
            exit;
        }

        $sql = "
            INSERT INTO app.config (
                clave,
                nombre,
                descripcion,
                valor
            ) VALUES (
                :clave,
                :nombre,
                :descripcion,
                :valor
            )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':clave' => $clave,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':valor' => $valor
        ]);

        $msg = 'Regla creada correctamente.';
    }
}

elseif ($accion === 'update') {
    if ($claveOriginal === '') {
        $msg = 'Falta la clave original.';
    } elseif ($clave === '' || $nombre === '') {
        $msg = 'La clave y el nombre son obligatorios.';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $clave)) {
        $msg = 'La clave solo puede contener minúsculas, números y guion bajo.';
    } else {
        if ($clave !== $claveOriginal) {
            $sql = "
                SELECT 1
                FROM app.config
                WHERE clave = :clave
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':clave' => $clave
            ]);

            if ($stmt->fetch()) {
                $msg = 'Ya existe otra regla con esa clave.';
                $to = '/index.php?main=configuracion_form&clave=' . urlencode($claveOriginal) . '&msg=' . urlencode($msg);
                echo '<script>location.href=' . json_encode($to) . ';</script>';
                echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '"></noscript>';
                exit;
            }
        }

        $sql = "
            UPDATE app.config
            SET
                clave = :clave,
                nombre = :nombre,
                descripcion = :descripcion,
                valor = :valor
            WHERE clave = :clave_original
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':clave' => $clave,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':valor' => $valor,
            ':clave_original' => $claveOriginal
        ]);

        $msg = 'Regla actualizada correctamente.';
    }
}

elseif ($accion === 'delete') {
    if ($claveOriginal === '') {
        $msg = 'Falta la clave.';
    } else {
        $sql = "
            DELETE FROM app.config
            WHERE clave = :clave
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':clave' => $claveOriginal
        ]);

        $msg = 'Regla eliminada correctamente.';
    }
}

$to = '/index.php?main=' . urlencode($returnMain) . '&msg=' . urlencode($msg);

echo '<script>location.href=' . json_encode($to) . ';</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '"></noscript>';
exit;