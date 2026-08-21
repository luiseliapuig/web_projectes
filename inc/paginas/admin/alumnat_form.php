<?php
declare(strict_types=1);

soloSuperadmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cursoSolicitado = isset($_GET['curso']) && is_string($_GET['curso'])
    ? trim($_GET['curso'])
    : cursoAcademicoActual();
if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $cursoSolicitado)) {
    $cursoSolicitado = cursoAcademicoActual();
}

$grupos = $pdo->query("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr, c.nombre, c.orden
    FROM app.grupos g
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    ORDER BY c.orden, c.abr, g.grupo
")->fetchAll(PDO::FETCH_ASSOC);
$ciclos = [];
foreach ($grupos as $grupo) {
    $ciclos[(int) $grupo['id_ciclo']] = [
        'id_ciclo' => (int) $grupo['id_ciclo'],
        'abr' => (string) $grupo['abr'],
        'nombre' => (string) $grupo['nombre'],
    ];
}

$data = [
    'id_alumno' => 0,
    'nombre' => '',
    'apellidos' => '',
    'email' => '',
    'activo' => 1,
    'curso_academico' => $cursoSolicitado,
    'grupo_id' => (int) ($grupos[0]['id_grupo'] ?? 0),
];

// Se carga la matrícula del curso solicitado; para datos antiguos se intenta
// resolver el grupo desde las columnas de compatibilidad.
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT
            a.id_alumno, a.nombre, a.apellidos, a.email, a.activo,
            :curso_academico AS curso_academico,
            COALESCE(
                rag.grupo_id,
                (
                    SELECT g.id_grupo
                    FROM app.grupos g
                    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
                    WHERE c.abr = a.ciclo
                      AND g.grupo IS NOT DISTINCT FROM a.grupo
                      AND a.curso_academico = :curso_academico
                    LIMIT 1
                )
            ) AS grupo_id
        FROM app.alumnos a
        LEFT JOIN app.rel_alumnos_grupos rag
            ON rag.alumno_id = a.id_alumno
           AND rag.curso_academico = :curso_academico
        WHERE a.id_alumno = :id_alumno
          AND (rag.alumno_id IS NOT NULL OR a.curso_academico = :curso_academico)
        LIMIT 1
    ");
    $stmt->execute([
        ':curso_academico' => $cursoSolicitado,
        ':id_alumno' => $id,
    ]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$alumno) {
        http_response_code(404);
        echo '<div class="container-fluid py-4"><div class="alert alert-danger">Alumne no trobat en aquest curs.</div></div>';
        return;
    }
    $data = $alumno;
}

$grupoSeleccionado = (int) ($data['grupo_id'] ?? 0);
$cicloSeleccionado = 0;
foreach ($grupos as $grupo) {
    if ((int) $grupo['id_grupo'] === $grupoSeleccionado) {
        $cicloSeleccionado = (int) $grupo['id_ciclo'];
        break;
    }
}
if ($cicloSeleccionado === 0 && $ciclos !== []) {
    $cicloSeleccionado = (int) array_key_first($ciclos);
}
$esEdicion = $id > 0;
?>

<script>
window.PAGE_TITLE = '<?= $esEdicion ? 'Editar alumne' : 'Nou alumne' ?>';
</script>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-xl-8">
            <div class="card-style mb-30">
                <div class="mb-4">
                    <h6 class="mb-1"><?= $esEdicion ? 'Editar alumne' : 'Nou alumne' ?></h6>
                    <p class="text-muted mb-0">Dades personals i matrícula acadèmica.</p>
                </div>

                <?php if ($grupos === []): ?>
                    <div class="alert alert-warning mb-0">Cal crear almenys un grup abans de registrar alumnat.</div>
                <?php else: ?>
                    <form method="post" action="/index.php?main=alumnat_accion">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="accio" value="guardar">
                        <input type="hidden" name="id_alumno" value="<?= (int) $data['id_alumno'] ?>">
                        <input type="hidden" name="curso_original" value="<?= htmlspecialchars($cursoSolicitado, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="nombre" class="form-label">Nom</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required value="<?= htmlspecialchars((string) $data['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-7">
                                <label for="apellidos" class="form-label">Cognoms</label>
                                <input type="text" name="apellidos" id="apellidos" class="form-control" maxlength="150" required value="<?= htmlspecialchars((string) $data['apellidos'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" maxlength="255" required value="<?= htmlspecialchars((string) $data['email'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="curso_academico" class="form-label">Curs acadèmic</label>
                                <input type="text" name="curso_academico" id="curso_academico" class="form-control" pattern="[0-9]{4}-[0-9]{2}" maxlength="7" required value="<?= htmlspecialchars((string) $data['curso_academico'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="ciclo_id" class="form-label">Cicle</label>
                                <select id="ciclo_id" class="form-select" required>
                                    <?php foreach ($ciclos as $ciclo): ?>
                                        <option value="<?= $ciclo['id_ciclo'] ?>" <?= $cicloSeleccionado === $ciclo['id_ciclo'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ciclo['abr'] . ' — ' . $ciclo['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="grupo_id" class="form-label">Grup</label>
                                <select name="grupo_id" id="grupo_id" class="form-select" required>
                                    <?php foreach ($grupos as $grupo): ?>
                                        <option value="<?= (int) $grupo['id_grupo'] ?>" data-ciclo="<?= (int) $grupo['id_ciclo'] ?>" <?= $grupoSeleccionado === (int) $grupo['id_grupo'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim($grupo['abr'] . ' ' . $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" <?= (int) $data['activo'] === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="activo">Actiu</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="main-btn primary-btn btn-hover">Guardar</button>
                            <a href="/index.php?main=alumnat&amp;curso=<?= rawurlencode($cursoSolicitado) ?>" class="main-btn light-btn btn-hover">Tornar</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const ciclo = document.getElementById('ciclo_id');
    const grupo = document.getElementById('grupo_id');
    if (!ciclo || !grupo) return;

    const actualizarGrupos = () => {
        let primera = null;
        Array.from(grupo.options).forEach((opcion) => {
            const visible = opcion.dataset.ciclo === ciclo.value;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && primera === null) primera = opcion;
        });
        if (grupo.selectedOptions[0]?.disabled && primera) primera.selected = true;
    };

    ciclo.addEventListener('change', actualizarGrupos);
    actualizarGrupos();
})();
</script>
