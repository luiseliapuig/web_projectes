<?php
declare(strict_types=1);

soloSuperadmin();

// Categorías ordenadas por familia y posición configurada.
$stmt = $pdo->query("
    SELECT cp.id_categoria_proyecto, cp.nombre, cp.activo, cp.orden,
           f.nombre AS familia
    FROM app.proyecto_categorias cp
    INNER JOIN app.familias_ciclos f
        ON f.id_familia_ciclo = cp.familia_ciclo_id
    ORDER BY f.orden, f.nombre, cp.activo DESC, cp.orden, cp.nombre
");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$error = $_SESSION['categories_projectes_error'] ?? '';
unset($_SESSION['categories_projectes_error']);
?>

<script>window.PAGE_TITLE = 'Categories de projectes';</script>

<style>
.categories-projectes-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Categories de projectes</h1>
            <p class="text-muted mb-0">Finalitat general del projecte per família professional.</p>
        </div>
        <a href="/index.php?main=categories-projectes_form&amp;modo=new" class="btn btn-puig-solid rounded-pill px-4">Nova categoria</a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && is_string($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 categories-projectes-table">
                <thead class="table-light">
                    <tr><th class="ps-4">Categoria</th><th>Família</th><th class="text-center">Ordre</th><th class="text-center">Activa</th><th class="text-end pe-4">Accions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias as $categoria): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= htmlspecialchars((string) $categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $categoria['familia'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><?= (int) $categoria['orden'] ?></td>
                            <td class="text-center">
                                <i class="bi <?= (bool) $categoria['activo'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= (bool) $categoria['activo'] ? 'Activa' : 'Inactiva' ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="/index.php?main=categories-projectes_form&amp;modo=edit&amp;id=<?= (int) $categoria['id_categoria_proyecto'] ?>" class="btn btn-outline-primary">Editar</a>
                                    <a href="/index.php?main=categories-projectes_form&amp;modo=delete&amp;id=<?= (int) $categoria['id_categoria_proyecto'] ?>" class="btn btn-outline-danger">Borrar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($categorias === []): ?><tr><td colspan="5" class="text-center text-muted py-5">No hi ha categories creades.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
