<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección interna con un destino fijo y un único filtro controlado.
$redirigirAlumnat = static function (string $curso, string $sufijo = ''): never {
    if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $curso)) {
        $curso = cursoAcademicoActual();
    }
    $url = '/index.php?main=alumnat&curso=' . rawurlencode($curso) . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$accion = isset($_POST['accio']) && is_string($_POST['accio'])
    ? trim($_POST['accio'])
    : '';
$returnCurso = isset($_POST['return_curso']) && is_string($_POST['return_curso'])
    ? trim($_POST['return_curso'])
    : cursoAcademicoActual();

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !in_array($accion, ['guardar', 'eliminar'], true)
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    $_SESSION['alumnat_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirAlumnat($returnCurso);
}

$id = isset($_POST['id_alumno']) ? (int) $_POST['id_alumno'] : 0;

// Solo se elimina una identidad sin proyectos. Así se pueden limpiar pruebas
// sin destruir entregas, evaluaciones ni pertenencias históricas reales.
if ($accion === 'eliminar') {
    if ($id <= 0) {
        $_SESSION['alumnat_error'] = 'L’alumne indicat no és vàlid.';
        $redirigirAlumnat($returnCurso);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM app.rel_proyectos_alumnos
            WHERE alumno_id = :alumno_id
        ");
        $stmt->execute([':alumno_id' => $id]);
        $proyectos = (int) $stmt->fetchColumn();
        if ($proyectos > 0) {
            throw new DomainException('con_proyectos');
        }

        $stmt = $pdo->prepare("DELETE FROM app.rel_alumnos_grupos WHERE alumno_id = :alumno_id");
        $stmt->execute([':alumno_id' => $id]);
        $stmt = $pdo->prepare("DELETE FROM app.alumnos WHERE id_alumno = :alumno_id RETURNING id_alumno");
        $stmt->execute([':alumno_id' => $id]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('no_encontrado');
        }

        $pdo->commit();
        $redirigirAlumnat($returnCurso, '&msg=eliminat');
    } catch (DomainException) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['alumnat_error'] = 'No es pot eliminar l’alumne perquè forma part d’un projecte. Pots desactivar-lo.';
        $redirigirAlumnat($returnCurso);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error eliminant alumne: ' . $e->getMessage());
        $_SESSION['alumnat_error'] = 'No s’ha pogut eliminar l’alumne.';
        $redirigirAlumnat($returnCurso);
    }
}

// Validación de identidad y matrícula.
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$apellidos = isset($_POST['apellidos']) && is_string($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
$email = isset($_POST['email']) && is_string($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
$curso = isset($_POST['curso_academico']) && is_string($_POST['curso_academico'])
    ? trim($_POST['curso_academico'])
    : '';
$cursoOriginal = isset($_POST['curso_original']) && is_string($_POST['curso_original'])
    ? trim($_POST['curso_original'])
    : $curso;
$grupoId = isset($_POST['grupo_id']) ? (int) $_POST['grupo_id'] : 0;
$activo = isset($_POST['activo']);

if (
    $nombre === ''
    || $apellidos === ''
    || mb_strlen($nombre) > 100
    || mb_strlen($apellidos) > 150
    || mb_strlen($email) > 255
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !preg_match('/^[0-9]{4}-[0-9]{2}$/', $curso)
    || !preg_match('/^[0-9]{4}-[0-9]{2}$/', $cursoOriginal)
    || $grupoId <= 0
) {
    $_SESSION['alumnat_error'] = 'Revisa els camps obligatoris de l’alumne.';
    $redirigirAlumnat($curso !== '' ? $curso : $returnCurso);
}

$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.grupo, c.abr AS ciclo
    FROM app.grupos g
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE g.id_grupo = :grupo_id
    LIMIT 1
");
$stmt->execute([':grupo_id' => $grupoId]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$grupo) {
    $_SESSION['alumnat_error'] = 'El grup seleccionat no és vàlid.';
    $redirigirAlumnat($curso);
}

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id_alumno FROM app.alumnos WHERE id_alumno = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('no_encontrado');
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM app.alumnos
            WHERE lower(email) = :email
              AND id_alumno <> :id
            LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':id' => $id]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('email_duplicado');
        }
    } else {
        // Un repetidor reutiliza su identidad y recibe una matrícula adicional.
        $stmt = $pdo->prepare("SELECT id_alumno FROM app.alumnos WHERE lower(email) = :email LIMIT 1 FOR UPDATE");
        $stmt->execute([':email' => $email]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE app.alumnos
            SET nombre = :nombre,
                apellidos = :apellidos,
                email = :email,
                activo = :activo,
                ciclo = CASE WHEN curso_academico IS NULL OR curso_academico <= :curso_academico THEN :ciclo ELSE ciclo END,
                grupo = CASE WHEN curso_academico IS NULL OR curso_academico <= :curso_academico THEN :grupo ELSE grupo END,
                curso_academico = CASE WHEN curso_academico IS NULL OR curso_academico <= :curso_academico THEN :curso_academico ELSE curso_academico END
            WHERE id_alumno = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellidos' => $apellidos,
            ':email' => $email,
            ':activo' => $activo,
            ':ciclo' => $grupo['ciclo'],
            ':grupo' => $grupo['grupo'],
            ':curso_academico' => $curso,
            ':id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.alumnos (
                nombre, apellidos, email, ciclo, grupo, curso_academico, activo
            ) VALUES (
                :nombre, :apellidos, :email, :ciclo, :grupo, :curso_academico, :activo
            )
            RETURNING id_alumno
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellidos' => $apellidos,
            ':email' => $email,
            ':ciclo' => $grupo['ciclo'],
            ':grupo' => $grupo['grupo'],
            ':curso_academico' => $curso,
            ':activo' => $activo,
        ]);
        $id = (int) $stmt->fetchColumn();
    }

    // Si se cambia el curso desde edición, la matrícula anterior solo se mueve
    // cuando no está respaldando un proyecto histórico.
    if ($cursoOriginal !== $curso) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM app.rel_proyectos_alumnos rpa
            INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
            WHERE rpa.alumno_id = :alumno_id
              AND p.curso_academico = :curso_original
            LIMIT 1
        ");
        $stmt->execute([':alumno_id' => $id, ':curso_original' => $cursoOriginal]);
        if ($stmt->fetchColumn()) {
            throw new DomainException('curso_con_proyecto');
        }

        $stmt = $pdo->prepare("
            DELETE FROM app.rel_alumnos_grupos
            WHERE alumno_id = :alumno_id
              AND curso_academico = :curso_original
        ");
        $stmt->execute([':alumno_id' => $id, ':curso_original' => $cursoOriginal]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO app.rel_alumnos_grupos (alumno_id, grupo_id, curso_academico)
        VALUES (:alumno_id, :grupo_id, :curso_academico)
        ON CONFLICT (alumno_id, curso_academico)
        DO UPDATE SET grupo_id = EXCLUDED.grupo_id
    ");
    $stmt->execute([
        ':alumno_id' => $id,
        ':grupo_id' => $grupoId,
        ':curso_academico' => $curso,
    ]);

    $pdo->commit();
    $redirigirAlumnat($curso, '&msg=guardat');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['alumnat_error'] = $e->getMessage() === 'email_duplicado'
        ? 'Ja existeix un altre alumne amb aquest email.'
        : 'No es pot moure aquesta matrícula perquè l’alumne té un projecte en el curs original.';
    $redirigirAlumnat($curso);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error guardant alumne: ' . $e->getMessage());
    $_SESSION['alumnat_error'] = 'No s’ha pogut guardar l’alumne.';
    $redirigirAlumnat($curso);
}
