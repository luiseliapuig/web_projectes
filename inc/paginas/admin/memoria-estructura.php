<?php
declare(strict_types=1);

soloSuperadmin();

// Llistat administratiu dels apartats de memòria, agrupats per família i
// categoria (obtingudes sempre via JOIN; mai es desa la família a
// memoria_estructura). S'inclouen també els apartats desactivats, igual que
// fan la resta de CRUD de l'admin.
$stmt = $pdo->query("
    SELECT me.id_memoria_estructura, me.titulo, me.descripcion, me.enlace_guia,
           me.activo, me.orden,
           cp.id_categoria_proyecto, cp.nombre AS categoria,
           f.id_familia_ciclo, f.nombre AS familia
    FROM app.memoria_estructura me
    INNER JOIN app.proyecto_categorias cp ON cp.id_categoria_proyecto = me.categoria_proyecto_id
    INNER JOIN app.familias_ciclos f ON f.id_familia_ciclo = cp.familia_ciclo_id
    ORDER BY f.orden, f.nombre, cp.orden, cp.nombre, me.orden, me.id_memoria_estructura
");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupació en memòria (família → categoria → apartats), preservant l'ordre
// ja donat per la consulta.
$grups = [];
foreach ($files as $fila) {
    $familiaId = (int) $fila['id_familia_ciclo'];
    $categoriaId = (int) $fila['id_categoria_proyecto'];
    if (!isset($grups[$familiaId])) {
        $grups[$familiaId] = ['nombre' => $fila['familia'], 'categories' => []];
    }
    if (!isset($grups[$familiaId]['categories'][$categoriaId])) {
        $grups[$familiaId]['categories'][$categoriaId] = ['nombre' => $fila['categoria'], 'items' => []];
    }
    $grups[$familiaId]['categories'][$categoriaId]['items'][] = $fila;
}

$error = $_SESSION['memoria_estructura_error'] ?? '';
unset($_SESSION['memoria_estructura_error']);
?>

<script>window.PAGE_TITLE = 'Estructura de la memòria';</script>
<style>
.memoria-estructura-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}
.memoria-estructura-descripcio {
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.memoria-estructura-fila {
    cursor: grab;
}
.memoria-estructura-dragging {
    opacity: .5;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Estructura de la memòria</h1>
            <p class="text-muted mb-0">Apartats de memòria per categoria de projecte. Arrossega les files per reordenar dins de cada categoria.</p>
        </div>
        <a href="/index.php?main=memoria-estructura_form&amp;modo=new" class="btn btn-puig-solid rounded-pill px-4">
            Nou apartat
        </a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && is_string($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($grups === []): ?>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body text-center text-muted py-5">No hi ha cap apartat de memòria creat.</div>
        </div>
    <?php else: ?>
        <?php foreach ($grups as $familia): ?>
            <h2 class="h5 mt-4 mb-3"><?= htmlspecialchars((string) $familia['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>
            <?php foreach ($familia['categories'] as $categoriaId => $categoria): ?>
                <h3 class="h6 text-uppercase text-muted mb-2">
                    <?= htmlspecialchars((string) $categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 memoria-estructura-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width:2.5rem"></th>
                                    <th class="text-center">Ordre</th>
                                    <th>Títol</th>
                                    <th>Descripció</th>
                                    <th>Guia</th>
                                    <th class="text-center">Actiu</th>
                                    <th class="text-end pe-4">Accions</th>
                                </tr>
                            </thead>
                            <tbody class="memoria-estructura-sortable" data-categoria-id="<?= (int) $categoriaId ?>">
                                <?php foreach ($categoria['items'] as $item): ?>
                                    <tr class="memoria-estructura-fila" draggable="true" data-id="<?= (int) $item['id_memoria_estructura'] ?>">
                                        <td class="ps-4 text-muted">
                                            <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                        </td>
                                        <td class="text-center memoria-estructura-ordre-num"><?= (int) $item['orden'] ?></td>
                                        <td><?= htmlspecialchars((string) $item['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="memoria-estructura-descripcio" title="<?= htmlspecialchars((string) ($item['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <?php if (trim((string) ($item['descripcion'] ?? '')) !== ''): ?>
                                                <?= htmlspecialchars((string) $item['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (trim((string) ($item['enlace_guia'] ?? '')) !== ''): ?>
                                                <a href="<?= htmlspecialchars((string) $item['enlace_guia'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="link-secondary text-decoration-none">
                                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Guia
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ((bool) $item['activo']): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Actiu" aria-hidden="true"></i>
                                                <span class="visually-hidden">Actiu</span>
                                            <?php else: ?>
                                                <i class="bi bi-x-circle-fill text-danger" title="Inactiu" aria-hidden="true"></i>
                                                <span class="visually-hidden">Inactiu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <a href="/index.php?main=memoria-estructura_form&amp;modo=edit&amp;id=<?= (int) $item['id_memoria_estructura'] ?>"
                                                   class="btn btn-outline-primary">Editar</a>
                                                <a href="/index.php?main=memoria-estructura_form&amp;modo=delete&amp;id=<?= (int) $item['id_memoria_estructura'] ?>"
                                                   class="btn btn-outline-danger">Borrar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode(tokenCsrf(), JSON_THROW_ON_ERROR) ?>;

    document.querySelectorAll('.memoria-estructura-sortable').forEach((tbody) => {
        let filaArrossegada = null;

        tbody.addEventListener('dragstart', (event) => {
            const fila = event.target.closest('tr.memoria-estructura-fila');
            if (!fila) {
                return;
            }
            filaArrossegada = fila;
            fila.classList.add('memoria-estructura-dragging');
        });

        tbody.addEventListener('dragend', () => {
            if (filaArrossegada) {
                filaArrossegada.classList.remove('memoria-estructura-dragging');
            }
            filaArrossegada = null;
        });

        tbody.addEventListener('dragover', (event) => {
            if (!filaArrossegada) {
                return;
            }
            event.preventDefault();
            const filaSobre = event.target.closest('tr.memoria-estructura-fila');
            if (!filaSobre || filaSobre === filaArrossegada || filaSobre.parentElement !== tbody) {
                return;
            }
            const rect = filaSobre.getBoundingClientRect();
            const despresDelMig = (event.clientY - rect.top) / rect.height > 0.5;
            tbody.insertBefore(filaArrossegada, despresDelMig ? filaSobre.nextSibling : filaSobre);
        });

        tbody.addEventListener('drop', async (event) => {
            if (!filaArrossegada) {
                return;
            }
            event.preventDefault();

            const categoriaId = tbody.dataset.categoriaId;
            const files = Array.from(tbody.querySelectorAll('tr.memoria-estructura-fila'));
            const ids = files.map((fila) => fila.dataset.id);

            const dades = new FormData();
            dades.append('csrf_token', csrfToken);
            dades.append('categoria_proyecto_id', categoriaId);
            ids.forEach((id) => dades.append('ordre[]', id));

            try {
                const resposta = await fetch('/index.php?main=memoria-estructura_orden_accion', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: dades,
                });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    alert(resultat.missatge || 'No s’ha pogut desar l’ordre.');
                    window.location.reload();
                    return;
                }
                files.forEach((fila, index) => {
                    const cel = fila.querySelector('.memoria-estructura-ordre-num');
                    if (cel) {
                        cel.textContent = String(index + 1);
                    }
                });
            } catch (error) {
                alert('Error de connexió en desar l’ordre.');
                window.location.reload();
            }
        });
    });
})();
</script>
