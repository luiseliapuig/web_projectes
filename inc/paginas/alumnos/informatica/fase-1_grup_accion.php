<?php
declare(strict_types=1);

// Endpoint específic de l'arquitectura 'informatica': no carrega
// projecte_context.php (no li correspon, és una acció d'escriptura pura,
// sense render), però sí necessita el gate d'arquitectura.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';

$redirigir = static function (string $destino): never {
    echo '<script>location.href=' . json_encode($destino) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$alumnoId = (int) ($_SESSION['alumno_id'] ?? 0);
$modalidad = isset($_POST['modalitat']) && is_string($_POST['modalitat'])
    ? trim($_POST['modalitat'])
    : '';
$companeroId = isset($_POST['company_id']) ? (int) $_POST['company_id'] : 0;

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || $alumnoId <= 0
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || !in_array($modalidad, ['individual', 'parella'], true)
    || ($modalidad === 'parella' && ($companeroId <= 0 || $companeroId === $alumnoId))
) {
    $_SESSION['fase_1_grup_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigir('/fases-del-projecte/fase-1/definir-grup');
}

if ($modalidad === 'individual') {
    $companeroId = 0;
}

$cursoAcademico = cursoAcademicoActual();
$idsBloqueo = [$alumnoId];
if ($companeroId > 0) {
    $idsBloqueo[] = $companeroId;
}
sort($idsBloqueo);

try {
    $pdo->beginTransaction();

    // Los bloqueos se solicitan siempre en el mismo orden para serializar dos
    // confirmaciones simultáneas de la misma pareja sin crear dos proyectos.
    $stmtBloqueo = $pdo->prepare('SELECT pg_advisory_xact_lock(:alumno_id)');
    foreach ($idsBloqueo as $idBloqueo) {
        $stmtBloqueo->execute([':alumno_id' => $idBloqueo]);
    }

    $stmt = $pdo->prepare("
        SELECT rag.grupo_id
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        WHERE rag.alumno_id = :alumno_id
          AND rag.curso_academico = :curso_academico
          AND a.activo = true
        LIMIT 1
        FOR UPDATE OF rag, a
    ");
    $stmt->execute([':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
    $grupoId = (int) ($stmt->fetchColumn() ?: 0);
    if ($grupoId <= 0) {
        throw new DomainException('sin_grupo');
    }

    // Gate d'arquitectura ABANS de qualsevol escriptura: aquesta acció és
    // específica de la Fase 1 d''informatica'; si el cicle real de l'alumnat
    // no hi pertany (NULL, una altra arquitectura o una clau desconeguda),
    // es talla aquí. No n'hi ha prou amb amagar l'enllaç a la navegació.
    $stmt = $pdo->prepare("
        SELECT c.fases_clave
        FROM app.grupos g
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE g.id_grupo = :grupo_id
    ");
    $stmt->execute([':grupo_id' => $grupoId]);
    $fasesClaveGrupo = $stmt->fetchColumn();
    if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $fasesClaveGrupo !== false ? $fasesClaveGrupo : null], 'informatica')) {
        $pdo->rollBack();
        http_response_code(403);
        die('Accés no permès');
    }

    if ($companeroId > 0) {
        $stmt->execute([':alumno_id' => $companeroId, ':curso_academico' => $cursoAcademico]);
        $grupoCompaneroId = (int) ($stmt->fetchColumn() ?: 0);
        if ($grupoCompaneroId !== $grupoId) {
            throw new DomainException('companero_no_disponible');
        }
    }

    $consultarProyectoActivo = static function (PDO $pdo, int $idAlumno, string $curso): ?array {
        $stmtProyecto = $pdo->prepare("
            SELECT p.id_proyecto, p.grupo_id
            FROM app.rel_proyectos_alumnos rpa
            INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
            WHERE rpa.alumno_id = :alumno_id
              AND p.curso_academico = :curso_academico
              AND p.estado = 'activo'
            ORDER BY p.id_proyecto
            FOR UPDATE OF p, rpa
        ");
        $stmtProyecto->execute([':alumno_id' => $idAlumno, ':curso_academico' => $curso]);
        $proyectos = $stmtProyecto->fetchAll(PDO::FETCH_ASSOC);
        if (count($proyectos) > 1) {
            throw new DomainException('agrupacion_incompatible');
        }
        return $proyectos[0] ?? null;
    };

    $proyectoPropio = $consultarProyectoActivo($pdo, $alumnoId, $cursoAcademico);
    $proyectoCompanero = $companeroId > 0
        ? $consultarProyectoActivo($pdo, $companeroId, $cursoAcademico)
        : null;
    $miembrosDeseados = $companeroId > 0 ? [$alumnoId, $companeroId] : [$alumnoId];
    sort($miembrosDeseados);

    if ($proyectoPropio !== null) {
        if ((int) $proyectoPropio['grupo_id'] !== $grupoId) {
            throw new DomainException('agrupacion_incompatible');
        }

        $stmt = $pdo->prepare("
            SELECT alumno_id
            FROM app.rel_proyectos_alumnos
            WHERE proyecto_id = :proyecto_id
            ORDER BY alumno_id
            FOR UPDATE
        ");
        $stmt->execute([':proyecto_id' => (int) $proyectoPropio['id_proyecto']]);
        $miembrosActuales = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($miembrosActuales !== $miembrosDeseados) {
            throw new DomainException('agrupacion_incompatible');
        }

        if ($proyectoCompanero !== null
            && (int) $proyectoCompanero['id_proyecto'] !== (int) $proyectoPropio['id_proyecto']) {
            throw new DomainException('companero_no_disponible');
        }

        // Caso idempotente: la pareja ya existe, posiblemente porque el otro
        // alumno la creó antes. Solo se confirma la tarea del alumno actual.
        $stmt = $pdo->prepare("
            UPDATE app.rel_proyectos_alumnos
            SET grupo_trabajo_confirmado_en = COALESCE(grupo_trabajo_confirmado_en, CURRENT_TIMESTAMP)
            WHERE proyecto_id = :proyecto_id AND alumno_id = :alumno_id
        ");
        $stmt->execute([
            ':proyecto_id' => (int) $proyectoPropio['id_proyecto'],
            ':alumno_id' => $alumnoId,
        ]);
        $proyectoId = (int) $proyectoPropio['id_proyecto'];
    } else {
        if ($proyectoCompanero !== null) {
            throw new DomainException('companero_no_disponible');
        }

        $stmt = $pdo->prepare("
            INSERT INTO app.proyectos (
                nombre, curso_academico, grupo_id, estado, publicado, fecha_actualizacion
            ) VALUES (
                NULL, :curso_academico, :grupo_id, 'activo', false, CURRENT_TIMESTAMP
            )
            RETURNING id_proyecto
        ");
        $stmt->execute([':curso_academico' => $cursoAcademico, ':grupo_id' => $grupoId]);
        $proyectoId = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO app.rel_proyectos_alumnos (
                proyecto_id, alumno_id, grupo_trabajo_confirmado_en
            ) VALUES (
                :proyecto_id, :alumno_id,
                CASE WHEN :confirmar = 1 THEN CURRENT_TIMESTAMP ELSE NULL END
            )
        ");
        foreach ($miembrosDeseados as $miembroId) {
            $stmt->execute([
                ':proyecto_id' => $proyectoId,
                ':alumno_id' => $miembroId,
                ':confirmar' => $miembroId === $alumnoId ? 1 : 0,
            ]);
        }

        // Se conserva el modelo docente actual: el profesorado del grupo queda
        // relacionado y el tutor principal podrá asignarse posteriormente.
        $stmt = $pdo->prepare("
            INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
            SELECT :proyecto_id, rpg.profesor_id, 'cotutor'
            FROM app.rel_profesores_grupos rpg
            WHERE rpg.grupo_id = :grupo_id
              AND rpg.curso_academico = :curso_academico
            ON CONFLICT (proyecto_id, profesor_id) DO NOTHING
        ");
        $stmt->execute([
            ':proyecto_id' => $proyectoId,
            ':grupo_id' => $grupoId,
            ':curso_academico' => $cursoAcademico,
        ]);
    }

    $pdo->commit();
    $_SESSION['projecte_id'] = $proyectoId;
    $_SESSION['projecte_nom'] = '';
    $_SESSION['fase_1_grup_mensaje'] = 'El grup de treball s’ha definit correctament.';
    $redirigir('/fases-del-projecte/fase-1');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['fase_1_grup_error'] = match ($e->getMessage()) {
        'sin_grupo' => 'No tens cap grup assignat durant el curs actual.',
        'companero_no_disponible' => 'Aquesta persona ja té definit un altre grup de treball. Si hi ha cap problema, parleu amb el tutor o tutora.',
        default => 'Ja tens definida una agrupació diferent. Si hi ha un error o necessites fer un canvi, parla amb el tutor o tutora.',
    };
    $redirigir('/fases-del-projecte/fase-1/definir-grup');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error definint el grup de treball de l’alumnat: ' . $e->getMessage());
    $_SESSION['fase_1_grup_error'] = 'No s’ha pogut definir el grup de treball. Torna-ho a provar o parla amb el tutor o tutora.';
    $redirigir('/fases-del-projecte/fase-1/definir-grup');
}
