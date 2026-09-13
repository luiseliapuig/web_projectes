<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-6_memoria_funcions.php';
require_once __DIR__ . '/fase-2_proposta_funcions.php';
require_once __DIR__ . '/enlaces-recursos.php';

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estatMemoria = fase6MemoriaObtenirEstat($pdo, $projecteId);
$classificacio = fase2ClassificacioObtenirEstat($pdo, $projecteId);
$plantillaMemoriaCaUrl = '';
$plantillaMemoriaEsUrl = '';
switch ($classificacio['categoria_id']) {
    case 2: // Projecte de desenvolupament
        $plantillaMemoriaCaUrl = $plantilla_memoria_desarrollo_ca;
        $plantillaMemoriaEsUrl = $plantilla_memoria_desarrollo_es;
        break;
    case 1: // Projecte d'investigació
    case 5: // Projecte I+D: comparteix la família documental d'investigació
        $plantillaMemoriaCaUrl = $plantilla_memoria_investigacion_ca;
        $plantillaMemoriaEsUrl = $plantilla_memoria_investigacion_es;
        break;
}
$enlaceRevisioMemoria = '/memoria';
?>
<section class="bloc <?= $estatMemoria['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
    <div class="bloc-contingut">
        <div class="bloc-tipus"><?= $estatMemoria['completada'] ? 'Completada' : 'Activitat' ?></div>
        <h2>Document de la memòria</h2>
        <p class="mb-3">Poseu en marxa el document viu de la memòria i manteniu-lo actualitzat durant el desenvolupament del projecte.</p>

        <ol class="mb-3 ps-3">
            <li class="mb-2">Escolliu una de les dues plantilles i creeu-ne la vostra pròpia còpia.</li>
            <li>Utilitzeu aquesta còpia com a document viu de la memòria i aneu-la completant durant el projecte.</li>
        </ol>

        <?php if ($plantillaMemoriaCaUrl !== '' || $plantillaMemoriaEsUrl !== ''): ?>
            <div class="tasca-recursos mb-4">
                <?php if ($plantillaMemoriaCaUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($plantillaMemoriaCaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Memòria del projecte (ca)</a>
                <?php endif; ?>
                <?php if ($plantillaMemoriaCaUrl !== '' && $plantillaMemoriaEsUrl !== ''): ?>
                    <span class="tasca-recursos-separador" aria-hidden="true">·</span>
                <?php endif; ?>
                <?php if ($plantillaMemoriaEsUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($plantillaMemoriaEsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Memòria del projecte (es)</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <ol class="mb-4 ps-3" start="3">
            <li class="mb-2"><strong>Compartiu el document de manera que el professorat hi pugui accedir.</strong> No n’hi ha prou que només funcioni des del vostre compte.</li>
            <li>Enganxeu aquí l’enllaç de la vostra còpia, no l’enllaç de la plantilla.</li>
        </ol>

        <?php if ($esAlumnat): ?>
            <div>
                <label class="form-label small fw-semibold" for="fase6-memoria-url">Enllaç al document de la memòria</label>
                <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
                    <input type="url" class="form-control" id="fase6-memoria-url" maxlength="2048" value="<?= htmlspecialchars($estatMemoria['url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://docs.google.com/…">
                    <button class="btn <?= $estatMemoria['completada'] ? 'btn-success' : 'btn-puig' ?> px-3" type="button" id="fase6-memoria-desar">Desar</button>
                </div>
                <p class="small text-muted mb-0 mt-2 d-none" id="fase6-memoria-missatge"></p>
            </div>
        <?php elseif ($estatMemoria['url'] !== ''): ?>
            <a href="<?= htmlspecialchars($estatMemoria['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase btn-outline-success">
                <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Obrir el document de la memòria
            </a>
        <?php else: ?>
            <p class="text-muted fst-italic mb-0">L’alumnat encara no ha desat cap enllaç.</p>
        <?php endif; ?>

        <?php if ($esAlumnat && $estatMemoria['completada']): ?>
            <div class="bloc-zona bg-success-subtle border-success-subtle py-4">
                <p class="text-uppercase small fw-semibold bloc-zona-titol text-success-emphasis mb-2">Revisió de la memòria</p>
                <p class="mb-2">Ara ja pots utilitzar l’eina de revisió de la memòria per demanar al tutor o tutora la revisió dels diferents apartats.</p>
                <a href="<?= htmlspecialchars($enlaceRevisioMemoria, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase btn-success mt-1">Anar a la revisió de la memòria</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($esAlumnat): ?>
<script>
(() => {
    const boto = document.getElementById('fase6-memoria-desar');
    const input = document.getElementById('fase6-memoria-url');
    const missatge = document.getElementById('fase6-memoria-missatge');

    boto?.addEventListener('click', async () => {
        if (!input.reportValidity()) return;
        boto.disabled = true;
        missatge.classList.add('d-none');
        missatge.textContent = '';

        const dades = new FormData();
        dades.append('proyecto_id', <?= $projecteId ?>);
        dades.append('url', input.value.trim());
        dades.append('csrf_token', <?= json_encode(tokenCsrf()) ?>);

        try {
            const resposta = await fetch('/index.php?main=alumne-fase-6-memoria-accio', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: dades
            });
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut desar l’enllaç.');
            window.location.reload();
        } catch (error) {
            missatge.textContent = error.message;
            missatge.classList.remove('d-none');
            boto.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
