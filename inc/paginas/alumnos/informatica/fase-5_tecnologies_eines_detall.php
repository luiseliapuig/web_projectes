<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-5_stack_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumne = $rolVisualitzacio !== 'professor';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$estatStack = fase5StackObtenirEstat($pdo, $idProjecte);
$blocs = [
    'tecnologia' => ['titol' => 'Tecnologies', 'items' => $estatStack['tecnologies'], 'text' => 'Selecciona els llenguatges, frameworks, biblioteques i altres tecnologies que formen part del projecte.'],
    'eina' => ['titol' => 'Eines', 'items' => $estatStack['eines'], 'text' => 'Indica les eines de desenvolupament, disseny, proves, desplegament o organització que utilitzareu.'],
];
?>
<div class="d-grid gap-4" id="fase5-stack" data-projecte-id="<?= $idProjecte ?>" data-csrf="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($blocs as $tipus => $bloc): ?>
        <section class="bloc bloc-informacio fase5-stack-bloc">
            <div class="bloc-contingut">
                <div class="bloc-tipus">Selecció</div>
                <h2><?= htmlspecialchars($bloc['titol'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mb-3"><?= htmlspecialchars($bloc['text'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!$esAlumne && $tipus === 'eina'): ?>
                    <?php if ($bloc['items'] !== []): ?><p class="stack-eines-resum mb-0"><span class="fw-semibold">Eines:</span> <?= htmlspecialchars(implode(', ', array_column($bloc['items'], 'nombre')), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 mb-3 fase5-stack-seleccio" aria-live="polite">
                        <?php foreach ($bloc['items'] as $item): ?>
                            <span class="d-inline-flex align-items-center gap-2 fase5-stack-pill<?= !$esAlumne ? ' stack-tecnologia-pill' : '' ?>" data-id="<?= (int) $item['id'] ?>" data-tipus="<?= $tipus ?>">
                                <?= htmlspecialchars((string) $item['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($item['propuesto_en'] !== null): ?><span class="fase5-stack-pendent">· pendent de revisió</span><?php endif; ?>
                                <?php if ($esAlumne): ?><button type="button" class="fase5-stack-treure" aria-label="Treure <?= htmlspecialchars((string) $item['nombre'], ENT_QUOTES, 'UTF-8') ?>">&times;</button><?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($esAlumne): ?>
                    <div class="position-relative col-12 col-lg-8 fase5-stack-cercador" data-tipus="<?= $tipus ?>">
                        <label class="form-label" for="fase5-stack-<?= $tipus ?>">Cerca i afegeix</label>
                        <input type="search" class="form-control" id="fase5-stack-<?= $tipus ?>" maxlength="150" autocomplete="off" placeholder="Escriu per cercar…">
                        <div class="list-group position-absolute start-0 end-0 shadow-sm fase5-stack-resultats d-none"></div>
                        <div class="small text-danger mt-2 fase5-stack-error d-none" role="alert"></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php if ($esAlumne): ?>
<script>
(() => {
    const arrel = document.getElementById('fase5-stack');
    if (!arrel) return;
    const endpoint = '/index.php?main=alumne-fase-5-stack-accion';
    const enviar = async (dades) => {
        const cos = new URLSearchParams({proyecto_id: arrel.dataset.projecteId, csrf_token: arrel.dataset.csrf, ...dades});
        const resposta = await fetch(endpoint, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body: cos});
        const json = await resposta.json();
        if (!resposta.ok || !json.ok) throw new Error(json.missatge || 'No s’ha pogut completar l’acció.');
        return json;
    };
    const registrarTreure = boto => boto.addEventListener('click', async () => {
        const pill = boto.closest('.fase5-stack-pill');
        try { await enviar({accio: 'treure', tipus: pill.dataset.tipus, id: pill.dataset.id}); pill.remove(); } catch (e) { alert(e.message); }
    });
    const afegirPill = (cercador, id, nom, pendent) => {
        const seleccio = cercador.closest('.bloc-contingut').querySelector('.fase5-stack-seleccio');
        const pill = document.createElement('span');
        pill.className = 'd-inline-flex align-items-center gap-2 fase5-stack-pill';
        pill.dataset.id = id; pill.dataset.tipus = cercador.dataset.tipus;
        pill.append(document.createTextNode(nom));
        if (pendent) { const estat = document.createElement('span'); estat.className = 'fase5-stack-pendent'; estat.textContent = ' · pendent de revisió'; pill.append(estat); }
        const treure = document.createElement('button'); treure.type = 'button'; treure.className = 'fase5-stack-treure'; treure.setAttribute('aria-label', `Treure ${nom}`); treure.innerHTML = '&times;';
        registrarTreure(treure); pill.append(treure); seleccio.append(pill);
        cercador.querySelector('input').value = ''; cercador.querySelector('.fase5-stack-resultats').classList.add('d-none');
    };
    arrel.querySelectorAll('.fase5-stack-cercador').forEach(cercador => {
        const input = cercador.querySelector('input');
        const llista = cercador.querySelector('.fase5-stack-resultats');
        const error = cercador.querySelector('.fase5-stack-error');
        let temporitzador;
        const mostrarError = missatge => { error.textContent = missatge; error.classList.remove('d-none'); };
        input.addEventListener('input', () => {
            clearTimeout(temporitzador); error.classList.add('d-none');
            const cerca = input.value.trim();
            if (!cerca) { llista.replaceChildren(); llista.classList.add('d-none'); return; }
            temporitzador = setTimeout(async () => {
                try {
                    const json = await enviar({accio: 'cercar', tipus: cercador.dataset.tipus, cerca});
                    llista.replaceChildren();
                    if (json.resultats.length) {
                        json.resultats.forEach(item => {
                            const boto = document.createElement('button');
                            boto.type = 'button'; boto.className = 'list-group-item list-group-item-action fase5-stack-resultat';
                            const nom = document.createElement('span'); nom.className = 'd-block fw-semibold fase5-stack-resultat-nom'; nom.textContent = item.nombre; boto.append(nom);
                            if (item.descripcion) { const descripcio = document.createElement('small'); descripcio.className = 'fase5-stack-resultat-descripcio'; descripcio.textContent = item.descripcion; boto.append(descripcio); }
                            boto.addEventListener('click', async () => { try { await enviar({accio: 'afegir', tipus: cercador.dataset.tipus, id: item.id}); afegirPill(cercador, item.id, item.nombre, false); } catch (e) { mostrarError(e.message); } });
                            llista.append(boto);
                        });
                    } else {
                        const buit = document.createElement('div'); buit.className = 'list-group-item fase5-stack-sense-resultats'; buit.textContent = `No s’ha trobat “${cerca}”`; llista.append(buit);
                        const proposar = document.createElement('button'); proposar.type = 'button'; proposar.className = 'list-group-item list-group-item-action fw-semibold fase5-stack-proposar'; proposar.textContent = `Proposar “${cerca}”`;
                        proposar.addEventListener('click', async () => { try { const proposta = await enviar({accio: 'proposar', tipus: cercador.dataset.tipus, nom: cerca}); afegirPill(cercador, proposta.id, proposta.nom, proposta.pendent); } catch (e) { mostrarError(e.message); } });
                        llista.append(proposar);
                    }
                    llista.classList.remove('d-none');
                } catch (e) { llista.classList.add('d-none'); mostrarError(e.message); }
            }, 250);
        });
    });
    arrel.querySelectorAll('.fase5-stack-treure').forEach(registrarTreure);
})();
</script>
<?php endif; ?>
