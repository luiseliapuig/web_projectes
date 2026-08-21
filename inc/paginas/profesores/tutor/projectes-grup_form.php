<?php
declare(strict_types=1);

$profesorId = (int) ($_SESSION['professor_id'] ?? 0);
$cursoAcademico = cursoAcademicoActual();
$proyectoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Solo se ofrecen los grupos que el profesor tiene asignados este curso.
$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.grupo, c.id_ciclo, c.abr, c.nombre, c.orden
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo = rpg.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE rpg.profesor_id = :profesor_id
      AND rpg.curso_academico = :curso_academico
      AND c.activo = true
    ORDER BY c.orden, c.abr, g.grupo
");
$stmt->execute([
    ':profesor_id' => $profesorId,
    ':curso_academico' => $cursoAcademico,
]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$grupoIdsPermitidos = array_map('intval', array_column($grupos, 'id_grupo'));
$grupoRecordado = (int) ($_SESSION['tutor_filtres']['por_curso'][$cursoAcademico]['grupo_id'] ?? 0);
if (!in_array($grupoRecordado, $grupoIdsPermitidos, true)) {
    $grupoRecordado = 0;
}

// Profesorado disponible para cada grupo, usado al escoger tutor principal.
$stmt = $pdo->prepare("
    SELECT rpg.grupo_id, p.id_profesor, p.nombre, p.apellidos
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.profesores p ON p.id_profesor = rpg.profesor_id
    WHERE rpg.curso_academico = :curso_academico
      AND rpg.grupo_id IN (
          SELECT grupo_id
          FROM app.rel_profesores_grupos
          WHERE profesor_id = :profesor_id
            AND curso_academico = :curso_academico
      )
      AND p.activo = true
    ORDER BY p.apellidos, p.nombre
");
$stmt->execute([
    ':profesor_id' => $profesorId,
    ':curso_academico' => $cursoAcademico,
]);
$profesoresPorGrupo = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $profesorGrupo) {
    $profesoresPorGrupo[(int) $profesorGrupo['grupo_id']][] = $profesorGrupo;
}

// En edición, la propia consulta comprueba la relación del profesor al proyecto.
$proyecto = [
    'id_proyecto' => 0,
    'grupo_id' => $grupoRecordado,
    'estado' => 'activo',
    'tutor_id' => 0,
];
$alumnoIdsProyecto = [];

if ($proyectoId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.grupo_id, p.estado,
               COALESCE(t.profesor_id, 0) AS tutor_id
        FROM app.proyectos p
        INNER JOIN app.rel_proyectos_profesores acceso
            ON acceso.proyecto_id = p.id_proyecto
           AND acceso.profesor_id = :profesor_id
        LEFT JOIN app.rel_proyectos_profesores t
            ON t.proyecto_id = p.id_proyecto
           AND t.rol = 'tutor'
        WHERE p.id_proyecto = :proyecto_id
          AND p.curso_academico = :curso_academico
        LIMIT 1
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':proyecto_id' => $proyectoId,
        ':curso_academico' => $cursoAcademico,
    ]);
    $proyectoEncontrado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proyectoEncontrado || !in_array((int) $proyectoEncontrado['grupo_id'], $grupoIdsPermitidos, true)) {
        http_response_code(403);
        echo '<div class="container-fluid py-4"><div class="alert alert-danger">No tens permís per editar aquest projecte.</div></div>';
        return;
    }

    $proyecto = $proyectoEncontrado;
    $stmt = $pdo->prepare("
        SELECT a.id_alumno
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id = :proyecto_id
    ");
    $stmt->execute([':proyecto_id' => $proyectoId]);
    $alumnoIdsProyecto = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// Se muestran únicamente alumnos activos matriculados en los grupos del
// profesor que no estén ya en otro proyecto activo del curso. En edición se
// mantienen disponibles los miembros del propio proyecto.
$stmt = $pdo->prepare("
    SELECT a.id_alumno, a.nombre, a.apellidos, a.email, rag.grupo_id
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
    WHERE rag.curso_academico = :curso_academico
      AND a.activo = true
      AND rag.grupo_id IN (
          SELECT grupo_id
          FROM app.rel_profesores_grupos
          WHERE profesor_id = :profesor_id
            AND curso_academico = :curso_academico
      )
      AND NOT EXISTS (
          SELECT 1
          FROM app.rel_proyectos_alumnos rpa_ocupado
          INNER JOIN app.proyectos p_ocupado
              ON p_ocupado.id_proyecto = rpa_ocupado.proyecto_id
          WHERE rpa_ocupado.alumno_id = a.id_alumno
            AND p_ocupado.curso_academico = :curso_academico
            AND p_ocupado.estado = 'activo'
            AND p_ocupado.id_proyecto <> :proyecto_id
      )
    ORDER BY a.nombre, a.apellidos
");
$stmt->execute([
    ':curso_academico' => $cursoAcademico,
    ':profesor_id' => $profesorId,
    ':proyecto_id' => $proyectoId,
]);
$alumnosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ciclos = [];
foreach ($grupos as $grupo) {
    $ciclos[(int) $grupo['id_ciclo']] = [
        'id_ciclo' => (int) $grupo['id_ciclo'],
        'abr' => (string) $grupo['abr'],
        'nombre' => (string) $grupo['nombre'],
    ];
}

$grupoSeleccionado = (int) $proyecto['grupo_id'];
$cicloSeleccionado = 0;
foreach ($grupos as $grupo) {
    if ((int) $grupo['id_grupo'] === $grupoSeleccionado) {
        $cicloSeleccionado = (int) $grupo['id_ciclo'];
        break;
    }
}
?>

<script>
window.PAGE_TITLE = '<?= $proyectoId > 0 ? 'Editar projecte' : 'Nou projecte' ?>';
</script>
<style>
.alumne-opcio { display: flex; }
.alumne-opcio.d-none { display: none !important; }
</style>

<div class="container-fluid py-4">
    <div class="mb-3">
        <h1 class="h3 mb-1"><?= $proyectoId > 0 ? 'Editar projecte' : 'Nou projecte' ?></h1>
        <p class="text-muted mb-0">Crea el projecte i selecciona l’alumnat disponible del grup.</p>
    </div>
    <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">

        <?php if ($grupos === []): ?>
            <div class="alert alert-warning mb-0">No tens cap grup assignat durant el curs <?= htmlspecialchars($cursoAcademico, ENT_QUOTES, 'UTF-8') ?>.</div>
        <?php else: ?>
            <form method="post" action="/index.php?main=projectes-grup_accion" id="projecte-grup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accio" value="guardar">
                <input type="hidden" name="id_proyecto" value="<?= (int) $proyecto['id_proyecto'] ?>">

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="ciclo_id" class="form-label">Cicle</label>
                        <select id="ciclo_id" class="form-select" required>
                            <option value="" <?= $cicloSeleccionado === 0 ? 'selected' : '' ?> disabled>Selecciona un cicle</option>
                            <?php foreach ($ciclos as $ciclo): ?>
                                <option value="<?= $ciclo['id_ciclo'] ?>" <?= $cicloSeleccionado === $ciclo['id_ciclo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ciclo['abr'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="grupo_id" class="form-label">Grup classe</label>
                        <select name="grupo_id" id="grupo_id" class="form-select" required>
                            <option value="" <?= $grupoSeleccionado === 0 ? 'selected' : '' ?>>Selecciona un grup</option>
                            <?php foreach ($grupos as $grupo): ?>
                                <option
                                    value="<?= (int) $grupo['id_grupo'] ?>"
                                    data-ciclo="<?= (int) $grupo['id_ciclo'] ?>"
                                    <?= $grupoSeleccionado === (int) $grupo['id_grupo'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($grupo['abr'] . ' ' . $grupo['grupo'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estat</label>
                        <select name="estado" id="estado" class="form-select" required>
                            <option value="activo" <?= $proyecto['estado'] === 'activo' ? 'selected' : '' ?>>Actiu</option>
                            <option value="inactivo" <?= $proyecto['estado'] !== 'activo' ? 'selected' : '' ?>>Inactiu</option>
                        </select>
                    </div>
                </div>

                <div class="border-top pt-4 mb-4">
                    <div class="mb-3">
                        <h6 class="mb-1">Alumnat del projecte</h6>
                        <p class="text-muted mb-0">Selecciona una o més persones del grup que encara no formen part d’un altre projecte actiu. Només els projectes inactius poden quedar sense alumnat.</p>
                    </div>

                    <div id="alumnes-container" class="border rounded overflow-hidden">
                        <?php foreach ($alumnosDisponibles as $alumno): ?>
                            <?php $alumnoId = (int) $alumno['id_alumno']; ?>
                            <label class="alumne-opcio align-items-center gap-3 px-3 py-3 border-bottom <?= (int) $alumno['grupo_id'] === $grupoSeleccionado ? '' : 'd-none' ?>" data-grupo="<?= (int) $alumno['grupo_id'] ?>">
                                <input
                                    class="form-check-input flex-shrink-0 mt-0"
                                    type="checkbox"
                                    name="alumno_ids[]"
                                    value="<?= $alumnoId ?>"
                                    <?= in_array($alumnoId, $alumnoIdsProyecto, true) ? 'checked' : '' ?>
                                >
                                <span>
                                    <span class="d-block fw-semibold"><?= htmlspecialchars(trim($alumno['nombre'] . ' ' . $alumno['apellidos']), ENT_QUOTES, 'UTF-8') ?></span>
                                    <small class="text-muted"><?= htmlspecialchars((string) $alumno['email'], ENT_QUOTES, 'UTF-8') ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <div id="alumnes-buit" class="px-3 py-4 text-muted">
                            <?= $grupoSeleccionado > 0 ? 'No hi ha alumnat disponible en aquest grup.' : 'Selecciona primer un grup.' ?>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-4 mb-4">
                    <h6 class="mb-1">Tutor principal</h6>
                    <p class="text-muted mb-3">És opcional i es pot decidir més endavant.</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="tutor_id" id="tutor_cap" value="" <?= (int) $proyecto['tutor_id'] === 0 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tutor_cap">Sense assignar</label>
                    </div>
                    <div id="tutors-container">
                        <?php foreach ($profesoresPorGrupo as $grupoId => $profesoresGrupo): ?>
                            <?php foreach ($profesoresGrupo as $profesor): ?>
                                <?php $radioId = 'tutor_' . (int) $grupoId . '_' . (int) $profesor['id_profesor']; ?>
                                <div class="form-check tutor-opcio" data-grupo="<?= (int) $grupoId ?>">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="tutor_id"
                                        id="<?= $radioId ?>"
                                        value="<?= (int) $profesor['id_profesor'] ?>"
                                        <?= (int) $proyecto['tutor_id'] === (int) $profesor['id_profesor'] ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="<?= $radioId ?>">
                                        <?= htmlspecialchars(trim($profesor['nombre'] . ' ' . $profesor['apellidos']), ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-puig-solid px-4">Guardar</button>
                    <a href="/index.php?main=projectes-grup" class="btn btn-puig px-4">Tornar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(() => {
    const ciclo = document.getElementById('ciclo_id');
    const grupo = document.getElementById('grupo_id');
    const container = document.getElementById('alumnes-container');
    const vacio = document.getElementById('alumnes-buit');
    if (!ciclo || !grupo || !container || !vacio) return;

    const actualizarAlumnos = () => {
        const grupoId = grupo.value;
        let visibles = 0;
        container.querySelectorAll('.alumne-opcio').forEach((opcion) => {
            const visible = opcion.dataset.grupo === grupoId;
            const checkbox = opcion.querySelector('input');
            opcion.classList.toggle('d-none', !visible);
            checkbox.disabled = !visible;
            if (!visible) checkbox.checked = false;
            if (visible) visibles++;
        });
        vacio.textContent = grupoId === ''
            ? 'Selecciona primer un grup.'
            : 'No hi ha alumnat disponible en aquest grup.';
        vacio.hidden = visibles > 0;
    };

    const actualizarTutors = () => {
        const grupoId = grupo.value;
        document.querySelectorAll('.tutor-opcio').forEach((opcion) => {
            const visible = opcion.dataset.grupo === grupoId;
            opcion.hidden = !visible;
            opcion.querySelector('input').disabled = !visible;
        });
        const seleccionado = document.querySelector('input[name="tutor_id"]:checked');
        if (seleccionado && seleccionado.disabled) document.getElementById('tutor_cap').checked = true;
    };

    const actualizarGrupos = () => {
        const cicloId = ciclo.value;
        Array.from(grupo.options).forEach((opcion) => {
            const visible = opcion.value === '' || opcion.dataset.ciclo === cicloId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
        });
        if (grupo.selectedOptions[0]?.disabled) grupo.value = '';
        actualizarTutors();
        actualizarAlumnos();
    };

    ciclo.addEventListener('change', actualizarGrupos);
    grupo.addEventListener('change', () => {
        actualizarTutors();
        actualizarAlumnos();
    });

    actualizarGrupos();
})();
</script>
