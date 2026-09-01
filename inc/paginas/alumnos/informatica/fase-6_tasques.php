<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-6_funcions.php';

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$faseBloquejada = $rolVisualitzacio === 'alumne' && !empty($aparencaFaseActiva['bloquejada']);
$estatFaseSis = fase6ObtenirEstat($pdo, $projecteId);
$estatMemoria = $estatFaseSis['document'];
$estatFitxa = $estatFaseSis['fitxa'];
$estatMemoriaDefinitiva = $estatFaseSis['memoria_final'];
if (!$faseBloquejada) {
    $hrefMemoria = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $projecteId . '/fases/fase-6/document-memoria'
        : '/fases-del-projecte/fase-6/document-memoria';
    $hrefFitxa = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $projecteId . '/fases/fase-6/fitxa-publica'
        : '/fases-del-projecte/fase-6/fitxa-publica';
    $hrefEntregaMemoria = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $projecteId . '/fases/fase-6/entrega-memoria'
        : '/fases-del-projecte/fase-6/entrega-memoria';
}
?>
<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($faseBloquejada): ?>
        <?php
        $tasquesBloquejades = [
            ['titol' => 'Document de la memòria', 'descripcio' => 'Creeu i compartiu el document viu on anireu elaborant la memòria del projecte.'],
            ['titol' => 'Fitxa pública del projecte', 'descripcio' => 'Prepareu el nom, el resum, la descripció i la imatge amb què es presentarà el projecte a la web.'],
            ['titol' => 'Entrega de la memòria', 'descripcio' => 'Prepareu la versió definitiva de la memòria en un únic document PDF.'],
        ];
        ?>
        <?php foreach ($tasquesBloquejades as $tasca): ?>
            <section class="bloc bloc-bloquejat">
                <div class="bloc-contingut">
                    <div class="bloc-tipus">Bloquejada</div>
                    <h2><?= htmlspecialchars($tasca['titol'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mb-3"><?= htmlspecialchars($tasca['descripcio'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar la Fase 4.</p>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
    <section class="bloc <?= $estatMemoria['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estatMemoria['completada'] ? 'Completada' : 'Activitat' ?></div>
            <h2>Document de la memòria</h2>
            <p class="mb-3">Creeu i compartiu el document viu on anireu elaborant la memòria del projecte.</p>
            <?php if ($estatMemoria['url'] !== ''): ?>
                <div class="mb-3">
                    <a href="<?= htmlspecialchars($estatMemoria['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Document de la memòria
                    </a>
                </div>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($hrefMemoria, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatMemoria['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
        </div>
    </section>
    <section class="bloc <?= $estatFitxa['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estatFitxa['completada'] ? 'Completada' : 'Activitat' ?></div>
            <h2>Fitxa pública del projecte</h2>
            <p class="mb-3">Prepareu el nom, el resum, la descripció i la imatge amb què es presentarà el projecte a la web.</p>
            <?php if ($estatFitxa['imatge_url'] !== ''): ?>
                <img src="<?= htmlspecialchars($estatFitxa['imatge_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($estatFitxa['nombre'] ?: 'Imatge del projecte', ENT_QUOTES, 'UTF-8') ?>" class="fase6-fitxa-miniatura img-fluid rounded mb-3">
            <?php endif; ?>
            <a href="<?= htmlspecialchars($hrefFitxa, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatFitxa['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
        </div>
    </section>
    <section class="lliurament-final lliurament-final--compacte<?= $estatMemoriaDefinitiva['completada'] ? ' lliurament-final--completat' : '' ?>">
        <header class="lliurament-final-cap">Memòria final</header>
        <div class="lliurament-final-cos">
            <h2>Entrega de la memòria</h2>
            <p>Prepareu la versió definitiva de la memòria en un únic document PDF.</p>
            <div class="lliurament-final-opcions lliurament-final-opcions--una">
                <div class="lliurament-final-opcio">
                    <div class="lliurament-final-subtitol">Versió definitiva</div>
                    <p>La memòria final recull i presenta el desenvolupament complet del projecte.</p>
                </div>
            </div>
            <div class="lliurament-final-accions d-flex flex-column align-items-start gap-3">
                <?php if ($estatMemoriaDefinitiva['completada']): ?>
                    <?php $memoriaPdfCta = $estatMemoriaDefinitiva['pdf']; ?>
                    <?php include __DIR__ . '/fase-6_memoria_cta.php'; ?>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($hrefEntregaMemoria, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatMemoriaDefinitiva['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
