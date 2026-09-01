<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-6_memoria_funcions.php';
require_once dirname(__DIR__, 3) . '/pdf/funciones.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estatEntrega = fase6MemoriaDefinitivaObtenirEstat($pdo, $projecteId);
?>
<section class="lliurament-final<?= $estatEntrega['completada'] ? ' lliurament-final--completat' : '' ?>">
    <header class="lliurament-final-cap">Entrega final</header>
    <div class="lliurament-final-cos">
        <div class="lliurament-final-intro">
            <h2>Memòria del projecte</h2>
            <p>Prepareu la versió definitiva de la memòria del projecte incorporant les correccions fetes durant el curs i genereu-ne un únic document en format PDF.</p>
        </div>

        <div class="lliurament-final-opcions lliurament-final-opcions--una">
            <div class="lliurament-final-opcio">
                <div class="lliurament-final-subtitol">Document definitiu</div>
                <p>Reviseu la memòria, incorporeu-hi les correccions pendents i genereu un únic PDF amb la versió que quedarà com a document final del projecte.</p>
            </div>
        </div>

        <div class="lliurament-final-important">
            <p class="mb-2"><strong>Recordeu:</strong> abans de pujar la memòria, comprimiu el fitxer PDF per reduir-ne el pes. Podeu utilitzar una eina com <a href="https://www.ilovepdf.com/compress_pdf" target="_blank" rel="noopener noreferrer" class="lliurament-final-enllac">iLovePDF</a> o qualsevol altra eina equivalent de compressió de PDF.</p>
            <p class="mb-0">Comproveu sempre el document comprimit abans d’entregar-lo per assegurar-vos que el text i les imatges es visualitzen correctament.</p>
        </div>

        <div class="lliurament-final-entrega lliurament-final-edicio">
            <div class="lliurament-final-subtitol">Memòria en PDF</div>
            <p class="mb-3">Pugeu aquí el PDF definitiu de la memòria del projecte.</p>
            <?php if ($esAlumnat): ?>
                <div class="lliurament-final-formulari">
                    <input type="file" class="form-control" id="fase6-entrega-memoria-pdf" accept="application/pdf,.pdf" aria-label="PDF definitiu de la memòria">
                    <button class="btn lliurament-final-btn text-nowrap" type="button" id="fase6-entrega-memoria-desar">Entregar</button>
                </div>
                <p class="form-text mb-0 mt-2">Només PDF, màxim 20 MB.</p>
                <p class="small text-muted mb-0 mt-2 d-none" id="fase6-entrega-memoria-missatge"></p>
            <?php elseif (!$estatEntrega['completada']): ?>
                <p class="text-muted fst-italic mb-0">L’alumnat encara no ha entregat la memòria definitiva.</p>
            <?php endif; ?>
        </div>

        <?php if ($estatEntrega['completada']): ?>
            <div class="lliurament-final-entrega">
                <div class="lliurament-final-subtitol">Memòria entregada</div>
                <?php $memoriaPdfCta = $estatEntrega['pdf']; ?>
                <?php include __DIR__ . '/fase-6_memoria_cta.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if ($esAlumnat): ?>
<script>
(() => {
    const input = document.getElementById('fase6-entrega-memoria-pdf');
    const boto = document.getElementById('fase6-entrega-memoria-desar');
    const missatge = document.getElementById('fase6-entrega-memoria-missatge');
    const maxim = <?= PDF_MIDA_MAXIMA_BYTES ?>;
    boto?.addEventListener('click', async () => {
        const fitxer = input.files[0];
        missatge.classList.add('d-none');
        if (!fitxer) {
            missatge.textContent = 'Seleccioneu un fitxer PDF.';
            missatge.classList.remove('d-none');
            return;
        }
        if (!fitxer.name.toLowerCase().endsWith('.pdf') || (fitxer.type && fitxer.type !== 'application/pdf')) {
            missatge.textContent = 'Només s’admeten fitxers PDF.';
            missatge.classList.remove('d-none');
            return;
        }
        if (fitxer.size <= 0 || fitxer.size > maxim) {
            missatge.textContent = 'El PDF ha de pesar com a màxim 20 MB.';
            missatge.classList.remove('d-none');
            return;
        }
        boto.disabled = true;
        const dades = new FormData();
        dades.append('proyecto_id', <?= $projecteId ?>);
        dades.append('csrf_token', <?= json_encode(tokenCsrf()) ?>);
        dades.append('pdf', fitxer);
        try {
            const resposta = await fetch('/index.php?main=alumne-fase-6-entrega-memoria-accio', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
            const cos = await resposta.text();
            let resultat;
            try { resultat = JSON.parse(cos); } catch (_) { throw new Error('El servidor no ha pogut completar l’entrega.'); }
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut entregar la memòria.');
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
