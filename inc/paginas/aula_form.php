<?php soloSuperadmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$data = [
    'id_aula' => 0,
    'codigo' => '',
    'nombre' => '',
    'piso' => '',
];

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT
            id_aula,
            codigo,
            nombre,
            piso
        FROM app.aulas
        WHERE id_aula = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        die('Aula no trobada');
    }

    $data = $row;
}

$isEdit = (int)$data['id_aula'] > 0;
?>

<script>
window.PAGE_TITLE = '<?= $isEdit ? 'Editar aula' : 'Nova aula' ?>';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card-style mb-30">
                <h6 class="mb-3"><?= $isEdit ? 'Editar aula' : 'Nova aula' ?></h6>

                <form method="post" action="index.php?main=aula_accion&raw=1">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_aula" value="<?= (int)$data['id_aula'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Codi</label>
                        <input
                            type="text"
                            name="codigo"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['codigo']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['nombre']) ?>"
                            
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pis</label>
                        <input
                            type="text"
                            name="piso"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['piso']) ?>"
                        >
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="main-btn primary-btn btn-hover">
                            Guardar
                        </button>
                        <a href="index.php?main=aula" class="main-btn light-btn btn-hover">
                            Tornar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>