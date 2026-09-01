<?php
declare(strict_types=1);

// Component compartit pels modes automàtic (sense tutor) i manual (tots).
?>
<div class="card shadow-sm rounded-4 border mt-3 resum-tutors-component">
    <div class="resum-tutors-cap d-flex justify-content-between align-items-center gap-3 <?= $modeTutorsManual ? 'resum-tutors-cap--manual' : 'resum-tutors-cap--pendent' ?>">
            <h2 class="h6 text-uppercase mb-0 d-flex align-items-center gap-2">
                <?php if (!$modeTutorsManual): ?><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><?php endif; ?>
                <span><?= $modeTutorsManual ? 'Tutors' : 'Projectes pendents d’assignar tutor' ?></span>
            </h2>
            <?php if ($modeTutorsManual): ?>
                <a href="/resum?grupo_id=<?= $grupoId ?>" class="small resum-link-secundari">Tancar</a>
            <?php endif; ?>
    </div>
    <div class="bg-white">
        <?php if ($feedbackTutorActualitzat || $errorGestioTutors !== ''): ?>
        <div class="px-3 pt-3">
        <?php if ($feedbackTutorActualitzat): ?>
            <p class="small text-success fw-semibold mb-3" role="status">Tutor actualitzat</p>
        <?php endif; ?>
        <?php if ($errorGestioTutors !== ''): ?>
            <p class="small text-danger fw-semibold mb-3" role="alert"><?= htmlspecialchars($errorGestioTutors, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($projectesGestioTutors === []): ?>
            <p class="text-muted px-3 pb-3 mb-0">Aquest grup no té cap projecte actiu.</p>
        <?php else: ?>
            <div class="resum-tutors-files">
                <?php foreach ($projectesGestioTutors as $projecteTutor): ?>
                    <?php
                    $idProjecteTutor = (int) $projecteTutor['id_proyecto'];
                    $membresTutor = $miembrosPorProyecto[$idProjecteTutor] ?? [];
                    $nomProjecteTutor = trim((string) $projecteTutor['nombre']);
                    if ($nomProjecteTutor === '') {
                        $nomProjecteTutor = $membresTutor !== [] ? implode(' / ', $membresTutor) : 'Projecte ' . $idProjecteTutor;
                    }
                    $identitatProjecteTutor = $membresTutor !== [] ? implode(' · ', $membresTutor) : $nomProjecteTutor;
                    $tutorsActuals = $tutorsPerProjecte[$idProjecteTutor] ?? [];
                    $tutorActual = count($tutorsActuals) === 1 ? $tutorsActuals[0] : null;
                    $candidatsTutor = $candidatsTutorPerProjecte[$idProjecteTutor] ?? [];
                    ?>
                    <div class="resum-tutors-fila" data-projecte-tutor data-tutor-actual-id="<?= (int) ($tutorActual['id_profesor'] ?? 0) ?>">
                        <div class="resum-tutors-identitat fw-semibold"><?= htmlspecialchars($identitatProjecteTutor, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="resum-tutors-opcions"><div class="w-100">
                        <?php if (count($tutorsActuals) > 1): ?>
                            <div class="small text-danger mb-2">Aquest projecte té més d’un tutor formal. Assigna’n un per corregir-ho.</div>
                        <?php endif; ?>
                        <?php if ($candidatsTutor === []): ?>
                            <div class="small text-muted">No hi ha cap relació professor-projecte disponible per assignar.</div>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($candidatsTutor as $candidatTutor): ?>
                                    <?php $esTutorActual = $tutorActual !== null && (int) $tutorActual['id_profesor'] === (int) $candidatTutor['id_profesor']; ?>
                                    <div class="form-check form-check-inline mb-0 me-3">
                                        <input class="form-check-input js-tutor-radio" type="radio"
                                               name="tutor_projecte_<?= $idProjecteTutor ?>"
                                               id="tutor-projecte-<?= $idProjecteTutor ?>-<?= (int) $candidatTutor['id_profesor'] ?>"
                                               value="<?= (int) $candidatTutor['id_profesor'] ?>"
                                               data-projecte-id="<?= $idProjecteTutor ?>"
                                               data-projecte-nom="<?= htmlspecialchars($nomProjecteTutor, ENT_QUOTES, 'UTF-8') ?>"
                                               data-professor-nom="<?= htmlspecialchars((string) $candidatTutor['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                               data-tutor-actual-nom="<?= htmlspecialchars((string) ($tutorActual['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                               <?= $esTutorActual ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="tutor-projecte-<?= $idProjecteTutor ?>-<?= (int) $candidatTutor['id_profesor'] ?>"><?= htmlspecialchars((string) $candidatTutor['nombre'], ENT_QUOTES, 'UTF-8') ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="assignarTutorModal" tabindex="-1" aria-labelledby="assignarTutorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="/index.php?main=resum-tutor_assignar-tutor" id="assignar-tutor-form">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="assignarTutorModalLabel">Assignar tutor</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
            </div>
            <div class="modal-body"><p class="mb-0" id="assignar-tutor-missatge"></p></div>
            <div class="modal-footer">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="proyecto_id" value="">
                <input type="hidden" name="profesor_id" value="">
                <input type="hidden" name="grupo_id" value="<?= $grupoId ?>">
                <input type="hidden" name="mode_tutors" value="<?= $modeTutorsManual ? 'manual' : 'pendents' ?>">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                <button type="submit" class="btn btn-puig-solid">Confirmar</button>
            </div>
        </form>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('assignarTutorModal');
    const formulari = document.getElementById('assignar-tutor-form');
    if (!modalElement || !formulari || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalElement);
    const missatge = document.getElementById('assignar-tutor-missatge');
    let radioProposat = null;
    let confirmant = false;

    document.querySelectorAll('.js-tutor-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            radioProposat = radio;
            confirmant = false;
            formulari.elements.proyecto_id.value = radio.dataset.projecteId;
            formulari.elements.profesor_id.value = radio.value;
            const tutorActual = radio.dataset.tutorActualNom || '';
            let text = 'Aquesta acció assignarà ' + radio.dataset.professorNom
                + ' com a tutor del projecte ' + radio.dataset.projecteNom + '.';
            if (tutorActual && tutorActual !== radio.dataset.professorNom) {
                text += ' El tutor actual, ' + tutorActual + ', passarà a ser cotutor.';
            }
            missatge.textContent = text;
            modal.show();
        });
    });

    formulari.addEventListener('submit', function () { confirmant = true; });
    modalElement.addEventListener('hidden.bs.modal', function () {
        if (confirmant || !radioProposat) return;
        const contenidor = radioProposat.closest('[data-projecte-tutor]');
        if (!contenidor) return;
        const tutorActualId = contenidor.dataset.tutorActualId;
        contenidor.querySelectorAll('.js-tutor-radio').forEach(function (radio) {
            radio.checked = tutorActualId !== '0' && radio.value === tutorActualId;
        });
        radioProposat = null;
    });
});
</script>
