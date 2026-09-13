<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-6_funcions.php';
require_once __DIR__ . '/enlaces-recursos.php';

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$faseBloquejada = $rolVisualitzacio === 'alumne' && !empty($aparencaFaseActiva['bloquejada']);
$estatFaseSis = fase6ObtenirEstat($pdo, $projecteId);
$estatMemoria = $estatFaseSis['document'];
$estatFitxa = $estatFaseSis['fitxa'];
$estatMemoriaDefinitiva = $estatFaseSis['memoria_final'];
$categoriaProjecteId = null;
$categoriaRecursosMemoriaId = null;
$apartatsGuiaMemoria = [];
$guiaMemoriaUrl = '';
$plantillaMemoriaCaUrl = '';
$plantillaMemoriaEsUrl = '';

if ($projecteId > 0) {
    $stmtCategoria = $pdo->prepare('SELECT categoria_proyecto_id FROM app.proyectos WHERE id_proyecto = :id');
    $stmtCategoria->execute([':id' => $projecteId]);
    $categoriaResultat = $stmtCategoria->fetchColumn();
    $categoriaProjecteId = $categoriaResultat !== false && $categoriaResultat !== null
        ? (int) $categoriaResultat
        : null;

    // I+D conserva la seva categoria pròpia, però documentalment utilitza
    // la mateixa família de recursos i apartats que Investigació.
    $categoriaRecursosMemoriaId = $categoriaProjecteId === 5 ? 1 : $categoriaProjecteId;

    if ($categoriaRecursosMemoriaId === 2) {
        $guiaMemoriaUrl = trim((string) $guia_memoria_desarrollo);
        $plantillaMemoriaCaUrl = trim((string) $plantilla_memoria_desarrollo_ca);
        $plantillaMemoriaEsUrl = trim((string) $plantilla_memoria_desarrollo_es);
    } elseif ($categoriaRecursosMemoriaId === 1) {
        $guiaMemoriaUrl = trim((string) $guia_memoria_investigacion);
        $plantillaMemoriaCaUrl = trim((string) $plantilla_memoria_investigacion_ca);
        $plantillaMemoriaEsUrl = trim((string) $plantilla_memoria_investigacion_es);
    }

    if ($categoriaRecursosMemoriaId !== null) {
        $stmtApartats = $pdo->prepare('
            SELECT titulo, enlace_guia
            FROM app.memoria_estructura
            WHERE categoria_proyecto_id = :categoria_id
              AND activo = true
            ORDER BY orden, id_memoria_estructura
        ');
        $stmtApartats->execute([':categoria_id' => $categoriaRecursosMemoriaId]);
        $apartatsGuiaMemoria = $stmtApartats->fetchAll(PDO::FETCH_ASSOC);
    }
}
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

    <?php if ($categoriaRecursosMemoriaId === 1 || $categoriaRecursosMemoriaId === 2): ?>
    <style>
    .memoria-recursos {
        --memoria-recurs-color: #2563A6;
        background: #fff;
        border: 1px solid var(--memoria-recurs-color);
        border-radius: 14px;
        overflow: hidden;
    }
    .memoria-recursos--bloquejada {
        --memoria-recurs-color: #8a94a4;
        border-color: #d8dee6;
    }
    .memoria-recursos__header {
        padding: 12px 22px;
        background: var(--memoria-recurs-color);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
    }
    .memoria-recursos__body { padding: 24px 26px 22px; }
    .memoria-recursos__body > h2 { margin: 0 0 8px; font-size: 25px; line-height: 1.2; }
    .memoria-recursos__intro { margin: 0 0 20px; color: #5f6b82; font-size: 15px; line-height: 1.55; }
    .memoria-recursos__grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 16px; }
    .memoria-recursos__panel { padding: 17px 18px; background: #fff; border: 1px solid #d8e0ea; border-radius: 11px; }
    .memoria-recursos__panel--secondary { background: #fafbfc; }
    .memoria-recursos__panel h3 { margin: 0 0 9px; font-size: 16px; }
    .memoria-index { margin: 0 0 16px; padding-left: 21px; }
    .memoria-index li { margin: 5px 0; color: #5f6b82; line-height: 1.35; }
    .memoria-recursos a:not(.btn-recurso) { color: var(--memoria-recurs-color); }
    .memoria-recursos a:not(.btn-recurso):hover { text-decoration: underline; }
    .btn-recurso {
        display: inline-block;
        padding: 9px 14px;
        border-radius: 7px;
        background: var(--memoria-recurs-color);
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
    }
    .btn-recurso:hover,
    .btn-recurso:focus-visible { color: #fff; filter: brightness(.9); }
    .plantilles-text { margin: 0 0 13px; color: #5f6b82; font-size: 14px; line-height: 1.5; }
    .plantilla-link { display: block; margin: 8px 0; font-size: 14px; font-weight: 600; text-decoration: none; }
    .memoria-recursos__ia { margin-top: 17px; padding-top: 14px; border-top: 1px solid #e2e6ec; color: #5f6b82; font-size: 14px; line-height: 1.5; }
    .memoria-recursos__ia a { font-weight: 600; text-decoration: none; }
    @media (max-width: 700px) {
        .memoria-recursos__body { padding: 22px 18px 20px; }
        .memoria-recursos__grid { grid-template-columns: 1fr; }
    }
    </style>
    <section class="memoria-recursos<?= $faseBloquejada ? ' memoria-recursos--bloquejada' : '' ?>">
        <div class="memoria-recursos__header">GUIA I PLANTILLES</div>
        <div class="memoria-recursos__body">
            <h2>Memòria del projecte</h2>
            <p class="memoria-recursos__intro">En aquest espai trobaràs la guia per redactar la memòria del projecte i les plantilles corresponents al teu tipus de projecte.</p>
            <div class="memoria-recursos__grid">
                <div class="memoria-recursos__panel">
                    <h3>Guia de la memòria</h3>
                    <?php if ($apartatsGuiaMemoria !== []): ?>
                        <ol class="memoria-index">
                            <?php foreach ($apartatsGuiaMemoria as $apartatGuia): ?>
                                <?php $enllacApartat = trim((string) ($apartatGuia['enlace_guia'] ?? '')); ?>
                                <li>
                                    <?php if ($enllacApartat !== ''): ?>
                                        <a href="<?= htmlspecialchars($enllacApartat, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $apartatGuia['titulo'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) $apartatGuia['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <?php if ($guiaMemoriaUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($guiaMemoriaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn-recurso">Obrir la guia completa</a>
                    <?php endif; ?>
                </div>
                <div class="memoria-recursos__panel memoria-recursos__panel--secondary">
                    <h3>Plantilles de la memòria</h3>
                    <p class="plantilles-text">Utilitza la plantilla corresponent com a base per redactar la memòria amb l’estructura recomanada.</p>
                    <?php if ($plantillaMemoriaCaUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($plantillaMemoriaCaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="plantilla-link"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Plantilla en català</a>
                    <?php endif; ?>
                    <?php if ($plantillaMemoriaEsUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($plantillaMemoriaEsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="plantilla-link"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> Plantilla en castellà</a>
                    <?php endif; ?>
                    <?php if (trim((string) $guia_memoria_ia) !== ''): ?>
                        <div class="memoria-recursos__ia">
                            👉 Consulta també l’apartat sobre <a href="<?= htmlspecialchars(trim((string) $guia_memoria_ia), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">l’ús de la intel·ligència artificial</a> per fer-ne un ús correcte i responsable dins de la memòria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
