<?php
declare(strict_types=1);

// Recursos de la tasca "Document funcional". Es mantenen junts i fora del
// markup perquè afegir la plantilla catalana no exigeixi canviar l'estructura
// de la vista. En aquesta primera passada només es mostra la presentació:
// les plantilles pertanyeran al detall de la tasca en la passada següent.
require __DIR__ . '/fase-3_recursos.php';
require_once __DIR__ . '/fase-2_proposta_funcions.php';
require_once __DIR__ . '/fase-3_document_funcional_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$idProjecteTasques = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$tascaBloquejada = $rolVisualitzacio === 'alumne' && !fase2PropostaObtenirEstat($pdo, $idProjecteTasques)['completada'];
$estatFuncional = $tascaBloquejada ? null : fase3DocumentFuncionalObtenirEstat($pdo, $idProjecteTasques);
$enllacEntrar = $rolVisualitzacio === 'professor' ? '/projecte/' . $idProjecteTasques . '/fases/fase-3/document-funcional' : '/fases-del-projecte/fase-3/document-funcional';
$documentResum = $estatFuncional ? ($estatFuncional['pdf'] !== '' ? $estatFuncional['pdf'] : ($estatFuncional['url'] !== '' ? $estatFuncional['url'] : null)) : null;
?>

<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

    <!-- Mateix component informatiu que "Projectes d’altres cursos" de
         Fase 1: context didàctic neutre, no una tasca ni un recurs operatiu. -->
    <section class="bloc bloc-informacio">
        <div class="bloc-contingut">
            <div class="bloc-tipus">Presentació</div>
            <h2>Document funcional</h2>
            <p class="mb-3">Aquesta presentació mostra com plantejar i estructurar un document funcional abans de començar a desenvolupar el projecte.</p>
            <?php if ($fase3PresentacioUrl !== ''): ?>
                <a href="<?= htmlspecialchars($fase3PresentacioUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase btn-fase-informacio">Obrir la presentació</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Primera passada: targeta-resum real de la tasca, sense duplicar-hi
         instruccions, plantilles ni els dos passos futurs. El CTA queda
         preparat visualment però sense ruta fictícia fins que existeixi el
         detall funcional de la tasca. -->
    <section class="bloc <?= $tascaBloquejada ? 'bloc-bloquejat' : $estatFuncional['classe_bloc'] ?>">
        <div class="bloc-contingut">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div><div class="bloc-tipus">Tasca</div><h2 class="mb-0">Document funcional</h2></div>
                <span class="badge rounded-pill px-3 py-2 <?= $tascaBloquejada ? 'text-bg-secondary' : $estatFuncional['classe_badge'] ?>"><?= $tascaBloquejada ? 'Bloquejada' : htmlspecialchars($estatFuncional['text'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <p class="mb-3">Prepareu el document que definirà els requisits i les funcionalitats del projecte abans de començar-ne el desenvolupament.</p>
            <?php if ($tascaBloquejada): ?>
                <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar la Fase 2.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <a href="<?= htmlspecialchars($enllacEntrar, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatFuncional['classe_cta'] ?>">Entrar</a>
                    <?php if ($documentResum !== null): ?>
                        <a href="<?= htmlspecialchars($documentResum, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link <?= $estatFuncional['completada'] ? 'tasca-recurs-resultat--completat' : ($estatFuncional['atencion'] ? 'tasca-recurs-resultat--atencio' : 'tasca-recurs-resultat--activitat') ?>"><i class="bi <?= $estatFuncional['pdf'] !== '' ? 'bi-file-earmark-pdf' : 'bi-link-45deg' ?>" aria-hidden="true"></i> Document funcional</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
