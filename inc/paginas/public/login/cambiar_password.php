<?php
declare(strict_types=1);

if (!esProfesor() && !esAlumno()) {
    echo '<div class="alert alert-danger">Accés no permès.</div>';
    return;
}
$msg = trim((string) ($_GET['msg'] ?? ''));
?>
<script>window.PAGE_TITLE = 'Canviar contrasenya';</script>
<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center"><div class="col-12 col-lg-6 col-xl-5">
        <div class="projectes-header mb-4 text-center">
            <h1 class="projectes-title mb-2">Canviar contrasenya</h1>
            <p class="projectes-subtitle mb-0">Confirma la contrasenya actual abans de crear-ne una de nova.</p>
        </div>
        <?php if ($msg === 'ok'): ?><div class="alert alert-success">La contrasenya s’ha actualitzat.</div>
        <?php elseif ($msg === 'current'): ?><div class="alert alert-warning">La contrasenya actual no és correcta.</div>
        <?php elseif ($msg === 'mismatch'): ?><div class="alert alert-warning">Les contrasenyes noves no coincideixen.</div>
        <?php elseif ($msg === 'weak'): ?><div class="alert alert-warning">La contrasenya nova ha de tenir almenys 10 caràcters.</div>
        <?php elseif ($msg === 'error'): ?><div class="alert alert-danger">No s’ha pogut actualitzar la contrasenya.</div><?php endif; ?>
        <div class="card-style mb-30">
            <form action="/index.php?main=cambiar_password_accion" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3"><label for="password_actual" class="edit-label-subtle">Contrasenya actual</label><input type="password" class="form-control meta-input" id="password_actual" name="password_actual" required autocomplete="current-password"></div>
                <div class="mb-3"><label for="password" class="edit-label-subtle">Nova contrasenya</label><input type="password" class="form-control meta-input" id="password" name="password" minlength="10" maxlength="255" required autocomplete="new-password"></div>
                <div class="mb-4"><label for="password_repeat" class="edit-label-subtle">Repeteix la nova contrasenya</label><input type="password" class="form-control meta-input" id="password_repeat" name="password_repeat" minlength="10" maxlength="255" required autocomplete="new-password"></div>
                <div class="d-flex justify-content-end"><button type="submit" class="btn btn-puig px-4">Guardar contrasenya</button></div>
            </form>
        </div>
    </div></div>
</div>
