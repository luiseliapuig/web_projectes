<?php
declare(strict_types=1);

$token = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';
$tokenValido = false;
$esInvitacionCaducada = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT 1 FROM app.profesor_password_reset r INNER JOIN app.profesores p ON p.id_profesor=r.profesor_id
        WHERE r.token_hash=:profesor_hash AND r.usado_en IS NULL AND r.expira_en>CURRENT_TIMESTAMP AND p.activo=true
        UNION ALL
        SELECT 1 FROM app.alumno_password_reset r INNER JOIN app.alumnos a ON a.id_alumno=r.alumno_id
        WHERE r.token_hash=:alumno_hash AND r.usado_en IS NULL AND r.expira_en>CURRENT_TIMESTAMP AND a.activo=true
        LIMIT 1
    ");
    $stmt->execute([':profesor_hash' => $hash, ':alumno_hash' => $hash]);
    $tokenValido = (bool) $stmt->fetchColumn();

    if (!$tokenValido) {
        $stmtTipo = $pdo->prepare("
            SELECT tipo FROM app.profesor_password_reset WHERE token_hash = :profesor_hash
            UNION ALL
            SELECT tipo FROM app.alumno_password_reset WHERE token_hash = :alumno_hash
            LIMIT 1
        ");
        $stmtTipo->execute([':profesor_hash' => $hash, ':alumno_hash' => $hash]);
        $esInvitacionCaducada = $stmtTipo->fetchColumn() === 'invitacion';
    }
}
$msg = trim((string) ($_GET['msg'] ?? ''));
?>
<script>window.PAGE_TITLE = 'Nova contrasenya';</script>
<div class="container-fluid px-4 py-4 mt-60 mb-40"><div class="row justify-content-center"><div class="col-12 col-lg-6 col-xl-5">
    <div class="projectes-header mb-4 text-center"><h1 class="projectes-title mb-2">Crea una nova contrasenya</h1><p class="projectes-subtitle mb-0">L’enllaç és personal i només es pot utilitzar una vegada.</p></div>
    <?php if (!$tokenValido): ?>
        <?php if ($esInvitacionCaducada): ?>
            <div class="alert alert-warning">Aquesta invitació ja no és vàlida o ha caducat. Utilitza «Has oblidat la contrasenya?» per rebre un enllaç nou.</div>
            <div class="text-center"><a href="/recuperar-contrasenya" class="btn btn-puig">Recuperar contrasenya</a></div>
        <?php else: ?>
            <div class="alert alert-warning">Aquest enllaç no és vàlid o ha caducat.</div>
            <div class="text-center"><a href="/recuperar-contrasenya" class="btn btn-puig">Sol·licitar un altre enllaç</a></div>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($msg === 'mismatch'): ?><div class="alert alert-warning">Les contrasenyes no coincideixen.</div>
        <?php elseif ($msg === 'weak'): ?><div class="alert alert-warning">La contrasenya ha de tenir almenys 10 caràcters.</div>
        <?php elseif ($msg === 'invalid'): ?><div class="alert alert-danger">No s’ha pogut validar l’operació.</div><?php endif; ?>
        <div class="card-style mb-30"><form action="/index.php?main=restablecer_password_accion" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3"><label for="password" class="edit-label-subtle">Nova contrasenya</label><input type="password" class="form-control meta-input" id="password" name="password" minlength="10" maxlength="255" required autocomplete="new-password"></div>
            <div class="mb-4"><label for="password_repeat" class="edit-label-subtle">Repeteix la contrasenya</label><input type="password" class="form-control meta-input" id="password_repeat" name="password_repeat" minlength="10" maxlength="255" required autocomplete="new-password"></div>
            <div class="d-flex justify-content-end"><button type="submit" class="btn btn-puig px-4">Guardar contrasenya</button></div>
        </form></div>
    <?php endif; ?>
</div></div></div>
