<?php soloSuperadmin();



/**
 * ============================================================
 * CONFIGURACION_FORM
 * ============================================================
 * Formulario único:
 * - nueva regla
 * - editar regla
 * - eliminar regla
 *
 * REGLA:
 * - si llega clave => editar
 * - si llega modo=delete y clave => eliminar
 * - si no llega clave => nueva
 */

if (
    ($_SESSION['auth_tipo'] ?? '') !== 'professor' ||
    ($_SESSION['professor_rol'] ?? '') !== 'superadmin'
) {
    echo '<div class="container py-4"><div class="alert alert-danger">Acceso no permitido.</div></div>';
    return;
}

$msg = trim((string)($_GET['msg'] ?? ''));
$claveGet = trim((string)($_GET['clave'] ?? ''));
$modoGet = trim((string)($_GET['modo'] ?? ''));

$esDelete = ($modoGet === 'delete' && $claveGet !== '');
$esEdit = ($claveGet !== '' && !$esDelete);
$esNew = ($claveGet === '');

$clave = '';
$nombre = '';
$descripcion = '';
$valor = '0';

if ($claveGet !== '') {
    $sql = "
        SELECT
            clave,
            nombre,
            descripcion,
            valor
        FROM app.config
        WHERE clave = :clave
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':clave' => $claveGet
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo '<div class="container py-4"><div class="alert alert-danger">La regla no existe.</div></div>';
        return;
    }

    $clave = (string)$row['clave'];
    $nombre = (string)($row['nombre'] ?? '');
    $descripcion = (string)($row['descripcion'] ?? '');
    $valor = ((string)$row['valor'] === '1') ? '1' : '0';
}

$titulo = 'Nueva regla';
if ($esEdit) {
    $titulo = 'Editar regla';
}
if ($esDelete) {
    $titulo = 'Eliminar regla';
}
?>

<div class="container py-4">

    <div class="mb-4">
        <a href="/index.php?main=configuracion" class="text-decoration-none">← Volver a configuración</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-lg-5">

                    <h1 class="mb-4"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>

                    <?php if ($msg !== ''): ?>
                        <div class="alert alert-warning">
                            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form action="/index.php?main=configuracion_accion&raw=1" method="post">

                        <?php if ($esDelete): ?>
                            <input type="hidden" name="accion" value="delete">
                            <input type="hidden" name="clave_original" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="alert alert-warning">
                                Vas a eliminar esta regla de configuración.
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Clave interna</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nombre</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">Eliminar</button>
                                <a href="/index.php?main=configuracion" class="btn btn-outline-secondary">Cancelar</a>
                            </div>

                        <?php else: ?>
                            <input type="hidden" name="accion" value="<?= $esEdit ? 'update' : 'insert' ?>">
                            <input type="hidden" name="clave_original" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-3">
                                <label for="clave" class="form-label fw-semibold">Clave interna</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="clave"
                                    name="clave"
                                    value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>"
                                    maxlength="100"
                                    required
                                    placeholder="ejemplo: mostrar_defensas"
                                >
                                <div class="form-text">
                                    Identificador técnico único. Mejor en minúsculas y con guion bajo.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="nombre" class="form-label fw-semibold">Nombre</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="nombre"
                                    name="nombre"
                                    value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>"
                                    maxlength="150"
                                    required
                                    placeholder="Ejemplo: Mostrar defensas"
                                >
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                                <textarea
                                    class="form-control"
                                    id="descripcion"
                                    name="descripcion"
                                    rows="3"
                                    maxlength="255"
                                    placeholder="Explica brevemente qué controla esta regla."
                                ><?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    id="valor"
                                    name="valor"
                                    value="1"
                                    <?= $valor === '1' ? 'checked' : '' ?>
                                >
                                <label class="form-check-label fw-semibold" for="valor">
                                    Regla activa
                                </label>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <?= $esEdit ? 'Guardar cambios' : 'Crear regla' ?>
                                </button>

                                <?php if ($esEdit): ?>
                                    <a
                                        href="/index.php?main=configuracion_form&modo=delete&clave=<?= urlencode($clave) ?>"
                                        class="btn btn-outline-danger"
                                    >
                                        Eliminar
                                    </a>
                                <?php endif; ?>

                                <a href="/index.php?main=configuracion" class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                            </div>
                        <?php endif; ?>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>