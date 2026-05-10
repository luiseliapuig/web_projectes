<?php soloSuperadmin();

$stmt = $pdo->query("
    SELECT
        id_profesor,
        nombre,
        apellidos,
        email,
        departamento,
        activo,
        uuid_acceso,
        rol
    FROM app.profesores
    ORDER BY apellidos ASC, nombre ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
window.PAGE_TITLE = 'Professorat';
</script>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger mb-3" role="alert">
        <?= $_GET['error'] ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminat'): ?>
    <div class="alert alert-success mb-3" role="alert">
        Professor eliminat correctament.
    </div>
<?php endif; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-style mb-30">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Professorat</h6>
                    <a href="index.php?main=profesor_form" class="main-btn primary-btn btn-hover btn-sm">
                        Nou professor
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Departament</th>
                                <th>Actiu</th>
                                <th>Superadmin</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="6">No hi ha professors.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td>
                                            <?= h($r['nombre'] . ' ' . $r['apellidos']) ?>
                                            <br>
                                            <a
                                                href="/login/<?= h($r['uuid_acceso']) ?>"
                                                target="_blank"
                                                class="small text-muted mt-1"
                                            >
                                                🔑 Acceso
                                            </a>
                                        </td>
                                        <td><?= h($r['email']) ?></td>
                                        <td><?= h((string)$r['departamento']) ?></td>
                                        <td><?= (int)$r['activo'] === 1 ? 'Sí' : 'No' ?></td>
                                        <td><?= $r['rol'] === 'superadmin' ? 'Sí' : 'No' ?></td>
                                        <td class="text-nowrap">
                                            <a href="index.php?main=profesor_form&id=<?= (int)$r['id_profesor'] ?>">
                                                Editar
                                            </a>
                                            &nbsp;·&nbsp;
                                            <form
                                                method="POST"
                                                action="index.php?main=profesor_accion"
                                                style="display:inline"
                                                onsubmit="return confirm('Segur que vols eliminar <?= h(addslashes($r['nombre'] . ' ' . $r['apellidos'])) ?>?\nAquesta acció no es pot desfer.')"
                                            >
                                                <input type="hidden" name="id_profesor" value="<?= (int)$r['id_profesor'] ?>">
                                                <input type="hidden" name="accio" value="eliminar">
                                                <button type="submit" class="btn btn-link p-0 text-danger" style="vertical-align:baseline">
                                                    Eliminar
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
