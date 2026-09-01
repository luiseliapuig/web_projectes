<?php
declare(strict_types=1);

// Detall de la tasca "Proposta de projecte" (Fase 2): tot l'espai de treball
// organitzat en TRES PASSOS interns d'UNA mateixa tasca (mai tres tasques,
// mai tres rutes noves): PAS 1 classificació del projecte, PAS 2 el flux
// documental que ja existia, PAS 3 la pujada del PDF definitiu que ja
// existia. Els tres passos són sempre visibles (encara que bloquejats), per
// que l'alumnat entengui des del principi el recorregut complet. La vista de
// fase (fase-2_tasques.php) només en mostra la targeta-resum; mai aquest
// contingut. Implementació concreta d'aquesta tasca, no un contracte general
// (vegeu docs/codex/arquitectura.md).

require_once __DIR__ . '/fase-2_proposta_funcions.php';

// ─────────────────────────────────────────────────────────────────────────
// Recursos/plantilles d'aquesta tasca. Configuració centralitzada: canvia
// aquí el text o l'URL sense tocar la maquetació de més avall. Si un URL
// queda buit, aquell recurs simplement no es mostra (no s'inventa cap URL).
// ─────────────────────────────────────────────────────────────────────────
$plantillaCaText = 'Proposta de projecte (ca)';
$plantillaCaUrl = 'https://docs.google.com/document/d/1kv2C17lt8Qs7Cm-LzsS338uHhmPJ5KlkqOUuodexJX0/edit?tab=t.0';

$plantillaEsText = 'Proposta de projecte (es)';
$plantillaEsUrl = 'https://docs.google.com/document/d/1kv2C17lt8Qs7Cm-LzsS338uHhmPJ5KlkqOUuodexJX0/edit?tab=t.0';

// ── Rol de qui visualitza: 'alumne' (per defecte) o 'professor'. Cada shell
// (fase-2_proposta.php per a l'alumnat, fase-2-tutor_proposta.php per al
// professorat) fixa aquestes variables abans d'incloure aquest fitxer. ──────
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$potValidarProposta = !empty($potValidar);
$esAlumnat = $rolVisualitzacio === 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);

$estat = fase2PropostaObtenirEstat($pdo, $idProjecte);
$propostaUrl = $estat['url'];
$propostaPdf = $estat['pdf'];
$propostaValidada = $estat['validada'];
$solicitudOberta = $estat['solicitud_oberta'];

// ── PAS 1: classificació del projecte (categoria +, si escau, subtipus). ──
$classificacio = fase2ClassificacioObtenirEstat($pdo, $idProjecte);
$pas1Completat = $classificacio['completat'];
$categoriaSeleccionadaVisual = $classificacio['categoria_id'] ?? $classificacio['categoria_per_defecte'];
$tiposCategoriaSeleccionada = $classificacio['tipos_per_categoria'][$categoriaSeleccionadaVisual] ?? [];

// ── PAS 2: el flux documental ja existent. Bloquejat mentre el Pas 1 no
// estigui complet — no n'hi ha prou amb amagar-ho visualment: fase-2_accion.php
// també ho torna a comprovar abans d'escriure res. ──────────────────────────
$pas2Bloquejat = !$pas1Completat;
// "Completat" a efectes d'aquest pas intern és només la validació del tutor
// (propuesta_validada_en), no el criteri global de la tasca (que exigeix a
// més el PDF definitiu): la tasca pot seguir pendent mentre aquest pas ja
// es dona per fet.
$pas2Completat = $propostaValidada;
$pas2Atencio = !$pas2Completat && $solicitudOberta !== null;
// Els recursos/botons d'un pas hereten SEMPRE el color del seu propi bloc,
// mai l'estat global de la tasca (que pot seguir groc per falta de PDF
// mentre aquest pas concret ja està completat en verd). Mateixa composició
// que ja fa servir fase2PropostaObtenirEstat(), recalculada aquí a escala
// de pas.
$pas2ClasseOutline = $pas2Completat ? 'btn-outline-success' : ($pas2Atencio ? 'btn-atencio' : 'btn-puig');
$pas2ClasseCta = $pas2Completat ? 'btn-outline-success' : ($pas2Atencio ? 'btn-atencio-solid' : 'btn-puig-solid');

// ── PAS 3: pujada del PDF definitiu. Disponible només un cop validat. ─────
$pas3Bloquejat = !$propostaValidada;
$pas3Completat = $estat['completada'];
$pas3Actiu = $propostaValidada && !$pas3Completat;
// El PDF definitiu, un cop existeix, es mostra sempre dins del Pas 3 (mai al
// Pas 2): és l'artefacte que el completa. Mentre el pas és actiu (validat,
// encara sense PDF) el color és groc d'atenció, mai el granate genèric.
$pas3ClasseOutline = $pas3Completat ? 'btn-outline-success' : 'btn-atencio';
?>

<!-- Subtítol de la tasca + estat global: mai un segon H1 (el propi
     fase_base.php ja renderitza "Proposta de projecte" com a H1 de la
     pàgina, amb el breadcrumb i l'eyebrow "Fase 2" per damunt). -->
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <p class="text-muted mb-0">Definiu la idea inicial del projecte i feu-la validar pel tutor o tutora.</p>
    <span class="badge rounded-pill px-3 py-2 <?= $estat['classe_badge'] ?>"><?= htmlspecialchars($estat['text'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     PAS 1 · Tipus de projecte
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="bloc <?= $pas1Completat ? 'bloc-completat' : 'bloc-activitat' ?> mb-4">
    <div class="bloc-contingut">
        <div class="bloc-tipus">PAS 1 · <?= $pas1Completat ? 'Completat' : 'Activitat' ?></div>
        <h2 class="h5 mb-2">Tipus de projecte</h2>
        <p class="mb-3">Defineix quin tipus de projecte fareu abans de preparar la proposta.</p>

        <?php if ($esAlumnat): ?>
            <div class="fase2-classificacio" data-proyecto-id="<?= $idProjecte ?>">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold" for="fase2-categoria-select">Desenvolupament / Investigació</label>
                        <select class="form-select form-select-sm<?= $pas1Completat ? ' form-completat' : '' ?>" id="fase2-categoria-select">
                            <?php foreach ($classificacio['categories'] as $categoria): ?>
                                <option value="<?= (int) $categoria['id_categoria_proyecto'] ?>" <?= (int) $categoria['id_categoria_proyecto'] === $categoriaSeleccionadaVisual ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6" id="fase2-tipo-columna" style="<?= $tiposCategoriaSeleccionada === [] ? 'display:none;' : '' ?>">
                        <label class="form-label small fw-semibold" for="fase2-tipo-select">Tipus de projecte</label>
                        <select class="form-select form-select-sm<?= $pas1Completat ? ' form-completat' : '' ?>" id="fase2-tipo-select">
                            <option value="">Selecciona el tipus…</option>
                            <?php foreach ($tiposCategoriaSeleccionada as $tipo): ?>
                                <option value="<?= (int) $tipo['id'] ?>" <?= $tipo['id'] === $classificacio['tipo_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $tipo['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="small text-muted mb-0 mt-2" id="fase2-classificacio-missatge">&nbsp;</p>
                <script type="application/json" id="fase2-tipos-per-categoria"><?= json_encode($classificacio['tipos_per_categoria'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            </div>
        <?php else: ?>
            <?php if ($classificacio['categoria_nombre'] === null): ?>
                <p class="mb-0"><span class="text-muted fst-italic">L’alumnat encara no ha classificat el projecte.</span></p>
            <?php elseif ($pas1Completat): ?>
                <!-- Classificació ja tancada: més pes visual (verd de
                     completat, semibold), mateix llenguatge que la resta de
                     "resultats completats" del sistema (vegeu
                     .fase-resultat-completat a fase-1_contingut.php). Sense
                     pastilla ni caixa pròpia: només text integrat al Pas 1. -->
                <p class="mb-0 fase-resultat-completat">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <?= htmlspecialchars((string) $classificacio['categoria_nombre'], ENT_QUOTES, 'UTF-8') ?><?= $classificacio['tipo_nombre'] !== null ? ' › ' . htmlspecialchars((string) $classificacio['tipo_nombre'], ENT_QUOTES, 'UTF-8') : '' ?>
                </p>
            <?php else: ?>
                <p class="mb-0"><?= htmlspecialchars((string) $classificacio['categoria_nombre'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════
     PAS 2 · Proposta de projecte
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="bloc <?= $pas2Bloquejat ? 'bloc-bloquejat' : ($pas2Completat ? 'bloc-completat' : ($pas2Atencio ? 'bloc-atencio' : 'bloc-activitat')) ?> mb-4">
    <div class="bloc-contingut">
        <div class="bloc-tipus">PAS 2 · <?= $pas2Bloquejat ? 'Bloquejat' : ($pas2Completat ? 'Completat' : ($pas2Atencio ? 'Atenció' : 'Activitat')) ?></div>
        <h2 class="h5 mb-2">Proposta de projecte</h2>
        <p class="mb-3">Treballa sobre la proposta i comparteix-la amb el tutor o tutora perquè la pugui revisar.</p>

        <?php if ($pas2Bloquejat): ?>
            <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer defineix el tipus de projecte.</p>
        <?php else: ?>
            <h3 class="h6 mb-3">Instruccions</h3>
            <ol class="fase-llista mb-3">
                <li class="mb-2">Crea a Google Drive una carpeta específica per al projecte: hi aniràs guardant, durant tot el curs, la documentació que es vagi generant.</li>
                <li class="mb-2">Fes una còpia de la plantilla de la Proposta de projecte i guarda-la dins d’aquesta carpeta.</li>
                <li class="mb-2">Comparteix el document amb el tutor o tutora perquè el pugui consultar i revisar.</li>
                <li class="mb-2">Treballa sobre aquest document: el pots anar modificant tantes vegades com calgui.</li>
                <li>Quan el consideris preparat per ser revisat, desa’n l’enllaç aquí sota i sol·licita la revisió.</li>
            </ol>

            <!-- Recursos/plantilles: documents de suport, no accions. Mai botons.
                 En una sola fila sempre que hi hagi espai (wrap natural en
                 pantalles petites): enllaços independents separats per un
                 punt purament visual, no un tercer enllaç. -->
            <div class="tasca-recursos mb-4">
                <?php if ($plantillaCaUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($plantillaCaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> <?= htmlspecialchars($plantillaCaText, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>
                <?php if ($plantillaCaUrl !== '' && $plantillaEsUrl !== ''): ?>
                    <span class="tasca-recursos-separador" aria-hidden="true">·</span>
                <?php endif; ?>
                <?php if ($plantillaEsUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($plantillaEsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> <?= htmlspecialchars($plantillaEsText, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Documents adjunts d'AQUEST pas: el document de treball (mai el
                 concepte intern "document viu"), anomenat pel que és. Outline
                 + geometria comuna (.btn-fase), color de l'estat del PROPI
                 Pas 2 (mai l'estat global de la tasca, que pot seguir groc
                 per falta de PDF encara que aquest pas ja estigui validat).
                 El PDF definitiu es mostra al Pas 3, on es puja: mai aquí. -->
            <?php if ($propostaUrl !== ''): ?>
                <h3 class="h6 mb-2">Documents adjunts</h3>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="<?= htmlspecialchars($propostaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase <?= $pas2ClasseOutline ?>">
                        <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Proposta de projecte
                    </a>
                </div>
            <?php elseif (!$esAlumnat): ?>
                <h3 class="h6 mb-2">Documents adjunts</h3>
                <p class="text-muted fst-italic mb-4">L’alumnat encara no ha desat cap enllaç.</p>
            <?php endif; ?>

            <?php if ($esAlumnat && $propostaPdf === ''): ?>
                <div class="fase2-doc-viu" data-proyecto-id="<?= $idProjecte ?>">
                    <label class="form-label small fw-semibold" for="fase2-url-input">Enllaç del document</label>
                    <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
                        <input type="url" class="form-control" id="fase2-url-input" placeholder="https://docs.google.com/…" value="<?= htmlspecialchars($propostaUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="button" class="btn btn-puig px-3" id="fase2-guardar-url-btn">Desar enllaç</button>
                    </div>
                    <p class="small text-muted mb-3">Recorda compartir el document amb el teu tutor o tutora perquè el pugui consultar i revisar.</p>
                    <p class="small text-muted mb-3" id="fase2-url-missatge">&nbsp;</p>

                    <?php if ($propostaValidada): ?>
                        <p class="small text-success mb-0"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> La proposta ha estat validada. Continua al Pas 3 per pujar-ne la versió definitiva en PDF.</p>
                    <?php elseif ($solicitudOberta !== null): ?>
                        <p class="small text-muted mb-0">
                            Revisió sol·licitada el <?= htmlspecialchars(fase2PropostaData((string) $solicitudOberta['solicitado_en']), ENT_QUOTES, 'UTF-8') ?>.
                            Pots continuar editant l’enllaç mentre esperes.
                        </p>
                    <?php else: ?>
                        <button type="button" class="btn btn-fase btn-puig-solid" id="fase2-solicitar-btn" <?= $propostaUrl === '' ? 'disabled' : '' ?>>Sol·licitar revisió</button>
                        <p class="small text-muted mb-0 mt-2" id="fase2-solicitar-missatge">&nbsp;</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
            // La intervenció del tutor pertany semànticament al Pas 2 (és
            // precisament la revisió d'aquest document): es renderitza com a
            // secció interna del mateix bloc, mai com un bloc germà que es
            // pugui confondre amb un quart pas. Mateixa condició d'autorització
            // i de sol·licitud d'abans, només reubicada aquí (no duplicada
            // enlloc més): exclusiva del tutor formal del projecte
            // (esTutorFormalDelProyecte, no un cotutor) i només mentre hi ha
            // una sol·licitud oberta encara no atesa. Un cop validada, la
            // intervenció desapareix — el propi Pas 2 ja mostra "Completat" i
            // el Pas 3 ja queda disponible; no calia repetir-ho aquí. No es
            // modela la devolució/correcció: si el document necessita canvis,
            // tutor i alumnat ho parlen fora de la plataforma. El tutor pot
            // tancar discretament la sol·licitud sense validar perquè
            // l'alumnat en pugui presentar una altra més endavant.
            $tutorInterventionVisible = $potValidarProposta && $solicitudOberta !== null && !$propostaValidada;
        ?>
        <?php if (!$esAlumnat && $tutorInterventionVisible): ?>
            <div class="bloc-zona bloc-zona-atencio fase2-tutor-intervencio position-relative" data-proyecto-id="<?= $idProjecte ?>">
                <button type="button" class="btn btn-link bloc-zona-tancar position-absolute top-0 end-0 mt-2 me-2 p-1" data-bs-toggle="modal" data-bs-target="#fase2-tancar-revisio-modal" aria-label="Tancar la sol·licitud de revisió">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
                <p class="text-uppercase small fw-semibold bloc-zona-titol">La teva intervenció com a tutor</p>
                <p class="mb-2">Revisió sol·licitada el <?= htmlspecialchars(fase2PropostaData((string) $solicitudOberta['solicitado_en']), ENT_QUOTES, 'UTF-8') ?>.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-fase <?= $pas2ClasseCta ?>" id="fase2-validar-btn">Validar proposta</button>
                </div>
                <p class="small text-muted mb-0 mt-2" id="fase2-tutor-missatge">&nbsp;</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!$esAlumnat && $tutorInterventionVisible): ?>
<div class="modal fade" id="fase2-tancar-revisio-modal" tabindex="-1" aria-labelledby="fase2-tancar-revisio-titol" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bloc-zona-atencio border-warning-subtle">
                <h2 class="modal-title fs-5 text-warning-emphasis" id="fase2-tancar-revisio-titol">Tancar la sol·licitud de revisió?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
            </div>
            <div class="modal-body"><p class="mb-0">La proposta no quedarà validada i l’alumne podrà tornar a sol·licitar-ne la revisió.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                <button type="button" class="btn btn-atencio-solid" id="fase2-tancar-revisio">Tancar sol·licitud</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════
     PAS 3 · Puja la proposta aprovada en PDF
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="bloc <?= $pas3Bloquejat ? 'bloc-bloquejat' : ($pas3Completat ? 'bloc-completat' : 'bloc-atencio') ?> mb-4">
    <div class="bloc-contingut">
        <div class="bloc-tipus">PAS 3 · <?= $pas3Bloquejat ? 'Bloquejat' : ($pas3Completat ? 'Completat' : 'Atenció') ?></div>
        <h2 class="h5 mb-2">Puja la proposta aprovada en PDF</h2>
        <p class="mb-3">Quan el tutor o tutora hagi validat la proposta, puja’n aquí la versió definitiva en PDF.</p>

        <?php if ($pas3Bloquejat): ?>
            <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Disponible quan el tutor o tutora hagi validat la proposta.</p>
        <?php elseif ($pas3Completat): ?>
            <p class="mb-0 fase-resultat-completat"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> PDF definitiu pujat correctament.</p>
            <?php if ($propostaPdf !== ''): ?>
                <h3 class="h6 mb-2 mt-3">Documents adjunts</h3>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= htmlspecialchars($propostaPdf, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase <?= $pas3ClasseOutline ?>">
                        <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> PDF definitiu
                    </a>
                </div>
            <?php endif; ?>
        <?php elseif ($esAlumnat): ?>
            <div class="fase2-pdf-pujada" data-proyecto-id="<?= $idProjecte ?>">
                <label class="form-label small fw-semibold" for="fase2-pdf-input">Fitxer PDF</label>
                <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
                    <input type="file" class="form-control" id="fase2-pdf-input" accept="application/pdf">
                    <button type="button" class="btn btn-puig-solid px-3" id="fase2-pujar-pdf-btn">Pujar PDF</button>
                </div>
                <p class="small text-muted mb-0" id="fase2-pdf-missatge">&nbsp;</p>
            </div>
        <?php else: ?>
            <p class="mb-0 text-muted">Pendent que l’alumnat hi pugi el PDF definitiu.</p>
        <?php endif; ?>
    </div>
</section>


<script>
(() => {
    const csrfToken = <?= json_encode(tokenCsrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const urlAccioAlumne = '/index.php?main=alumne-fase-2-accion';
    const urlAccioTutor = '/index.php?main=fase-2-tutor_accion';

    // ── PAS 1: classificació (autoguardat, sense botó "Desar") ─────────────
    const classificacio = document.querySelector('.fase2-classificacio');
    if (classificacio) {
        const proyectoId = classificacio.dataset.proyectoId;
        const selectCategoria = document.getElementById('fase2-categoria-select');
        const colTipo = document.getElementById('fase2-tipo-columna');
        const selectTipo = document.getElementById('fase2-tipo-select');
        const missatgeClassificacio = document.getElementById('fase2-classificacio-missatge');
        const tiposPerCategoriaEl = document.getElementById('fase2-tipos-per-categoria');
        let tiposPerCategoria = {};
        try {
            tiposPerCategoria = JSON.parse(tiposPerCategoriaEl?.textContent || '{}');
        } catch (error) {
            tiposPerCategoria = {};
        }

        const actualitzarOpcionsTipo = (categoriaId) => {
            const tipos = tiposPerCategoria[categoriaId] || tiposPerCategoria[String(categoriaId)] || [];
            selectTipo.innerHTML = '';
            const optBuit = document.createElement('option');
            optBuit.value = '';
            optBuit.textContent = 'Selecciona el tipus…';
            selectTipo.appendChild(optBuit);
            tipos.forEach((tipo) => {
                const opt = document.createElement('option');
                opt.value = String(tipo.id);
                opt.textContent = tipo.nombre;
                selectTipo.appendChild(opt);
            });
            colTipo.style.display = tipos.length > 0 ? '' : 'none';
        };

        selectCategoria?.addEventListener('change', async () => {
            const categoriaId = selectCategoria.value;
            actualitzarOpcionsTipo(categoriaId);
            selectCategoria.disabled = true;
            const dades = new FormData();
            dades.append('accio', 'guardar_categoria');
            dades.append('proyecto_id', proyectoId);
            dades.append('categoria_proyecto_id', categoriaId);
            dades.append('csrf_token', csrfToken);
            try {
                const resposta = await fetch(urlAccioAlumne, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    missatgeClassificacio.textContent = resultat.missatge || 'No s’ha pogut desar.';
                    selectCategoria.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (error) {
                missatgeClassificacio.textContent = 'Error de connexió.';
                selectCategoria.disabled = false;
            }
        });

        selectTipo?.addEventListener('change', async () => {
            const tipoId = selectTipo.value;
            if (!tipoId) {
                return;
            }
            selectTipo.disabled = true;
            const dades = new FormData();
            dades.append('accio', 'guardar_tipo');
            dades.append('proyecto_id', proyectoId);
            // S'envia també la categoria actualment mostrada: si el valor
            // per defecte del primer select ja coincidia amb la selecció de
            // l'alumnat, el seu "change" mai s'ha disparat i encara no s'ha
            // desat a BD. guardar_tipo la torna a validar i la desa sencera.
            dades.append('categoria_proyecto_id', selectCategoria.value);
            dades.append('tipo_proyecto_id', tipoId);
            dades.append('csrf_token', csrfToken);
            try {
                const resposta = await fetch(urlAccioAlumne, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    missatgeClassificacio.textContent = resultat.missatge || 'No s’ha pogut desar.';
                    selectTipo.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (error) {
                missatgeClassificacio.textContent = 'Error de connexió.';
                selectTipo.disabled = false;
            }
        });
    }

    // ── PAS 2: enllaç viu i sol·licitud de revisió ──────────────────────────
    const docViu = document.querySelector('.fase2-doc-viu');
    if (docViu) {
        const proyectoId = docViu.dataset.proyectoId;
        const inputUrl = document.getElementById('fase2-url-input');
        const botoGuardar = document.getElementById('fase2-guardar-url-btn');
        const missatgeUrl = document.getElementById('fase2-url-missatge');
        const botoSolicitar = document.getElementById('fase2-solicitar-btn');
        const missatgeSolicitar = document.getElementById('fase2-solicitar-missatge');

        botoGuardar?.addEventListener('click', async () => {
            const url = inputUrl.value.trim();
            if (!url) {
                return;
            }
            botoGuardar.disabled = true;
            const dades = new FormData();
            dades.append('accio', 'guardar_url');
            dades.append('proyecto_id', proyectoId);
            dades.append('url', url);
            dades.append('csrf_token', csrfToken);
            try {
                const resposta = await fetch(urlAccioAlumne, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    missatgeUrl.textContent = resultat.missatge || 'No s’ha pogut desar l’enllaç.';
                    botoGuardar.disabled = false;
                    return;
                }
                missatgeUrl.textContent = 'Enllaç desat.';
                if (botoSolicitar) {
                    botoSolicitar.disabled = false;
                }
                botoGuardar.disabled = false;
            } catch (error) {
                missatgeUrl.textContent = 'Error de connexió.';
                botoGuardar.disabled = false;
            }
        });

        botoSolicitar?.addEventListener('click', async () => {
            botoSolicitar.disabled = true;
            const dades = new FormData();
            dades.append('accio', 'solicitar_revisio');
            dades.append('proyecto_id', proyectoId);
            dades.append('csrf_token', csrfToken);
            try {
                const resposta = await fetch(urlAccioAlumne, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    missatgeSolicitar.textContent = resultat.missatge || 'No s’ha pogut sol·licitar la revisió.';
                    botoSolicitar.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (error) {
                missatgeSolicitar.textContent = 'Error de connexió.';
                botoSolicitar.disabled = false;
            }
        });
    }

    // ── PAS 3: pujada del PDF definitiu ─────────────────────────────────────
    const pdfPujada = document.querySelector('.fase2-pdf-pujada');
    if (pdfPujada) {
        const proyectoId = pdfPujada.dataset.proyectoId;
        const inputPdf = document.getElementById('fase2-pdf-input');
        const botoPdf = document.getElementById('fase2-pujar-pdf-btn');
        const missatgePdf = document.getElementById('fase2-pdf-missatge');

        botoPdf?.addEventListener('click', async () => {
            if (!inputPdf.files || !inputPdf.files[0]) {
                return;
            }
            botoPdf.disabled = true;
            const dades = new FormData();
            dades.append('accio', 'pujar_pdf');
            dades.append('proyecto_id', proyectoId);
            dades.append('pdf', inputPdf.files[0]);
            dades.append('csrf_token', csrfToken);
            try {
                const resposta = await fetch(urlAccioAlumne, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    missatgePdf.textContent = resultat.missatge || 'No s’ha pogut pujar el PDF.';
                    botoPdf.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (error) {
                missatgePdf.textContent = 'Error de connexió.';
                botoPdf.disabled = false;
            }
        });
    }

    const tutorIntervencio = document.querySelector('.fase2-tutor-intervencio');
    if (tutorIntervencio) {
        const proyectoId = tutorIntervencio.dataset.proyectoId;
        const missatge = document.getElementById('fase2-tutor-missatge');
        const enviar = async (accio, boto) => {
            boto.disabled = true;
            const dades = new FormData();
            dades.append('accio', accio);
            dades.append('proyecto_id', proyectoId);
            dades.append('csrf_token', csrfToken);
            try {
                const resposta = await fetch(urlAccioTutor, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    if (missatge) {
                        missatge.textContent = resultat.missatge || 'No s’ha pogut completar l’acció.';
                    }
                    boto.disabled = false;
                    return;
                }
                window.location.reload();
            } catch (error) {
                if (missatge) {
                    missatge.textContent = 'Error de connexió.';
                }
                boto.disabled = false;
            }
        };
        document.getElementById('fase2-validar-btn')?.addEventListener('click', (event) => enviar('validar', event.currentTarget));
        document.getElementById('fase2-tancar-revisio')?.addEventListener('click', (event) => enviar('tancar_solicitud', event.currentTarget));
    }
})();
</script>
