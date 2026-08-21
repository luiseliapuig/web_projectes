<?php
declare(strict_types=1);

// Listado de proyectos vinculados al profesor durante el curso vigente.
$profesorId = (int) ($_SESSION['professor_id'] ?? 0);
$cursoAcademico = cursoAcademicoActual();

$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr, c.orden
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo = rpg.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE rpg.profesor_id = :profesor_id
      AND rpg.curso_academico = :curso_academico
      AND c.activo = true
    ORDER BY c.orden, c.abr, g.grupo
");
$stmt->execute([':profesor_id' => $profesorId, ':curso_academico' => $cursoAcademico]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ciclos = [];
foreach ($grupos as $grupo) {
    $ciclos[(int) $grupo['id_ciclo']] = ['id_ciclo' => (int) $grupo['id_ciclo'], 'abr' => (string) $grupo['abr']];
}

$preferenciasTutor = isset($_SESSION['tutor_filtres']) && is_array($_SESSION['tutor_filtres'])
    ? $_SESSION['tutor_filtres']
    : [];
$filtrosCurso = isset($preferenciasTutor['por_curso'][$cursoAcademico]) && is_array($preferenciasTutor['por_curso'][$cursoAcademico])
    ? $preferenciasTutor['por_curso'][$cursoAcademico]
    : [];
$filtrosEnPeticion = array_key_exists('ciclo_id', $_GET) || array_key_exists('grupo_id', $_GET);
$cicloId = $filtrosEnPeticion ? (int) ($_GET['ciclo_id'] ?? 0) : (int) ($filtrosCurso['ciclo_id'] ?? 0);
$grupoId = $filtrosEnPeticion ? (int) ($_GET['grupo_id'] ?? 0) : (int) ($filtrosCurso['grupo_id'] ?? 0);
if ($cicloId > 0 && !isset($ciclos[$cicloId])) {
    $cicloId = 0;
}
$grupoIds = array_map('intval', array_column($grupos, 'id_grupo'));
if ($grupoId > 0 && !in_array($grupoId, $grupoIds, true)) {
    $grupoId = 0;
}
if ($grupoId > 0) {
    foreach ($grupos as $grupo) {
        if ((int) $grupo['id_grupo'] === $grupoId && $cicloId > 0 && (int) $grupo['id_ciclo'] !== $cicloId) {
            $grupoId = 0;
            break;
        }
    }
}
$_SESSION['tutor_filtres']['curso'] = $cursoAcademico;
$_SESSION['tutor_filtres']['por_curso'][$cursoAcademico] = ['ciclo_id' => $cicloId, 'grupo_id' => $grupoId];

$sql = "
    SELECT p.id_proyecto, p.estado, c.abr AS ciclo, c.color, g.grupo,
           COALESCE(STRING_AGG(TRIM(a.nombre || ' ' || a.apellidos), ', ' ORDER BY a.nombre, a.apellidos), '') AS alumnos,
           TRIM(pt.nombre || ' ' || pt.apellidos) AS tutor
    FROM app.proyectos p
    INNER JOIN app.rel_proyectos_profesores acceso ON acceso.proyecto_id = p.id_proyecto AND acceso.profesor_id = :profesor_id
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    LEFT JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
    LEFT JOIN app.rel_proyectos_profesores relacion_tutor ON relacion_tutor.proyecto_id = p.id_proyecto AND relacion_tutor.rol = 'tutor'
    LEFT JOIN app.profesores pt ON pt.id_profesor = relacion_tutor.profesor_id
    WHERE p.curso_academico = :curso_academico
";
$params = [':profesor_id' => $profesorId, ':curso_academico' => $cursoAcademico];
if ($cicloId > 0) {
    $sql .= ' AND c.id_ciclo = :ciclo_id';
    $params[':ciclo_id'] = $cicloId;
}
if ($grupoId > 0) {
    $sql .= ' AND g.id_grupo = :grupo_id';
    $params[':grupo_id'] = $grupoId;
}
$sql .= ' GROUP BY p.id_proyecto, p.estado, c.orden, c.abr, c.color, g.grupo, pt.nombre, pt.apellidos
          ORDER BY c.orden, c.abr, g.grupo, alumnos';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mensajes breves devueltos por la acción de guardado.
$error = $_SESSION['projectes_grup_error'] ?? '';
unset($_SESSION['projectes_grup_error']);
$warning = $_SESSION['projectes_grup_warning'] ?? '';
unset($_SESSION['projectes_grup_warning']);
$notice = $_SESSION['projectes_grup_notice'] ?? '';
unset($_SESSION['projectes_grup_notice']);
$mensaje = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
?>

<script>
window.PAGE_TITLE = 'Administrar projectes';
</script>

<style>
.projectes-grup-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}

.projectes-grup-table {
    min-width: 820px;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Administrar projectes</h1>
            <p class="text-muted mb-0">Crea i gestiona els projectes dels grups que tens assignats.</p>
        </div>
        <a href="/index.php?main=projectes-grup_form" class="btn btn-puig-solid rounded-pill px-4">Nou projecte</a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (is_string($warning) && $warning !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (is_string($notice) && $notice !== ''): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($mensaje === 'guardat'): ?>
        <div class="alert alert-success" role="alert">Projecte guardat correctament.</div>
    <?php endif; ?>

    <?php if ($mensaje === 'eliminat'): ?>
        <div class="alert alert-success" role="alert">Projecte eliminat correctament.</div>
    <?php endif; ?>

    <form method="get" class="row g-2 align-items-end mb-3" id="projectes-grup-filtres">
        <input type="hidden" name="main" value="projectes-grup">
        <div class="col-sm-4 col-lg-3">
            <label for="ciclo_id" class="form-label">Cicle</label>
            <select name="ciclo_id" id="ciclo_id" class="form-select">
                <option value="0">Tots</option>
                <?php foreach ($ciclos as $ciclo): ?>
                    <option value="<?= $ciclo['id_ciclo'] ?>" <?= $cicloId === $ciclo['id_ciclo'] ? 'selected' : '' ?>><?= htmlspecialchars($ciclo['abr'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4 col-lg-3">
            <label for="grupo_id" class="form-label">Grup</label>
            <select name="grupo_id" id="grupo_id" class="form-select">
                <option value="0">Tots</option>
                <?php foreach ($grupos as $grupo): ?>
                    <option value="<?= (int) $grupo['id_grupo'] ?>" data-ciclo="<?= (int) $grupo['id_ciclo'] ?>" <?= $grupoId === (int) $grupo['id_grupo'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($grupo['abr'] . ' ' . $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-6 text-lg-end text-muted pb-2">Total: <strong><?= count($proyectos) ?></strong></div>
    </form>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 projectes-grup-table">
                <colgroup>
                    <col style="width: 15%">
                    <col style="width: 36%">
                    <col style="width: 22%">
                    <col style="width: 10%">
                    <col style="width: 17%">
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Grup classe</th>
                        <th>Alumnat del projecte</th>
                        <th>Tutor</th>
                        <th>Estat</th>
                        <th class="text-end pe-4">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($proyectos === []): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">Encara no hi ha projectes en els teus grups.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($proyectos as $proyecto): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge rounded-pill border px-3 py-2 fw-semibold <?= clasesColorCiclo((string) $proyecto['color']) ?>">
                                        <?= htmlspecialchars(trim($proyecto['ciclo'] . ' ' . $proyecto['grupo']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) $proyecto['alumnos'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($proyecto['tutor'] ?: 'Sense assignar'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($proyecto['estado'] === 'activo'): ?>
                                        <span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Actiu</span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Inactiu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <form
                                        method="post"
                                        action="/index.php?main=projectes-grup_accion"
                                        class="btn-group btn-group-sm"
                                        onsubmit="return confirm('Segur que vols eliminar aquest projecte?')"
                                    >
                                        <a href="/index.php?main=projectes-grup_form&amp;id=<?= (int) $proyecto['id_proyecto'] ?>" class="btn btn-outline-primary">Editar</a>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="accio" value="eliminar">
                                        <input type="hidden" name="id_proyecto" value="<?= (int) $proyecto['id_proyecto'] ?>">
                                        <button type="submit" class="btn btn-outline-danger">Borrar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('projectes-grup-filtres');
    const ciclo = document.getElementById('ciclo_id');
    const grupo = document.getElementById('grupo_id');
    if (!form || !ciclo || !grupo) return;
    const actualizarGrupos = () => Array.from(grupo.options).forEach((opcion) => {
        const visible = opcion.value === '0' || ciclo.value === '0' || opcion.dataset.ciclo === ciclo.value;
        opcion.hidden = !visible;
        opcion.disabled = !visible;
    });
    actualizarGrupos();
    ciclo.addEventListener('change', () => { grupo.value = '0'; actualizarGrupos(); form.submit(); });
    grupo.addEventListener('change', () => form.submit());
})();
</script>
