<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-5_produccio_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estatProduccio = fase5ProduccioObtenirEstat($pdo, $idProjecte);
$teUrlPublica = fase5ProduccioUrlValida($estatProduccio['url']);
?>
<section class="lliurament-final<?= $estatProduccio['completada'] ? ' lliurament-final--completat' : '' ?>">
    <header class="lliurament-final-cap">Publicació final</header>
    <div class="lliurament-final-cos">
        <div class="lliurament-final-intro">
            <h2>Publiqueu el resultat del projecte</h2>
            <p>El projecte ha de quedar accessible des de fora del vostre entorn de desenvolupament. L’objectiu és que qualsevol persona pugui consultar el resultat final del treball que heu fet.</p>
        </div>

        <div class="lliurament-final-opcions">
            <div class="lliurament-final-opcio">
                <h3 class="lliurament-final-opcio-titol">Web o aplicació web</h3>
                <p>Si el vostre projecte és accessible directament des del navegador, indiqueu l’adreça pública del desplegament.</p>
            </div>
            <div class="lliurament-final-opcio">
                <h3 class="lliurament-final-opcio-titol">Altres tipus de projecte</h3>
                <p>Si el producte no és una web, prepareu una landing page pública que presenti el projecte i permeti entendre què heu construït.</p>
            </div>
        </div>

        <div class="lliurament-final-exemples">
            <div class="lliurament-final-subtitol">Exemples</div>
            <p>Aquí podrem incorporar més endavant alguns projectes de cursos anteriors com a referència.</p>
        </div>

        <div class="lliurament-final-important">
            <strong>Important:</strong> Comproveu que l’enllaç funciona des d’un navegador on no tingueu iniciada la sessió i que no apunta a localhost ni a un entorn privat.
        </div>

        <?php if ($esAlumnat): ?>
            <div class="lliurament-final-entrega lliurament-final-edicio">
                <div class="lliurament-final-subtitol">URL pública del projecte</div>
                <div class="lliurament-final-formulari">
                    <input type="url" class="form-control" id="fase5-produccio-url" maxlength="2048" value="<?= htmlspecialchars($estatProduccio['url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://..." aria-label="URL pública del projecte">
                    <button class="btn lliurament-final-btn" type="button" id="fase5-produccio-desar">Desar</button>
                </div>
                <p class="small text-muted mb-0 mt-1 d-none" id="fase5-produccio-missatge"></p>
            </div>
        <?php elseif (!$teUrlPublica): ?>
            <div class="lliurament-final-entrega">
                <p class="text-muted fst-italic mb-0">L’alumnat encara no ha desat cap URL de producció.</p>
            </div>
        <?php endif; ?>

        <?php if ($teUrlPublica): ?>
            <div class="lliurament-final-entrega">
                <div class="lliurament-final-subtitol">Resultat publicat</div>
                <div class="mega-buttons">
                    <?php $urlProduccioCta = $estatProduccio['url']; ?>
                    <?php include __DIR__ . '/fase-5_produccio_cta.php'; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if ($esAlumnat): ?>
<script>
(() => {
    const boto = document.getElementById('fase5-produccio-desar');
    const input = document.getElementById('fase5-produccio-url');
    const missatge = document.getElementById('fase5-produccio-missatge');
    boto?.addEventListener('click', async () => {
        if (!input.reportValidity()) return;
        boto.disabled = true;
        const dades = new FormData();
        dades.append('url', input.value.trim());
        dades.append('proyecto_id', <?= $idProjecte ?>);
        dades.append('csrf_token', <?= json_encode(tokenCsrf()) ?>);
        try {
            const resposta = await fetch('/index.php?main=alumne-fase-5-produccio-accio', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
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
