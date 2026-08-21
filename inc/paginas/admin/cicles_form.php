<?php
declare(strict_types=1);

soloSuperadmin();

// Preparación de los modos y valores del formulario único.
$modo = isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : 'new';
$modo = in_array($modo, ['new', 'edit', 'delete'], true) ? $modo : 'new';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$colores = coloresCicloPermitidos();
$ciclo = [
    'id_ciclo' => 0,
    'abr' => '',
    'nombre' => '',
    'activo' => true,
    'color' => 'secondary',
    'orden' => 1,
    'familia_ciclo_id' => 0,
];

if ($modo === 'new') {
    $ciclo['orden'] = (int) $pdo->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM app.ciclos")->fetchColumn();
}

// En edición y borrado, el ciclo se recupera siempre desde la base de datos.
if ($modo !== 'new') {
    $stmt = $pdo->prepare("
        SELECT id_ciclo, abr, nombre, activo, color, orden, familia_ciclo_id
        FROM app.ciclos
        WHERE id_ciclo = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['cicles_error'] = 'Cicle no trobat.';
        echo '<script>location.href="/index.php?main=cicles";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=/index.php?main=cicles"></noscript>';
        exit;
    }
    $ciclo = $row;
}

// Familias activas y, en edición, la familia actual aunque esté desactivada.
$stmt = $pdo->prepare("
    SELECT id_familia_ciclo, nombre, orden
    FROM app.familias_ciclos
    WHERE activo = true OR id_familia_ciclo = :familia_actual
    ORDER BY orden, nombre
");
$stmt->execute([':familia_actual' => (int) $ciclo['familia_ciclo_id']]);
$familias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($modo === 'new' && $familias !== []) {
    $ciclo['familia_ciclo_id'] = (int) $familias[0]['id_familia_ciclo'];
}

$titulo = match ($modo) {
    'edit' => 'Editar cicle',
    'delete' => 'Borrar cicle',
    default => 'Nou cicle',
};
?>

<script>
window.PAGE_TITLE = '<?= $titulo ?>';
</script>

<div class="container py-4">
    <h1 class="h3 mb-3"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="/index.php?main=cicles_accion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="modo" value="<?= htmlspecialchars($modo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_ciclo" value="<?= (int) $ciclo['id_ciclo'] ?>">

                <?php if ($modo === 'delete'): ?>
                    <div class="alert alert-danger">
                        Segur que vols borrar el cicle <strong><?= htmlspecialchars((string) $ciclo['abr'], ENT_QUOTES, 'UTF-8') ?></strong>?
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="familia_ciclo_id" class="form-label">Família professional</label>
                        <select id="familia_ciclo_id" name="familia_ciclo_id" class="form-select" required
                                <?= $modo === 'delete' ? 'disabled' : '' ?>>
                            <?php foreach ($familias as $familia): ?>
                                <option value="<?= (int) $familia['id_familia_ciclo'] ?>"
                                    <?= (int) $ciclo['familia_ciclo_id'] === (int) $familia['id_familia_ciclo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $familia['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="abr" class="form-label">Abreviatura</label>
                        <input id="abr" name="abr" class="form-control" maxlength="10" required
                               value="<?= htmlspecialchars((string) $ciclo['abr'], ENT_QUOTES, 'UTF-8') ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="nombre" class="form-label">Nom</label>
                        <input id="nombre" name="nombre" class="form-control" maxlength="120" required
                               value="<?= htmlspecialchars((string) $ciclo['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="color" class="form-label">Color</label>
                        <select id="color" name="color" class="form-select" required <?= $modo === 'delete' ? 'disabled' : '' ?>>
                            <?php foreach ($colores as $color): ?>
                                <option value="<?= $color ?>" <?= $ciclo['color'] === $color ? 'selected' : '' ?>>
                                    <?= ucfirst($color) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="orden" class="form-label">Ordre</label>
                        <input id="orden" name="orden" type="number" class="form-control"
                               min="1" max="32767" required
                               value="<?= (int) $ciclo['orden'] ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                </div>

                <?php if ($modo !== 'delete'): ?>
                    <div class="form-check mb-3">
                        <input id="activo" name="activo" type="checkbox" class="form-check-input" value="1"
                               <?= (bool) $ciclo['activo'] ? 'checked' : '' ?>>
                        <label for="activo" class="form-check-label">Actiu</label>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn <?= $modo === 'delete' ? 'btn-danger' : 'btn-primary' ?>">
                        <?= $modo === 'delete' ? 'Sí, borrar' : 'Guardar' ?>
                    </button>
                    <a href="/index.php?main=cicles" class="btn btn-outline-secondary">Cancel·lar</a>
                </div>
            </form>
        </div>
    </div>
</div>
