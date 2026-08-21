<?php
declare(strict_types=1);

soloSuperadmin();

// Listado de familias con el número de ciclos vinculados.
$stmt = $pdo->query("
    SELECT f.id_familia_ciclo, f.nombre, f.activo, f.orden,
           COUNT(c.id_ciclo) AS total_ciclos
    FROM app.familias_ciclos f
    LEFT JOIN app.ciclos c ON c.familia_ciclo_id = f.id_familia_ciclo
    GROUP BY f.id_familia_ciclo, f.nombre, f.activo, f.orden
    ORDER BY f.activo DESC, f.orden, f.nombre
");
$familias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$error = $_SESSION['families_cicles_error'] ?? '';
unset($_SESSION['families_cicles_error']);
?>

<script>
window.PAGE_TITLE = 'Famílies de cicles';
</script>

<style>
.families-cicles-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Famílies de cicles</h1>
            <p class="text-muted mb-0">Organització dels cicles per família professional.</p>
        </div>
        <a href="/index.php?main=families-cicles_form&amp;modo=new" class="btn btn-puig-solid rounded-pill px-4">
            Nova família
        </a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && is_string($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 families-cicles-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Família</th>
                        <th class="text-center">Ordre</th>
                        <th class="text-center">Cicles</th>
                        <th class="text-center">Activa</th>
                        <th class="text-end pe-4">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($familias as $familia): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= htmlspecialchars((string) $familia['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><?= (int) $familia['orden'] ?></td>
                            <td class="text-center"><?= (int) $familia['total_ciclos'] ?></td>
                            <td class="text-center">
                                <?php if ((bool) $familia['activo']): ?>
                                    <i class="bi bi-check-circle-fill text-success" title="Activa" aria-hidden="true"></i>
                                    <span class="visually-hidden">Activa</span>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger" title="Inactiva" aria-hidden="true"></i>
                                    <span class="visually-hidden">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="/index.php?main=families-cicles_form&amp;modo=edit&amp;id=<?= (int) $familia['id_familia_ciclo'] ?>"
                                       class="btn btn-outline-primary">Editar</a>
                                    <a href="/index.php?main=families-cicles_form&amp;modo=delete&amp;id=<?= (int) $familia['id_familia_ciclo'] ?>"
                                       class="btn btn-outline-danger">Borrar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($familias === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">No hi ha famílies creades.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
