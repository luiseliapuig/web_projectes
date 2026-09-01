<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-5_autoavaluacio_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$preguntes = fase5AutoavaluacioPreguntes();
$estatAutoavaluacio = fase5AutoavaluacioObtenirEstat($pdo, $idProjecte);
$hrefTornar = $esAlumnat
    ? '/fases-del-projecte/fase-5'
    : '/projecte/' . $idProjecte . '/fases/fase-5';
?>
<section class="bloc <?= $estatAutoavaluacio['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
    <div class="bloc-contingut">
        <div class="bloc-tipus"><?= $estatAutoavaluacio['completada'] ? 'Completada' : ($estatAutoavaluacio['iniciada'] ? 'En curs' : 'Activitat') ?></div>
        <h2>Reflexió final del projecte</h2>
        <p class="mb-4">Reflexioneu amb calma sobre què heu après, el resultat aconseguit, allò que ha quedat pendent i les millores que faríeu.</p>

        <?php if ($esAlumnat): ?>
            <form id="fase5-autoavaluacio-form" class="d-grid gap-4">
                <?php foreach ($preguntes as $camp => $pregunta): ?>
                    <div>
                        <label for="fase5-<?= $camp ?>" class="form-label fw-semibold"><?= htmlspecialchars($pregunta, ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea id="fase5-<?= $camp ?>" name="<?= $camp ?>" class="form-control" rows="7"><?= htmlspecialchars($estatAutoavaluacio['respostes'][$camp], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                <?php endforeach; ?>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-fase btn-puig-solid" id="fase5-autoavaluacio-desar">Desar</button>
                    <a href="<?= htmlspecialchars($hrefTornar, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase btn-outline-secondary">Tornar</a>
                </div>
                <p class="small text-muted mb-0" id="fase5-autoavaluacio-missatge">&nbsp;</p>
            </form>
        <?php else: ?>
            <div class="d-grid gap-4">
                <?php foreach ($preguntes as $camp => $pregunta): ?>
                    <div>
                        <h3 class="h6 mb-2"><?= htmlspecialchars($pregunta, ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if ($estatAutoavaluacio['respostes'][$camp] !== ''): ?>
                            <p class="mb-0 text-break"><?= nl2br(htmlspecialchars($estatAutoavaluacio['respostes'][$camp], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php else: ?>
                            <p class="text-muted fst-italic mb-0">Encara no hi ha resposta.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if ($esAlumnat): ?>
<script>
(() => {
    const formulari = document.getElementById('fase5-autoavaluacio-form');
    const boto = document.getElementById('fase5-autoavaluacio-desar');
    const missatge = document.getElementById('fase5-autoavaluacio-missatge');
    formulari?.addEventListener('submit', async event => {
        event.preventDefault(); boto.disabled = true;
        const dades = new FormData(formulari);
        dades.append('proyecto_id', <?= $idProjecte ?>);
        dades.append('csrf_token', <?= json_encode(tokenCsrf()) ?>);
        try {
            const resposta = await fetch('/index.php?main=alumne-fase-5-autoavaluacio-accio', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
            const resultat = await resposta.json();
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut desar l’autoavaluació.');
            window.location.reload();
        } catch (error) {
            missatge.textContent = error.message; boto.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
