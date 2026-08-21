<?php
declare(strict_types=1);

soloSuperadmin();

// Els cicles procedeixen del catàleg i defineixen els filtres del panell.
$ciclos = $pdo->query("
    SELECT id_ciclo, abr, color
    FROM app.ciclos
    ORDER BY orden, abr
")->fetchAll(PDO::FETCH_ASSOC);
$cicloIdsValidos = array_map('intval', array_column($ciclos, 'id_ciclo'));
$cicloId = isset($_GET['ciclo_id']) ? (int) $_GET['ciclo_id'] : 0;
if (!in_array($cicloId, $cicloIdsValidos, true)) {
    $cicloId = 0;
}

// El curso vigente es el filtro inicial, conservando el acceso al histórico.
$cursoActual = cursoAcademicoActual();
$cursos = $pdo->query("SELECT DISTINCT curso_academico FROM app.proyectos WHERE curso_academico ~ '^[0-9]{4}-[0-9]{2}$' ORDER BY curso_academico DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($cursoActual, $cursos, true)) array_unshift($cursos, $cursoActual);
$curso = isset($_GET['curso']) && is_string($_GET['curso']) ? trim($_GET['curso']) : $cursoActual;
if (!in_array($curso, $cursos, true)) $curso = $cursoActual;

// La vista combina el grupo nuevo con los campos heredados del histórico.
$sql = "
    SELECT
        p.id_proyecto,
        p.nombre,
        p.curso_academico,
        p.estado,
        cg.abr AS ciclo,
        cg.color,
        g.grupo,
        COALESCE((
            SELECT STRING_AGG(TRIM(a.nombre || ' ' || a.apellidos), ', '
                              ORDER BY a.apellidos, a.nombre)
            FROM app.rel_proyectos_alumnos rpa
            INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
            WHERE rpa.proyecto_id = p.id_proyecto
        ), '') AS alumnos,
        COALESCE((
            SELECT STRING_AGG(
                TRIM(pr.nombre || ' ' || pr.apellidos)
                    || CASE WHEN rpp.rol = 'tutor' THEN ' (tutor)' ELSE '' END,
                ', ' ORDER BY (rpp.rol = 'tutor') DESC, pr.apellidos, pr.nombre
            )
            FROM app.rel_proyectos_profesores rpp
            INNER JOIN app.profesores pr ON pr.id_profesor = rpp.profesor_id
            WHERE rpp.proyecto_id = p.id_proyecto
        ), '') AS profesores
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos cg ON cg.id_ciclo = g.id_ciclo
";
$where = ['p.curso_academico = :curso'];
$params = [':curso' => $curso];
if ($cicloId > 0) {
    $where[] = 'cg.id_ciclo = :ciclo_id';
    $params[':ciclo_id'] = $cicloId;
}
$sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " ORDER BY p.curso_academico DESC, cg.orden, g.grupo, alumnos";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$mensaje = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
$error = $_SESSION['proyectos_admin_error'] ?? '';
unset($_SESSION['proyectos_admin_error']);
?>

<script>
window.PAGE_TITLE = 'Projectes';
</script>

<style>
.projectes-admin-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}

.projectes-admin-table {
    min-width: 1050px;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Projectes</h1>
            <p class="text-muted mb-0">Visió general i administració excepcional de tots els projectes.</p>
        </div>
        <span class="text-muted"><?= count($proyectos) ?> projectes</span>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($mensaje === 'guardat'): ?>
        <div class="alert alert-success">Projecte guardat correctament.</div>
    <?php elseif ($mensaje === 'eliminat'): ?>
        <div class="alert alert-success">Projecte eliminat correctament.</div>
    <?php endif; ?>

    <nav class="projectes-filter mb-4" aria-label="Filtrar per cicle">
        <div class="d-flex flex-wrap gap-2">
            <a href="/index.php?main=proyectos&amp;curso=<?= rawurlencode($curso) ?>" class="projectes-filter-pill <?= $cicloId === 0 ? 'active' : '' ?>">Tots</a>
            <?php foreach ($ciclos as $ciclo): ?>
                <a href="/index.php?main=proyectos&amp;curso=<?= rawurlencode($curso) ?>&amp;ciclo_id=<?= (int) $ciclo['id_ciclo'] ?>"
                   class="projectes-filter-pill <?= $cicloId === (int) $ciclo['id_ciclo'] ? 'active' : '' ?>">
                    <?= htmlspecialchars((string) $ciclo['abr'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <form method="get" action="/index.php" class="row g-2 align-items-end mb-4">
        <input type="hidden" name="main" value="proyectos">
        <input type="hidden" name="ciclo_id" value="<?= $cicloId ?>">
        <div class="col-sm-6 col-md-4 col-lg-3">
            <label for="curso" class="form-label">Curs acadèmic</label>
            <select name="curso" id="curso" class="form-select" onchange="this.form.submit()">
                <?php foreach ($cursos as $cursoDisponible): ?>
                    <option value="<?= htmlspecialchars((string) $cursoDisponible, ENT_QUOTES, 'UTF-8') ?>" <?= $cursoDisponible === $curso ? 'selected' : '' ?>><?= htmlspecialchars((string) $cursoDisponible, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><div class="col-auto"><button class="btn btn-puig" type="submit">Aplicar</button></div></noscript>
    </form>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 projectes-admin-table">
                <colgroup>
                    <col style="width: 12%">
                    <col style="width: 25%">
                    <col style="width: 27%">
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 16%">
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Grup classe</th>
                        <th>Alumnat del projecte</th>
                        <th>Professorat</th>
                        <th>Curs</th>
                        <th>Estat</th>
                        <th class="text-end pe-4">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($proyectos === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No hi ha projectes per al filtre seleccionat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($proyectos as $proyecto): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge rounded-pill border px-3 py-2 <?= clasesColorCiclo((string) $proyecto['color']) ?>">
                                        <?= htmlspecialchars(trim((string) $proyecto['ciclo'] . ' ' . (string) $proyecto['grupo']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars((string) ($proyecto['alumnos'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ((string) $proyecto['nombre'] !== ''): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $proyecto['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($proyecto['profesores'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $proyecto['curso_academico'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $proyecto['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-nowrap text-end pe-4">
                                    <form method="post" action="/index.php?main=proyectos_accion" class="btn-group btn-group-sm"
                                          onsubmit="return confirm('Segur que vols eliminar aquest projecte?')">
                                        <a href="/index.php?main=proyectos_form&amp;id=<?= (int) $proyecto['id_proyecto'] ?>&amp;return_curso=<?= rawurlencode($curso) ?>&amp;return_ciclo_id=<?= $cicloId ?>"
                                           class="btn btn-outline-primary">Editar</a>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="accion" value="borrar">
                                        <input type="hidden" name="id_proyecto" value="<?= (int) $proyecto['id_proyecto'] ?>">
                                        <input type="hidden" name="return_ciclo_id" value="<?= $cicloId ?>">
                                        <input type="hidden" name="return_curso" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
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
