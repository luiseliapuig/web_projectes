<?php
declare(strict_types=1);
?>
<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

    <section class="bloc bloc-activitat">
        <div class="bloc-contingut">
            <div class="bloc-tipus">Activitat</div>
            <h2>Proposta de projecte</h2>
            <div class="mb-3 text-secondary">
                <p class="mb-1"><strong class="text-dark">Instruccions:</strong> Feu una còpia de la plantilla i completeu la proposta del vostre projecte.</p>
                <p class="mb-1"><strong class="text-dark">Entrega:</strong> PDF exportat des del document.</p>
                <p><strong class="text-dark">Modalitat:</strong> individual o en parella.</p>
            </div>
            <button type="button" class="btn btn-puig-solid btn-sm px-3 mb-3" disabled>Plantilla de proposta</button>
            <p>Aquesta proposta defineix la idea inicial del projecte: què voleu construir, amb quines tecnologies i amb quins objectius.</p>
        </div>
    </section>
</div>
