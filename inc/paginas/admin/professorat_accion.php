<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección interna compatible con el layout ya renderizado por index.php.
$redirigirProfessorat = static function (string $sufijo = ''): never {
    $url = '/index.php?main=professorat' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

// La acción solo admite formularios POST autenticados y con token válido.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    $_SESSION['professorat_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirProfessorat();
}

$id = isset($_POST['id_profesor']) ? (int) $_POST['id_profesor'] : 0;
$esAlta = $id <= 0;
$enviarInvitacion = $esAlta && isset($_POST['enviar_invitacion']);
$accio = isset($_POST['accio']) && is_string($_POST['accio'])
    ? trim($_POST['accio'])
    : '';

// Eliminación protegida frente a relaciones existentes.
if ($accio === 'eliminar') {
    if ($id <= 0) {
        $_SESSION['professorat_error'] = 'Professor no vàlid.';
        $redirigirProfessorat();
    }

    $dependencias = [];

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM app.evaluacion_tribunal
        WHERE profesor_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $total = (int) $stmt->fetchColumn();
    if ($total > 0) {
        $dependencias[] = "té {$total} avaluació(ns) de tribunal";
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM app.rel_profesores_tribunal
        WHERE profesor_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $total = (int) $stmt->fetchColumn();
    if ($total > 0) {
        $dependencias[] = "pertany al tribunal de {$total} projecte(s)";
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM app.ajustes_nota_individual
        WHERE creado_por_profesor_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $total = (int) $stmt->fetchColumn();
    if ($total > 0) {
        $dependencias[] = "ha creat {$total} ajust(os) de nota individual";
    }

    // Las asignaciones docentes se conservan como histórico entre promociones.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM app.rel_profesores_grupos
        WHERE profesor_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $total = (int) $stmt->fetchColumn();
    if ($total > 0) {
        $dependencias[] = "té {$total} assignació(ns) històrica(es) a grups";
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM app.rel_proyectos_profesores
        WHERE profesor_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $total = (int) $stmt->fetchColumn();
    if ($total > 0) {
        $dependencias[] = "està vinculat a {$total} projecte(s)";
    }

    if ($dependencias !== []) {
        $_SESSION['professorat_error'] = 'No es pot eliminar el professor perquè '
            . implode(', ', $dependencias) . '.';
        $redirigirProfessorat();
    }

    $stmt = $pdo->prepare("DELETE FROM app.profesores WHERE id_profesor = :id");
    $stmt->execute([':id' => $id]);
    $redirigirProfessorat('&msg=eliminat');
}

// Validación de los datos permitidos para alta y edición.
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre'])
    ? trim($_POST['nombre'])
    : '';
$apellidos = isset($_POST['apellidos']) && is_string($_POST['apellidos'])
    ? trim($_POST['apellidos'])
    : '';
$email = isset($_POST['email']) && is_string($_POST['email'])
    ? strtolower(trim($_POST['email']))
    : '';
$departamento = isset($_POST['departamento']) && is_string($_POST['departamento'])
    ? trim($_POST['departamento'])
    : '';
$activo = isset($_POST['activo']) ? 1 : 0;
$rol = isset($_POST['superadmin']) ? 'superadmin' : null;
$departamentosPermitidos = ['Informàtica', 'Administració i gestió', 'Altres'];
$cursoAcademico = cursoAcademicoActual();

// Normalización y autorización de los grupos seleccionados.
$grupoIdsRecibidos = $_POST['grupo_ids'] ?? [];
$grupoIds = [];
if (is_array($grupoIdsRecibidos)) {
    foreach ($grupoIdsRecibidos as $grupoIdRecibido) {
        if (is_int($grupoIdRecibido) || (is_string($grupoIdRecibido) && ctype_digit($grupoIdRecibido))) {
            $grupoId = (int) $grupoIdRecibido;
            if ($grupoId > 0) {
                $grupoIds[] = $grupoId;
            }
        }
    }
}
$grupoIds = array_values(array_unique($grupoIds));

$stmt = $pdo->prepare("
    SELECT g.id_grupo
    FROM app.grupos g
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE c.activo = true
       OR EXISTS (
            SELECT 1
            FROM app.rel_profesores_grupos rpg
            WHERE rpg.grupo_id = g.id_grupo
              AND rpg.profesor_id = :profesor_id
              AND rpg.curso_academico = :curso_academico
       )
");
$stmt->execute([
    ':profesor_id' => $id,
    ':curso_academico' => $cursoAcademico,
]);
$grupoIdsPermitidos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if (
    $nombre === ''
    || $apellidos === ''
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !in_array($departamento, $departamentosPermitidos, true)
    || array_diff($grupoIds, $grupoIdsPermitidos) !== []
) {
    $_SESSION['professorat_error'] = 'Revisa els camps obligatoris del professor.';
    $redirigirProfessorat();
}

// Profesor y asignaciones anuales se guardan como una única operación.
try {
    $pdo->beginTransaction();

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE app.profesores
            SET nombre = :nombre,
                apellidos = :apellidos,
                email = :email,
                departamento = :departamento,
                activo = :activo,
                rol = :rol
            WHERE id_profesor = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellidos' => $apellidos,
            ':email' => $email,
            ':departamento' => $departamento,
            ':activo' => $activo,
            ':rol' => $rol,
            ':id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.profesores (
                nombre, apellidos, email, departamento, activo, rol
            ) VALUES (
                :nombre, :apellidos, :email, :departamento, :activo, :rol
            )
            RETURNING id_profesor
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':apellidos' => $apellidos,
            ':email' => $email,
            ':departamento' => $departamento,
            ':activo' => $activo,
            ':rol' => $rol,
        ]);
        $id = (int) $stmt->fetchColumn();
    }

    // Solo se reemplazan las asignaciones del curso vigente; el histórico se conserva.
    $stmt = $pdo->prepare("
        DELETE FROM app.rel_profesores_grupos
        WHERE profesor_id = :profesor_id
          AND curso_academico = :curso_academico
    ");
    $stmt->execute([
        ':profesor_id' => $id,
        ':curso_academico' => $cursoAcademico,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO app.rel_profesores_grupos (profesor_id, grupo_id, curso_academico)
        VALUES (:profesor_id, :grupo_id, :curso_academico)
    ");
    foreach ($grupoIds as $grupoId) {
        $stmt->execute([
            ':profesor_id' => $id,
            ':grupo_id' => $grupoId,
            ':curso_academico' => $cursoAcademico,
        ]);
    }

    // Las asignaciones del curso vigente se reflejan también en sus proyectos.
    // Un tutor principal se conserva hasta que se cambie expresamente allí.
    $stmt = $pdo->prepare("
        DELETE FROM app.rel_proyectos_profesores rpp
        USING app.proyectos p
        WHERE rpp.proyecto_id = p.id_proyecto
          AND rpp.profesor_id = :profesor_id
          AND rpp.rol = 'cotutor'
          AND p.curso_academico = :curso_academico
          AND NOT EXISTS (
              SELECT 1
              FROM app.rel_profesores_grupos rpg
              WHERE rpg.profesor_id = :profesor_id
                AND rpg.grupo_id = p.grupo_id
                AND rpg.curso_academico = p.curso_academico
          )
    ");
    $stmt->execute([
        ':profesor_id' => $id,
        ':curso_academico' => $cursoAcademico,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
        SELECT p.id_proyecto, :profesor_id, 'cotutor'
        FROM app.proyectos p
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_id
        WHERE p.curso_academico = :curso_academico
        ON CONFLICT (proyecto_id, profesor_id) DO NOTHING
    ");
    $stmt->execute([
        ':profesor_id' => $id,
        ':curso_academico' => $cursoAcademico,
    ]);

    $pdo->commit();
} catch (PDOException) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['professorat_error'] = 'No s’han pogut guardar les dades del professor.';
    $redirigirProfessorat();
}

if ($esAlta && $enviarInvitacion) {
    try {
        require_once dirname(__DIR__, 2) . '/email/bootstrap.php';
        $invitation = new ProfessorInvitation($pdo, new EmailService(EmailConfig::fromEnvironment()));
        $invitation->send($id);
        $redirigirProfessorat('&msg=creat-invitat');
    } catch (Throwable $e) {
        error_log('No se pudo enviar la invitación al profesor #' . $id . ': ' . $e->getMessage());
        $_SESSION['professorat_warning'] = 'El professor s’ha creat, però no s’ha pogut enviar la invitació. Pots reenviar-la des del llistat.';
        $redirigirProfessorat('&msg=creat');
    }
}

$redirigirProfessorat($esAlta ? '&msg=creat' : '&msg=guardat');
