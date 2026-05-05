<?php
soloSuperadmin();

$stmt = $pdo->query("
    SELECT email
    FROM app.alumnos
    WHERE activo = true
      AND email IS NOT NULL
      AND email <> ''
    ORDER BY email ASC
");

$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

// limpiar
$emails = array_map('trim', $emails);
$emails = array_filter($emails);
$emails = array_unique($emails);

$cadena = implode(', ', $emails);
?>

<script>
window.PAGE_TITLE = 'Emails alumnes';
</script>

<div class="container-fluid">
    <div class="card-style mb-30">

        <h6 class="mb-3">Emails alumnes</h6>

        <textarea
            id="emails"
            class="form-control"
            rows="8"
            onclick="this.select()"
        ><?= htmlspecialchars($cadena) ?></textarea>

        <div class="mt-3">
            <button class="main-btn primary-btn btn-hover btn-sm" onclick="copiarEmails()">
                Copiar
            </button>
        </div>

        <div id="msg" class="mt-2 text-muted small"></div>

    </div>
</div>

<script>
function copiarEmails() {
    const ta = document.getElementById('emails');
    ta.select();
    document.execCommand('copy');

    const msg = document.getElementById('msg');
    msg.textContent = 'Copiat!';
    setTimeout(() => msg.textContent = '', 2000);
}
</script>