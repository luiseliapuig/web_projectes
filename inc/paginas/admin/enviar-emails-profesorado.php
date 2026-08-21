<?php
declare(strict_types=1);

soloSuperadmin();

$stmt = $pdo->query("
    SELECT
        nombre,
        apellidos,
        email,
        rol
    FROM app.profesores
    WHERE activo = true
    ORDER BY apellidos, nombre
");
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$generarEnlaceEmail = static function (array $profesor): string {
    $asunto = 'Accés a la web de Projectes';
    $enlaceAcceso = 'https://projectes.elpuig.xeill.net/acces';
    $cuerpo = "Bon dia " . trim((string) $profesor['nombre']) . ",

Et faig arribar el teu enllaç d'accés a la web de Projectes Puig Castellar.

Accés directe:
" . $enlaceAcceso . "

Des d'allà podràs consultar els projectes que tens assignats i participar en les valoracions corresponents.

Una salutació.";

    return 'https://mail.google.com/mail/?view=cm'
        . '&to=' . rawurlencode((string) $profesor['email'])
        . '&su=' . rawurlencode($asunto)
        . '&body=' . rawurlencode($cuerpo);
};
?>

<script>
window.PAGE_TITLE = 'Emails professorat';
</script>

<style>
.email-professorat-action {
    padding: .25rem .6rem;
    font-size: .875rem;
    line-height: 1.5;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Enviament d’emails a professorat</h1>
            <p class="text-muted mb-0">Llistat de professors amb accés directe per correu electrònic.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <?php if ($profesores === []): ?>
                <div class="p-4 text-muted">No hi ha professors disponibles.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Professor/a</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Rol</th>
                                <th class="py-3 text-end pe-4">Acció</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profesores as $profesor): ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars(trim($profesor['apellidos'] . ', ' . $profesor['nombre']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <?= htmlspecialchars((string) $profesor['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if (!empty($profesor['rol'])): ?>
                                            <span class="badge bg-secondary-subtle text-dark border">
                                                <?= htmlspecialchars((string) $profesor['rol'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-end pe-4">
                                        <a
                                            href="<?= htmlspecialchars($generarEnlaceEmail($profesor), ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-puig email-professorat-action"
                                        >
                                            Enviar email
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
