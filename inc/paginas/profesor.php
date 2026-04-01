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
                                            <?= htmlspecialchars($r['nombre'] . ' ' . $r['apellidos']) ?>
                                            <br>
                                             <a
                href="/login/<?= htmlspecialchars($r['uuid_acceso']) ?>"
                target="_blank"
                class="small text-muted mt-1"
            >
                🔑 Acceso
            </a>
                                        </td>
                                        <td><?= htmlspecialchars($r['email']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['departamento']) ?></td>
                                        <td><?= (int)$r['activo'] === 1 ? 'Sí' : 'No' ?></td>
                                        <td><?= $r['rol'] === 'superadmin' ? 'Sí' : 'No' ?></td>
                                        <td>
                                            <a href="index.php?main=profesor_form&id=<?= (int)$r['id_profesor'] ?>">
                                                Editar
                                            </a>
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