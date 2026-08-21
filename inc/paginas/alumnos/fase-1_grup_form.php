<?php
declare(strict_types=1);

$permitirSinProyecto = true;
$contextoCursoActual = true;
if (!(require __DIR__ . '/projecte_context.php')) {
    return;
}

$alumnoId = (int) $_SESSION['alumno_id'];
$cursoAcademico = cursoAcademicoActual();
$grupoId = (int) ($proyectoAlumno['grupo_id'] ?? 0);
if ($grupoId <= 0) {
    $stmt = $pdo->prepare("
        SELECT grupo_id
        FROM app.rel_alumnos_grupos
        WHERE alumno_id = :alumno_id AND curso_academico = :curso_academico
        LIMIT 1
    ");
    $stmt->execute([':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
    $grupoId = (int) ($stmt->fetchColumn() ?: 0);
}

$proyectoId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$miembrosActuales = [];
$tareaConfirmada = false;
if ($proyectoId > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, a.nombre, a.apellidos,
               propio.grupo_trabajo_confirmado_en
        FROM app.rel_proyectos_alumnos propio
        INNER JOIN app.rel_proyectos_alumnos miembro
            ON miembro.proyecto_id = propio.proyecto_id
        INNER JOIN app.alumnos a ON a.id_alumno = miembro.alumno_id
        WHERE propio.proyecto_id = :proyecto_id
          AND propio.alumno_id = :alumno_id
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoId]);
    $miembrosActuales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tareaConfirmada = $miembrosActuales !== []
        && $miembrosActuales[0]['grupo_trabajo_confirmado_en'] !== null;
}

$companeroActualId = 0;
foreach ($miembrosActuales as $miembroActual) {
    if ((int) $miembroActual['id_alumno'] !== $alumnoId) {
        $companeroActualId = (int) $miembroActual['id_alumno'];
        break;
    }
}
$agrupacionRepresentable = count($miembrosActuales) <= 2;
$agrupacionPredefinida = $proyectoId > 0 && $miembrosActuales !== [];

// Solo se muestran compañeros del mismo grupo que estén libres o que ya
// compartan exactamente este proyecto de dos miembros con el alumno actual.
$stmt = $pdo->prepare("
    SELECT a.id_alumno, a.nombre, a.apellidos
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
    WHERE rag.grupo_id = :grupo_id
      AND rag.curso_academico = :curso_academico
      AND rag.alumno_id <> :alumno_id
      AND a.activo = true
      AND NOT EXISTS (
          SELECT 1
          FROM app.rel_proyectos_alumnos rpa_candidat
          INNER JOIN app.proyectos p_candidat
              ON p_candidat.id_proyecto = rpa_candidat.proyecto_id
          WHERE rpa_candidat.alumno_id = a.id_alumno
            AND p_candidat.curso_academico = :curso_academico
            AND p_candidat.estado = 'activo'
            AND (
                NOT EXISTS (
                    SELECT 1 FROM app.rel_proyectos_alumnos rpa_propi
                    WHERE rpa_propi.proyecto_id = p_candidat.id_proyecto
                      AND rpa_propi.alumno_id = :alumno_id
                )
                OR (SELECT COUNT(*) FROM app.rel_proyectos_alumnos rpa_membre
                    WHERE rpa_membre.proyecto_id = p_candidat.id_proyecto) <> 2
            )
      )
    ORDER BY a.nombre, a.apellidos
");
$stmt->execute([
    ':grupo_id' => $grupoId,
    ':curso_academico' => $cursoAcademico,
    ':alumno_id' => $alumnoId,
]);
$companerosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = isset($_SESSION['fase_1_grup_error']) && is_string($_SESSION['fase_1_grup_error'])
    ? $_SESSION['fase_1_grup_error']
    : '';
unset($_SESSION['fase_1_grup_error']);
$faseActiva = 1;
$fasesNavegacionBloqueada = true;
?>
<script>window.PAGE_TITLE = 'Defineix el grup de treball';</script>
<div class="container-fluid py-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3"><?php include __DIR__ . '/fases_navegacion.php'; ?></aside>
        <section class="col-lg-9">
    <div class="mb-3">
        <span class="small fase-etiqueta">Fase 1</span>
        <h1 class="h3 mb-1 mt-1">Defineix el grup de treball</h1>
        <p class="text-muted mb-0">Registra si faràs el projecte individualment o en parella.</p>
    </div>

    <div class="card shadow-sm border-0 rounded-4 p-4 p-lg-5">
        <?php if ($error !== ''): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="mb-4">
            <h2 class="h5 mb-3">Abans de continuar</h2>
            <ul class="fase-llista mb-0">
                <li class="mb-2">Aquesta decisió ha d’estar acordada prèviament.</li>
                <li class="mb-2">Si feu el projecte en parella, tots dos heu de completar aquesta tasca i seleccionar-vos mútuament.</li>
                <li class="mb-2">Una vegada definit el grup, no el podreu modificar directament.</li>
                <li>Si hi ha un error o necessiteu fer un canvi, parleu amb el tutor o tutora.</li>
            </ul>
        </div>

        <?php if ($tareaConfirmada): ?>
            <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Aquesta tasca ja està completada.</div>
            <a href="/el-meu-projecte/fase-1" class="btn btn-puig">Tornar a la Fase 1</a>
        <?php elseif (!$agrupacionRepresentable): ?>
            <div class="alert alert-warning mb-4">El projecte té una agrupació que no es pot confirmar des d’aquesta activitat. Parla amb el tutor o tutora.</div>
            <a href="/el-meu-projecte/fase-1" class="btn btn-puig">Tornar a la Fase 1</a>
        <?php else: ?>
            <form method="post" action="/index.php?main=alumne-fase-1-grup-accion" id="definir-grup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

                <fieldset class="mb-4">
                    <legend class="h5 mb-3">Com faràs el projecte?</legend>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="modalitat" id="modalitat-individual" value="individual" <?= $companeroActualId === 0 && $proyectoId > 0 ? 'checked' : '' ?> <?= $agrupacionPredefinida && $companeroActualId > 0 ? 'disabled' : '' ?> required>
                        <label class="form-check-label" for="modalitat-individual">Individualment</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modalitat" id="modalitat-parella" value="parella" <?= $companeroActualId > 0 ? 'checked' : '' ?> <?= $agrupacionPredefinida && $companeroActualId === 0 ? 'disabled' : '' ?> required>
                        <label class="form-check-label" for="modalitat-parella">En parella</label>
                    </div>
                </fieldset>

                <div id="camp-company" class="mb-4" <?= $companeroActualId > 0 ? '' : 'hidden' ?>>
                    <label for="company_id" class="form-label fw-semibold">Amb qui faràs el projecte?</label>
                    <select class="form-select" name="company_id" id="company_id" <?= $companeroActualId > 0 ? 'required' : 'disabled' ?>>
                        <option value="">Selecciona un company o companya</option>
                        <?php foreach ($companerosDisponibles as $companero): ?>
                            <option value="<?= (int) $companero['id_alumno'] ?>" <?= $companeroActualId === (int) $companero['id_alumno'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim((string) $companero['nombre'] . ' ' . (string) $companero['apellidos']), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($companerosDisponibles === []): ?>
                        <div class="form-text">No hi ha cap company o companya disponible en aquest grup.</div>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-puig-solid px-4" id="obrir-confirmacio">Continuar</button>
                    <a href="/el-meu-projecte/fase-1" class="btn btn-puig px-4">Cancel·lar</a>
                </div>
            </form>

            <div class="modal fade" id="confirmar-grup" tabindex="-1" aria-labelledby="confirmar-grup-titol" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow">
                        <div class="modal-header border-0 px-4 pt-4 pb-2">
                            <div>
                                <h5 class="modal-title" id="confirmar-grup-titol">Confirmar el grup de treball</h5>
                                <p class="text-muted mb-0 mt-1 small">Revisa la decisió abans de continuar.</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
                        </div>
                        <div class="modal-body px-4 py-3">
                            <p class="mb-2" id="resum-confirmacio"></p>
                            <p class="text-muted small mb-0">Després de confirmar, hauràs de parlar amb el tutor o tutora si necessites modificar el grup.</p>
                        </div>
                        <div class="modal-footer border-0 px-4 pt-2 pb-4">
                            <button type="button" class="btn btn-puig px-4" data-bs-dismiss="modal">Cancel·lar</button>
                            <button type="submit" form="definir-grup-form" class="btn btn-puig-solid px-4">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
        </section>
    </div>
</div>

<?php if (!$tareaConfirmada && $agrupacionRepresentable): ?>
<script>
(() => {
    const form = document.getElementById('definir-grup-form');
    const parella = document.getElementById('modalitat-parella');
    const campCompany = document.getElementById('camp-company');
    const company = document.getElementById('company_id');
    const obrir = document.getElementById('obrir-confirmacio');
    const resum = document.getElementById('resum-confirmacio');
    const modalElement = document.getElementById('confirmar-grup');
    if (!form || !parella || !campCompany || !company || !obrir || !resum || !modalElement) return;

    const actualitzarModalitat = () => {
        const enParella = parella.checked;
        campCompany.hidden = !enParella;
        company.disabled = !enParella;
        company.required = enParella;
    };
    form.querySelectorAll('input[name="modalitat"]').forEach((radio) => radio.addEventListener('change', actualitzarModalitat));
    obrir.addEventListener('click', () => {
        actualitzarModalitat();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        resum.textContent = parella.checked
            ? `Confirmes que faràs el projecte en parella amb ${company.selectedOptions[0].textContent.trim()}?`
            : 'Confirmes que faràs el projecte individualment?';
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
    actualitzarModalitat();
})();
</script>
<?php endif; ?>
