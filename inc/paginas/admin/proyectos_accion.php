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
    || $proyectoId <= 0 || !in_array($accion, ['guardar', 'borrar'], true)) {
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
    $stmt = $pdo->prepare("SELECT 1 FROM app.rel_profesores_grupos WHERE profesor_id = :profesor AND grupo_id = :grupo AND curso_academico = :curso LIMIT 1");
    $stmt->execute([':profesor' => $tutorId, ':grupo' => $grupoId, ':curso' => $curso]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['proyectos_admin_error'] = 'El tutor no està assignat al grup en aquest curs.';
        $redirigir($sufijoRetorno);
    }
}

// Normalización del alumnado recibido.
$alumnos = [];
$emails = [];
foreach (is_array($_POST['alumnos'] ?? null) ? $_POST['alumnos'] : [] as $entrada) {
    if (!is_array($entrada)) continue;
    $nombre = isset($entrada['nombre']) && is_string($entrada['nombre']) ? trim($entrada['nombre']) : '';
    $apellidos = isset($entrada['apellidos']) && is_string($entrada['apellidos']) ? trim($entrada['apellidos']) : '';
    $email = isset($entrada['email']) && is_string($entrada['email']) ? strtolower(trim($entrada['email'])) : '';
    if ($nombre === '' || $apellidos === '' || mb_strlen($nombre) > 100
        || mb_strlen($apellidos) > 150 || mb_strlen($email) > 255
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($emails[$email])) {
        $_SESSION['proyectos_admin_error'] = 'Revisa les dades de l’alumnat i evita correus repetits.';
        $redirigir($sufijoRetorno);
    }
    $emails[$email] = true;
    $alumnos[] = ['nombre' => $nombre, 'apellidos' => $apellidos, 'email' => $email];
}
if ($alumnos === []) {
    $_SESSION['proyectos_admin_error'] = 'El projecte ha de tenir almenys un alumne.';
    $redirigir($sufijoRetorno);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT 1 FROM app.proyectos WHERE id_proyecto = :id FOR UPDATE');
    $stmt->execute([':id' => $proyectoId]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('no_encontrado');
    $stmt = $pdo->prepare('SELECT alumno_id FROM app.rel_proyectos_alumnos WHERE proyecto_id = :id ORDER BY alumno_id FOR UPDATE');
    $stmt->execute([':id' => $proyectoId]);
    $alumnoIdsAnteriores = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $alumnoIds = [];
    foreach ($alumnos as $alumno) {
        $stmt = $pdo->prepare('SELECT id_alumno FROM app.alumnos WHERE lower(email) = :email LIMIT 1 FOR UPDATE');
        $stmt->execute([':email' => $alumno['email']]);
        $alumnoId = (int) ($stmt->fetchColumn() ?: 0);
        if ($alumnoId > 0) {
            $stmt = $pdo->prepare('UPDATE app.alumnos SET nombre = :nombre, apellidos = :apellidos, email = :email, activo = true WHERE id_alumno = :id');
            $stmt->execute([':nombre' => $alumno['nombre'], ':apellidos' => $alumno['apellidos'], ':email' => $alumno['email'], ':id' => $alumnoId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO app.alumnos (nombre, apellidos, email, ciclo, grupo, curso_academico, activo) VALUES (:nombre, :apellidos, :email, :ciclo, :grupo, :curso, true) RETURNING id_alumno");
            $stmt->execute([':nombre' => $alumno['nombre'], ':apellidos' => $alumno['apellidos'], ':email' => $alumno['email'], ':ciclo' => $grupo['ciclo'], ':grupo' => $grupo['grupo'], ':curso' => $curso]);
            $alumnoId = (int) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare("INSERT INTO app.rel_alumnos_grupos (alumno_id, grupo_id, curso_academico) VALUES (:alumno, :grupo, :curso) ON CONFLICT (alumno_id, curso_academico) DO UPDATE SET grupo_id = EXCLUDED.grupo_id");
        $stmt->execute([':alumno' => $alumnoId, ':grupo' => $grupoId, ':curso' => $curso]);

        $stmt = $pdo->prepare("SELECT 1 FROM app.rel_proyectos_alumnos rpa INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id WHERE rpa.alumno_id = :alumno AND p.curso_academico = :curso AND p.estado = 'activo' AND p.id_proyecto <> :proyecto LIMIT 1");
        $stmt->execute([':alumno' => $alumnoId, ':curso' => $curso, ':proyecto' => $proyectoId]);
        if ($stmt->fetchColumn()) throw new DomainException('alumno_asignado');
        $alumnoIds[] = $alumnoId;
    }

    $stmt = $pdo->prepare("UPDATE app.proyectos SET curso_academico = :curso, grupo_id = :grupo_id, estado = :estado, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_proyecto = :id");
    $stmt->execute([':curso' => $curso, ':grupo_id' => $grupoId, ':estado' => $estado, ':id' => $proyectoId]);

    $alumnoIdsComparacion = $alumnoIds;
    sort($alumnoIdsComparacion);
    if ($alumnoIdsAnteriores !== $alumnoIdsComparacion) {
        // Solo un cambio real de integrantes reinicia sus confirmaciones.
        $pdo->prepare('DELETE FROM app.rel_proyectos_alumnos WHERE proyecto_id = :id')->execute([':id' => $proyectoId]);
        $stmt = $pdo->prepare('INSERT INTO app.rel_proyectos_alumnos (proyecto_id, alumno_id) VALUES (:proyecto, :alumno)');
        foreach ($alumnoIds as $alumnoId) $stmt->execute([':proyecto' => $proyectoId, ':alumno' => $alumnoId]);
    }

    // Todos los docentes del grupo quedan vinculados; uno puede ser tutor.
    $pdo->prepare('DELETE FROM app.rel_proyectos_profesores WHERE proyecto_id = :id')->execute([':id' => $proyectoId]);
    $stmt = $pdo->prepare("INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol) SELECT :proyecto, rpg.profesor_id, CASE WHEN rpg.profesor_id = :tutor THEN 'tutor' ELSE 'cotutor' END FROM app.rel_profesores_grupos rpg WHERE rpg.grupo_id = :grupo AND rpg.curso_academico = :curso");
    $stmt->execute([':proyecto' => $proyectoId, ':tutor' => $tutorId, ':grupo' => $grupoId, ':curso' => $curso]);

    $pdo->commit();
    $sufijoGuardado = '&curso=' . rawurlencode($curso);
    if ($cicloRetorno > 0) $sufijoGuardado .= '&ciclo_id=' . $cicloRetorno;
    $redirigir($sufijoGuardado . '&msg=guardat');
} catch (DomainException) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['proyectos_admin_error'] = 'Un alumne ja pertany a un altre projecte actiu aquest curs.';
    $redirigir($sufijoRetorno);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Error guardant projecte administratiu: ' . $e->getMessage());
    $_SESSION['proyectos_admin_error'] = 'No s’ha pogut guardar el projecte.';
    $redirigir($sufijoRetorno);
}
