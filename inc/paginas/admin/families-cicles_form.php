<?php
declare(strict_types=1);

soloSuperadmin();

// Preparación del formulario único para alta, edición y borrado.
$modo = isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : 'new';
$modo = in_array($modo, ['new', 'edit', 'delete'], true) ? $modo : 'new';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$familia = [
    'id_familia_ciclo' => 0,
    'nombre' => '',
    'activo' => true,
    'orden' => (int) $pdo->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM app.familias_ciclos")->fetchColumn(),
];

if ($modo !== 'new') {
    $stmt = $pdo->prepare("
        SELECT id_familia_ciclo, nombre, activo, orden
        FROM app.familias_ciclos
        WHERE id_familia_ciclo = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['families_cicles_error'] = 'Família no trobada.';
        echo '<script>location.href="/index.php?main=families-cicles";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=/index.php?main=families-cicles"></noscript>';
        exit;
    }
    $familia = $row;
}

$titulo = match ($modo) {
    'edit' => 'Editar família',
    'delete' => 'Borrar família',
    default => 'Nova família',
};
?>

<script>window.PAGE_TITLE = '<?= $titulo ?>';</script>

<div class="container py-4">
    <h1 class="h3 mb-3"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="/index.php?main=families-cicles_accion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="modo" value="<?= htmlspecialchars($modo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_familia_ciclo" value="<?= (int) $familia['id_familia_ciclo'] ?>">

                <?php if ($modo === 'delete'): ?>
                    <div class="alert alert-danger">
                        Segur que vols borrar la família <strong><?= htmlspecialchars((string) $familia['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>?
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="nombre" class="form-label">Nom</label>
                        <input id="nombre" name="nombre" class="form-control" maxlength="120" required
                               value="<?= htmlspecialchars((string) $familia['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="orden" class="form-label">Ordre</label>
                        <input id="orden" name="orden" type="number" class="form-control" min="1" max="32767" required
                               value="<?= (int) $familia['orden'] ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                </div>

                <?php if ($modo !== 'delete'): ?>
                    <div class="form-check mb-3">
                        <input id="activo" name="activo" type="checkbox" class="form-check-input" value="1"
                               <?= (bool) $familia['activo'] ? 'checked' : '' ?>>
                        <label for="activo" class="form-check-label">Activa</label>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn <?= $modo === 'delete' ? 'btn-danger' : 'btn-primary' ?>">
                        <?= $modo === 'delete' ? 'Sí, borrar' : 'Guardar' ?>
                    </button>
                    <a href="/index.php?main=families-cicles" class="btn btn-outline-secondary">Cancel·lar</a>
                </div>
            </form>
        </div>
    </div>
</div>
