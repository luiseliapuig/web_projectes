<?php soloSuperadmin();

declare(strict_types=1);

$accion = $_POST['accion'] ?? '';

function gruposPorCiclo(string $ciclo): array
{
    if ($ciclo === 'SMX') {
        return ['A', 'B', 'C', 'D'];
    }

    if (in_array($ciclo, ['DAM', 'DAW', 'ASIX'], true)) {
        return ['A', 'B'];
    }

    return [];
}

$ciclosValidos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
$estadosValidos = ['activo', 'finalizado'];

$returnCiclo = $_POST['return_ciclo'] ?? '';
$returnGrupo = $_POST['return_grupo'] ?? '';

$returnCiclo = in_array($returnCiclo, $ciclosValidos, true) ? $returnCiclo : '';
$returnGrupo = in_array($returnGrupo, ['A', 'B', 'C', 'D'], true) ? $returnGrupo : '';

$returnUrl = 'index.php?main=proyecto';
$query = [];

if ($returnCiclo !== '') {
    $query[] = 'ciclo=' . urlencode($returnCiclo);
}
if ($returnGrupo !== '') {
    $query[] = 'grupo=' . urlencode($returnGrupo);
}
if ($query) {
    $returnUrl .= '&' . implode('&', $query);
}

if ($accion === 'borrar') {
    $id = isset($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : 0;

    if ($id <= 0) {
        die('ID no vàlid');
    }

    $stmt = $pdo->prepare("
        DELETE FROM app.proyectos
        WHERE id_proyecto = :id
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ' . $returnUrl);
    exit;
}

if ($accion !== 'guardar') {
    die('Acció no permesa');
}

$id = isset($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : 0;

$nombre = trim($_POST['nombre'] ?? '');
$cursoAcademico = trim($_POST['curso_academico'] ?? '');
$ciclo = trim($_POST['ciclo'] ?? '');
$grupo = trim($_POST['grupo'] ?? '');
$estado = trim($_POST['estado'] ?? 'activo');
$tutorId = trim((string)($_POST['tutor_id'] ?? ''));
$defensaAulaId = trim((string)($_POST['defensa_aula_id'] ?? ''));
$defensaFecha = trim($_POST['defensa_fecha'] ?? '');
$publicado = !empty($_POST['publicado']);
$alumnos = $_POST['alumnos'] ?? [];

if ($cursoAcademico === '' || $ciclo === '') {
    die('Falten camps obligatoris');
}

if (!in_array($ciclo, $ciclosValidos, true)) {
    die('Cicle no vàlid');
}

$gruposValidos = gruposPorCiclo($ciclo);
$usaGrupo = count($gruposValidos) > 0;

if ($usaGrupo) {
    if (!in_array($grupo, $gruposValidos, true)) {
        die('Grup no vàlid');
    }
} else {
    $grupo = '';
}

if (!in_array($estado, $estadosValidos, true)) {
    die('Estat no vàlid');
}

if (!preg_match('/^\d{4}-\d{2}$/', $cursoAcademico)) {
    die('Format de curs acadèmic no vàlid');
}

if (!is_array($alumnos) || count($alumnos) === 0) {
    die('Has de seleccionar almenys un alumne');
}

$alumnos = array_values(array_unique(array_map('intval', $alumnos)));
$alumnos = array_filter($alumnos, fn($v) => $v > 0);

if (count($alumnos) === 0) {
    die('Selecció d\'alumnes no vàlida');
}

$tutorId = $tutorId !== '' ? (int)$tutorId : null;
$defensaAulaId = $defensaAulaId !== '' ? (int)$defensaAulaId : null;
$defensaFechaSql = $defensaFecha !== '' ? str_replace('T', ' ', $defensaFecha) . ':00' : null;

try {
    $pdo->beginTransaction();

    $placeholders = [];
    $params = [
        'ciclo' => $ciclo,
        'proyecto_id' => $id,
    ];

    if ($usaGrupo) {
        $params['grupo'] = $grupo;
    }

    foreach ($alumnos as $i => $idAlumno) {
        $key = 'a' . $i;
        $placeholders[] = ':' . $key;
        $params[$key] = $idAlumno;
    }

    $sqlValid = "
        SELECT COUNT(*)::int
        FROM app.alumnos a
        WHERE a.activo = true
          AND a.ciclo = :ciclo
    ";

    if ($usaGrupo) {
        $sqlValid .= " AND a.grupo = :grupo ";
    }

    $sqlValid .= "
          AND a.id_alumno IN (" . implode(',', $placeholders) . ")
          AND (
                a.id_alumno IN (
                    SELECT rpa.alumno_id
                    FROM app.rel_proyectos_alumnos rpa
                    WHERE rpa.proyecto_id = :proyecto_id
                )
                OR a.id_alumno NOT IN (
                    SELECT rpa2.alumno_id
                    FROM app.rel_proyectos_alumnos rpa2
                    WHERE rpa2.proyecto_id <> :proyecto_id
                )
          )
    ";

    $stmtValid = $pdo->prepare($sqlValid);
    $stmtValid->execute($params);
    $totalValidos = (int)$stmtValid->fetchColumn();

    if ($totalValidos !== count($alumnos)) {
        throw new RuntimeException('Hi ha alumnes no disponibles o assignats a un altre projecte');
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE app.proyectos
            SET
                curso_academico = :curso_academico,
                ciclo = :ciclo,
                grupo = :grupo,
                estado = :estado,
                tutor_id = :tutor_id,
                defensa_aula_id = :defensa_aula_id,
                defensa_fecha = :defensa_fecha,
                publicado = :publicado,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_proyecto = :id
        ");

        $stmt->bindValue(':curso_academico', $cursoAcademico, PDO::PARAM_STR);
        $stmt->bindValue(':ciclo', $ciclo, PDO::PARAM_STR);

        if ($grupo === '') {
            $stmt->bindValue(':grupo', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':grupo', $grupo, PDO::PARAM_STR);
        }

        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

        if ($tutorId === null) {
            $stmt->bindValue(':tutor_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':tutor_id', $tutorId, PDO::PARAM_INT);
        }

        if ($defensaAulaId === null) {
            $stmt->bindValue(':defensa_aula_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':defensa_aula_id', $defensaAulaId, PDO::PARAM_INT);
        }

        if ($defensaFechaSql === null) {
            $stmt->bindValue(':defensa_fecha', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':defensa_fecha', $defensaFechaSql, PDO::PARAM_STR);
        }

        $stmt->bindValue(':publicado', $publicado, PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $proyectoId = $id;

        $stmtDelete = $pdo->prepare("
            DELETE FROM app.rel_proyectos_alumnos
            WHERE proyecto_id = :id
        ");
        $stmtDelete->bindValue(':id', $proyectoId, PDO::PARAM_INT);
        $stmtDelete->execute();

    } else {
        $stmt = $pdo->prepare("
            INSERT INTO app.proyectos (
                nombre,
                curso_academico,
                ciclo,
                grupo,
                estado,
                tutor_id,
                defensa_aula_id,
                defensa_fecha,
                publicado,
                fecha_actualizacion
            ) VALUES (
                :nombre,
                :curso_academico,
                :ciclo,
                :grupo,
                :estado,
                :tutor_id,
                :defensa_aula_id,
                :defensa_fecha,
                :publicado,
                CURRENT_TIMESTAMP
            )
            RETURNING id_proyecto
        ");

        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':curso_academico', $cursoAcademico, PDO::PARAM_STR);
        $stmt->bindValue(':ciclo', $ciclo, PDO::PARAM_STR);

        if ($grupo === '') {
            $stmt->bindValue(':grupo', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':grupo', $grupo, PDO::PARAM_STR);
        }

        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

        if ($tutorId === null) {
            $stmt->bindValue(':tutor_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':tutor_id', $tutorId, PDO::PARAM_INT);
        }

        if ($defensaAulaId === null) {
            $stmt->bindValue(':defensa_aula_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':defensa_aula_id', $defensaAulaId, PDO::PARAM_INT);
        }

        if ($defensaFechaSql === null) {
            $stmt->bindValue(':defensa_fecha', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':defensa_fecha', $defensaFechaSql, PDO::PARAM_STR);
        }

        $stmt->bindValue(':publicado', $publicado, PDO::PARAM_BOOL);
        $stmt->execute();

        $proyectoId = (int)$stmt->fetchColumn();
    }

    $stmtRel = $pdo->prepare("
        INSERT INTO app.rel_proyectos_alumnos (proyecto_id, alumno_id)
        VALUES (:proyecto_id, :alumno_id)
    ");

    foreach ($alumnos as $idAlumno) {
        $stmtRel->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        $stmtRel->bindValue(':alumno_id', $idAlumno, PDO::PARAM_INT);
        $stmtRel->execute();
    }

    $pdo->commit();

    header('Location: ' . $returnUrl);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Error guardant el projecte: ' . $e->getMessage());
}