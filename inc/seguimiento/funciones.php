<?php
declare(strict_types=1);

/** Fuente única del periodo, semana y candidatos del generador semanal. */
function seguimientoContextoCanonico(PDO $pdo, ?DateTimeImmutable $ahora = null): array
{
    $zona = new DateTimeZone('Europe/Madrid');
    $ahora = $ahora === null ? new DateTimeImmutable('now', $zona) : $ahora->setTimezone($zona);
    $lunes = $ahora->modify('monday this week')->modify('+7 days');
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
        SELECT rpa.proyecto_id, rpa.alumno_id
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        WHERE p.estado = 'activo' AND p.curso_academico = :curso_academico
          AND a.activo = true AND a.curso_academico = :curso_academico
        ORDER BY rpa.proyecto_id, rpa.alumno_id
    ");
    $stmt->execute([':curso_academico' => $curso]);
    $candidatos = array_map(static fn(array $fila): array => [
        'proyecto_id' => (int) $fila['proyecto_id'], 'alumno_id' => (int) $fila['alumno_id'],
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

/** Comprueba, crea y registra el periodo canónico mediante cron o admin. */
function seguimientoReconciliarPeriodoActual(PDO $pdo, string $origen): array
{
    if (!in_array($origen, ['cron', 'manual'], true)) throw new InvalidArgumentException('Origen de seguiment no vàlid.');
    $contexto = null;
    $resultado = null;
    try {
        $pdo->beginTransaction();
        $pdo->query('SELECT pg_advisory_xact_lock(1936028277, 1)');
        $contexto = seguimientoContextoCanonico($pdo);
        $total = count($contexto['candidatos']);
        $resultado = ['fecha_inicio' => $contexto['fecha_inicio'], 'fecha_fin' => $contexto['fecha_fin'],
            'candidatos' => $total, 'creados' => 0, 'ya_existentes' => 0, 'errores' => 0,
            'detalle_error' => $contexto['detalle_error']];
        if ($contexto['disponible']) {
            $insert = $pdo->prepare("
                INSERT INTO app.seguimiento_alumnos (proyecto_id, alumno_id, semana, fecha_inicio, fecha_fin)
                VALUES (:proyecto_id, :alumno_id, :semana, :fecha_inicio, :fecha_fin)
                ON CONFLICT (proyecto_id, alumno_id, semana) DO NOTHING
            ");
            foreach ($contexto['candidatos'] as $candidato) {
                $insert->execute([':proyecto_id' => $candidato['proyecto_id'], ':alumno_id' => $candidato['alumno_id'],
                    ':semana' => $contexto['semana'], ':fecha_inicio' => $contexto['fecha_inicio'], ':fecha_fin' => $contexto['fecha_fin']]);
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

/** Estado vivo: candidatos canónicos frente a la clave única real. */
function seguimientoEstadoActual(PDO $pdo): array
{
    $contexto = seguimientoContextoCanonico($pdo);
    $existentes = 0;
    if ($contexto['disponible'] && $contexto['candidatos'] !== []) {
        $stmt = $pdo->prepare('SELECT proyecto_id, alumno_id FROM app.seguimiento_alumnos WHERE semana = :semana');
        $stmt->execute([':semana' => $contexto['semana']]);
        $claves = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) $claves[(int) $fila['proyecto_id'] . ':' . (int) $fila['alumno_id']] = true;
        foreach ($contexto['candidatos'] as $candidato) {
            if (isset($claves[$candidato['proyecto_id'] . ':' . $candidato['alumno_id']])) $existentes++;
        }
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM app.seguimiento_ejecuciones WHERE fecha_inicio = :inicio AND fecha_fin = :fin');
    $stmt->execute([':inicio' => $contexto['fecha_inicio'], ':fin' => $contexto['fecha_fin']]);
    $esperados = count($contexto['candidatos']);
    return $contexto + ['esperados' => $esperados, 'existentes' => $existentes,
        'pendientes' => max(0, $esperados - $existentes), 'ejecuciones' => (int) $stmt->fetchColumn()];
}
