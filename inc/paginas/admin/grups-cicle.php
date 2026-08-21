<?php
declare(strict_types=1);

solosuperadmin();

$msg = $_GET['msg'] ?? '';

$sql = "
   SELECT
    g.id_grupo,
    c.abr AS ciclo,
    c.nombre AS ciclo_nombre,
    c.color AS ciclo_color,
    c.orden AS ciclo_orden,
    f.nombre AS familia_nombre,
    f.orden AS familia_orden,
    g.grupo,
    g.torn,
    a.codigo AS aula_codigo,
    a.nombre AS aula_nombre
FROM app.grupos g
JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
JOIN app.familias_ciclos f ON f.id_familia_ciclo = c.familia_ciclo_id
LEFT JOIN app.aulas a ON a.id_aula = g.id_aula
ORDER BY f.orden, f.nombre, c.orden, c.abr, g.torn, g.grupo
";

$grupos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>

<script>
window.PAGE_TITLE = 'Grups/cicle';
</script>

<style>
.grups-cicle-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}
</style>

<div class="container-fluid py-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Grups per cicle</h1>
            <p class="text-muted mb-0">Gestió dels grups per cicle i aula assignada.</p>
        </div>

        <a href="/index.php?main=grups-cicle_form&amp;modo=new"
           class="btn btn-puig-solid rounded-pill px-4">
            Nou grup
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

   <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 grups-cicle-table">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Família</th>
                    <th>Cicle</th>

                    <th class="text-center">Grup</th>
                    <th>Aula</th>
                    <th class="text-center">Torn</th>
                    <th class="text-end pe-4">Accions</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($grupos as $g): ?>
                <tr>
                    <td class="ps-4 fw-semibold">
                        <?= htmlspecialchars((string) $g['familia_nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td>
<?php
$clase = clasesColorCiclo((string) $g['ciclo_color']);
?>

<span class="badge rounded-pill border px-3 py-2 fw-semibold <?= $clase ?>">
    <?= htmlspecialchars($g['ciclo']) ?>
    <?= htmlspecialchars($g['grupo']) ?>
</span>
                    </td>


                    <td class="text-center">
                        <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                            <?= htmlspecialchars($g['grupo']) ?>
                        </span>
                    </td>

                    <td>
                        <?php if ($g['aula_codigo']): ?>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($g['aula_codigo']) ?>
                            </div>
                            <?php if (!empty($g['aula_nombre'])): ?>
                                <div class="small text-muted">
                                    <?= htmlspecialchars($g['aula_nombre']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted fst-italic">Sense aula assignada</span>
                        <?php endif; ?>
                    </td>

                    <td class="text-center">
        <span class="badge rounded-pill <?= $g['torn'] === 'Matí' ? 'text-bg-warning' : 'text-bg-info' ?>">
            <?= htmlspecialchars($g['torn']) ?>
        </span>
    </td>

                    <td class="text-end pe-4">
                        <div class="btn-group btn-group-sm">
                            <a href="/index.php?main=grups-cicle_form&amp;modo=edit&amp;id=<?= (int) $g['id_grupo'] ?>"
                               class="btn btn-outline-primary">
                                Editar
                            </a>

                            <a href="/index.php?main=grups-cicle_form&amp;modo=delete&amp;id=<?= (int) $g['id_grupo'] ?>"
                               class="btn btn-outline-danger">
                                Borrar
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$grupos): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        Encara no hi ha grups creats.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
