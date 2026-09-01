<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-6_memoria_funcions.php';
require __DIR__ . '/fase-6_recursos.php';

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estatMemoria = fase6MemoriaObtenirEstat($pdo, $projecteId);
?>
<section class="bloc <?= $estatMemoria['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
    <div class="bloc-contingut">
        <div class="bloc-tipus"><?= $estatMemoria['completada'] ? 'Completada' : 'Activitat' ?></div>
        <h2>Document de la memòria</h2>
        <p class="mb-3">Poseu en marxa el document viu de la memòria i manteniu-lo actualitzat durant el desenvolupament del projecte.</p>

        <h3 class="h6 mb-2">Plantilla de la memòria</h3>
        <div class="tasca-recursos mb-4">
            <a href="<?= htmlspecialchars($fase6PlantillaMemoriaCaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Plantilla de la memòria (català)</a>
            <span class="tasca-recursos-separador" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars($fase6PlantillaMemoriaEsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Plantilla de la memòria (castellà)</a>
        </div>

        <ol class="mb-4 ps-3">
            <li class="mb-2">Escolliu una de les dues plantilles i creeu-ne la vostra pròpia còpia.</li>
            <li class="mb-2">Utilitzeu aquesta còpia com a document viu de la memòria i aneu-la completant durant el projecte.</li>
            <li class="mb-2"><strong>Compartiu el document de manera que el professorat hi pugui accedir.</strong> No n’hi ha prou que només funcioni des del vostre compte.</li>
            <li>Enganxeu aquí l’enllaç de la vostra còpia, no l’enllaç de la plantilla.</li>
        </ol>

        <?php if ($esAlumnat): ?>
            <div>
                <label class="form-label small fw-semibold" for="fase6-memoria-url">Enllaç al document de la memòria</label>
                <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
                    <input type="url" class="form-control" id="fase6-memoria-url" maxlength="2048" value="<?= htmlspecialchars($estatMemoria['url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://docs.google.com/…">
                    <button class="btn btn-puig px-3" type="button" id="fase6-memoria-desar">Desar</button>
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
