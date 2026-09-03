<?php
declare(strict_types=1);

$redirigir = static function (string $destino): never {
    echo '<script>location.href=' . json_encode($destino) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$alumnoId = (int) ($_SESSION['alumno_id'] ?? 0);
$idSeguimiento = isset($_POST['id_seguimiento']) ? (int) $_POST['id_seguimiento'] : 0;

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || $alumnoId <= 0
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || $idSeguimiento <= 0
) {
    $_SESSION['alumne_autoseguiment_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigir('/autoseguiment');
}

try {
    // El registre ha de ser de l'alumnat autenticat i del seu curs vigent;
    // el formulari mai crea seguiments nous, només actualitza un d'existent.
    $stmt = $pdo->prepare("
        SELECT curso_academico, fecha_inicio, fecha_fin
        FROM app.seguimiento_alumnos
        WHERE id_seguimiento = :id AND alumno_id = :alumno_id
        LIMIT 1
    ");
    $stmt->execute([':id' => $idSeguimiento, ':alumno_id' => $alumnoId]);
    $seguimiento = $stmt->fetch(PDO::FETCH_ASSOC);

    $cursoAcademico = cursoAcademicoActual();
    if (!$seguimiento || (string) $seguimiento['curso_academico'] !== $cursoAcademico) {
        throw new DomainException('no_autoritzat');
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        WHERE rag.alumno_id = :alumno_id
          AND rag.curso_academico = :curso_academico
          AND a.activo = true
        LIMIT 1
    ");
    $stmt->execute([':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
    if (!$stmt->fetchColumn()) throw new DomainException('no_autoritzat');

    // Només es pot guardar mentre avui cau dins la setmana d'aquest registre;
    // la mateixa condició es repeteix a l'UPDATE perquè la BD sigui l'última autoritat.
    $avui = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    if ($avui < $seguimiento['fecha_inicio'] || $avui > $seguimiento['fecha_fin']) {
        throw new DomainException('setmana_tancada');
    }

    // El compliment de l'objectiu anterior només és vàlid si realment existeix
    // un objectiu previ (setmana immediatament anterior amb objetivo_siguiente
    // informat); en cas contrari es força a NULL, tal com exigeix el model.
    $stmt = $pdo->prepare("
        SELECT objetivo_siguiente
        FROM app.seguimiento_alumnos
        WHERE alumno_id = :alumno_id
          AND curso_academico = :curso_academico
          AND fecha_fin < :fecha_inicio_actual
        ORDER BY fecha_fin DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':alumno_id' => $alumnoId,
        ':curso_academico' => $cursoAcademico,
        ':fecha_inicio_actual' => $seguimiento['fecha_inicio'],
    ]);
    $hayObjetivoAnterior = trim((string) ($stmt->fetchColumn() ?: '')) !== '';

    $cumplimientoPost = $_POST['cumplimiento_objetivo_anterior'] ?? '';
    $cumplimiento = ($hayObjetivoAnterior && in_array($cumplimientoPost, ['0', '1', '2'], true))
        ? (int) $cumplimientoPost
        : null;

    $trabajoRealizado = isset($_POST['trabajo_realizado']) && is_string($_POST['trabajo_realizado'])
        ? trim($_POST['trabajo_realizado'])
        : '';
    $incidencias = isset($_POST['incidencias']) && is_string($_POST['incidencias'])
        ? trim($_POST['incidencias'])
        : '';
    $objetivoSiguiente = isset($_POST['objetivo_siguiente']) && is_string($_POST['objetivo_siguiente'])
        ? trim($_POST['objetivo_siguiente'])
        : '';

    if (mb_strlen($trabajoRealizado) > 4000 || mb_strlen($incidencias) > 4000 || mb_strlen($objetivoSiguiente) > 4000) {
        throw new DomainException('massa_llarg');
    }

    // Únic UPDATE possible des del formulari de l'alumnat: mai INSERT. La
    // creació setmanal és responsabilitat d'un procés automàtic futur.
    $stmt = $pdo->prepare("
        UPDATE app.seguimiento_alumnos
        SET cumplimiento_objetivo_anterior = :cumplimiento,
            trabajo_realizado = :trabajo,
            incidencias = :incidencias,
            objetivo_siguiente = :objetivo,
            updated_at = NOW()
        WHERE id_seguimiento = :id
          AND alumno_id = :alumno_id
          AND fecha_inicio <= CURRENT_DATE
          AND fecha_fin >= CURRENT_DATE
    ");
    $stmt->execute([
        ':cumplimiento' => $cumplimiento,
        ':trabajo' => $trabajoRealizado !== '' ? $trabajoRealizado : null,
        ':incidencias' => $incidencias !== '' ? $incidencias : null,
        ':objetivo' => $objetivoSiguiente !== '' ? $objetivoSiguiente : null,
        ':id' => $idSeguimiento,
        ':alumno_id' => $alumnoId,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new DomainException('setmana_tancada');
    }

    $_SESSION['alumne_autoseguiment_mensaje'] = 'El seguiment s’ha guardat correctament.';
    $redirigir('/autoseguiment');
} catch (DomainException $e) {
    $_SESSION['alumne_autoseguiment_error'] = match ($e->getMessage()) {
        'setmana_tancada' => 'Aquesta setmana ja no es pot modificar.',
        'massa_llarg' => 'El text és massa llarg. Redueix-lo i torna-ho a provar.',
        default => 'No tens accés a aquest seguiment.',
    };
    $redirigir('/autoseguiment');
} catch (Throwable $e) {
    error_log('Error guardant l’autoseguiment de l’alumnat: ' . $e->getMessage());
    $_SESSION['alumne_autoseguiment_error'] = 'No s’ha pogut guardar el seguiment. Torna-ho a provar.';
    $redirigir('/autoseguiment');
}
