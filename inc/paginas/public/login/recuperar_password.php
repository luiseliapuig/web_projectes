<?php
declare(strict_types=1);

if (esProfesor() || esAlumno()) {
    echo '<script>location.href="/";</script><noscript><meta http-equiv="refresh" content="0;url=/"></noscript>';
    exit;
}
$msg = trim((string) ($_GET['msg'] ?? ''));
?>
<script>window.PAGE_TITLE = 'Recuperar contrasenya';</script>
<div class="container-fluid px-4 py-4 mt-60 mb-40">
    <div class="row justify-content-center"><div class="col-12 col-lg-6 col-xl-5">
        <div class="projectes-header mb-4 text-center">
            <h1 class="projectes-title mb-2">Recuperar contrasenya</h1>
            <p class="projectes-subtitle mb-0">T’enviarem un enllaç segur al correu del teu perfil.</p>
        </div>
        <?php if ($msg === 'sent'): ?>
            <div class="alert alert-success">Si el correu correspon a un usuari actiu, rebràs les instruccions en uns instants.</div>
        <?php elseif ($msg === 'invalid'): ?>
            <div class="alert alert-warning">Revisa el correu introduït.</div>
        <?php endif; ?>
        <div class="card-style mb-30">
            <form action="/index.php?main=recuperar_password_accion" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-4">
                    <label for="email" class="edit-label-subtle">Correu electrònic</label>
                    <input type="email" class="form-control meta-input" id="email" name="email" maxlength="255" required autocomplete="email" autofocus>
                </div>
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <a href="/acces" class="auth-secondary-link">Tornar a l’accés</a>
                    <button type="submit" class="btn btn-puig px-4">Enviar enllaç</button>
                </div>
            </form>
        </div>
    </div></div>
</div>
