<?php
declare(strict_types=1);

// Contingut de la tasca "Defineix el grup de treball" (Fase 1). Wrapper:
// fase-1_grup_form.php (gate + dades).
?>
<p class="text-muted mb-4">Registra si faràs el projecte individualment o en parella.</p>

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
    <div class="alert alert-success mb-0"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Aquesta tasca ja està completada.</div>
<?php elseif (!$agrupacionRepresentable): ?>
    <div class="alert alert-warning mb-0">El projecte té una agrupació que no es pot confirmar des d’aquesta activitat. Parla amb el tutor o tutora.</div>
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
            <button type="button" class="btn btn-fase btn-puig-solid" id="obrir-confirmacio">Continuar</button>
            <a href="/fases-del-projecte/fase-1" class="btn btn-fase btn-puig">Cancel·lar</a>
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
                    <button type="button" class="btn btn-fase btn-puig" data-bs-dismiss="modal">Cancel·lar</button>
                    <button type="submit" form="definir-grup-form" class="btn btn-fase btn-puig-solid">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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
