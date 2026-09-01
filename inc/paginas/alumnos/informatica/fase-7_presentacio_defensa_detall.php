<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-7_funcions.php';
require_once dirname(__DIR__, 3) . '/pdf/funciones.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estatPresentacio = fase7PresentacioDefensaObtenirEstat($pdo, $projecteId);
?>
<section class="bloc <?= $estatPresentacio['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
    <div class="bloc-contingut">
        <div class="bloc-tipus"><?= $estatPresentacio['completada'] ? 'Completada' : 'Activitat' ?></div>
        <h2>Presentació de la defensa</h2>
        <p class="mb-4">Pugeu en format PDF la presentació que utilitzareu durant la defensa del projecte.</p>

        <div class="lliurament-final-entrega lliurament-final-edicio">
            <div class="lliurament-final-subtitol">Presentació en PDF</div>
            <p class="mb-3">Pugeu en format PDF la presentació que utilitzareu durant la defensa del projecte.</p>
            <?php if ($esAlumnat): ?>
                <div class="lliurament-final-formulari">
                    <input type="file" class="form-control" id="fase7-presentacio-pdf" accept="application/pdf,.pdf" aria-label="PDF de la presentació de la defensa">
                    <button class="btn lliurament-final-btn text-nowrap" type="button" id="fase7-presentacio-entregar">Entregar</button>
                </div>
                <p class="form-text mb-0 mt-2">Només PDF, màxim 20 MB.</p>
                <p class="small text-muted mb-0 mt-2 d-none" id="fase7-presentacio-missatge"></p>
            <?php elseif (!$estatPresentacio['completada']): ?>
                <p class="text-muted fst-italic mb-0">L’alumnat encara no ha entregat la presentació de la defensa.</p>
            <?php endif; ?>
        </div>

        <?php if ($estatPresentacio['completada']): ?>
            <div class="lliurament-final-entrega">
                <div class="lliurament-final-subtitol">Presentació entregada</div>
                <?php $presentacioPdfCta = $estatPresentacio['pdf_url']; ?>
                <?php include __DIR__ . '/fase-7_presentacio_cta.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if ($esAlumnat): ?>
<script>
(() => {
    const input = document.getElementById('fase7-presentacio-pdf');
    const boto = document.getElementById('fase7-presentacio-entregar');
    const missatge = document.getElementById('fase7-presentacio-missatge');
    const maxim = <?= PDF_MIDA_MAXIMA_BYTES ?>;
    boto?.addEventListener('click', async () => {
        const fitxer = input.files[0];
        missatge.classList.add('d-none');
        if (!fitxer) { missatge.textContent = 'Seleccioneu un fitxer PDF.'; missatge.classList.remove('d-none'); return; }
        if (!fitxer.name.toLowerCase().endsWith('.pdf') || (fitxer.type && fitxer.type !== 'application/pdf')) { missatge.textContent = 'Només s’admeten fitxers PDF.'; missatge.classList.remove('d-none'); return; }
        if (fitxer.size <= 0 || fitxer.size > maxim) { missatge.textContent = 'El PDF ha de pesar com a màxim 20 MB.'; missatge.classList.remove('d-none'); return; }
        boto.disabled = true;
        const dades = new FormData();
        dades.append('proyecto_id', <?= $projecteId ?>);
        dades.append('csrf_token', <?= json_encode(tokenCsrf()) ?>);
        dades.append('pdf', fitxer);
        try {
            const resposta = await fetch('/index.php?main=alumne-fase-7-presentacio-defensa-accio', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: dades});
            const cos = await resposta.text();
            let resultat;
            try { resultat = JSON.parse(cos); } catch (_) { throw new Error('El servidor no ha pogut completar l’entrega.'); }
            if (!resultat.ok) throw new Error(resultat.missatge || 'No s’ha pogut entregar la presentació.');
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
