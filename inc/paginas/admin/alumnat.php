<?php
declare(strict_types=1);

soloSuperadmin();

// Cursos disponibles: se conserva el histórico y se añade siempre el vigente.
$cursoActual = cursoAcademicoActual();
$cursos = $pdo->query("
    SELECT curso_academico
    FROM (
        SELECT curso_academico FROM app.rel_alumnos_grupos
        UNION
        SELECT curso_academico FROM app.alumnos
    ) cursos
    WHERE curso_academico ~ '^[0-9]{4}-[0-9]{2}$'
    ORDER BY curso_academico DESC
")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($cursoActual, $cursos, true)) {
    array_unshift($cursos, $cursoActual);
}

$curso = isset($_GET['curso']) && is_string($_GET['curso'])
    ? trim($_GET['curso'])
    : $cursoActual;
if (!in_array($curso, $cursos, true)) {
    $curso = $cursoActual;
}

// Los filtros académicos proceden del catálogo, no de listas codificadas.
$ciclos = $pdo->query("
    SELECT id_ciclo, abr, nombre
    FROM app.ciclos
    ORDER BY orden, abr
")->fetchAll(PDO::FETCH_ASSOC);
$grupos = $pdo->query("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr
    FROM app.grupos g
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    ORDER BY c.orden, c.abr, g.grupo
")->fetchAll(PDO::FETCH_ASSOC);

$cicloId = isset($_GET['ciclo_id']) ? (int) $_GET['ciclo_id'] : 0;
$grupoId = isset($_GET['grupo_id']) ? (int) $_GET['grupo_id'] : 0;
$cicloIdsValidos = array_map('intval', array_column($ciclos, 'id_ciclo'));
$grupoIdsValidos = array_map('intval', array_column($grupos, 'id_grupo'));
if (!in_array($cicloId, $cicloIdsValidos, true)) {
    $cicloId = 0;
}
if (!in_array($grupoId, $grupoIdsValidos, true)) {
    $grupoId = 0;
}

// Las matrículas nuevas son la fuente principal. El segundo bloque mantiene
// visibles registros heredados que no pudieron relacionarse con un grupo.
$sql = "
    WITH matriculas AS (
        SELECT
            a.id_alumno, a.nombre, a.apellidos, a.email, a.activo,
            rag.curso_academico, g.id_grupo, c.id_ciclo, c.abr AS ciclo,
            g.grupo, c.color, c.orden
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        INNER JOIN app.grupos g ON g.id_grupo = rag.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo

        UNION ALL

        SELECT
            a.id_alumno, a.nombre, a.apellidos, a.email, a.activo,
            a.curso_academico, g.id_grupo, c.id_ciclo, a.ciclo,
            a.grupo, COALESCE(c.color, 'secondary'), COALESCE(c.orden, 999)
        FROM app.alumnos a
        LEFT JOIN app.ciclos c ON c.abr = a.ciclo
        LEFT JOIN app.grupos g
            ON g.id_ciclo = c.id_ciclo
           AND g.grupo IS NOT DISTINCT FROM a.grupo
        WHERE NOT EXISTS (
            SELECT 1
            FROM app.rel_alumnos_grupos rag
            WHERE rag.alumno_id = a.id_alumno
              AND rag.curso_academico = a.curso_academico
        )
    )
    SELECT m.*,
           (SELECT COUNT(*) FROM app.rel_proyectos_alumnos rpa WHERE rpa.alumno_id = m.id_alumno) AS proyectos
    FROM matriculas m
    WHERE m.curso_academico = :curso_academico
";
$params = [':curso_academico' => $curso];
if ($cicloId > 0) {
    $sql .= " AND m.id_ciclo = :ciclo_id";
    $params[':ciclo_id'] = $cicloId;
}
if ($grupoId > 0) {
    $sql .= " AND m.id_grupo = :grupo_id";
    $params[':grupo_id'] = $grupoId;
}
$sql .= " ORDER BY m.orden, m.ciclo, m.grupo, m.apellidos, m.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = $_SESSION['alumnat_error'] ?? '';
unset($_SESSION['alumnat_error']);
$mensaje = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
?>

<script>
window.PAGE_TITLE = 'Alumnat';
</script>

<style>
.alumnat-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}

.alumnat-table {
    min-width: 760px;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Alumnat</h1>
            <p class="text-muted mb-0">Matrícules i dades de l’alumnat per curs acadèmic.</p>
        </div>
        <a href="/index.php?main=alumnat_form&amp;curso=<?= rawurlencode($curso) ?>" class="btn btn-puig-solid rounded-pill px-4">Nou alumne</a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($mensaje === 'guardat'): ?>
        <div class="alert alert-success" role="alert">Alumne guardat correctament.</div>
    <?php elseif ($mensaje === 'eliminat'): ?>
        <div class="alert alert-success" role="alert">Alumne eliminat correctament.</div>
    <?php endif; ?>

    <form method="get" class="row g-2 align-items-end mb-4" id="filtres-alumnat">
            <input type="hidden" name="main" value="alumnat">
            <div class="col-sm-4 col-lg-3">
                <label for="curso" class="form-label">Curs acadèmic</label>
                <select name="curso" id="curso" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($cursos as $cursoOpcion): ?>
                        <option value="<?= htmlspecialchars((string) $cursoOpcion, ENT_QUOTES, 'UTF-8') ?>" <?= $curso === $cursoOpcion ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $cursoOpcion, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-lg-3">
                <label for="ciclo_id" class="form-label">Cicle</label>
                <select name="ciclo_id" id="ciclo_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">Tots</option>
                    <?php foreach ($ciclos as $ciclo): ?>
                        <option value="<?= (int) $ciclo['id_ciclo'] ?>" <?= $cicloId === (int) $ciclo['id_ciclo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $ciclo['abr'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-lg-3">
                <label for="grupo_id" class="form-label">Grup</label>
                <select name="grupo_id" id="grupo_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">Tots</option>
                    <?php foreach ($grupos as $grupo): ?>
                        <option value="<?= (int) $grupo['id_grupo'] ?>" <?= $grupoId === (int) $grupo['id_grupo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim($grupo['abr'] . ' ' . $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 text-lg-end text-muted pb-2">Total: <strong><?= count($alumnos) ?></strong></div>
    </form>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 alumnat-table">
                <colgroup>
                    <col style="width: 25%">
                    <col style="width: 35%">
                    <col style="width: 18%">
                    <col style="width: 8%">
                    <col style="width: 14%">
                </colgroup>
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Nom</th>
                        <th>Email</th>
                        <th>Grup</th>
                        <th class="text-center">Actiu</th>
                        <th class="text-end pe-4">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alumnos === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">No hi ha alumnes amb aquests filtres.</td></tr>
                    <?php else: ?>
                        <?php foreach ($alumnos as $alumno): ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?= htmlspecialchars(trim($alumno['apellidos'] . ', ' . $alumno['nombre']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $alumno['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge rounded-pill border px-3 py-2 fw-semibold <?= clasesColorCiclo((string) $alumno['color']) ?>">
                                        <?= htmlspecialchars(trim((string) $alumno['ciclo'] . ' ' . (string) $alumno['grupo']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ((int) $alumno['activo'] === 1): ?>
                                        <i class="bi bi-check-circle-fill text-success" title="Actiu" aria-hidden="true"></i><span class="visually-hidden">Actiu</span>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger" title="Inactiu" aria-hidden="true"></i><span class="visually-hidden">Inactiu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <form method="post" action="/index.php?main=alumnat_accion" class="btn-group btn-group-sm" onsubmit="return confirm('Segur que vols eliminar aquest alumne?')">
                                        <a href="/index.php?main=alumnat_form&amp;id=<?= (int) $alumno['id_alumno'] ?>&amp;curso=<?= rawurlencode($curso) ?>"
                                           class="btn btn-outline-primary">Editar</a>
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="accio" value="eliminar">
                                        <input type="hidden" name="id_alumno" value="<?= (int) $alumno['id_alumno'] ?>">
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
