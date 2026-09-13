<?php
declare(strict_types=1);

require_once __DIR__ . '/enlaces-recursos.php';
require_once __DIR__ . '/fase-2_proposta_funcions.php';
require_once __DIR__ . '/fase-3_document_funcional_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$idProjecteTasques = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$tascaBloquejada = $rolVisualitzacio === 'alumne' && !fase2PropostaObtenirEstat($pdo, $idProjecteTasques)['completada'];
$estatFuncional = $tascaBloquejada ? null : fase3DocumentFuncionalObtenirEstat($pdo, $idProjecteTasques);
$classificacioFase3 = fase2ClassificacioObtenirEstat($pdo, $idProjecteTasques);
$esProjecteRecerca = in_array($classificacioFase3['categoria_id'], [1, 5], true);
$nomDocumentFase3 = $esProjecteRecerca ? 'Pla de recerca' : 'Document funcional';
$descripcioDocumentFase3 = $esProjecteRecerca
    ? 'Prepareu el document que definirà els objectius, l’abast i la metodologia de la recerca abans de començar-ne el desenvolupament.'
    : 'Prepareu el document que definirà els requisits, l’abast i les funcionalitats del projecte abans de començar-ne el desenvolupament.';
$enllacEntrar = $rolVisualitzacio === 'professor' ? '/projecte/' . $idProjecteTasques . '/fases/fase-3/document-funcional' : '/fases-del-projecte/fase-3/document-funcional';
$documentResum = $estatFuncional ? ($estatFuncional['pdf'] !== '' ? $estatFuncional['pdf'] : ($estatFuncional['url'] !== '' ? $estatFuncional['url'] : null)) : null;
?>

<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

    <!-- Mateix component informatiu que "Projectes d’altres cursos" de
         Fase 1: context didàctic neutre, no una tasca ni un recurs operatiu. -->
    <style>
    .fase-3-presentacio--activa::before { background: #2563A6; }
    .fase-3-presentacio--activa .bloc-tipus { color: #2563A6; }
    .fase-3-presentacio--activa .btn-fase-informacio {
        color: #fff;
        background: #2563A6;
        border-color: #2563A6;
    }
    .fase-3-presentacio--activa .btn-fase-informacio:hover,
    .fase-3-presentacio--activa .btn-fase-informacio:focus-visible {
        color: #fff;
        background: #1f528a;
        border-color: #1f528a;
    }
    </style>
    <section class="bloc bloc-informacio<?= $tascaBloquejada ? '' : ' fase-3-presentacio--activa' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus">Presentació</div>
            <h2>Definició del projecte</h2>
            <p class="mb-3">Aquesta presentació explica com definir i estructurar el projecte abans de començar-ne el desenvolupament o la recerca.</p>
            <?php if ($presentacion_funcional !== ''): ?>
                <a href="<?= htmlspecialchars($presentacion_funcional, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase btn-fase-informacio">Obrir la presentació</a>
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
                <div><div class="bloc-tipus">Tasca</div><h2 class="mb-0"><?= htmlspecialchars($nomDocumentFase3, ENT_QUOTES, 'UTF-8') ?></h2></div>
                <span class="badge rounded-pill px-3 py-2 <?= $tascaBloquejada ? 'text-bg-secondary' : $estatFuncional['classe_badge'] ?>"><?= $tascaBloquejada ? 'Bloquejada' : htmlspecialchars($estatFuncional['text'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <p class="mb-3"><?= htmlspecialchars($descripcioDocumentFase3, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($tascaBloquejada): ?>
                <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar la Fase 2.</p>
            <?php else: ?>
                <?php if ($documentResum !== null): ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <a href="<?= htmlspecialchars($documentResum, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link <?= $estatFuncional['completada'] ? 'tasca-recurs-resultat--completat' : ($estatFuncional['atencion'] ? 'tasca-recurs-resultat--atencio' : 'tasca-recurs-resultat--activitat') ?>"><i class="bi <?= $estatFuncional['pdf'] !== '' ? 'bi-file-earmark-pdf' : 'bi-link-45deg' ?>" aria-hidden="true"></i> <?= htmlspecialchars($nomDocumentFase3, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($enllacEntrar, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatFuncional['classe_cta'] ?>">Entrar</a>
            <?php endif; ?>
        </div>
    </section>
</div>
