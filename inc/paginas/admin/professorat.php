<?php
declare(strict_types=1);

soloSuperadmin();

// Recuperación del profesorado y sus grupos asignados en el curso vigente.
$cursoAcademico = cursoAcademicoActual();
$stmt = $pdo->prepare("
    SELECT
        p.id_profesor,
        p.nombre,
        p.apellidos,
        p.email,
        p.departamento,
        p.activo,
        p.rol,
        COALESCE(
            JSONB_AGG(
                JSONB_BUILD_OBJECT(
                    'etiqueta', TRIM(c.abr || ' ' || COALESCE(g.grupo, '')),
                    'color', c.color
                )
                ORDER BY c.orden, c.abr, g.grupo
            ) FILTER (WHERE g.id_grupo IS NOT NULL),
            '[]'::jsonb
        ) AS grupos
    FROM app.profesores p
    LEFT JOIN app.rel_profesores_grupos rpg
        ON rpg.profesor_id = p.id_profesor
       AND rpg.curso_academico = :curso_academico
    LEFT JOIN app.grupos g ON g.id_grupo = rpg.grupo_id
    LEFT JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    GROUP BY p.id_profesor, p.nombre, p.apellidos, p.email,
             p.departamento, p.activo, p.rol
    ORDER BY
        COALESCE(NULLIF(p.departamento, ''), 'Altres') ASC,
        p.nombre ASC,
        p.apellidos ASC
");
$stmt->execute([':curso_academico' => $cursoAcademico]);
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mensaje puntual generado por una acción que no pudo completarse.
$error = $_SESSION['professorat_error'] ?? '';
unset($_SESSION['professorat_error']);
$warning = $_SESSION['professorat_warning'] ?? '';
unset($_SESSION['professorat_warning']);
?>

<script>
window.PAGE_TITLE = 'Professorat';
</script>

<style>
.professorat-table tbody tr:last-child > td {
    padding-bottom: 1rem;
}

.professorat-table {
    min-width: 920px;
}

.professorat-email,
.professorat-department {
    font-size: .875rem;
}

.professorat-email {
    margin-top: .2rem;
    color: var(--bs-secondary-color);
    font-weight: 400;
    overflow-wrap: anywhere;
}

.professorat-invite-action {
    padding: .25rem .6rem;
    font-size: .875rem;
    line-height: 1.5;
    white-space: nowrap;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Professorat</h1>
            <p class="text-muted mb-0">Gestió del professorat, els permisos i els grups assignats.</p>
        </div>
        <a href="/index.php?main=professorat_form" class="btn btn-puig-solid rounded-pill px-4">
            Nou professor
        </a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert alert-danger mb-3" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (is_string($warning) && $warning !== ''): ?>
        <div class="alert alert-warning mb-3" role="alert"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminat'): ?>
        <div class="alert alert-success mb-3" role="alert">
            Professor eliminat correctament.
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'creat-invitat'): ?>
        <div class="alert alert-success mb-3" role="alert">Professor creat i invitació enviada.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'invitacio-enviada'): ?>
        <div class="alert alert-success mb-3" role="alert">Invitació enviada correctament.</div>
    <?php endif; ?>

            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 professorat-table">
                        <colgroup>
                            <col style="width: 31%">
                            <col style="width: 12%">
                            <col style="width: 17%">
                            <col style="width: 7%">
                            <col style="width: 10%">
                            <col style="width: 23%">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Nom</th>
                                <th>Departament</th>
                                <th>Grups <?= htmlspecialchars($cursoAcademico, ENT_QUOTES, 'UTF-8') ?></th>
                                <th class="text-center">Actiu</th>
                                <th class="text-center">Superadmin</th>
                                <th class="text-end pe-4">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($profesores === []): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5">No hi ha professors creats.</td></tr>
                            <?php else: ?>
                                <?php foreach ($profesores as $profesor): ?>
                                    <?php
                                    $nombreCompleto = trim($profesor['nombre'] . ' ' . $profesor['apellidos']);
                                    $gruposProfesor = json_decode((string) $profesor['grupos'], true);
                                    $gruposProfesor = is_array($gruposProfesor) ? $gruposProfesor : [];
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold"><?= htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="professorat-email"><?= htmlspecialchars((string) $profesor['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td class="professorat-department"><?= htmlspecialchars((string) ($profesor['departamento'] ?: 'Altres'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($gruposProfesor === []): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($gruposProfesor as $grupoProfesor): ?>
                                                        <span class="badge rounded-pill border px-3 py-2 fw-semibold <?= clasesColorCiclo((string) ($grupoProfesor['color'] ?? 'secondary')) ?>">
                                                            <?= htmlspecialchars((string) ($grupoProfesor['etiqueta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ((int) $profesor['activo'] === 1): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Actiu" aria-hidden="true"></i>
                                                <span class="visually-hidden">Actiu</span>
                                            <?php else: ?>
                                                <i class="bi bi-x-circle-fill text-danger" title="Inactiu" aria-hidden="true"></i>
                                                <span class="visually-hidden">Inactiu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($profesor['rol'] === 'superadmin'): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Superadmin" aria-hidden="true"></i>
                                                <span class="visually-hidden">Superadmin</span>
                                            <?php else: ?>
                                                <span class="visually-hidden">No és superadmin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 text-nowrap">
                                            <div class="d-flex justify-content-end align-items-center gap-2">
                                            <form method="post" action="/index.php?main=professorat_invitacion_accion">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id_profesor" value="<?= (int) $profesor['id_profesor'] ?>">
                                                <button type="submit" class="btn btn-puig professorat-invite-action"<?= (int) $profesor['activo'] === 1 ? '' : ' disabled title="Activa el professor abans d\'enviar-li una invitació"' ?>>Enviar invitació</button>
                                            </form>
                                            <form
                                                method="post"
                                                action="/index.php?main=professorat_accion"
                                                class="btn-group btn-group-sm"
                                                onsubmit="return confirm('Segur que vols eliminar aquest professor? Aquesta acció no es pot desfer.')"
                                            >
                                                <a href="/index.php?main=professorat_form&amp;id=<?= (int) $profesor['id_profesor'] ?>"
                                                   class="btn btn-outline-primary">Editar</a>
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id_profesor" value="<?= (int) $profesor['id_profesor'] ?>">
                                                <input type="hidden" name="accio" value="eliminar">
                                                <button type="submit" class="btn btn-outline-danger">
                                                    Borrar
                                                </button>
                                            </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
</div>
