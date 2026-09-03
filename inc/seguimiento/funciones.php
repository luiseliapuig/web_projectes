<?php
declare(strict_types=1);

/** Fuente única del periodo, semana y candidatos del generador semanal. */
function seguimientoContextoCanonico(PDO $pdo, ?DateTimeImmutable $ahora = null, string $periodo = 'siguiente'): array
{
    if (!in_array($periodo, ['actual', 'siguiente'], true)) {
        throw new InvalidArgumentException('Període de seguiment no vàlid.');
    }
    $zona = new DateTimeZone('Europe/Madrid');
    $ahora = $ahora === null ? new DateTimeImmutable('now', $zona) : $ahora->setTimezone($zona);
    $lunes = $ahora->modify('monday this week');
    if ($periodo === 'siguiente') {
        $lunes = $lunes->modify('+7 days');
    }
    $fechaInicio = $lunes->format('Y-m-d');
    $fechaFin = $lunes->modify('+6 days')->format('Y-m-d');
    $curso = cursoAcademicoActual($ahora);
    $stmt = $pdo->query('SELECT fecha_inicio, fecha_fin FROM app.seguimiento_config WHERE id = 1');
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        return ['disponible' => false, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'semana' => null,
            'curso_academico' => $curso, 'candidatos' => [], 'detalle_error' => 'No hi ha configuració d’autoseguiment.'];
    }
    $inicioConfig = new DateTimeImmutable((string) $config['fecha_inicio'], $zona);
    $semana = intdiv((int) $inicioConfig->diff($lunes)->days, 7) + 1;
    if ($fechaInicio < (string) $config['fecha_inicio'] || $fechaInicio > (string) $config['fecha_fin']) {
        return ['disponible' => false, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'semana' => $semana,
            'curso_academico' => $curso, 'candidatos' => [], 'detalle_error' => 'La setmana objectiu queda fora del període configurat.'];
    }
    $stmt = $pdo->prepare("
        SELECT rag.alumno_id, proyecto.proyecto_id
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        INNER JOIN app.grupos g ON g.id_grupo = rag.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo AND c.activo = true
        LEFT JOIN LATERAL (
            SELECT rpa.proyecto_id
            FROM app.rel_proyectos_alumnos rpa
            INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
            WHERE rpa.alumno_id = rag.alumno_id
              AND p.estado = 'activo'
              AND p.curso_academico = rag.curso_academico
            ORDER BY rpa.proyecto_id DESC
            LIMIT 1
        ) proyecto ON true
        WHERE rag.curso_academico = :curso_academico
          AND a.activo = true
        ORDER BY rag.alumno_id
    ");
    $stmt->execute([':curso_academico' => $curso]);
    $candidatos = array_map(static fn(array $fila): array => [
        'proyecto_id' => $fila['proyecto_id'] !== null ? (int) $fila['proyecto_id'] : null,
        'alumno_id' => (int) $fila['alumno_id'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    return ['disponible' => true, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'semana' => $semana,
        'curso_academico' => $curso, 'candidatos' => $candidatos, 'detalle_error' => null];
}

/** Debe llamarse dentro del bloqueo asesor transaccional del generador. */
function seguimientoRegistrarEjecucion(PDO $pdo, string $origen, array $resultado): int
{
    $stmt = $pdo->prepare("
        INSERT INTO app.seguimiento_ejecuciones (origen, fecha_inicio, fecha_fin, numero_ejecucion,
            candidatos, creados, ya_existentes, errores, detalle_error)
        SELECT :origen, :fecha_inicio, :fecha_fin, COALESCE(MAX(numero_ejecucion), 0) + 1,
               :candidatos, :creados, :ya_existentes, :errores, :detalle_error
        FROM app.seguimiento_ejecuciones
        WHERE fecha_inicio = :periodo_inicio AND fecha_fin = :periodo_fin
        RETURNING numero_ejecucion
    ");
    $stmt->execute([':origen' => $origen, ':fecha_inicio' => $resultado['fecha_inicio'], ':fecha_fin' => $resultado['fecha_fin'],
        ':candidatos' => $resultado['candidatos'], ':creados' => $resultado['creados'],
        ':ya_existentes' => $resultado['ya_existentes'], ':errores' => $resultado['errores'],
        ':detalle_error' => $resultado['detalle_error'], ':periodo_inicio' => $resultado['fecha_inicio'],
        ':periodo_fin' => $resultado['fecha_fin']]);
    return (int) $stmt->fetchColumn();
}

/** Comprueba, crea y registra uno de los dos periodos canónicos permitidos. */
function seguimientoReconciliarPeriodoCanonico(PDO $pdo, string $origen, string $periodo): array
{
    if (!in_array($origen, ['cron', 'manual'], true)) throw new InvalidArgumentException('Origen de seguiment no vàlid.');
    if (!in_array($periodo, ['actual', 'siguiente'], true)) throw new InvalidArgumentException('Període de seguiment no vàlid.');
    $contexto = null;
    $resultado = null;
    try {
        $pdo->beginTransaction();
        $pdo->query('SELECT pg_advisory_xact_lock(1936028277, 1)');
        $contexto = seguimientoContextoCanonico($pdo, null, $periodo);
        $total = count($contexto['candidatos']);
        $resultado = ['fecha_inicio' => $contexto['fecha_inicio'], 'fecha_fin' => $contexto['fecha_fin'],
            'candidatos' => $total, 'creados' => 0, 'ya_existentes' => 0, 'errores' => 0,
            'detalle_error' => $contexto['detalle_error']];
        if ($contexto['disponible']) {
            $insert = $pdo->prepare("
                INSERT INTO app.seguimiento_alumnos
                    (proyecto_id, alumno_id, curso_academico, semana, fecha_inicio, fecha_fin)
                VALUES (:proyecto_id, :alumno_id, :curso_academico, :semana, :fecha_inicio, :fecha_fin)
                ON CONFLICT (alumno_id, curso_academico, fecha_inicio, fecha_fin) DO NOTHING
            ");
            foreach ($contexto['candidatos'] as $candidato) {
                $insert->execute([':proyecto_id' => $candidato['proyecto_id'], ':alumno_id' => $candidato['alumno_id'],
                    ':curso_academico' => $contexto['curso_academico'], ':semana' => $contexto['semana'],
                    ':fecha_inicio' => $contexto['fecha_inicio'], ':fecha_fin' => $contexto['fecha_fin']]);
                $insert->rowCount() === 1 ? $resultado['creados']++ : $resultado['ya_existentes']++;
            }
        }
        $resultado['numero_ejecucion'] = seguimientoRegistrarEjecucion($pdo, $origen, $resultado);
        $pdo->commit();
        return $resultado;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_array($contexto)) {
            $total = count($contexto['candidatos']);
            $existentesProcesados = is_array($resultado) ? (int) $resultado['ya_existentes'] : 0;
            $fallido = ['fecha_inicio' => $contexto['fecha_inicio'], 'fecha_fin' => $contexto['fecha_fin'],
                'candidatos' => $total, 'creados' => 0, 'ya_existentes' => $existentesProcesados,
                'errores' => max(1, $total - $existentesProcesados),
                'detalle_error' => 'No s’ha pogut completar la generació del període.'];
            try {
                $pdo->beginTransaction();
                $pdo->query('SELECT pg_advisory_xact_lock(1936028277, 1)');
                $fallido['numero_ejecucion'] = seguimientoRegistrarEjecucion($pdo, $origen, $fallido);
                $pdo->commit();
            } catch (Throwable $logError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }
        throw $e;
    }
}

/** Compatibilidad: el worker ordinario continúa preparando la semana siguiente. */
function seguimientoReconciliarPeriodoActual(PDO $pdo, string $origen): array
{
    return seguimientoReconciliarPeriodoCanonico($pdo, $origen, 'siguiente');
}

/** Estado vivo: candidatos canónicos frente a la clave única real. */
function seguimientoEstadoActual(PDO $pdo, string $periodo = 'siguiente'): array
{
    $contexto = seguimientoContextoCanonico($pdo, null, $periodo);
    $existentes = 0;
    if ($contexto['disponible'] && $contexto['candidatos'] !== []) {
        $stmt = $pdo->prepare('SELECT alumno_id FROM app.seguimiento_alumnos WHERE curso_academico = :curso AND fecha_inicio = :inicio AND fecha_fin = :fin');
        $stmt->execute([':curso' => $contexto['curso_academico'], ':inicio' => $contexto['fecha_inicio'], ':fin' => $contexto['fecha_fin']]);
        $claves = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) $claves[(int) $fila['alumno_id']] = true;
        foreach ($contexto['candidatos'] as $candidato) {
            if (isset($claves[$candidato['alumno_id']])) $existentes++;
        }
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM app.seguimiento_ejecuciones WHERE fecha_inicio = :inicio AND fecha_fin = :fin');
    $stmt->execute([':inicio' => $contexto['fecha_inicio'], ':fin' => $contexto['fecha_fin']]);
    $esperados = count($contexto['candidatos']);
    return $contexto + ['esperados' => $esperados, 'existentes' => $existentes,
        'pendientes' => max(0, $esperados - $existentes), 'ejecuciones' => (int) $stmt->fetchColumn()];
}

/** Regla operativa docente del Autoseguiment para un alumno y curso. */
function seguimientoPuedeValorarProfesor(PDO $pdo, int $alumnoId, int $profesorId, string $cursoAcademico): bool
{
    if ($alumnoId <= 0 || $profesorId <= 0 || $cursoAcademico === '') return false;

    $stmt = $pdo->prepare("
        SELECT proyecto.tutor_formal_id
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = rag.grupo_id
           AND rpg.curso_academico = rag.curso_academico
           AND rpg.profesor_id = :profesor_id
        LEFT JOIN LATERAL (
            SELECT (
                SELECT rpp.profesor_id
                FROM app.rel_proyectos_profesores rpp
                WHERE rpp.proyecto_id = p.id_proyecto AND rpp.rol = 'tutor'
                LIMIT 1
            ) AS tutor_formal_id
            FROM app.rel_proyectos_alumnos rpa
            INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
            WHERE rpa.alumno_id = rag.alumno_id
              AND p.curso_academico = rag.curso_academico
              AND p.estado = 'activo'
            ORDER BY p.id_proyecto DESC
            LIMIT 1
        ) proyecto ON true
        WHERE rag.alumno_id = :alumno_id
          AND rag.curso_academico = :curso_academico
        LIMIT 1
    ");
    $stmt->execute([':profesor_id' => $profesorId, ':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
    $contexto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contexto) return false;
    return $contexto['tutor_formal_id'] === null || (int) $contexto['tutor_formal_id'] === $profesorId;
}
