<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-4_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estat = fase4PlanificacioGestioObtenirEstat($pdo, $idProjecte);
$esPlanificacio = $fase4Tasca['clau'] === 'planificacio';
$url = $esPlanificacio ? $estat['planificacio_url'] : $estat['gestio_url'];
$completada = $url !== '';
?>
<div class="d-grid gap-4">
    <section class="bloc <?= $completada ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $completada ? 'Completada' : 'Activitat' ?></div>
            <h2><?= htmlspecialchars($fase4Tasca['titol'], ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if ($esPlanificacio): ?>
                <p class="mb-3">Abans de començar el desenvolupament, organitzeu temporalment el projecte. Creeu una planificació on es vegin les principals etapes i tasques i la seva distribució en el temps, utilitzant l’eina indicada per al projecte o el curs.</p>
            <?php else: ?>
                <p class="mb-3">El tauler de gestió servirà per organitzar i seguir el treball durant el desenvolupament. Creeu-lo amb Trello o Taiga i organitzeu inicialment el treball de manera suficient per començar a utilitzar-lo.</p>
            <?php endif; ?>
            <p class="mb-3">Configureu el resultat perquè es pugui consultar mitjançant un enllaç públic i deseu-lo aquí.</p>
            <?php if ($esAlumnat): ?>
                <div>
                    <label class="form-label small fw-semibold" for="fase4-url"><?= htmlspecialchars($fase4Tasca['camp_etiqueta'], ENT_QUOTES, 'UTF-8') ?></label>
                    <p class="small text-muted mb-2">Comprova que l’enllaç es pot consultar sense iniciar sessió.</p>
                    <div class="input-group input-group-sm mb-1" style="max-width: 560px;">
                        <input type="url" class="form-control" id="fase4-url" maxlength="2048" value="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://..." required>
                        <button class="btn btn-puig px-3" type="button" id="fase4-guardar-url">Desar enllaç</button>
                    </div>
                    <p class="small text-muted mb-0" id="fase4-url-missatge">&nbsp;</p>
                </div>
            <?php elseif ($completada): ?>
                <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat"><i class="bi bi-link-45deg" aria-hidden="true"></i> <?= htmlspecialchars($fase4Tasca['evidencia'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php else: ?>
                <p class="text-muted fst-italic mb-0">L’alumnat encara no ha desat cap enllaç.</p>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php if ($esAlumnat): ?>
<script>
(() => {
    const boto = document.getElementById('fase4-guardar-url');
    const input = document.getElementById('fase4-url');
    const missatge = document.getElementById('fase4-url-missatge');
    boto?.addEventListener('click', async () => {
        if (!input.reportValidity()) return;
        boto.disabled = true;
        const dades = new FormData();
        dades.append('tasca', <?= json_encode($fase4Tasca['clau']) ?>);
        dades.append('url', input.value.trim());
        dades.append('proyecto_id', <?= $idProjecte ?>);
        dades.append('csrf_token', <?= json_encode(tokenCsrf()) ?>);
        try {
            const resposta = await fetch('/index.php?main=alumne-fase-4-accion', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut desar l’enllaç.');
            window.location.reload();
        } catch (error) {
            missatge.textContent = error.message;
            boto.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
