<?php
declare(strict_types=1);

soloSuperadmin();

$claveSolicitada = isset($_GET['clave']) && is_string($_GET['clave'])
    ? trim($_GET['clave'])
    : '';
$modoEliminar = ($_GET['modo'] ?? '') === 'eliminar' && $claveSolicitada !== '';
$regla = [
    'clave' => '',
    'nombre' => '',
    'descripcion' => '',
    'valor' => '0',
];

// La clave identifica la regla que se edita o elimina.
if ($claveSolicitada !== '') {
    $stmt = $pdo->prepare("
        SELECT clave, nombre, descripcion, valor
        FROM app.config
        WHERE clave = :clave
        LIMIT 1
    ");
    $stmt->execute([':clave' => $claveSolicitada]);
    $reglaEncontrada = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reglaEncontrada) {
        http_response_code(404);
        echo '<div class="container-fluid py-4"><div class="alert alert-danger">La regla no existeix.</div></div>';
        return;
    }
    $regla = $reglaEncontrada;
}

$esEdicion = $claveSolicitada !== '' && !$modoEliminar;
$titulo = $modoEliminar ? 'Eliminar regla' : ($esEdicion ? 'Editar regla' : 'Nova regla');
?>

<script>
window.PAGE_TITLE = '<?= $titulo ?>';
</script>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-xl-8">
            <div class="card-style mb-30">
                <div class="mb-4">
                    <h6 class="mb-1"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h6>
                    <p class="text-muted mb-0">Defineix una regla global del sistema.</p>
                </div>

                <form method="post" action="/index.php?main=configuracion_accion">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="clave_original" value="<?= htmlspecialchars((string) $regla['clave'], ENT_QUOTES, 'UTF-8') ?>">

                    <?php if ($modoEliminar): ?>
                        <input type="hidden" name="accio" value="eliminar">
                        <div class="alert alert-warning">Eliminar una regla pot desactivar la funció que la consulta. Confirma que ja no és necessària.</div>
                        <dl class="row mb-4">
                            <dt class="col-sm-3">Clau interna</dt>
                            <dd class="col-sm-9"><code><?= htmlspecialchars((string) $regla['clave'], ENT_QUOTES, 'UTF-8') ?></code></dd>
                            <dt class="col-sm-3">Nom</dt>
                            <dd class="col-sm-9"><?= htmlspecialchars((string) $regla['nombre'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </dl>
                        <div class="d-flex gap-2">
                            <button type="submit" class="main-btn danger-btn btn-hover">Eliminar</button>
                            <a href="/index.php?main=configuracion" class="main-btn light-btn btn-hover">Cancel·lar</a>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="accio" value="<?= $esEdicion ? 'actualizar' : 'crear' ?>">

                        <div class="mb-3">
                            <label for="clave" class="form-label">Clau interna</label>
                            <input type="text" name="clave" id="clave" class="form-control" maxlength="100" pattern="[a-z0-9_]+" required value="<?= htmlspecialchars((string) $regla['clave'], ENT_QUOTES, 'UTF-8') ?>" placeholder="mostrar_defensas">
                            <div class="form-text">Nom tècnic únic, en minúscules i amb guions baixos.</div>
                        </div>
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nom</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" maxlength="150" required value="<?= htmlspecialchars((string) $regla['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripció</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="255"><?= htmlspecialchars((string) ($regla['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="form-check form-switch mb-4">
                            <input type="hidden" name="valor" value="0">
                            <input class="form-check-input" type="checkbox" name="valor" id="valor" value="1" <?= (string) $regla['valor'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="valor">Regla activa</label>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="main-btn primary-btn btn-hover">Guardar</button>
                            <?php if ($esEdicion): ?>
                                <a href="/index.php?main=configuracion_form&amp;modo=eliminar&amp;clave=<?= rawurlencode((string) $regla['clave']) ?>" class="main-btn danger-btn-outline btn-hover">Eliminar</a>
                            <?php endif; ?>
                            <a href="/index.php?main=configuracion" class="main-btn light-btn btn-hover">Tornar</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
