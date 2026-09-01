<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-6_fitxa_publica_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$fitxa = fase6FitxaPublicaObtenirEstat($pdo, $projecteId);
?>
<section class="bloc <?= $fitxa['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
    <div class="bloc-contingut">
        <div class="bloc-tipus"><?= $fitxa['completada'] ? 'Completada' : 'Activitat' ?></div>
        <h2>Fitxa pública del projecte</h2>
        <p class="mb-4">Ara prepareu com es presentarà el vostre projecte a la web.</p>

        <?php if ($esAlumnat): ?>
            <form id="fase6-fitxa-form" enctype="multipart/form-data">
                <input type="hidden" name="proyecto_id" value="<?= $projecteId ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="fase6-fitxa-nom">Nom del projecte</label>
                    <input type="text" class="form-control" id="fase6-fitxa-nom" name="nombre" value="<?= htmlspecialchars($fitxa['nombre'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom del projecte">
                    <div class="form-text">El nom propi del projecte o, si no en té, un nom breu i clar.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="fase6-fitxa-resum">Resum</label>
                    <textarea class="form-control js-fase6-fitxa-autogrow" id="fase6-fitxa-resum" name="resumen" rows="2" maxlength="<?= FASE6_FITXA_RESUM_MAX ?>" placeholder="Una frase clara que expliqui què és el projecte i què fa"><?= htmlspecialchars($fitxa['resumen'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="form-text">Una presentació breu i clara del projecte.</div>
                    <div class="char-counter" id="fase6-fitxa-resum-comptador">0 / <?= FASE6_FITXA_RESUM_MAX ?></div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="fase6-fitxa-descripcio">Descripció</label>
                    <textarea class="form-control js-fase6-fitxa-autogrow" id="fase6-fitxa-descripcio" name="descripcion" rows="8" minlength="<?= FASE6_FITXA_DESCRIPCIO_MIN ?>" placeholder="Expliqueu què fa el projecte, com funciona i què aporta"><?= htmlspecialchars($fitxa['descripcion'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="form-text">Expliqueu el projecte en dos o tres paràgrafs clars i ben estructurats.</div>
                    <div class="char-counter" id="fase6-fitxa-descripcio-comptador">0 caràcters (mínim <?= FASE6_FITXA_DESCRIPCIO_MIN ?>)</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="fase6-fitxa-imatge">Imatge del projecte</label>
                    <?php if ($fitxa['imatge_url'] !== ''): ?>
                        <img src="<?= htmlspecialchars($fitxa['imatge_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($fitxa['nombre'] !== '' ? $fitxa['nombre'] : 'Imatge del projecte', ENT_QUOTES, 'UTF-8') ?>" class="fase6-fitxa-imatge img-fluid rounded mb-3">
                    <?php endif; ?>
                    <input type="file" class="form-control" id="fase6-fitxa-imatge" name="imagen" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">Pugeu una imatge clara del projecte en format JPG, JPEG, PNG o WEBP (màxim 20 MB). Si ja n’hi ha una, podeu substituir-la.</div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="submit" class="btn btn-fase btn-puig-solid" id="fase6-fitxa-desar">Desar</button>
                    <a href="/fases-del-projecte/fase-6" class="btn btn-fase btn-puig">Tornar</a>
                </div>
                <p class="small text-muted mb-0 mt-2 d-none" id="fase6-fitxa-missatge"></p>
            </form>
        <?php else: ?>
            <?php if ($fitxa['imatge_url'] !== ''): ?><img src="<?= htmlspecialchars($fitxa['imatge_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($fitxa['nombre'] ?: 'Imatge del projecte', ENT_QUOTES, 'UTF-8') ?>" class="fase6-fitxa-imatge img-fluid rounded mb-4"><?php endif; ?>
            <dl class="mb-0">
                <dt>Nom del projecte</dt><dd><?= $fitxa['nombre'] !== '' ? htmlspecialchars($fitxa['nombre'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted fst-italic">Pendent</span>' ?></dd>
                <dt>Resum</dt><dd><?= $fitxa['resumen'] !== '' ? nl2br(htmlspecialchars($fitxa['resumen'], ENT_QUOTES, 'UTF-8'), false) : '<span class="text-muted fst-italic">Pendent</span>' ?></dd>
                <dt>Descripció</dt><dd class="mb-0"><?= $fitxa['descripcion'] !== '' ? nl2br(htmlspecialchars($fitxa['descripcion'], ENT_QUOTES, 'UTF-8'), false) : '<span class="text-muted fst-italic">Pendent</span>' ?></dd>
            </dl>
        <?php endif; ?>
    </div>
</section>
<?php if ($esAlumnat): ?>
<script>
(() => {
    const formulari = document.getElementById('fase6-fitxa-form');
    const boto = document.getElementById('fase6-fitxa-desar');
    const missatge = document.getElementById('fase6-fitxa-missatge');
    const resum = document.getElementById('fase6-fitxa-resum');
    const resumComptador = document.getElementById('fase6-fitxa-resum-comptador');
    const descripcio = document.getElementById('fase6-fitxa-descripcio');
    const descripcioComptador = document.getElementById('fase6-fitxa-descripcio-comptador');
    const minim = <?= FASE6_FITXA_DESCRIPCIO_MIN ?>;
    const actualitzar = () => {
        resumComptador.textContent = resum.value.length + ' / ' + resum.maxLength;
        resumComptador.classList.toggle('limit', resum.value.length > resum.maxLength * .9);
        descripcioComptador.textContent = descripcio.value.length + ' caràcters (mínim ' + minim + ')';
        descripcioComptador.classList.toggle('limit', descripcio.value.length > 0 && descripcio.value.length < minim);
        descripcioComptador.classList.toggle('ok', descripcio.value.length >= minim);
        document.querySelectorAll('.js-fase6-fitxa-autogrow').forEach((camp) => { camp.style.height = 'auto'; camp.style.height = camp.scrollHeight + 'px'; });
    };
    resum.addEventListener('input', actualitzar);
    descripcio.addEventListener('input', actualitzar);
    actualitzar();
    formulari.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!formulari.reportValidity()) return;
        boto.disabled = true;
        missatge.classList.add('d-none');
        try {
            const resposta = await fetch('/index.php?main=alumne-fase-6-fitxa-publica-accio', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: new FormData(formulari)});
            const cos = await resposta.text();
            let resultat;
            try {
                resultat = JSON.parse(cos);
            } catch (_) {
                throw new Error('El servidor no ha pogut completar el guardat.');
            }
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’han pogut desar les dades.');
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
