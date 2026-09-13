<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-7_funcions.php';
require_once __DIR__ . '/enlaces-recursos.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$faseBloquejada = $rolVisualitzacio === 'alumne' && !empty($aparencaFaseActiva['bloquejada']);
$estatPresentacio = fase7PresentacioDefensaObtenirEstat($pdo, $projecteId);
if (!$faseBloquejada) {
    $hrefPresentacio = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $projecteId . '/fases/fase-7/presentacio-defensa'
        : '/fases-del-projecte/fase-7/presentacio-defensa';
}
?>
<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

    <style>
    .fase-7-presentacio--activa::before { background: #2563A6; }
    .fase-7-presentacio--activa .bloc-tipus { color: #2563A6; }
    .fase-7-presentacio--activa .btn-fase-informacio {
        color: #fff;
        background: #2563A6;
        border-color: #2563A6;
    }
    .fase-7-presentacio--activa .btn-fase-informacio:hover,
    .fase-7-presentacio--activa .btn-fase-informacio:focus-visible {
        color: #fff;
        background: #1f528a;
        border-color: #1f528a;
    }
    .fase-7-presentacio--activa .btn-fase-informacio-outline {
        color: #2563A6;
        background: #fff;
        border-color: #2563A6;
    }
    .fase-7-presentacio--activa .btn-fase-informacio-outline:hover,
    .fase-7-presentacio--activa .btn-fase-informacio-outline:focus-visible {
        color: #fff;
        background: #2563A6;
        border-color: #2563A6;
    }
    </style>
    <section class="bloc bloc-informacio<?= $faseBloquejada ? '' : ' fase-7-presentacio--activa' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus">Presentació</div>
            <h2>Defensa del projecte</h2>
            <p class="mb-3">Prepara la defensa del projecte i presenta el treball amb claredat i seguretat.</p>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($presentacion_defensa !== ''): ?>
                    <a href="<?= htmlspecialchars($presentacion_defensa, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase btn-fase-informacio">Obrir la presentació</a>
                <?php endif; ?>
                <?php if ($guia_defensa !== ''): ?>
                    <a href="<?= htmlspecialchars($guia_defensa, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-fase btn-fase-informacio btn-fase-informacio-outline">Guia de la defensa</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="bloc <?= $faseBloquejada ? 'bloc-bloquejat' : ($estatPresentacio['completada'] ? 'bloc-completat' : 'bloc-activitat') ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $faseBloquejada ? 'Bloquejada' : ($estatPresentacio['completada'] ? 'Completada' : 'Activitat') ?></div>
            <h2>Presentació de la defensa</h2>
            <p class="mb-3">Pugeu en format PDF la presentació que utilitzareu durant la defensa del projecte.</p>
            <?php if ($faseBloquejada): ?>
                <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar les Fases 5 i 6.</p>
            <?php else: ?>
                <?php if ($estatPresentacio['completada']): ?>
                    <div class="mb-3">
                        <a href="<?= htmlspecialchars($estatPresentacio['pdf_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Presentació de la defensa
                        </a>
                    </div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($hrefPresentacio, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatPresentacio['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
            <?php endif; ?>
        </div>
    </section>
</div>
