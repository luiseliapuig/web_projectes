<?php
declare(strict_types=1);

$alumnoId = (int) ($_SESSION['alumno_id'] ?? 0);
$cursoAcademico = cursoAcademicoActual();
$proyectoSesionId = (int) ($_SESSION['projecte_id'] ?? 0);
$soloCursoActual = !empty($contextoCursoActual);
$stmt = $pdo->prepare("
    SELECT p.id_proyecto, p.nombre, p.estado, p.grupo_id, c.abr AS ciclo, g.grupo, c.fases_clave
    FROM app.proyectos p
    INNER JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE rpa.alumno_id = :alumno_id
      AND p.estado = 'activo'
      AND (
          (:solo_curso_actual = 1 AND p.curso_academico = :curso_academico)
          OR
          (:solo_curso_actual = 0 AND (p.id_proyecto = :proyecto_sesion_id OR p.curso_academico = :curso_academico))
      )
    ORDER BY CASE WHEN p.id_proyecto = :proyecto_sesion_id THEN 0 ELSE 1 END,
             p.curso_academico DESC, p.id_proyecto DESC
    LIMIT 1
");
$stmt->execute([
    ':alumno_id' => $alumnoId,
    ':proyecto_sesion_id' => $proyectoSesionId,
    ':curso_academico' => $cursoAcademico,
    ':solo_curso_actual' => $soloCursoActual ? 1 : 0,
]);
$proyectoAlumno = $stmt->fetch(PDO::FETCH_ASSOC);
if ($proyectoAlumno) {
    $_SESSION['projecte_id'] = (int) $proyectoAlumno['id_proyecto'];
    $_SESSION['projecte_nom'] = (string) ($proyectoAlumno['nombre'] ?? '');
    return true;
}

unset($_SESSION['projecte_id'], $_SESSION['projecte_nom']);
if (empty($permitirSinProyecto)) {
    http_response_code(403);
    echo '<div class="container-fluid py-4"><div class="alert alert-danger">No tens cap projecte assignat.</div></div>';
    return false;
}

// La Fase 1 debe poder empezar antes de que exista el proyecto. En ese caso,
// el contexto académico procede de la matrícula vigente del alumno.
$stmt = $pdo->prepare("
    SELECT rag.grupo_id, c.abr AS ciclo, g.grupo, c.fases_clave
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.grupos g ON g.id_grupo = rag.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
    WHERE rag.alumno_id = :alumno_id
      AND rag.curso_academico = :curso_academico
      AND a.activo = true
    LIMIT 1
");
$stmt->execute([
    ':alumno_id' => $alumnoId,
    ':curso_academico' => $cursoAcademico,
]);
$matriculaAlumno = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$matriculaAlumno) {
    http_response_code(403);
    echo '<div class="container-fluid py-4"><div class="alert alert-danger">No tens cap grup assignat durant el curs actual.</div></div>';
    return false;
}

$proyectoAlumno = [
    'id_proyecto' => 0,
    'nombre' => null,
    'estado' => null,
    'grupo_id' => (int) $matriculaAlumno['grupo_id'],
    'ciclo' => (string) $matriculaAlumno['ciclo'],
    'grupo' => (string) $matriculaAlumno['grupo'],
    'fases_clave' => $matriculaAlumno['fases_clave'] !== null ? (string) $matriculaAlumno['fases_clave'] : null,
];
return true;
