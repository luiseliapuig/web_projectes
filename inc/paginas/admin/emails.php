<?php
declare(strict_types=1);

soloSuperadmin();
require_once dirname(__DIR__, 2) . '/email/bootstrap.php';

$configEmail = EmailConfig::fromEnvironment();
$resumen = $pdo->query("
    SELECT estado, COUNT(*) AS total
    FROM app.email_outbox
    GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);
$ultimos = $pdo->query("
    SELECT id_email, destinatario, asunto, tipo, estado, intentos, creado_en, enviado_en
    FROM app.email_outbox
    WHERE estado != 'enviado'
    ORDER BY creado_en DESC, id_email DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
$mensaje = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
$error = $_SESSION['emails_error'] ?? null;
unset($_SESSION['emails_error']);
?>
<style>
.email-queue-table > :not(caption) > * > * {
    padding: 1rem 1.5rem;
}
.email-queue-table th:last-child,
.email-queue-table td:last-child {
    text-align: center;
    width: 6rem;
}
.email-queue-table tbody tr:last-child td {
    padding-bottom: 1.5rem !important;
}
</style>
<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Sistema de correu</h1>
            <p class="text-muted mb-0">Enviaments manuals, cua automàtica i historial recent.</p>
        </div>
    </div>

    <?php if ($mensaje === 'encolat'): ?>
        <div class="alert alert-success">El missatge s’ha afegit a la cua.</div>
    <?php elseif ($mensaje === 'cua_processada'): ?>
        <div class="alert alert-success">
            Cua processada: <?= (int) ($_GET['enviats'] ?? 0) ?> enviats,
            <?= (int) ($_GET['pendents'] ?? 0) ?> pendents,
            <?= (int) ($_GET['fallits'] ?? 0) ?> errors.
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!$configEmail->isReady()): ?>
        <div class="alert alert-warning">
            El servei encara no està preparat. Falten o són incorrectes:
            <strong><?= htmlspecialchars(implode(', ', $configEmail->validationErrors()), ENT_QUOTES, 'UTF-8') ?></strong>.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Enviament manual de prova</h2>
                    <form method="post" action="/index.php?main=emails_accion">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label for="destinatario" class="form-label">Destinatari</label>
                            <input type="email" class="form-control" id="destinatario" name="destinatario" maxlength="320" required>
                        </div>
                        <div class="mb-3">
                            <label for="asunto" class="form-label">Assumpte</label>
                            <input type="text" class="form-control" id="asunto" name="asunto" maxlength="255" required>
                        </div>
                        <div class="mb-3">
                            <label for="contenido" class="form-label">Missatge</label>
                            <textarea class="form-control" id="contenido" name="contenido" rows="7" maxlength="10000" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-puig-solid">Afegir a la cua</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 border-bottom d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="h5 mb-2">Estat de la cua</h2>
                        <div class="d-flex flex-wrap gap-3 text-muted small">
                            <span>Pendents: <?= (int) ($resumen['pendiente'] ?? 0) ?></span>
                            <span>Enviats: <?= (int) ($resumen['enviado'] ?? 0) ?></span>
                            <span>Errors: <?= (int) ($resumen['error'] ?? 0) ?></span>
                        </div>
                    </div>
                    <form method="post" action="/index.php?main=emails_accion" class="mb-0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="accio" value="enviar_cola">
                        <button type="submit" class="btn btn-puig btn-sm">Enviar cua</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table email-queue-table align-middle mb-0">
                        <thead class="table-light"><tr><th>Destinatari</th><th>Assumpte</th><th>Estat</th><th>Intents</th></tr></thead>
                        <tbody>
                        <?php if ($ultimos === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Encara no hi ha enviaments.</td></tr>
                        <?php else: foreach ($ultimos as $email): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $email['destinatario'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $email['asunto'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $email['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) $email['intentos'] ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
