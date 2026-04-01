<?php soloSuperadmin();



/**
 * ============================================================
 * CONFIGURACION
 * ============================================================
 * Listado de reglas de configuración
 * + cambio rápido ON/OFF por AJAX
 */

if (
    ($_SESSION['auth_tipo'] ?? '') !== 'professor' ||
    ($_SESSION['professor_rol'] ?? '') !== 'superadmin'
) {
    echo '<div class="container py-4"><div class="alert alert-danger">Acceso no permitido.</div></div>';
    return;
}

$msg = trim((string)($_GET['msg'] ?? ''));

$sql = "
    SELECT
        clave,
        nombre,
        descripcion,
        valor
    FROM app.config
    ORDER BY nombre ASC, clave ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Configuración</h1>
            <p class="text-muted mb-0">
                Reglas globales del sistema. Puedes activarlas o desactivarlas rápidamente.
            </p>
        </div>

        <a href="/index.php?main=configuracion_form" class="btn btn-primary">
            Nueva regla
        </a>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div id="ajaxFeedback" style="display:none;"></div>

    <div class="card-style mb-30">

    <?php if (!$rows): ?>
        <div class="p-4 text-muted">
            No hay reglas creadas.
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width:280px;">
                            <h6>Regla</h6>
                        </th>
                        <th>
                            <h6>Descripción</h6>
                        </th>
                        <th style="width:120px;" class="text-center">
                            <h6>Activa</h6>
                        </th>
                        <th style="width:110px;" class="text-end">
                            <h6>Acciones</h6>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $clave = (string)$row['clave'];
                        $nombre = trim((string)($row['nombre'] ?? ''));
                        $descripcion = trim((string)($row['descripcion'] ?? ''));
                        $valor = ((string)$row['valor'] === '1');
                        ?>
                        <tr>
                            <td class="min-width">
                                <div>
                                    <p class="fw-semibold mb-1">
                                        <?= htmlspecialchars($nombre !== '' ? $nombre : $clave, ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="text-sm text-muted mb-0">
                                        <code><?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?></code>
                                    </p>
                                </div>
                            </td>

                            <td class="min-width">
                                <p class="mb-0">
                                    <?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </td>

                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block m-0">
                                    <input
                                        class="form-check-input js-toggle-config"
                                        type="checkbox"
                                        role="switch"
                                        data-clave="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $valor ? 'checked' : '' ?>
                                    >
                                </div>
                            </td>

                            <td class="text-end">
                                <div class="action justify-content-end">
                                    <a
                                        href="index.php?main=configuracion_form&clave=<?= urlencode($clave) ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        Editar
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
</div>

<script>
$(function () {
    $('.js-toggle-config').on('change', function () {
        var $check = $(this);
        var clave = $check.data('clave');
        var valor = $check.is(':checked') ? '1' : '0';
        var $feedback = $('#ajaxFeedback');

        $check.prop('disabled', true);

        $.ajax({
            url: '/index.php?main=configuracion_accion&raw=1',
            type: 'POST',
            dataType: 'json',
            data: {
                accion: 'toggle',
                clave: clave,
                valor: valor
            }
        })
        .done(function (resp) {
            if (!resp || !resp.ok) {
                $check.prop('checked', !$check.is(':checked'));

                $feedback
                    .removeClass()
                    .addClass('alert alert-danger mt-3')
                    .text(resp && resp.msg ? resp.msg : 'No se pudo actualizar la regla.')
                    .show();

                return;
            }

            $feedback
                .removeClass()
                .addClass('alert alert-success mt-3')
                .text('Regla actualizada correctamente.')
                .show();

            setTimeout(function () {
                $feedback.fadeOut(200, function () {
                    $(this).removeClass().text('');
                });
            }, 1200);
        })
        .fail(function () {
            $check.prop('checked', !$check.is(':checked'));

            $feedback
                .removeClass()
                .addClass('alert alert-danger mt-3')
                .text('Error de comunicación.')
                .show();
        })
        .always(function () {
            $check.prop('disabled', false);
        });
    });
});
</script>