<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-3_document_funcional_funcions.php';
require __DIR__ . '/fase-3_recursos.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$potValidarFuncional = !empty($potValidar);
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estat = fase3DocumentFuncionalObtenirEstat($pdo, $idProjecte);
$pas1Completat = $estat['validat'];
$pas1Bloc = $pas1Completat ? 'bloc-completat' : ($estat['solicitud_oberta'] ? 'bloc-atencio' : 'bloc-activitat');
$pas1Badge = $pas1Completat ? 'text-bg-success' : ($estat['solicitud_oberta'] ? 'text-bg-warning' : 'badge-activitat');
$pas1Text = $pas1Completat ? 'Completat' : ($estat['solicitud_oberta'] ? 'Revisió sol·licitada' : 'En curs');
$pas2Bloc = $estat['completada'] ? 'bloc-completat' : ($pas1Completat ? 'bloc-atencio' : 'bloc-bloquejat');
?>
<div class="d-grid gap-4">
<section class="bloc <?= $pas1Bloc ?>">
 <div class="bloc-contingut">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2"><div><div class="bloc-tipus">Pas 1</div><h2>Document funcional</h2></div><span class="badge rounded-pill px-3 py-2 <?= $pas1Badge ?>"><?= htmlspecialchars($pas1Text, ENT_QUOTES, 'UTF-8') ?></span></div>
  <p class="mb-3">Creeu el document funcional, compartiu-ne l’enllaç i demaneu-ne la revisió al tutor o tutora.</p>
  <?php if ($fase3PlantillaCaUrl !== '' || $fase3PlantillaEsUrl !== ''): ?>
      <div class="tasca-recursos mb-4">
          <?php if ($fase3PlantillaCaUrl !== ''): ?><a href="<?= htmlspecialchars($fase3PlantillaCaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Document funcional (ca)</a><?php endif; ?>
          <?php if ($fase3PlantillaCaUrl !== '' && $fase3PlantillaEsUrl !== ''): ?><span class="tasca-recursos-separador" aria-hidden="true">·</span><?php endif; ?>
          <?php if ($fase3PlantillaEsUrl !== ''): ?><a href="<?= htmlspecialchars($fase3PlantillaEsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Document funcional (es)</a><?php endif; ?>
      </div>
  <?php endif; ?>

  <?php if (!$esAlumnat): ?>
      <h3 class="h6 mb-2">Documents adjunts</h3>
      <?php if ($estat['url'] !== ''): ?>
          <div class="d-flex flex-wrap gap-2 mb-4"><a href="<?= htmlspecialchars($estat['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase <?= $estat['classe_outline'] ?>"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Document funcional</a></div>
      <?php else: ?>
          <p class="text-muted fst-italic mb-4">L’alumnat encara no ha desat cap enllaç.</p>
      <?php endif; ?>
  <?php endif; ?>

  <?php if ($esAlumnat && $estat['pdf'] === ''): ?>
      <div class="fase3-doc-viu" data-proyecto-id="<?= $idProjecte ?>">
          <label class="form-label small fw-semibold" for="fase3-url">Enllaç del document</label>
          <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
              <input type="url" class="form-control" id="fase3-url" maxlength="2048" value="<?= htmlspecialchars($estat['url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://docs.google.com/…">
              <button class="btn btn-puig px-3" type="button" id="fase3-guardar-url">Desar enllaç</button>
          </div>
          <p class="small text-muted mb-3">Recorda compartir el document amb el teu tutor o tutora perquè el pugui consultar i revisar.</p>
          <p class="small text-muted mb-3" id="fase3-url-missatge">&nbsp;</p>
          <?php if ($estat['validat']): ?>
              <p class="small text-success mb-0"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> El document funcional ha estat validat. Continua al Pas 2 per pujar-ne la versió definitiva en PDF.</p>
          <?php elseif ($estat['solicitud_oberta']): ?>
              <p class="small text-muted mb-0">Revisió sol·licitada el <?= htmlspecialchars(fase3DocumentFuncionalData((string) $estat['solicitud_oberta']['solicitado_en']), ENT_QUOTES, 'UTF-8') ?>. Pots continuar editant l’enllaç mentre esperes.</p>
          <?php else: ?>
              <button type="button" class="btn btn-fase btn-puig-solid" id="fase3-sollicitar" <?= $estat['url'] === '' ? 'disabled' : '' ?>>Sol·licitar revisió</button>
              <p class="small text-muted mb-0 mt-2" id="fase3-solicitar-missatge">&nbsp;</p>
          <?php endif; ?>
      </div>
  <?php endif; ?>
  <?php if (!$esAlumnat && $potValidarFuncional && $estat['solicitud_oberta'] && !$estat['validat']): ?>
      <div class="bloc-zona bloc-zona-atencio position-relative" data-proyecto-id="<?= $idProjecte ?>">
          <button type="button" class="btn btn-link bloc-zona-tancar position-absolute top-0 end-0 mt-2 me-2 p-1" data-bs-toggle="modal" data-bs-target="#fase3-tancar-revisio-modal" aria-label="Tancar la sol·licitud de revisió">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
          </button>
          <p class="text-uppercase small fw-semibold bloc-zona-titol">La teva intervenció com a tutor</p>
          <p class="mb-2">Revisió sol·licitada el <?= htmlspecialchars(fase3DocumentFuncionalData((string) $estat['solicitud_oberta']['solicitado_en']), ENT_QUOTES, 'UTF-8') ?>.</p>
          <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-fase btn-atencio-solid" id="fase3-validar">Validar document funcional</button>
          </div>
          <p class="small text-muted mb-0 mt-2" id="fase3-tutor-missatge">&nbsp;</p>
      </div>
  <?php endif; ?>
 </div>
</section>
<section class="bloc <?= $pas2Bloc ?>"><div class="bloc-contingut"><div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2"><div><div class="bloc-tipus">Pas 2</div><h2>PDF definitiu</h2></div><span class="badge rounded-pill px-3 py-2 <?= $estat['completada'] ? 'text-bg-success' : ($estat['validat'] ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= $estat['completada'] ? 'Completat' : ($estat['validat'] ? 'Pendent' : 'Bloquejat') ?></span></div>
<?php if (!$estat['validat']): ?><p class="mb-0"><i class="bi bi-lock-fill me-1"></i> El tutor o tutora ha de validar abans el document.</p><?php elseif ($estat['pdf'] !== ''): ?><a href="<?= htmlspecialchars($estat['pdf'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat"><i class="bi bi-file-earmark-pdf"></i> Document funcional definitiu</a><?php elseif ($esAlumnat): ?><div class="input-group"><input type="file" class="form-control" id="fase3-pdf" accept="application/pdf,.pdf"><button class="btn btn-fase btn-atencio-solid" type="button" id="fase3-pujar-pdf">Pujar PDF</button></div><p class="small text-muted mb-0 mt-2" id="fase3-pdf-missatge">&nbsp;</p><?php else: ?><p class="mb-0">Pendent que l’alumnat pugi el PDF definitiu.</p><?php endif; ?></div></section>
</div>

<?php if (!$esAlumnat && $potValidarFuncional && $estat['solicitud_oberta'] && !$estat['validat']): ?>
<div class="modal fade" id="fase3-tancar-revisio-modal" tabindex="-1" aria-labelledby="fase3-tancar-revisio-titol" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bloc-zona-atencio border-warning-subtle">
                <h2 class="modal-title fs-5 text-warning-emphasis" id="fase3-tancar-revisio-titol">Tancar la sol·licitud de revisió?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
            </div>
            <div class="modal-body"><p class="mb-0">El document no quedarà validat i l’alumne podrà tornar a sol·licitar-ne la revisió.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                <button type="button" class="btn btn-atencio-solid" id="fase3-tancar-revisio">Tancar sol·licitud</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(() => {
    const csrf = <?= json_encode(tokenCsrf()) ?>;
    const pid = <?= $idProjecte ?>;
    const endpointAlumne = '/index.php?main=alumne-fase-3-accion';

    async function envia(url, dades, missatge) {
        dades.append('proyecto_id', pid);
        dades.append('csrf_token', csrf);
        try {
            const resposta = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut completar l’acció.');
            window.location.reload();
        } catch (error) {
            if (missatge) missatge.textContent = error.message;
        }
    }

    const inputUrl = document.getElementById('fase3-url');
    const botoGuardar = document.getElementById('fase3-guardar-url');
    const botoSolicitar = document.getElementById('fase3-sollicitar');
    const missatgeUrl = document.getElementById('fase3-url-missatge');
    const missatgeSolicitar = document.getElementById('fase3-solicitar-missatge');

    botoGuardar?.addEventListener('click', async () => {
        if (!inputUrl.reportValidity()) return;
        botoGuardar.disabled = true;
        const dades = new FormData();
        dades.append('accio', 'guardar_url');
        dades.append('proyecto_id', pid);
        dades.append('url', inputUrl.value.trim());
        dades.append('csrf_token', csrf);
        try {
            const resposta = await fetch(endpointAlumne, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: dades });
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut desar l’enllaç.');
            missatgeUrl.textContent = 'Enllaç desat.';
            if (botoSolicitar) botoSolicitar.disabled = false;
        } catch (error) {
            missatgeUrl.textContent = error.message;
        } finally {
            botoGuardar.disabled = false;
        }
    });

    botoSolicitar?.addEventListener('click', () => {
        const dades = new FormData();
        dades.append('accio', 'solicitar_revisio');
        envia(endpointAlumne, dades, missatgeSolicitar);
    });
    document.getElementById('fase3-pujar-pdf')?.addEventListener('click', () => {
        const fitxer = document.getElementById('fase3-pdf');
        if (!fitxer.files[0]) return;
        const dades = new FormData();
        dades.append('accio', 'pujar_pdf');
        dades.append('pdf', fitxer.files[0]);
        envia(endpointAlumne, dades, document.getElementById('fase3-pdf-missatge'));
    });
    document.getElementById('fase3-validar')?.addEventListener('click', () => {
        const dades = new FormData();
        dades.append('accio', 'validar');
        envia('/index.php?main=fase-3-tutor_accion', dades, document.getElementById('fase3-tutor-missatge'));
    });
    document.getElementById('fase3-tancar-revisio')?.addEventListener('click', () => {
        const dades = new FormData();
        dades.append('accio', 'tancar_solicitud');
        envia('/index.php?main=fase-3-tutor_accion', dades, document.getElementById('fase3-tutor-missatge'));
    });
})();
</script>
