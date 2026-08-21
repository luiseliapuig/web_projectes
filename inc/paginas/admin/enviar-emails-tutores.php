<?php
declare(strict_types=1);

soloSuperadmin();


// Consulta de profesores
$sql = "
    SELECT DISTINCT
        pr.id_profesor,
        pr.nombre,
        pr.apellidos,
        pr.email,
        pr.rol
    FROM app.profesores pr
    INNER JOIN app.rel_proyectos_profesores rpp
        ON rpp.profesor_id = pr.id_profesor
       AND rpp.rol = 'tutor'
    WHERE pr.activo = true
    ORDER BY pr.apellidos, pr.nombre
";

$stmt = $pdo->query($sql);
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Genera el enlace mailto/Gmail para un profesor concreto
 */
function generarMailtoProfesor(array $profesor): string
{
    $destinatarios = [
        $profesor['email'],
    ];

    $asunto = 'Accés a la web de Projectes';

    // OJO: aquí metemos el UUID para que cada enlace sea único
    $enlace_acceso = 'https://projectes.elpuig.xeill.net/acces';

    $cuerpo = "Buenos días " . trim($profesor['nombre']) . ",

Te paso el acceso a la web de Proyectos:

" . $enlace_acceso . "

Para los tutores de proyecto he añadido una vista de los proyectos en los que sois tutores (Projectes tutoritzats).

He actualizado también la presentación para preparar la defensa. Es la misma que ya figura en el Moodle, por lo que puedes acceder desde allí si la quieres utilizar en clase.

Recuerda indicar a los alumnos que la entrega ya no se hace por Moodle, sino a través de la web.

Hoy les enviaré los enlaces a todos.

Un saludo.

Luis.";

    $mailto = 'https://mail.google.com/mail/?view=cm'
        . '&to='   . rawurlencode(implode(',', $destinatarios))
        . '&su='   . rawurlencode($asunto)
        . '&body=' . rawurlencode($cuerpo);

    return $mailto;
}
?>

<script>
window.PAGE_TITLE = 'Emails tutors';
</script>

<style>
.email-tutors-action {
    padding: .25rem .6rem;
    font-size: .875rem;
    line-height: 1.5;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Enviament d’emails a tutors</h1>
            <p class="text-muted mb-0">Llistat de professors amb accés directe per correu electrònic.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <?php if (empty($profesores)): ?>
                <div class="p-4 text-muted">
                    No hi ha professors disponibles.
                </div>
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
                                        <a href="<?= htmlspecialchars(generarMailtoProfesor($profesor), ENT_QUOTES, 'UTF-8') ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-puig email-tutors-action">
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
