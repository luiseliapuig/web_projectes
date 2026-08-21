<?php
declare(strict_types=1);

soloSuperadmin();

// Recuperación de las reglas globales del sistema.
$stmt = $pdo->query("
    SELECT clave, nombre, descripcion, valor
    FROM app.config
    ORDER BY nombre, clave
");
$reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = $_SESSION['configuracion_error'] ?? '';
unset($_SESSION['configuracion_error']);
$mensaje = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
?>

<script>
window.PAGE_TITLE = 'Configuració';
</script>

<style>
.configuracion-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}

.configuracion-edit {
    padding: .25rem .5rem;
    font-size: .875rem;
    line-height: 1.5;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Configuració</h1>
            <p class="text-muted mb-0">Regles globals que activen o desactiven funcions del sistema.</p>
        </div>
        <a href="/index.php?main=configuracion_form" class="btn btn-puig-solid rounded-pill px-4">Nova regla</a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($mensaje === 'creada'): ?>
        <div class="alert alert-success" role="alert">Regla creada correctament.</div>
    <?php elseif ($mensaje === 'guardada'): ?>
        <div class="alert alert-success" role="alert">Regla actualitzada correctament.</div>
    <?php elseif ($mensaje === 'eliminada'): ?>
        <div class="alert alert-success" role="alert">Regla eliminada correctament.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 configuracion-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Regla</th>
                        <th>Descripció</th>
                        <th class="text-center">Activa</th>
                        <th class="text-end pe-4">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reglas === []): ?>
                        <tr><td colspan="4" class="text-center text-muted py-5">No hi ha regles de configuració.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reglas as $regla): ?>
                            <?php $activa = (string) $regla['valor'] === '1'; ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars((string) $regla['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <code><?= htmlspecialchars((string) $regla['clave'], ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                                <td><?= htmlspecialchars((string) ($regla['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-center">
                                    <form method="post" action="/index.php?main=configuracion_accion" class="d-inline-block">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="accio" value="toggle">
                                        <input type="hidden" name="clave" value="<?= htmlspecialchars((string) $regla['clave'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="valor" value="0">
                                        <div class="form-check form-switch m-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="valor"
                                                value="1"
                                                aria-label="Activar o desactivar <?= htmlspecialchars((string) $regla['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $activa ? 'checked' : '' ?>
                                                onchange="this.form.submit()"
                                            >
                                        </div>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="/index.php?main=configuracion_form&amp;clave=<?= rawurlencode((string) $regla['clave']) ?>"
                                       class="btn btn-outline-primary configuracion-edit">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
