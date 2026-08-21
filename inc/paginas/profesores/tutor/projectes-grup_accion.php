<?php
declare(strict_types=1);

// Redirección segura compatible con el layout que ya ha empezado a renderizarse.
$redirigirProjectes = static function (string $sufijo = ''): never {
    $url = '/index.php?main=projectes-grup' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$profesorId = (int) ($_SESSION['professor_id'] ?? 0);
$accion = isset($_POST['accio']) && is_string($_POST['accio'])
    ? trim($_POST['accio'])
    : '';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || $profesorId <= 0
    || !in_array($accion, ['guardar', 'eliminar'], true)
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    $_SESSION['projectes_grup_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirProjectes();
}

$cursoAcademico = cursoAcademicoActual();
$proyectoId = isset($_POST['id_proyecto']) ? (int) $_POST['id_proyecto'] : 0;

// El borrado se limita a proyectos del curso vigente relacionados con el
// profesor. Las relaciones dependientes se eliminan mediante sus FK en cascada.
if ($accion === 'eliminar') {
    if ($proyectoId <= 0) {
        $_SESSION['projectes_grup_error'] = 'El projecte indicat no és vàlid.';
        $redirigirProjectes();
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM app.proyectos p
            USING app.rel_proyectos_profesores rpp
            WHERE p.id_proyecto = :proyecto_id
              AND p.curso_academico = :curso_academico
              AND rpp.proyecto_id = p.id_proyecto
              AND rpp.profesor_id = :profesor_id
            RETURNING p.id_proyecto
        ");
        $stmt->execute([
            ':proyecto_id' => $proyectoId,
            ':curso_academico' => $cursoAcademico,
            ':profesor_id' => $profesorId,
        ]);

        if (!$stmt->fetchColumn()) {
            $_SESSION['projectes_grup_error'] = 'No tens permís per eliminar aquest projecte.';
            $redirigirProjectes();
        }

        $redirigirProjectes('&msg=eliminat');
    } catch (Throwable $e) {
        error_log('Error eliminant projecte de grup: ' . $e->getMessage());
        $_SESSION['projectes_grup_error'] = 'No s’ha pogut eliminar el projecte.';
        $redirigirProjectes();
    }
}

$grupoId = isset($_POST['grupo_id']) ? (int) $_POST['grupo_id'] : 0;
$estado = isset($_POST['estado']) && is_string($_POST['estado'])
    ? trim($_POST['estado'])
    : '';
$tutorId = isset($_POST['tutor_id']) && $_POST['tutor_id'] !== ''
    ? (int) $_POST['tutor_id']
    : null;

// El grupo debe pertenecer al profesor y al curso vigente; ciclo y letra se
// recuperan de la base de datos, nunca de campos enviados por el navegador.
$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr AS ciclo
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo = rpg.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE rpg.profesor_id = :profesor_id
      AND rpg.grupo_id = :grupo_id
      AND rpg.curso_academico = :curso_academico
      AND c.activo = true
    LIMIT 1
");
$stmt->execute([
    ':profesor_id' => $profesorId,
    ':grupo_id' => $grupoId,
    ':curso_academico' => $cursoAcademico,
]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo || !in_array($estado, ['activo', 'inactivo'], true)) {
    $_SESSION['projectes_grup_error'] = 'Revisa el grup i l’estat del projecte.';
    $redirigirProjectes();
}
$_SESSION['tutor_filtres']['curso'] = $cursoAcademico;
$_SESSION['tutor_filtres']['por_curso'][$cursoAcademico] = [
    'ciclo_id' => (int) $grupo['id_ciclo'],
    'grupo_id' => $grupoId,
];

// El tutor es opcional, pero si se informa debe ser profesor activo del grupo.
if ($tutorId !== null) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM app.rel_profesores_grupos rpg
        INNER JOIN app.profesores p ON p.id_profesor = rpg.profesor_id
        WHERE rpg.profesor_id = :tutor_id
          AND rpg.grupo_id = :grupo_id
          AND rpg.curso_academico = :curso_academico
          AND p.activo = true
        LIMIT 1
    ");
    $stmt->execute([
        ':tutor_id' => $tutorId,
        ':grupo_id' => $grupoId,
        ':curso_academico' => $cursoAcademico,
    ]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['projectes_grup_error'] = 'El tutor seleccionat no pertany al grup.';
        $redirigirProjectes();
    }
}

$alumnoIdsRecibidos = $_POST['alumno_ids'] ?? [];
$alumnoIds = [];
if (is_array($alumnoIdsRecibidos)) {
    foreach ($alumnoIdsRecibidos as $alumnoIdRecibido) {
        if ((is_string($alumnoIdRecibido) || is_int($alumnoIdRecibido)) && ctype_digit((string) $alumnoIdRecibido)) {
            $alumnoId = (int) $alumnoIdRecibido;
            if ($alumnoId > 0) {
                $alumnoIds[$alumnoId] = $alumnoId;
            }
        }
    }
}
$alumnoIds = array_values($alumnoIds);

if ($alumnoIds === [] && $estado === 'activo') {
    $_SESSION['projectes_grup_error'] = 'Un projecte actiu ha de tenir almenys un alumne.';
    $redirigirProjectes();
}

try {
    $pdo->beginTransaction();
    $alumnoIdsAnteriores = [];
    // En edición se repite el permiso sobre el recurso antes de escribir.
    if ($proyectoId > 0) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM app.proyectos p
            INNER JOIN app.rel_proyectos_profesores rpp
                ON rpp.proyecto_id = p.id_proyecto
               AND rpp.profesor_id = :profesor_id
            WHERE p.id_proyecto = :proyecto_id
              AND p.curso_academico = :curso_academico
            FOR UPDATE OF p
        ");
        $stmt->execute([
            ':profesor_id' => $profesorId,
            ':proyecto_id' => $proyectoId,
            ':curso_academico' => $cursoAcademico,
        ]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('permiso');
        }
        $stmt = $pdo->prepare("
            SELECT alumno_id
            FROM app.rel_proyectos_alumnos
            WHERE proyecto_id = :proyecto_id
            ORDER BY alumno_id
            FOR UPDATE
        ");
        $stmt->execute([':proyecto_id' => $proyectoId]);
        $alumnoIdsAnteriores = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // Se vuelve a comprobar en servidor que cada alumno está activo,
    // matriculado en el grupo y libre de otro proyecto activo este curso.
    if ($alumnoIds !== []) {
        // Los bloqueos transaccionales serializan asignaciones concurrentes del
        // mismo alumno, incluso aunque todavía no exista una fila de relación.
        $alumnoIdsBloqueo = $alumnoIds;
        sort($alumnoIdsBloqueo);
        $stmtBloqueo = $pdo->prepare('SELECT pg_advisory_xact_lock(:alumno_id)');
        foreach ($alumnoIdsBloqueo as $alumnoIdBloqueo) {
            $stmtBloqueo->execute([':alumno_id' => $alumnoIdBloqueo]);
        }

        $marcadores = [];
        $parametrosAlumnos = [
            ':grupo_id' => $grupoId,
            ':curso_academico' => $cursoAcademico,
            ':curso_proyecto' => $cursoAcademico,
            ':proyecto_id' => $proyectoId,
        ];
        foreach ($alumnoIds as $indice => $alumnoId) {
            $marcador = ':alumno_' . $indice;
            $marcadores[] = $marcador;
            $parametrosAlumnos[$marcador] = $alumnoId;
        }

        $stmt = $pdo->prepare("
            SELECT a.id_alumno
            FROM app.rel_alumnos_grupos rag
            INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
            WHERE rag.grupo_id = :grupo_id
              AND rag.curso_academico = :curso_academico
              AND a.activo = true
              AND a.id_alumno IN (" . implode(', ', $marcadores) . ")
              AND NOT EXISTS (
                  SELECT 1
                  FROM app.rel_proyectos_alumnos rpa_ocupado
                  INNER JOIN app.proyectos p_ocupado
                      ON p_ocupado.id_proyecto = rpa_ocupado.proyecto_id
                  WHERE rpa_ocupado.alumno_id = a.id_alumno
                    AND p_ocupado.curso_academico = :curso_proyecto
                    AND p_ocupado.estado = 'activo'
                    AND p_ocupado.id_proyecto <> :proyecto_id
              )
            FOR UPDATE OF a, rag
        ");
        $stmt->execute($parametrosAlumnos);
        $alumnoIdsValidos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        sort($alumnoIdsValidos);
        $alumnoIdsComparacion = $alumnoIds;
        sort($alumnoIdsComparacion);
        if ($alumnoIdsValidos !== $alumnoIdsComparacion) {
            throw new DomainException('alumno_no_disponible');
        }
    }

    // El proyecto conserva solo sus datos propios; el profesorado se guarda
    // después en rel_proyectos_profesores como única fuente de asignaciones.
    if ($proyectoId > 0) {
        $stmt = $pdo->prepare("
            UPDATE app.proyectos
            SET nombre = NULL,
                curso_academico = :curso_academico,
                grupo_id = :grupo_id,
                estado = :estado,
                publicado = false,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_proyecto = :proyecto_id
        ");
        $stmt->execute([
            ':curso_academico' => $cursoAcademico,
            ':grupo_id' => $grupoId,
            ':estado' => $estado,
            ':proyecto_id' => $proyectoId,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.proyectos (
                nombre, curso_academico, grupo_id,
                estado, publicado, fecha_actualizacion
            ) VALUES (
                NULL, :curso_academico,
                :grupo_id, :estado, false,
                CURRENT_TIMESTAMP
            )
            RETURNING id_proyecto
        ");
        $stmt->execute([
            ':curso_academico' => $cursoAcademico,
            ':grupo_id' => $grupoId,
            ':estado' => $estado,
        ]);
        $proyectoId = (int) $stmt->fetchColumn();
    }

    $alumnoIdsComparacion = $alumnoIds;
    sort($alumnoIdsComparacion);
    if ($alumnoIdsAnteriores !== $alumnoIdsComparacion) {
        // Un cambio real de integrantes invalida sus confirmaciones anteriores;
        // editar otros datos del proyecto conserva el estado de la actividad.
        $stmt = $pdo->prepare("DELETE FROM app.rel_proyectos_alumnos WHERE proyecto_id = :proyecto_id");
        $stmt->execute([':proyecto_id' => $proyectoId]);
        $stmt = $pdo->prepare("
            INSERT INTO app.rel_proyectos_alumnos (proyecto_id, alumno_id)
            VALUES (:proyecto_id, :alumno_id)
        ");
        foreach ($alumnoIds as $alumnoId) {
            $stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoId]);
        }
    }

    // La relación de profesores es la fuente principal; todos los docentes del
    // grupo quedan como cotutores salvo el tutor principal opcional.
    $stmt = $pdo->prepare("DELETE FROM app.rel_proyectos_profesores WHERE proyecto_id = :proyecto_id");
    $stmt->execute([':proyecto_id' => $proyectoId]);
    $stmt = $pdo->prepare("
        INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
        SELECT :proyecto_id, rpg.profesor_id,
               CASE WHEN rpg.profesor_id = :tutor_id THEN 'tutor' ELSE 'cotutor' END
        FROM app.rel_profesores_grupos rpg
        WHERE rpg.grupo_id = :grupo_id
          AND rpg.curso_academico = :curso_academico
    ");
    $stmt->execute([
        ':proyecto_id' => $proyectoId,
        ':tutor_id' => $tutorId,
        ':grupo_id' => $grupoId,
        ':curso_academico' => $cursoAcademico,
    ]);

    $pdo->commit();

    $redirigirProjectes('&msg=guardat');
} catch (DomainException) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['projectes_grup_error'] = 'Un dels alumnes seleccionats ja no està disponible en aquest grup.';
    $redirigirProjectes();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error guardant projecte de grup: ' . $e->getMessage());
    $_SESSION['projectes_grup_error'] = 'No s’ha pogut guardar el projecte.';
    $redirigirProjectes();
}
