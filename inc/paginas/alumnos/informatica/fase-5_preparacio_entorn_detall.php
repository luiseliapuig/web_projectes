<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-5_repositoris_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estat = fase5RepositorisObtenirEstat($pdo, $idProjecte);
$fase5Seccio = $fase5Seccio ?? 'entorn';
$potValidarEntorn = !$esAlumnat && !empty($potValidarEntorn);
$plantillaEntornoCa = '';
$plantillaEntornoEs = '';
?>
<div class="d-grid gap-4">
    <?php if ($fase5Seccio === 'git'): ?>
    <section class="bloc <?= $estat['repositoris_informats'] ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estat['repositoris_informats'] ? 'Completada' : 'Activitat' ?></div>
            <h2>Repositoris Git</h2>
            <p class="mb-2">Tot projecte amb desenvolupament ha d’indicar almenys un repositori Git. Si tot el projecte viu en un únic repositori, deixa l’etiqueta buida. Si n’utilitzeu diversos, feu servir etiquetes breus per distingir-los.</p>
            <p class="small text-muted mb-4">Escriu només l’etiqueta, per exemple: Frontend, Backend, Web, App o API. El sistema mostrarà «Repositori Git (Etiqueta)».</p>

            <?php if ($esAlumnat): ?>
                <div class="mb-4">
                    <h3 class="h6 mb-3">Repositori principal</h3>
                    <div class="row g-3 align-items-end" style="max-width: 760px;">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold" for="fase5-git-principal-url">Enllaç</label>
                            <input type="url" class="form-control form-control-sm" id="fase5-git-principal-url" maxlength="2048" value="<?= htmlspecialchars($estat['principal_url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold" for="fase5-git-principal-etiqueta">Etiqueta <span class="fw-normal text-muted">(opcional)</span></label>
                            <input type="text" class="form-control form-control-sm" id="fase5-git-principal-etiqueta" maxlength="80" value="<?= htmlspecialchars($estat['principal_etiqueta'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Frontend">
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-fase btn-puig-solid" id="fase5-git-principal-desar">Desar</button>
                            <span class="small text-muted ms-2" id="fase5-git-principal-missatge"></span>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <?php if (!$esAlumnat): ?>
                <div class="mb-4">
                    <h3 class="h6 mb-3">Repositori principal</h3>
                    <?php if ($estat['principal_url'] !== ''): ?>
                        <a href="<?= htmlspecialchars($estat['principal_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-git" aria-hidden="true"></i> <?= htmlspecialchars($estat['principal_literal'], ENT_QUOTES, 'UTF-8') ?></a>
                        <div class="small text-muted text-break mt-1"><?= htmlspecialchars($estat['principal_url'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php else: ?>
                        <p class="text-muted fst-italic mb-0">Encara no s’ha desat el repositori principal.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($estat['addicionals'] !== []): ?>
                <div class="d-grid gap-3">
                    <?php foreach ($estat['addicionals'] as $repositori): ?>
                        <?php $literal = fase5RepositoriLiteral((string) $repositori['nom']); ?>
                        <div>
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <a href="<?= htmlspecialchars((string) $repositori['ruta'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-git" aria-hidden="true"></i> <?= htmlspecialchars($literal, ENT_QUOTES, 'UTF-8') ?></a>
                                <?php if ($esAlumnat): ?>
                                    <div class="d-flex align-items-center gap-2 small flex-shrink-0 fase5-git-accions" id="fase5-git-accions-<?= (int) $repositori['id'] ?>">
                                        <button type="button" class="btn btn-link link-secundari-puig p-0 small fase5-git-obrir-edicio" data-bs-toggle="collapse" data-bs-target="#fase5-git-editar-<?= (int) $repositori['id'] ?>" aria-expanded="false" aria-controls="fase5-git-editar-<?= (int) $repositori['id'] ?>">Editar</button>
                                        <span class="text-muted" aria-hidden="true">|</span>
                                        <button type="button" class="btn btn-link link-danger p-0 small fase5-git-obrir-eliminar" data-id="<?= (int) $repositori['id'] ?>" data-bs-toggle="modal" data-bs-target="#fase5-git-eliminar-modal">Eliminar</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted text-break mt-1"><?= htmlspecialchars((string) $repositori['ruta'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if ($esAlumnat): ?>
                                <div class="collapse mt-2" id="fase5-git-editar-<?= (int) $repositori['id'] ?>">
                                    <div class="row g-2 align-items-end" style="max-width: 760px;">
                                        <div class="col-md-7"><label class="form-label small" for="fase5-git-url-<?= (int) $repositori['id'] ?>">Enllaç</label><input type="url" class="form-control form-control-sm" id="fase5-git-url-<?= (int) $repositori['id'] ?>" maxlength="2048" value="<?= htmlspecialchars((string) $repositori['ruta'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="col-md-5"><label class="form-label small" for="fase5-git-etiqueta-<?= (int) $repositori['id'] ?>">Etiqueta</label><input type="text" class="form-control form-control-sm" id="fase5-git-etiqueta-<?= (int) $repositori['id'] ?>" maxlength="80" value="<?= htmlspecialchars((string) $repositori['nom'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                        <div class="col-12 d-flex flex-wrap align-items-center gap-3"><button type="button" class="btn btn-fase btn-puig fase5-git-editar" data-id="<?= (int) $repositori['id'] ?>">Desar canvis</button><button type="button" class="btn btn-link link-secundari-puig p-0 fase5-git-cancellar-edicio" data-id="<?= (int) $repositori['id'] ?>">Cancel·lar</button><span class="small text-muted" id="fase5-git-missatge-<?= (int) $repositori['id'] ?>"></span></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($esAlumnat): ?>
                <div class="<?= $estat['addicionals'] !== [] ? 'mt-2' : '' ?>">
                    <a href="#fase5-git-formulari-afegir" class="link-secundari-puig" id="fase5-git-mostrar-afegir" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="fase5-git-formulari-afegir">+ Afegir repositori</a>
                    <div class="collapse mt-3" id="fase5-git-formulari-afegir">
                        <div class="row g-3 align-items-end" style="max-width: 760px;">
                            <div class="col-md-7">
                                <label class="form-label small fw-semibold" for="fase5-git-nou-url">Enllaç</label>
                                <input type="url" class="form-control form-control-sm" id="fase5-git-nou-url" maxlength="2048" placeholder="https://..." required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold" for="fase5-git-nou-etiqueta">Etiqueta</label>
                                <input type="text" class="form-control form-control-sm" id="fase5-git-nou-etiqueta" maxlength="80" placeholder="Backend" required>
                            </div>
                            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                                <button type="button" class="btn btn-fase btn-puig-solid" id="fase5-git-afegir">Afegir repositori</button>
                                <button type="button" class="btn btn-link link-secundari-puig p-0" id="fase5-git-cancellar-afegir">Cancel·lar</button>
                                <span class="small text-muted" id="fase5-git-afegir-missatge"></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($esAlumnat && $estat['addicionals'] !== []): ?>
        <div class="modal fade" id="fase5-git-eliminar-modal" tabindex="-1" aria-labelledby="fase5-git-eliminar-titol" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="fase5-git-eliminar-titol">Eliminar repositori?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Aquest repositori deixarà d’estar associat al projecte.</p>
                        <p class="small text-danger mb-0 mt-2" id="fase5-git-eliminar-missatge"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                        <button type="button" class="btn btn-danger" id="fase5-git-confirmar-eliminar">Eliminar repositori</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php endif; ?>
    <?php if ($fase5Seccio === 'entorn'): ?>

    <section class="bloc <?= $estat['entorn_validat'] ? 'bloc-completat' : ($estat['entorn_solicitud_oberta'] ? 'bloc-atencio' : 'bloc-activitat') ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estat['entorn_validat'] ? 'Completada' : ($estat['entorn_solicitud_oberta'] ? 'Revisió sol·licitada' : 'Activitat') ?></div>
            <h2>Preparació de l’entorn</h2>
            <p class="mb-3">Abans de començar el desenvolupament, documenteu breument l’entorn tècnic i la manera de treballar de l’equip: eines, estructura general, base de dades o serveis si correspon, equips de treball i decisions tècniques necessàries per començar.</p>
            <p class="small text-muted mb-3">L’objectiu és deixar definits el terreny i les regles de treball, no crear una memòria extensa.</p>

            <?php if ($plantillaEntornoCa !== '' || $plantillaEntornoEs !== ''): ?>
                <div class="tasca-recursos mb-4">
                    <?php if ($plantillaEntornoCa !== ''): ?><a href="<?= htmlspecialchars($plantillaEntornoCa, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Preparació de l’entorn (ca)</a><?php endif; ?>
                    <?php if ($plantillaEntornoCa !== '' && $plantillaEntornoEs !== ''): ?><span class="tasca-recursos-separador" aria-hidden="true">·</span><?php endif; ?>
                    <?php if ($plantillaEntornoEs !== ''): ?><a href="<?= htmlspecialchars($plantillaEntornoEs, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Preparació de l’entorn (es)</a><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$esAlumnat): ?>
                <h3 class="h6 mb-2">Documents adjunts</h3>
                <?php if ($estat['entorn_url'] !== ''): ?>
                    <div class="d-flex flex-wrap gap-2 mb-4"><a href="<?= htmlspecialchars($estat['entorn_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase <?= $estat['entorn_validat'] ? 'btn-outline-success' : ($estat['entorn_solicitud_oberta'] ? 'btn-atencio' : 'btn-puig') ?>"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Preparació de l’entorn</a></div>
                <?php else: ?>
                    <p class="text-muted fst-italic mb-4">L’alumnat encara no ha desat cap enllaç.</p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($esAlumnat && $estat['entorn_pdf'] === ''): ?>
                <div>
                    <label class="form-label small fw-semibold" for="fase5-entorn-url">Enllaç del document</label>
                    <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
                        <input type="url" class="form-control" id="fase5-entorn-url" maxlength="2048" value="<?= htmlspecialchars($estat['entorn_url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://docs.google.com/…">
                        <button class="btn btn-puig px-3" type="button" id="fase5-entorn-guardar-url">Desar enllaç</button>
                    </div>
                    <p class="small text-muted mb-3">Recorda compartir el document amb el teu tutor o tutora perquè el pugui consultar i revisar.</p>
                    <p class="small text-muted mb-3" id="fase5-entorn-url-missatge">&nbsp;</p>
                    <?php if ($estat['entorn_validat']): ?>
                        <p class="small text-success mb-0"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> El document ha estat validat. Continua amb el PDF definitiu.</p>
                    <?php elseif ($estat['entorn_solicitud_oberta']): ?>
                        <p class="small text-muted mb-0">Revisió sol·licitada el <?= htmlspecialchars(fase5RepositorisData((string) $estat['entorn_solicitud_oberta']['solicitado_en']), ENT_QUOTES, 'UTF-8') ?>. Pots continuar editant l’enllaç mentre esperes.</p>
                    <?php else: ?>
                        <button type="button" class="btn btn-fase btn-puig-solid" id="fase5-entorn-sollicitar" <?= $estat['entorn_url'] === '' ? 'disabled' : '' ?>>Sol·licitar revisió</button>
                        <p class="small text-muted mb-0 mt-2" id="fase5-entorn-sollicitar-missatge">&nbsp;</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($potValidarEntorn && $estat['entorn_solicitud_oberta'] && !$estat['entorn_validat']): ?>
                <div class="bloc-zona bloc-zona-atencio position-relative">
                    <button type="button" class="btn btn-link bloc-zona-tancar position-absolute top-0 end-0 mt-2 me-2 p-1" data-bs-toggle="modal" data-bs-target="#fase5-entorn-tancar-revisio-modal" aria-label="Tancar la sol·licitud de revisió"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                    <p class="text-uppercase small fw-semibold bloc-zona-titol">La teva intervenció com a tutor</p>
                    <p class="mb-2">Revisió sol·licitada el <?= htmlspecialchars(fase5RepositorisData((string) $estat['entorn_solicitud_oberta']['solicitado_en']), ENT_QUOTES, 'UTF-8') ?>.</p>
                    <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-fase btn-atencio-solid" id="fase5-entorn-validar">Validar preparació de l’entorn</button></div>
                    <p class="small text-muted mb-0 mt-2" id="fase5-entorn-tutor-missatge">&nbsp;</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="bloc <?= $estat['entorn_completat'] ? 'bloc-completat' : ($estat['entorn_validat'] ? 'bloc-atencio' : 'bloc-bloquejat') ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estat['entorn_completat'] ? 'Completada' : ($estat['entorn_validat'] ? 'Pendent' : 'Bloquejada') ?></div>
            <h2>PDF definitiu</h2>
            <?php if (!$estat['entorn_validat']): ?>
                <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> El tutor o tutora ha de validar abans el document.</p>
            <?php elseif ($estat['entorn_pdf'] !== ''): ?>
                <a href="<?= htmlspecialchars($estat['entorn_pdf'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Document de preparació de l’entorn</a>
            <?php elseif ($esAlumnat): ?>
                <div class="input-group"><input type="file" class="form-control" id="fase5-entorn-pdf" accept="application/pdf,.pdf"><button class="btn btn-fase btn-atencio-solid" type="button" id="fase5-entorn-pujar-pdf">Pujar PDF</button></div>
                <p class="small text-muted mb-0 mt-2" id="fase5-entorn-pdf-missatge">&nbsp;</p>
            <?php else: ?>
                <p class="mb-0">Pendent que l’alumnat pugi el PDF definitiu.</p>
            <?php endif; ?>
        </div>
    </section>

<?php if ($potValidarEntorn && $estat['entorn_solicitud_oberta'] && !$estat['entorn_validat']): ?>
<div class="modal fade" id="fase5-entorn-tancar-revisio-modal" tabindex="-1" aria-labelledby="fase5-entorn-tancar-revisio-titol" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header bloc-zona-atencio border-warning-subtle"><h2 class="modal-title fs-5 text-warning-emphasis" id="fase5-entorn-tancar-revisio-titol">Tancar la sol·licitud de revisió?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button></div>
        <div class="modal-body"><p class="mb-0">El document no quedarà validat i l’alumne podrà tornar a sol·licitar-ne la revisió.</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button><button type="button" class="btn btn-atencio-solid" id="fase5-entorn-tancar-revisio">Tancar sol·licitud</button></div>
    </div></div>
</div>
<?php endif; ?>

<?php endif; ?>
</div>

<?php if ($fase5Seccio === 'entorn'): ?>
<script>
(() => {
    const projecteId = <?= $idProjecte ?>;
    const csrf = <?= json_encode(tokenCsrf()) ?>;

    async function enviaEntorn(endpoint, accio, dades, missatge) {
        dades.append('accio', accio);
        dades.append('proyecto_id', projecteId);
        dades.append('csrf_token', csrf);
        try {
            const resposta = await fetch(endpoint, {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut completar l’acció.');
            window.location.reload();
        } catch (error) {
            if (missatge) missatge.textContent = error.message;
        }
    }

    document.getElementById('fase5-entorn-guardar-url')?.addEventListener('click', async () => {
        const input = document.getElementById('fase5-entorn-url');
        if (!input.reportValidity()) return;
        const dades = new FormData(); dades.append('url', input.value.trim());
        enviaEntorn('/index.php?main=alumne-fase-5-entorn-accion', 'guardar_url', dades, document.getElementById('fase5-entorn-url-missatge'));
    });
    document.getElementById('fase5-entorn-sollicitar')?.addEventListener('click', () => {
        enviaEntorn('/index.php?main=alumne-fase-5-entorn-accion', 'solicitar_revisio', new FormData(), document.getElementById('fase5-entorn-sollicitar-missatge'));
    });
    document.getElementById('fase5-entorn-pujar-pdf')?.addEventListener('click', () => {
        const input = document.getElementById('fase5-entorn-pdf');
        if (!input.files[0]) return;
        const dades = new FormData(); dades.append('pdf', input.files[0]);
        enviaEntorn('/index.php?main=alumne-fase-5-entorn-accion', 'pujar_pdf', dades, document.getElementById('fase5-entorn-pdf-missatge'));
    });
    document.getElementById('fase5-entorn-validar')?.addEventListener('click', () => {
        enviaEntorn('/index.php?main=fase-5-tutor-entorn-accion', 'validar', new FormData(), document.getElementById('fase5-entorn-tutor-missatge'));
    });
    document.getElementById('fase5-entorn-tancar-revisio')?.addEventListener('click', () => {
        enviaEntorn('/index.php?main=fase-5-tutor-entorn-accion', 'tancar_solicitud', new FormData(), document.getElementById('fase5-entorn-tutor-missatge'));
    });
})();
</script>
<?php endif; ?>

<?php if ($esAlumnat && $fase5Seccio === 'git'): ?>
<script>
(() => {
    const endpoint = '/index.php?main=alumne-fase-5-repositoris-accion';
    const projecteId = <?= $idProjecte ?>;
    const csrf = <?= json_encode(tokenCsrf()) ?>;
    const formulariAfegir = document.getElementById('fase5-git-formulari-afegir');
    const mostrarAfegir = document.getElementById('fase5-git-mostrar-afegir');
    const formularisEdicio = [...document.querySelectorAll('[id^="fase5-git-editar-"]')];
    const accionsRepositori = [...document.querySelectorAll('.fase5-git-accions')];
    const modalEliminar = document.getElementById('fase5-git-eliminar-modal');
    let repositoriEliminarId = null;

    function mostraAccionsRepositori(mostrar) {
        accionsRepositori.forEach((accions) => { accions.hidden = !mostrar; });
    }

    formulariAfegir?.addEventListener('show.bs.collapse', () => {
        mostrarAfegir.hidden = true;
        mostraAccionsRepositori(false);
    });
    formulariAfegir?.addEventListener('hidden.bs.collapse', () => {
        mostrarAfegir.hidden = false;
        mostraAccionsRepositori(true);
    });
    document.getElementById('fase5-git-cancellar-afegir')?.addEventListener('click', () => {
        document.getElementById('fase5-git-nou-url').value = '';
        document.getElementById('fase5-git-nou-etiqueta').value = '';
        document.getElementById('fase5-git-afegir-missatge').textContent = '';
        bootstrap.Collapse.getOrCreateInstance(formulariAfegir).hide();
    });

    formularisEdicio.forEach((formulari) => {
        formulari.addEventListener('show.bs.collapse', () => {
            mostrarAfegir.hidden = true;
            mostraAccionsRepositori(false);
        });
        formulari.addEventListener('hidden.bs.collapse', () => {
            if (!formularisEdicio.some((element) => element.classList.contains('show'))) {
                mostrarAfegir.hidden = false;
                mostraAccionsRepositori(true);
            }
        });
    });

    document.querySelectorAll('.fase5-git-cancellar-edicio').forEach((boto) => boto.addEventListener('click', () => {
        const id = boto.dataset.id;
        const formulari = document.getElementById('fase5-git-editar-' + id);
        formulari.querySelectorAll('input').forEach((input) => { input.value = input.defaultValue; });
        document.getElementById('fase5-git-missatge-' + id).textContent = '';
        bootstrap.Collapse.getOrCreateInstance(formulari).hide();
    }));

    async function envia(accio, dades, missatge) {
        dades.append('accio', accio);
        dades.append('proyecto_id', projecteId);
        dades.append('csrf_token', csrf);
        try {
            const resposta = await fetch(endpoint, {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut completar l’acció.');
            window.location.reload();
        } catch (error) {
            if (missatge) missatge.textContent = error.message;
        }
    }

    document.getElementById('fase5-git-principal-desar')?.addEventListener('click', () => {
        const url = document.getElementById('fase5-git-principal-url');
        if (url.value.trim() !== '' && !url.reportValidity()) return;
        const dades = new FormData();
        dades.append('url', url.value.trim());
        dades.append('etiqueta', document.getElementById('fase5-git-principal-etiqueta').value.trim());
        envia('guardar_principal', dades, document.getElementById('fase5-git-principal-missatge'));
    });

    document.getElementById('fase5-git-afegir')?.addEventListener('click', () => {
        const url = document.getElementById('fase5-git-nou-url');
        const etiqueta = document.getElementById('fase5-git-nou-etiqueta');
        if (!url.reportValidity() || !etiqueta.reportValidity()) return;
        const dades = new FormData(); dades.append('url', url.value.trim()); dades.append('etiqueta', etiqueta.value.trim());
        envia('afegir', dades, document.getElementById('fase5-git-afegir-missatge'));
    });

    document.querySelectorAll('.fase5-git-editar').forEach((boto) => boto.addEventListener('click', () => {
        const id = boto.dataset.id;
        const url = document.getElementById('fase5-git-url-' + id);
        const etiqueta = document.getElementById('fase5-git-etiqueta-' + id);
        if (!url.reportValidity() || !etiqueta.reportValidity()) return;
        const dades = new FormData(); dades.append('id', id); dades.append('url', url.value.trim()); dades.append('etiqueta', etiqueta.value.trim());
        envia('editar', dades, document.getElementById('fase5-git-missatge-' + id));
    }));

    document.querySelectorAll('.fase5-git-obrir-eliminar').forEach((boto) => boto.addEventListener('click', () => {
        repositoriEliminarId = boto.dataset.id;
        document.getElementById('fase5-git-eliminar-missatge').textContent = '';
    }));

    modalEliminar?.addEventListener('hidden.bs.modal', () => {
        repositoriEliminarId = null;
        document.getElementById('fase5-git-eliminar-missatge').textContent = '';
    });

    document.getElementById('fase5-git-confirmar-eliminar')?.addEventListener('click', () => {
        if (repositoriEliminarId === null) return;
        const dades = new FormData();
        dades.append('id', repositoriEliminarId);
        envia('eliminar', dades, document.getElementById('fase5-git-eliminar-missatge'));
    });
})();
</script>
<?php endif; ?>
