<?php
declare(strict_types=1);

soloSuperadmin();

// Recuperación del alumnado activo perteneciente al curso académico vigente.
$cursoAcademico = cursoAcademicoActual();
$stmt = $pdo->prepare("
    SELECT email
    FROM app.alumnos
    WHERE activo = true
      AND curso_academico = :curso_academico
      AND email IS NOT NULL
      AND email <> ''
    ORDER BY email ASC
");
$stmt->execute([':curso_academico' => $cursoAcademico]);

$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Normalización de la lista antes de mostrarla.
$emails = array_map('trim', $emails);
$emails = array_filter($emails);
$emails = array_unique($emails);

$cadena = implode(', ', $emails);
?>

<script>
window.PAGE_TITLE = 'Emails alumnes';
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
        <h1 class="h3 mb-1">Llista d’emails de l’alumnat</h1>
        <p class="text-muted mb-0">Adreces de l’alumnat actiu del curs <?= htmlspecialchars($cursoAcademico, ENT_QUOTES, 'UTF-8') ?> preparades per copiar.</p>
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
