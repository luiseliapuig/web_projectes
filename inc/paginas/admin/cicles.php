<?php
declare(strict_types=1);

soloSuperadmin();

// Listado administrativo de ciclos, incluidos los desactivados.
$stmt = $pdo->query("
    SELECT c.id_ciclo, c.abr, c.nombre, c.activo, c.color, c.orden,
           f.nombre AS familia,
           COUNT(g.id_grupo) AS total_grupos
    FROM app.ciclos c
    INNER JOIN app.familias_ciclos f
        ON f.id_familia_ciclo = c.familia_ciclo_id
    LEFT JOIN app.grupos g ON g.id_ciclo = c.id_ciclo
    GROUP BY c.id_ciclo, c.abr, c.nombre, c.activo, c.color, c.orden, f.nombre, f.orden
    ORDER BY c.activo DESC, f.orden ASC, c.orden ASC, c.abr ASC
");
$ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$error = $_SESSION['cicles_error'] ?? '';
unset($_SESSION['cicles_error']);
?>

<script>
window.PAGE_TITLE = 'Cicles';
</script>

<style>
.cicles-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Cicles</h1>
            <p class="text-muted mb-0">Gestió dels cicles, el seu estat i el color identificatiu.</p>
        </div>
        <a href="/index.php?main=cicles_form&amp;modo=new" class="btn btn-puig-solid rounded-pill px-4">
            Nou cicle
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
            <table class="table table-hover align-middle mb-0 cicles-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Cicle</th>
                        <th>Nom</th>
                        <th>Família</th>
                        <th class="text-center">Ordre</th>
                        <th class="text-center">Grups</th>
                        <th class="text-center">Actiu</th>
                        <th class="text-end pe-4">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ciclos as $ciclo): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge rounded-pill border px-3 py-2 <?= clasesColorCiclo((string) $ciclo['color']) ?>">
                                    <?= htmlspecialchars((string) $ciclo['abr'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string) $ciclo['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $ciclo['familia'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center"><?= (int) $ciclo['orden'] ?></td>
                            <td class="text-center"><?= (int) $ciclo['total_grupos'] ?></td>
                            <td class="text-center">
                                <?php if ((bool) $ciclo['activo']): ?>
                                    <i class="bi bi-check-circle-fill text-success" title="Actiu" aria-hidden="true"></i>
                                    <span class="visually-hidden">Actiu</span>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger" title="Inactiu" aria-hidden="true"></i>
                                    <span class="visually-hidden">Inactiu</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <a href="/index.php?main=cicles_form&amp;modo=edit&amp;id=<?= (int) $ciclo['id_ciclo'] ?>"
                                       class="btn btn-outline-primary">Editar</a>
                                    <a href="/index.php?main=cicles_form&amp;modo=delete&amp;id=<?= (int) $ciclo['id_ciclo'] ?>"
                                       class="btn btn-outline-danger">Borrar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($ciclos === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No hi ha cicles creats.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
