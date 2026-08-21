<?php
declare(strict_types=1);

soloSuperadmin();

// Opciones cerradas admitidas tanto por el formulario como por la acción.
$departamentos = ['Informàtica', 'Administració i gestió', 'Altres'];
$cursoAcademico = cursoAcademicoActual();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$profesor = [
    'id_profesor' => 0,
    'nombre' => '',
    'apellidos' => '',
    'email' => '',
    'departamento' => 'Altres',
    'activo' => 1,
    'rol' => '',
];

// En edición, los datos siempre se recuperan por el identificador interno.
if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT id_profesor, nombre, apellidos, email, departamento, activo, rol
        FROM app.profesores
        WHERE id_profesor = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['professorat_error'] = 'Professor no trobat.';
        echo '<script>location.href="/index.php?main=professorat";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=/index.php?main=professorat"></noscript>';
        exit;
    }

    $profesor = $row;
    if (!in_array($profesor['departamento'], $departamentos, true)) {
        $profesor['departamento'] = 'Altres';
    }
}

$isEdit = (int) $profesor['id_profesor'] > 0;

// Grupos disponibles y asignaciones del profesor para el curso vigente.
$stmt = $pdo->prepare("
    SELECT g.id_grupo, c.abr AS ciclo, c.orden AS ciclo_orden, g.grupo, g.torn
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
    ORDER BY c.orden, c.abr, g.torn, g.grupo
");
$stmt->execute([
    ':profesor_id' => (int) $profesor['id_profesor'],
    ':curso_academico' => $cursoAcademico,
]);
$gruposDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$gruposSeleccionados = [];

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT grupo_id
        FROM app.rel_profesores_grupos
        WHERE profesor_id = :profesor_id
          AND curso_academico = :curso_academico
    ");
    $stmt->execute([
        ':profesor_id' => (int) $profesor['id_profesor'],
        ':curso_academico' => $cursoAcademico,
    ]);
    $gruposSeleccionados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
?>

<script>
window.PAGE_TITLE = '<?= $isEdit ? 'Editar professor' : 'Nou professor' ?>';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card-style mb-30">
                <h6 class="mb-3"><?= $isEdit ? 'Editar professor' : 'Nou professor' ?></h6>

                <form method="post" action="/index.php?main=professorat_accion">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_profesor" value="<?= (int) $profesor['id_profesor'] ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nom</label>
                        <input id="nombre" type="text" name="nombre" class="form-control"
                               value="<?= htmlspecialchars((string) $profesor['nombre'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="apellidos" class="form-label">Cognoms</label>
                        <input id="apellidos" type="text" name="apellidos" class="form-control"
                               value="<?= htmlspecialchars((string) $profesor['apellidos'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars((string) $profesor['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="departamento" class="form-label">Departament</label>
                        <select id="departamento" name="departamento" class="form-select" required>
                            <?php foreach ($departamentos as $departamento): ?>
                                <option value="<?= htmlspecialchars($departamento, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $profesor['departamento'] === $departamento ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($departamento, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <fieldset class="mb-3">
                        <legend class="form-label fw-semibold mb-2">
                            Grups on imparteix classe · <?= htmlspecialchars($cursoAcademico, ENT_QUOTES, 'UTF-8') ?>
                        </legend>
                        <div class="row g-2">
                            <?php foreach ($gruposDisponibles as $grupo): ?>
                                <?php
                                $grupoId = (int) $grupo['id_grupo'];
                                $etiquetaGrupo = trim($grupo['ciclo'] . ' ' . ($grupo['grupo'] ?? ''));
                                ?>
                                <div class="col-sm-6 col-md-4">
                                    <div class="form-check border rounded p-2 ps-5 h-100">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="grupo_ids[]"
                                            id="grupo_<?= $grupoId ?>"
                                            value="<?= $grupoId ?>"
                                            <?= in_array($grupoId, $gruposSeleccionados, true) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="grupo_<?= $grupoId ?>">
                                            <span class="fw-semibold"><?= htmlspecialchars($etiquetaGrupo, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-muted small">· <?= htmlspecialchars((string) $grupo['torn'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo"
                               value="1" <?= (int) $profesor['activo'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Actiu</label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="superadmin" id="superadmin"
                               value="1" <?= $profesor['rol'] === 'superadmin' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="superadmin">Superadmin</label>
                    </div>

                    <?php if (!$isEdit): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="enviar_invitacion" id="enviar_invitacion" value="1" checked>
                        <label class="form-check-label" for="enviar_invitacion">
                            Enviar invitació
                            <span class="d-block small text-muted">Rebrà un enllaç de cinc hores per crear la primera contrasenya.</span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" class="main-btn primary-btn btn-hover">Guardar</button>
                        <a href="/index.php?main=professorat" class="main-btn light-btn btn-hover">Tornar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
