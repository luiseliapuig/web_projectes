<?php
declare(strict_types=1);

soloSuperadmin();

// Recuperación de direcciones activas del profesorado.
$stmt = $pdo->query("
    SELECT email
    FROM app.profesores
    WHERE activo = true
      AND email IS NOT NULL
      AND email <> ''
    ORDER BY email ASC
");

$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Normalización de la lista antes de mostrarla.
$emails = array_map('trim', $emails);
$emails = array_filter($emails);
$emails = array_unique($emails);

$cadena = implode(', ', $emails);
?>

<script>
window.PAGE_TITLE = 'Emails professors';
</script>

<style>
.email-list-copy {
    padding: .25rem .6rem;
    font-size: .875rem;
    line-height: 1.5;
}
</style>

<div class="container-fluid py-4">
    <div class="mb-3">
        <h1 class="h3 mb-1">Llista d’emails del professorat</h1>
        <p class="text-muted mb-0">Adreces del professorat actiu preparades per copiar.</p>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">

        <textarea
            id="emails"
            class="form-control"
            rows="8"
            onclick="this.select()"
        ><?= htmlspecialchars($cadena, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div class="mt-3">
            <button type="button" class="btn btn-puig email-list-copy" onclick="copiarEmails()">
                Copiar
            </button>
        </div>

        <div id="msg" class="mt-2 text-muted small"></div>

        </div>
    </div>
</div>

<script>
// Copia de la lista completa al portapapeles.
function copiarEmails() {
    const ta = document.getElementById('emails');
    ta.select();
    document.execCommand('copy');

    const msg = document.getElementById('msg');
    msg.textContent = 'Copiat!';
    setTimeout(() => msg.textContent = '', 2000);
}
</script>
