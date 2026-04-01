<?php soloSuperadmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$data = [
    'id_profesor' => 0,
    'nombre' => '',
    'apellidos' => '',
    'email' => '',
    'departamento' => '',
    'activo' => 1,
    'uuid_acceso' => '',
    'rol' => '',
];

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT
            id_profesor,
            nombre,
            apellidos,
            email,
            departamento,
            activo,
            uuid_acceso,
            rol
        FROM app.profesores
        WHERE id_profesor = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $data = $row;
    } else {
        die('Professor no trobat');
    }
}

$isEdit = $data['id_profesor'] > 0;
?>

<script>
window.PAGE_TITLE = '<?= $isEdit ? 'Editar professor' : 'Nou professor' ?>';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card-style mb-30">
                <h6 class="mb-3"><?= $isEdit ? 'Editar professor' : 'Nou professor' ?></h6>

                <form method="post" action="index.php?main=profesor_accion&raw=1">
                    <input type="hidden" name="id_profesor" value="<?= (int)$data['id_profesor'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= htmlspecialchars((string)$data['nombre']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cognoms</label>
                        <input type="text" name="apellidos" class="form-control"
                               value="<?= htmlspecialchars((string)$data['apellidos']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars((string)$data['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Departament</label>
                        <input type="text" name="departamento" class="form-control"
                               value="<?= htmlspecialchars((string)$data['departamento']) ?>">
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo"
                               value="1" <?= (int)$data['activo'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">
                            Actiu
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="superadmin" id="superadmin"
                               value="1" <?= $data['rol'] === 'superadmin' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="superadmin">
                            Superadmin
                        </label>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="mb-3">
                            <label class="form-label">UUID accés</label>
                            <input type="text" class="form-control"
                                   value="<?= htmlspecialchars((string)$data['uuid_acceso']) ?>" disabled>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" class="main-btn primary-btn btn-hover">
                            Guardar
                        </button>
                        <a href="index.php?main=profesor" class="main-btn light-btn btn-hover">
                            Tornar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>