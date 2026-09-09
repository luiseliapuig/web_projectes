<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección compatible con el layout ya iniciado.
$redirigir = static function (string $sufijo = ''): never {
    $url = '/index.php?main=proyectos' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$accion = isset($_POST['accion']) && is_string($_POST['accion']) ? trim($_POST['accion']) : '';
$proyectoId = isset($_POST['id_proyecto']) ? (int) $_POST['id_proyecto'] : 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || !in_array($accion, ['guardar', 'borrar'], true)
    || ($accion === 'borrar' && $proyectoId <= 0)) {
    http_response_code(403);
    die('Sol·licitud no vàlida');
}

$cursoRetorno = isset($_POST['return_curso']) && is_string($_POST['return_curso']) ? trim($_POST['return_curso']) : '';
$cicloRetorno = isset($_POST['return_ciclo_id']) ? (int) $_POST['return_ciclo_id'] : 0;
$sufijoRetorno = preg_match('/^\d{4}-\d{2}$/', $cursoRetorno) ? '&curso=' . rawurlencode($cursoRetorno) : '';
if ($cicloRetorno > 0) $sufijoRetorno .= '&ciclo_id=' . $cicloRetorno;

// El borrado respeta las restricciones históricas de la base de datos.
if ($accion === 'borrar') {
    try {
        $stmt = $pdo->prepare('DELETE FROM app.proyectos WHERE id_proyecto = :id RETURNING id_proyecto');
        $stmt->execute([':id' => $proyectoId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('no_encontrado');
        $redirigir($sufijoRetorno . '&msg=eliminat');
    } catch (Throwable $e) {
        error_log('Error eliminant projecte administratiu: ' . $e->getMessage());
        $_SESSION['proyectos_admin_error'] = 'No s’ha pogut eliminar el projecte perquè conserva dades relacionades.';
        $redirigir($sufijoRetorno);
    }
}

$curso = isset($_POST['curso_academico']) && is_string($_POST['curso_academico']) ? trim($_POST['curso_academico']) : '';
$grupoId = isset($_POST['grupo_id']) ? (int) $_POST['grupo_id'] : 0;
$estado = isset($_POST['estado']) && is_string($_POST['estado']) ? trim($_POST['estado']) : '';
$tutorId = isset($_POST['tutor_id']) && $_POST['tutor_id'] !== '' ? (int) $_POST['tutor_id'] : null;
if (!preg_match('/^\d{4}-\d{2}$/', $curso) || $grupoId <= 0 || !in_array($estado, ['activo', 'inactivo'], true)) {
    $_SESSION['proyectos_admin_error'] = 'Revisa el curs, el grup i l’estat del projecte.';
    $redirigir($sufijoRetorno);
}

// Grupo y tutor se validan contra las asignaciones anuales actuales.
$stmt = $pdo->prepare("SELECT g.id_grupo, g.grupo, c.abr AS ciclo FROM app.grupos g INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE g.id_grupo = :id LIMIT 1");
$stmt->execute([':id' => $grupoId]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$grupo) {
    $_SESSION['proyectos_admin_error'] = 'El grup seleccionat no existeix.';
    $redirigir($sufijoRetorno);
}
if ($tutorId !== null) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM app.profesores p
        WHERE p.id_profesor = :profesor
          AND (
              (
                  p.activo = true
                  AND EXISTS (
                      SELECT 1
                      FROM app.rel_profesores_grupos rpg
                      WHERE rpg.profesor_id = p.id_profesor
                        AND rpg.grupo_id = :grupo
                        AND rpg.curso_academico = :curso
                  )
              )
              OR EXISTS (
                  SELECT 1
                  FROM app.rel_proyectos_profesores rpp
                  WHERE rpp.proyecto_id = :proyecto
                    AND rpp.profesor_id = p.id_profesor
              )
          )
        LIMIT 1
    ");
    $stmt->execute([
        ':profesor' => $tutorId,
        ':grupo' => $grupoId,
        ':curso' => $curso,
        ':proyecto' => $proyectoId,
    ]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['proyectos_admin_error'] = 'El tutor no està assignat al grup en aquest curs.';
        $redirigir($sufijoRetorno);
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
    $_SESSION['proyectos_admin_error'] = 'Un projecte actiu ha de tenir almenys un alumne.';
    $redirigir($sufijoRetorno);
}

try {
    $pdo->beginTransaction();
    $alumnoIdsAnteriores = [];
    $incorporarProfesoradoGrupo = $proyectoId <= 0;
    $tutorIdAnterior = null;
    if ($proyectoId > 0) {
        $stmt = $pdo->prepare('SELECT curso_academico, grupo_id FROM app.proyectos WHERE id_proyecto = :id FOR UPDATE');
        $stmt->execute([':id' => $proyectoId]);
        $proyectoAnterior = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proyectoAnterior) throw new RuntimeException('no_encontrado');
        $incorporarProfesoradoGrupo = (string) $proyectoAnterior['curso_academico'] !== $curso
            || (int) $proyectoAnterior['grupo_id'] !== $grupoId;
        $stmt = $pdo->prepare('SELECT alumno_id FROM app.rel_proyectos_alumnos WHERE proyecto_id = :id ORDER BY alumno_id FOR UPDATE');
        $stmt->execute([':id' => $proyectoId]);
        $alumnoIdsAnteriores = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $pdo->prepare("
            SELECT profesor_id, rol
            FROM app.rel_proyectos_profesores
            WHERE proyecto_id = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $proyectoId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $relacionProfesor) {
            if ($relacionProfesor['rol'] === 'tutor') {
                $tutorIdAnterior = (int) $relacionProfesor['profesor_id'];
                break;
            }
        }
    }

    if ($alumnoIds !== []) {
        $alumnoIdsBloqueo = $alumnoIds;
        sort($alumnoIdsBloqueo);
        $stmtBloqueo = $pdo->prepare('SELECT pg_advisory_xact_lock(:alumno_id)');
        foreach ($alumnoIdsBloqueo as $alumnoIdBloqueo) {
            $stmtBloqueo->execute([':alumno_id' => $alumnoIdBloqueo]);
        }

        $marcadores = [];
        $parametrosAlumnos = [
            ':grupo_id' => $grupoId,
            ':curso_matricula' => $curso,
            ':curso_proyecto' => $curso,
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
              AND rag.curso_academico = :curso_matricula
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

    if ($proyectoId > 0) {
        // No se modifican nombre ni publicado desde este formulario.
        $stmt = $pdo->prepare("UPDATE app.proyectos SET curso_academico = :curso, grupo_id = :grupo_id, estado = :estado, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_proyecto = :id");
        $stmt->execute([':curso' => $curso, ':grupo_id' => $grupoId, ':estado' => $estado, ':id' => $proyectoId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.proyectos (nombre, curso_academico, grupo_id, estado, publicado, fecha_actualizacion)
            VALUES (NULL, :curso, :grupo_id, :estado, false, CURRENT_TIMESTAMP)
            RETURNING id_proyecto
        ");
        $stmt->execute([':curso' => $curso, ':grupo_id' => $grupoId, ':estado' => $estado]);
        $proyectoId = (int) $stmt->fetchColumn();
    }

    $alumnoIdsComparacion = $alumnoIds;
    sort($alumnoIdsComparacion);
    if ($alumnoIdsAnteriores !== $alumnoIdsComparacion) {
        // Solo un cambio real de integrantes reinicia sus confirmaciones.
        $pdo->prepare('DELETE FROM app.rel_proyectos_alumnos WHERE proyecto_id = :id')->execute([':id' => $proyectoId]);
        $stmt = $pdo->prepare('INSERT INTO app.rel_proyectos_alumnos (proyecto_id, alumno_id) VALUES (:proyecto, :alumno)');
        foreach ($alumnoIds as $alumnoId) $stmt->execute([':proyecto' => $proyectoId, ':alumno' => $alumnoId]);
    }

    // Las relaciones históricas nunca se borran al guardar. Si el tutor no ha
    // cambiado, no se escribe ninguna relación existente.
    $cambiarTutor = $tutorIdAnterior !== $tutorId;
    if ($cambiarTutor) {
        $stmt = $pdo->prepare("
            UPDATE app.rel_proyectos_profesores
            SET rol = 'cotutor'
            WHERE proyecto_id = :proyecto
              AND rol = 'tutor'
        ");
        $stmt->execute([':proyecto' => $proyectoId]);
    }

    if ($incorporarProfesoradoGrupo) {
        $stmt = $pdo->prepare("
            INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
            SELECT :proyecto, rpg.profesor_id, 'cotutor'
            FROM app.rel_profesores_grupos rpg
            WHERE rpg.grupo_id = :grupo
              AND rpg.curso_academico = :curso
            ON CONFLICT (proyecto_id, profesor_id) DO NOTHING
        ");
        $stmt->execute([':proyecto' => $proyectoId, ':grupo' => $grupoId, ':curso' => $curso]);
    }

    if ($cambiarTutor && $tutorId !== null) {
        $stmt = $pdo->prepare("
            INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
            VALUES (:proyecto, :tutor, 'tutor')
            ON CONFLICT (proyecto_id, profesor_id)
            DO UPDATE SET rol = 'tutor'
        ");
        $stmt->execute([':proyecto' => $proyectoId, ':tutor' => $tutorId]);
    }

    $pdo->commit();
    $sufijoGuardado = '&curso=' . rawurlencode($curso);
    if ($cicloRetorno > 0) $sufijoGuardado .= '&ciclo_id=' . $cicloRetorno;
    $redirigir($sufijoGuardado . '&msg=guardat');
} catch (DomainException) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['proyectos_admin_error'] = 'Un dels alumnes seleccionats ja no està disponible en aquest grup.';
    $redirigir($sufijoRetorno);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error guardant projecte administratiu: ' . $e->getMessage());
    $_SESSION['proyectos_admin_error'] = 'No s’ha pogut guardar el projecte.';
    $redirigir($sufijoRetorno);
}
