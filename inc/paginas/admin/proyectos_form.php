<?php
declare(strict_types=1);

soloSuperadmin();

$proyectoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$proyecto = [
    'id_proyecto' => 0,
    'curso_academico' => cursoAcademicoActual(),
    'grupo_id' => 0,
    'estado' => 'activo',
    'tutor_id' => 0,
];
$alumnoIdsProyecto = [];

if ($proyectoId > 0) {
    // El superadmin puede mantener cualquier proyecto, también histórico.
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.curso_academico, p.grupo_id, p.estado,
               COALESCE(t.profesor_id, 0) AS tutor_id
        FROM app.proyectos p
        LEFT JOIN app.rel_proyectos_profesores t
            ON t.proyecto_id = p.id_proyecto AND t.rol = 'tutor'
        WHERE p.id_proyecto = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $proyectoId]);
    $proyectoEncontrado = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proyectoEncontrado) {
        http_response_code(404);
        die('Projecte no trobat');
    }
    $proyecto = $proyectoEncontrado;

    $stmt = $pdo->prepare('SELECT alumno_id FROM app.rel_proyectos_alumnos WHERE proyecto_id = :id');
    $stmt->execute([':id' => $proyectoId]);
    $alumnoIdsProyecto = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$cursoActual = cursoAcademicoActual();
$cursos = $pdo->query("SELECT DISTINCT curso_academico FROM app.proyectos WHERE curso_academico ~ '^[0-9]{4}-[0-9]{2}$' ORDER BY curso_academico DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
foreach ([$cursoActual, (string) $proyecto['curso_academico']] as $cursoNecesario) {
    if (!in_array($cursoNecesario, $cursos, true)) array_unshift($cursos, $cursoNecesario);
}
$cursoSeleccionado = isset($_GET['curso']) && is_string($_GET['curso']) ? trim($_GET['curso']) : (string) $proyecto['curso_academico'];
if (!in_array($cursoSeleccionado, $cursos, true)) $cursoSeleccionado = (string) $proyecto['curso_academico'];

// Catálogo completo de grupos y profesorado asignado durante el curso escogido.
$grupos = $pdo->query("
    SELECT g.id_grupo, g.grupo, c.id_ciclo, c.abr, c.nombre, c.orden
    FROM app.grupos g INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    ORDER BY c.orden, c.abr, g.grupo
")->fetchAll(PDO::FETCH_ASSOC);
$grupoSeleccionado = isset($_GET['grupo_id']) ? (int) $_GET['grupo_id'] : (int) $proyecto['grupo_id'];
$grupoIds = array_map('intval', array_column($grupos, 'id_grupo'));
if (!in_array($grupoSeleccionado, $grupoIds, true)) $grupoSeleccionado = $grupoIds[0] ?? 0;

$stmt = $pdo->prepare("
    SELECT rpg.grupo_id, p.id_profesor, p.nombre, p.apellidos
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.profesores p ON p.id_profesor = rpg.profesor_id
    WHERE rpg.curso_academico = :curso AND p.activo = true
    ORDER BY p.apellidos, p.nombre
");
$stmt->execute([':curso' => $cursoSeleccionado]);
$profesoresPorGrupo = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $profesor) {
    $profesoresPorGrupo[(int) $profesor['grupo_id']][] = $profesor;
}

// Las relaciones ya guardadas son históricas y no dependen de que el docente
// continúe asignado actualmente al grupo. Se incorporan al selector sin
// duplicar las opciones que siguen presentes en la asignación anual.
if ($proyectoId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id_profesor, p.nombre, p.apellidos
        FROM app.rel_proyectos_profesores rpp
        INNER JOIN app.profesores p ON p.id_profesor = rpp.profesor_id
        WHERE rpp.proyecto_id = :proyecto_id
        ORDER BY p.apellidos, p.nombre
    ");
    $stmt->execute([':proyecto_id' => $proyectoId]);
    $grupoProyectoId = (int) $proyecto['grupo_id'];
    $profesoresExistentes = [];
    foreach ($profesoresPorGrupo[$grupoProyectoId] ?? [] as $profesor) {
        $profesoresExistentes[(int) $profesor['id_profesor']] = true;
    }
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $profesorHistorico) {
        $profesorHistoricoId = (int) $profesorHistorico['id_profesor'];
        if (!isset($profesoresExistentes[$profesorHistoricoId])) {
            $profesoresPorGrupo[$grupoProyectoId][] = $profesorHistorico;
            $profesoresExistentes[$profesorHistoricoId] = true;
        }
    }
}

$stmt = $pdo->prepare("
    SELECT a.id_alumno, a.nombre, a.apellidos, a.email, rag.grupo_id
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
    WHERE rag.curso_academico = :curso_matricula
      AND a.activo = true
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
    ORDER BY a.apellidos, a.nombre
");
$stmt->execute([
    ':curso_matricula' => $cursoSeleccionado,
    ':curso_proyecto' => $cursoSeleccionado,
    ':proyecto_id' => $proyectoId,
]);
$alumnosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ciclos = [];
foreach ($grupos as $grupo) {
    $ciclos[(int) $grupo['id_ciclo']] = ['id' => (int) $grupo['id_ciclo'], 'abr' => $grupo['abr'], 'nombre' => $grupo['nombre']];
}
$cicloSeleccionado = 0;
foreach ($grupos as $grupo) if ((int) $grupo['id_grupo'] === $grupoSeleccionado) $cicloSeleccionado = (int) $grupo['id_ciclo'];
$returnCurso = isset($_GET['return_curso']) && is_string($_GET['return_curso']) ? $_GET['return_curso'] : $cursoSeleccionado;
$returnCicloId = isset($_GET['return_ciclo_id']) ? (int) $_GET['return_ciclo_id'] : 0;
?>

<script>window.PAGE_TITLE = '<?= $proyectoId > 0 ? 'Editar projecte' : 'Nou projecte' ?>';</script>
<style>
.alumne-opcio { display: flex; }
.alumne-opcio.d-none { display: none !important; }
</style>

<div class="container-fluid py-4">
    <div class="card-style mb-30">
        <div class="mb-4"><h6 class="mb-1"><?= $proyectoId > 0 ? 'Editar projecte' : 'Nou projecte' ?></h6><p class="text-muted mb-0">Administració del grup de projecte, alumnat i professorat vinculat.</p></div>

        <form method="post" action="/index.php?main=proyectos_accion" id="proyecto-admin-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id_proyecto" value="<?= $proyectoId ?>">
            <input type="hidden" name="return_curso" value="<?= htmlspecialchars($returnCurso, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_ciclo_id" value="<?= $returnCicloId ?>">

            <div class="row g-3 mb-4">
                <div class="col-md-3"><label for="curso_academico" class="form-label">Curs acadèmic</label><select name="curso_academico" id="curso_academico" class="form-select"><?php foreach ($cursos as $curso): ?><option value="<?= htmlspecialchars((string) $curso, ENT_QUOTES, 'UTF-8') ?>" <?= $curso === $cursoSeleccionado ? 'selected' : '' ?>><?= htmlspecialchars((string) $curso, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label for="ciclo_id" class="form-label">Cicle</label><select id="ciclo_id" class="form-select"><?php foreach ($ciclos as $ciclo): ?><option value="<?= $ciclo['id'] ?>" <?= $cicloSeleccionado === $ciclo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ciclo['abr'] . ' — ' . $ciclo['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label for="grupo_id" class="form-label">Grup classe</label><select name="grupo_id" id="grupo_id" class="form-select"><?php foreach ($grupos as $grupo): ?><option value="<?= (int) $grupo['id_grupo'] ?>" data-ciclo="<?= (int) $grupo['id_ciclo'] ?>" <?= $grupoSeleccionado === (int) $grupo['id_grupo'] ? 'selected' : '' ?>><?= htmlspecialchars($grupo['abr'] . ' ' . $grupo['grupo'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label for="estado" class="form-label">Estat</label><select name="estado" id="estado" class="form-select"><option value="activo" <?= $proyecto['estado'] === 'activo' ? 'selected' : '' ?>>Actiu</option><option value="inactivo" <?= $proyecto['estado'] !== 'activo' ? 'selected' : '' ?>>Inactiu</option></select></div>
            </div>

            <div class="border-top pt-4 mb-4">
                <div class="mb-3"><h6 class="mb-1">Alumnat del projecte</h6><p class="text-muted mb-0">Selecciona alumnat existent, actiu i matriculat al grup. Només els projectes inactius poden quedar sense alumnat.</p></div>
                <div id="alumnes-container" class="border rounded overflow-hidden">
                    <?php foreach ($alumnosDisponibles as $alumno): ?>
                        <?php $alumnoId = (int) $alumno['id_alumno']; ?>
                        <label class="alumne-opcio align-items-center gap-3 px-3 py-3 border-bottom <?= (int) $alumno['grupo_id'] === $grupoSeleccionado ? '' : 'd-none' ?>" data-grupo="<?= (int) $alumno['grupo_id'] ?>">
                            <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="alumno_ids[]" value="<?= $alumnoId ?>" <?= in_array($alumnoId, $alumnoIdsProyecto, true) ? 'checked' : '' ?>>
                            <span><span class="d-block fw-semibold"><?= htmlspecialchars(trim($alumno['nombre'] . ' ' . $alumno['apellidos']), ENT_QUOTES, 'UTF-8') ?></span><small class="text-muted"><?= htmlspecialchars((string) $alumno['email'], ENT_QUOTES, 'UTF-8') ?></small></span>
                        </label>
                    <?php endforeach; ?>
                    <div id="alumnes-buit" class="px-3 py-4 text-muted"><?= $grupoSeleccionado > 0 ? 'No hi ha alumnat disponible en aquest grup.' : 'Selecciona primer un grup.' ?></div>
                </div>
            </div>

            <div class="border-top pt-4 mb-4"><h6 class="mb-1">Tutor principal</h6><p class="text-muted mb-3">La resta del professorat assignat al grup quedarà com a cotutor.</p><div class="form-check mb-2"><input class="form-check-input" type="radio" name="tutor_id" id="tutor_cap" value="" <?= (int) $proyecto['tutor_id'] === 0 ? 'checked' : '' ?>><label for="tutor_cap" class="form-check-label">Sense assignar</label></div><div id="tutors-container"><?php foreach ($profesoresPorGrupo as $grupoId => $profesores): foreach ($profesores as $profesor): $radioId = 'tutor_' . $grupoId . '_' . $profesor['id_profesor']; ?><div class="form-check tutor-opcio" data-grupo="<?= $grupoId ?>"><input class="form-check-input" type="radio" name="tutor_id" id="<?= $radioId ?>" value="<?= (int) $profesor['id_profesor'] ?>" <?= (int) $proyecto['tutor_id'] === (int) $profesor['id_profesor'] ? 'checked' : '' ?>><label class="form-check-label" for="<?= $radioId ?>"><?= htmlspecialchars(trim($profesor['nombre'] . ' ' . $profesor['apellidos']), ENT_QUOTES, 'UTF-8') ?></label></div><?php endforeach; endforeach; ?></div></div>

            <div class="d-flex gap-2"><button class="main-btn primary-btn btn-hover" type="submit">Guardar</button><a href="/index.php?main=proyectos&amp;curso=<?= rawurlencode($returnCurso) ?>&amp;ciclo_id=<?= $returnCicloId ?>" class="main-btn light-btn btn-hover">Tornar</a></div>
        </form>
    </div>
</div>

<script>
(() => {
 const curso=document.getElementById('curso_academico'), ciclo=document.getElementById('ciclo_id'), grupo=document.getElementById('grupo_id'), cont=document.getElementById('alumnes-container'), vacio=document.getElementById('alumnes-buit');
 const tutors=()=>{document.querySelectorAll('.tutor-opcio').forEach(o=>{const v=o.dataset.grupo===grupo.value;o.hidden=!v;o.querySelector('input').disabled=!v;});const s=document.querySelector('input[name="tutor_id"]:checked');if(s?.disabled)document.getElementById('tutor_cap').checked=true;};
 const alumnos=()=>{let visibles=0;cont.querySelectorAll('.alumne-opcio').forEach(o=>{const v=o.dataset.grupo===grupo.value;const i=o.querySelector('input');o.classList.toggle('d-none',!v);i.disabled=!v;if(!v)i.checked=false;if(v)visibles++;});vacio.textContent=grupo.value===''?'Selecciona primer un grup.':'No hi ha alumnat disponible en aquest grup.';vacio.hidden=visibles>0;};
 const grupos=()=>{let primero=null;Array.from(grupo.options).forEach(o=>{const v=o.dataset.ciclo===ciclo.value;o.hidden=!v;o.disabled=!v;if(v&&!primero)primero=o;});if(grupo.selectedOptions[0]?.disabled&&primero)primero.selected=true;tutors();alumnos();};
 curso.addEventListener('change',()=>location.href=`/index.php?main=proyectos_form&id=<?= $proyectoId ?>&curso=${encodeURIComponent(curso.value)}&grupo_id=${grupo.value}`);ciclo.addEventListener('change',grupos);grupo.addEventListener('change',()=>{tutors();alumnos();});grupos();
})();
</script>
