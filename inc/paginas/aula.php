<?php soloSuperadmin();

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

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-style mb-30">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Aules</h6>
                    <a href="index.php?main=aula_form" class="main-btn primary-btn btn-hover btn-sm">
                        Nova aula
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Codi</th>
                                <th>Nom</th>
                                <th>Pis</th>
                                <th style="width: 140px;">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="4">No hi ha aules.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$r['codigo']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['nombre']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['piso']) ?></td>
                                        <td>
                                            <a href="index.php?main=aula_form&id=<?= (int)$r['id_aula'] ?>">Editar</a>

                                            <form
                                                method="post"
                                                action="index.php?main=aula_accion&raw=1"
                                                style="display:inline-block; margin-left:10px;"
                                                onsubmit="return confirm('Vols eliminar aquesta aula?');"
                                            >
                                                <input type="hidden" name="accion" value="borrar">
                                                <input type="hidden" name="id_aula" value="<?= (int)$r['id_aula'] ?>">
                                                <button type="submit" class="btn btn-link p-0 m-0 align-baseline">
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
    </div>
</div>