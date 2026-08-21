<?php
declare(strict_types=1);

soloSuperadmin();

// Tipos técnicos ordenados dentro de cada familia profesional.
$stmt = $pdo->query("
    SELECT tp.id_tipo_proyecto, tp.nombre, tp.activo, tp.orden, f.nombre AS familia
    FROM app.tipos_proyectos tp
    INNER JOIN app.familias_ciclos f ON f.id_familia_ciclo = tp.familia_ciclo_id
    ORDER BY f.orden, f.nombre, tp.activo DESC, tp.orden, tp.nombre
");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$error = $_SESSION['tipus_projectes_error'] ?? '';
unset($_SESSION['tipus_projectes_error']);
?>

<script>window.PAGE_TITLE = 'Tipus de projectes';</script>
<style>
.tipus-projectes-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}
</style>
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3"><div><h1 class="h3 mb-1">Tipus de projectes</h1><p class="text-muted mb-0">Naturalesa tècnica del projecte per família professional.</p></div><a href="/index.php?main=tipus-projectes_form&amp;modo=new" class="btn btn-puig-solid rounded-pill px-4">Nou tipus</a></div>
    <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (isset($_GET['msg']) && is_string($_GET['msg']) && $_GET['msg'] !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden"><div class="table-responsive"><table class="table table-hover align-middle mb-0 tipus-projectes-table">
        <thead class="table-light"><tr><th class="ps-4">Tipus</th><th>Família</th><th class="text-center">Ordre</th><th class="text-center">Actiu</th><th class="text-end pe-4">Accions</th></tr></thead>
        <tbody>
        <?php foreach ($tipos as $tipo): ?><tr>
            <td class="ps-4 fw-semibold"><?= htmlspecialchars((string) $tipo['nombre'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $tipo['familia'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-center"><?= (int) $tipo['orden'] ?></td>
            <td class="text-center"><i class="bi <?= (bool) $tipo['activo'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>" aria-hidden="true"></i><span class="visually-hidden"><?= (bool) $tipo['activo'] ? 'Actiu' : 'Inactiu' ?></span></td>
            <td class="text-end pe-4"><div class="btn-group btn-group-sm"><a href="/index.php?main=tipus-projectes_form&amp;modo=edit&amp;id=<?= (int) $tipo['id_tipo_proyecto'] ?>" class="btn btn-outline-primary">Editar</a><a href="/index.php?main=tipus-projectes_form&amp;modo=delete&amp;id=<?= (int) $tipo['id_tipo_proyecto'] ?>" class="btn btn-outline-danger">Borrar</a></div></td>
        </tr><?php endforeach; ?>
        <?php if ($tipos === []): ?><tr><td colspan="5" class="text-center text-muted py-5">No hi ha tipus creats.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</div>
