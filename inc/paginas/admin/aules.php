<?php
declare(strict_types=1);

soloSuperadmin();

$stmt = $pdo->query("
    SELECT
        id_aula,
        codigo,
        nombre,
        piso
    FROM app.aulas
    ORDER BY codigo ASC, nombre ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
window.PAGE_TITLE = 'Aules';
</script>

<style>
.aules-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}

</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Aules</h1>
            <p class="text-muted mb-0">Gestió de les aules, la seva identificació i ubicació.</p>
        </div>
        <a href="/index.php?main=aules_form" class="btn btn-puig-solid rounded-pill px-4">
            Nova aula
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 aules-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Codi</th>
                                <th>Nom</th>
                                <th>Pis</th>
                                <th class="text-end pe-4">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">No hi ha aules creades.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold"><?= htmlspecialchars((string) $r['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $r['piso'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end pe-4">
                                            <form
                                                method="post"
                                                action="/index.php?main=aules_accion"
                                                class="btn-group btn-group-sm"
                                                onsubmit="return confirm('Vols eliminar aquesta aula?');"
                                            >
                                                <a href="/index.php?main=aules_form&amp;id=<?= (int) $r['id_aula'] ?>"
                                                   class="btn btn-outline-primary">Editar</a>
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="accion" value="borrar">
                                                <input type="hidden" name="id_aula" value="<?= (int)$r['id_aula'] ?>">
                                                <button type="submit" class="btn btn-outline-danger">
                                                    Borrar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
    </div>
</div>
