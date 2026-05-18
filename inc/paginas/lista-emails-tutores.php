<?php
soloSuperadmin();

$stmt = $pdo->query("
    SELECT DISTINCT email
    FROM (
        -- Tutores
        SELECT p1.email
        FROM app.proyectos pr
        INNER JOIN app.profesores p1
            ON p1.id_profesor = pr.tutor_id
        WHERE p1.activo = true

        UNION

        -- Cotutores
        SELECT p2.email
        FROM app.proyectos pr
        INNER JOIN app.profesores p2
            ON p2.id_profesor = pr.cotutor_id
        WHERE p2.activo = true
          AND pr.cotutor_id IS NOT NULL
    ) t
    WHERE email IS NOT NULL
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
window.PAGE_TITLE = 'Emails tutors i cotutors';
</script>

<div class="container-fluid">
    <div class="card-style mb-30">

        <h6 class="mb-3">Emails tutors i cotutors</h6>

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