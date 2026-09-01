<?php
declare(strict_types=1);

// Pantalla arrel del recorregut de l'alumnat: "Fases del projecte" (nom
// canònic; abans "El meu projecte", ja retirat). RESUM del recorregut
// complet: una targeta per FASE (mai per tasca), amb el seu estat real, les
// evidències/resultats rellevants ja tancats i un únic CTA "Entrar" — mai el
// contingut d'una tasca en concret, això pertany a cada fase-N.php. Reutilitza
// fases_navegacion.php per a la barra lateral, igual que la resta de fases.
//
// El professorat entra en aquesta mateixa infraestructura com a "visitant
// amb drets" (mateix contracte que fase_base.php): quan qui inclou aquest
// fitxer ja arriba amb $proyectoAlumno resolt (context de professorat via
// fasesResolverContextTutor(), vegeu fases-tutor.php), no es torna a
// resoldre via projecte_context.php, que és estrictament de sessió d'alumne.
if (!isset($proyectoAlumno)) {
    $permitirSinProyecto = true;
    if (!(require __DIR__ . '/projecte_context.php')) {
        return;
    }
}
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';

require_once dirname(__DIR__, 2) . '/fases/funciones.php';
require_once __DIR__ . '/informatica/fase-1_funcions.php';
require_once __DIR__ . '/informatica/fase-2_proposta_funcions.php';
require_once __DIR__ . '/informatica/fase-3_document_funcional_funcions.php';
require_once __DIR__ . '/informatica/fase-4_funcions.php';
require_once __DIR__ . '/informatica/fase-5_funcions.php';
require_once __DIR__ . '/informatica/fase-6_funcions.php';
require_once __DIR__ . '/informatica/fase-7_funcions.php';

$faseActiva = 0;
$nombreProyecto = trim((string) ($proyectoAlumno['nombre'] ?? ''));
$idProjecteActual = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$alumnoSessioId = (int) ($_SESSION['alumno_id'] ?? 0);

// Llistat de fases de l'arquitectura del cicle: mateixa resolució segura que
// ja fa servir fases_navegacion.php (proyecto → ciclo → fases_clave →
// arquitectura registrada → fases.php).
$fasesProjecte = obtenerFasesProyecto($proyectoAlumno);

// -----------------------------------------------------------------------------
// Mateixes fonts de veritat que ja fa servir el sidebar (fases_navegacion.php)
// per decidir l'estat de cada fase — mai un criteri paral·lel. La targeta
// gran i la targeta del sidebar hi arriben SEMPRE d'acord perquè totes dues
// criden exactament els mateixos helpers amb els mateixos paràmetres.
// -----------------------------------------------------------------------------
$faseUnoCompletada = $alumnoSessioId > 0
    ? fase1CompletadaAlumnoProyecto($pdo, $alumnoSessioId, $idProjecteActual)
    : fase1CompletadaProyecte($pdo, $idProjecteActual);

$estatFaseDos = fase2PropostaObtenirEstat($pdo, $idProjecteActual);
$faseDosCompletada = $estatFaseDos['completada'];
$faseDosAtencio = $estatFaseDos['atencion'];
$estatFaseTres = fase3DocumentFuncionalObtenirEstat($pdo, $idProjecteActual);
$faseTresCompletada = $estatFaseTres['completada'];
$faseTresAtencio = $estatFaseTres['atencion'];
$estatFaseQuatre = fase4PlanificacioGestioObtenirEstat($pdo, $idProjecteActual);
$faseQuatreCompletada = $estatFaseQuatre['completada'];
$estatFaseCinc = fase5ObtenirEstat($pdo, $idProjecteActual);
$faseCincCompletada = $estatFaseCinc['completada'];
$estatFaseSis = fase6ObtenirEstat($pdo, $idProjecteActual);
$faseSisCompletada = $estatFaseSis['completada'];
$estatFaseSet = fase7PresentacioDefensaObtenirEstat($pdo, $idProjecteActual);
$faseSetCompletada = $estatFaseSet['completada'];

// Resultats detallats (evidències), només calculats quan realment aporten
// informació: mai s'inventa cap resultat d'una fase que encara no l'ha
// produït (vegeu docs — "no inventes evidencias todavía no existentes").
$resultatGrupTreball = $faseUnoCompletada
    ? fase1ResultadoGrupoTrabajo($pdo, $idProjecteActual, $rolVisualitzacio, $alumnoSessioId)
    : null;
$classificacioTasca = $faseDosCompletada
    ? fase2ClassificacioObtenirEstat($pdo, $idProjecteActual)
    : null;

// Composició de l'estat visual de cada fase (bloquejada/completada/atenció/
// activa) + el seu CTA i evidències — sempre en OUTLINE, mai sòlid, en
// aquesta pantalla de resum (el CTA sòlid pertany a l'espai de treball
// interior de cada fase, no al seu resum). Estructurat perquè afegir
// evidències a Fase 3-7 en el futur sigui només omplir 'evidencies' per al
// seu número de fase, sense tocar la resta del render.
function fasesProjecteTargeta(
    int $numeroFase,
    array $aparenca,
    ?array $resultatGrupTreball,
    ?array $classificacioTasca,
    ?array $estatFaseDos,
    ?array $estatFaseTres,
    ?array $estatFaseQuatre
): array {
    $classeBloc = $aparenca['completada']
        ? 'bloc-completat'
        : ($aparenca['atencio'] ? 'bloc-atencio' : ($aparenca['bloquejada'] ? 'bloc-bloquejat' : 'bloc-activitat'));
    $etiquetaEstat = $aparenca['completada']
        ? 'Completada'
        : ($aparenca['atencio'] ? 'Atenció' : ($aparenca['bloquejada'] ? 'Bloquejada' : 'Activa'));
    // Mateixa composició FORMA+color ja establerta (.btn-fase per la
    // geometria, .btn-puig/.btn-atencio/.btn-outline-success pel color):
    // aquí sempre la seva variant OUTLINE, mai la sòlida.
    $classeCta = $aparenca['completada']
        ? 'btn-outline-success'
        : ($aparenca['atencio'] ? 'btn-atencio' : 'btn-puig');

    $evidencies = [];
    if ($numeroFase === 1 && $resultatGrupTreball !== null) {
        if ($resultatGrupTreball['resultado'] !== '') {
            $evidencies[] = ['tipo' => 'text', 'text' => $resultatGrupTreball['resultado']];
        }
        if ($resultatGrupTreball['aceptado']) {
            $evidencies[] = ['tipo' => 'text', 'text' => 'Compromís acceptat'];
        }
    } elseif ($numeroFase === 2) {
        if ($classificacioTasca !== null && $classificacioTasca['completat']) {
            $textClassificacio = (string) $classificacioTasca['categoria_nombre'];
            if ($classificacioTasca['tipo_nombre'] !== null) {
                $textClassificacio .= ' › ' . $classificacioTasca['tipo_nombre'];
            }
            $evidencies[] = ['tipo' => 'text', 'text' => $textClassificacio];
        }
        if ($estatFaseDos !== null && $estatFaseDos['pdf'] !== '') {
            $evidencies[] = ['tipo' => 'pdf', 'text' => 'Proposta definitiva', 'href' => $estatFaseDos['pdf']];
        }
    } elseif ($numeroFase === 3 && $estatFaseTres !== null && $estatFaseTres['completada']) {
        $evidencies[] = ['tipo' => 'pdf', 'text' => 'Document funcional definitiu', 'href' => $estatFaseTres['pdf']];
    } elseif ($numeroFase === 4 && $estatFaseQuatre !== null) {
        if ($estatFaseQuatre['planificacio_url'] !== '') {
            $evidencies[] = ['tipo' => 'link', 'text' => 'Planificació temporal', 'href' => $estatFaseQuatre['planificacio_url']];
        }
        if ($estatFaseQuatre['gestio_url'] !== '') {
            $evidencies[] = ['tipo' => 'link', 'text' => 'Tauler de gestió', 'href' => $estatFaseQuatre['gestio_url']];
        }
    }

    return [
        'classe_bloc' => $classeBloc,
        'etiqueta_estat' => $etiquetaEstat,
        'classe_cta' => $classeCta,
        'evidencies' => $evidencies,
    ];
}
?>
<script>window.PAGE_TITLE = 'Fases del projecte';</script>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Fases del projecte</h1>
        <p class="text-muted mb-0">Segueix les fases del projecte i accedeix a cada espai de treball.</p>
    </div>
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3"><?php include __DIR__ . '/fases_navegacion.php'; ?></aside>
        <section class="col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-lg-5">
                <h2 class="h4 mb-2">Fases del projecte</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars(trim((string) $proyectoAlumno['ciclo'] . ' ' . (string) $proyectoAlumno['grupo']), ENT_QUOTES, 'UTF-8') ?></p>

                <?php if ($fasesProjecte === []): ?>
                    <p class="mb-0">Selecciona una fase del menú lateral per començar.</p>
                <?php else: ?>
                    <div class="d-grid gap-4">
                        <?php foreach ($fasesProjecte as $numeroFase => $fase): ?>
                            <?php
                            $aparenca = fasesEstatAparenca((int) $numeroFase, $faseUnoCompletada, $faseDosCompletada, $faseDosAtencio, $faseTresCompletada, $faseTresAtencio, $faseQuatreCompletada, $faseCincCompletada, $faseSisCompletada, $faseSetCompletada);
                            $targeta = fasesProjecteTargeta((int) $numeroFase, $aparenca, $resultatGrupTreball, $classificacioTasca, $estatFaseDos, $estatFaseTres, $estatFaseQuatre);
                            $descripcioFase = trim((string) ($fase['descripcio'] ?? ''));
                            // Genèric per a qualsevol de les 7 fases (mateix
                            // criteri que fases_navegacion.php): el
                            // professorat navega al context del PROJECTE que
                            // consulta, mai a "la meva fase N" (que és el que
                            // representa $fase['ruta'] per a l'alumnat).
                            $hrefFase = $rolVisualitzacio === 'professor'
                                ? '/projecte/' . $idProjecteActual . '/fases/fase-' . $numeroFase
                                : $fase['ruta'];
                            $baseFaseCinc = $rolVisualitzacio === 'professor'
                                ? '/projecte/' . $idProjecteActual . '/fases/fase-5/'
                                : '/fases-del-projecte/fase-5/';
                            $baseFaseSis = $rolVisualitzacio === 'professor'
                                ? '/projecte/' . $idProjecteActual . '/fases/fase-6/'
                                : '/fases-del-projecte/fase-6/';
                            $hrefPresentacioDefensa = $rolVisualitzacio === 'professor'
                                ? '/projecte/' . $idProjecteActual . '/fases/fase-7/presentacio-defensa'
                                : '/fases-del-projecte/fase-7/presentacio-defensa';
                            ?>
                            <!-- Mateixa jerarquia i espaiat que les targetes
                                 interiors (vegeu fase-1_contingut.php /
                                 fase-2_tasques.php): .bloc-tipus, <h2> i <p>
                                 sense classes que en sobreescriguin els
                                 marges — només s'afegeixen utilitats
                                 (mb-3, gap-2) allà on la targeta interior
                                 també ho fa (paràgraf/resultat abans d'un
                                 CTA). -->
                            <section class="bloc <?= $targeta['classe_bloc'] ?>">
                                <div class="bloc-contingut">
                                    <div class="bloc-tipus">Fase <?= (int) $numeroFase ?> · <?= htmlspecialchars($targeta['etiqueta_estat'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <h2><?= htmlspecialchars(str_replace("\n", ' ', $fase['titulo']), ENT_QUOTES, 'UTF-8') ?></h2>
                                    <?php if ($descripcioFase !== ''): ?>
                                        <p class="mb-3"><?= htmlspecialchars($descripcioFase, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>

                                    <?php if ((int) $numeroFase === 5 && !$aparenca['bloquejada']): ?>
                                        <div class="d-grid gap-3 mb-3">
                                            <div class="pb-3 border-bottom">
                                                <?php if ($estatFaseCinc['repositoris']['repositoris'] !== []): ?>
                                                    <div class="d-grid gap-2">
                                                        <?php foreach ($estatFaseCinc['repositoris']['repositoris'] as $repositori): ?>
                                                            <a href="<?= htmlspecialchars($repositori['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat">
                                                                <i class="bi bi-git" aria-hidden="true"></i> <?= htmlspecialchars($repositori['literal'], ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="pb-3 border-bottom">
                                                <div class="stack-resum-titol<?= $estatFaseCinc['stack']['tecnologies'] === [] ? ' mb-3' : '' ?>">Tecnologies</div>
                                                <?php if ($estatFaseCinc['stack']['tecnologies'] !== []): ?>
                                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                                        <?php foreach ($estatFaseCinc['stack']['tecnologies'] as $tecnologia): ?>
                                                            <span class="stack-tecnologia-pill"><?= htmlspecialchars((string) $tecnologia['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="stack-resum-titol">Eines</div>
                                                <?php if ($estatFaseCinc['stack']['eines'] !== []): ?>
                                                    <p class="stack-eines-resum mb-0"><?= htmlspecialchars(implode(' · ', array_column($estatFaseCinc['stack']['eines'], 'nombre')), ENT_QUOTES, 'UTF-8') ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="pb-3 border-bottom">
                                                <a href="<?= htmlspecialchars($baseFaseCinc . 'autoavaluacio-final', ENT_QUOTES, 'UTF-8') ?>" class="tasca-recurs-link <?= $estatFaseCinc['autoavaluacio']['completada'] ? 'tasca-recurs-resultat--completat' : 'tasca-recurs-resultat--activitat' ?> fw-semibold">
                                                    <i class="bi <?= $estatFaseCinc['autoavaluacio']['completada'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>" aria-hidden="true"></i> Autoavaluació final
                                                </a>
                                            </div>

                                            <div>
                                                <?php if ($estatFaseCinc['produccio']['completada']): ?>
                                                    <?php $urlProduccioCta = $estatFaseCinc['produccio']['url']; ?>
                                                    <?php include __DIR__ . '/informatica/fase-5_produccio_cta.php'; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ((int) $numeroFase === 6 && !$aparenca['bloquejada']): ?>
                                        <div class="d-grid gap-3 mb-3">
                                            <div class="pb-3 border-bottom">
                                                <?php if ($estatFaseSis['document']['completada']): ?>
                                                    <a href="<?= htmlspecialchars($estatFaseSis['document']['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat">
                                                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Document de la memòria
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars($baseFaseSis . 'document-memoria', ENT_QUOTES, 'UTF-8') ?>" class="tasca-recurs-link tasca-recurs-resultat--activitat fw-semibold">
                                                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i> Document de la memòria
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <div class="pb-3 border-bottom">
                                                <a href="<?= htmlspecialchars($baseFaseSis . 'fitxa-publica', ENT_QUOTES, 'UTF-8') ?>" class="tasca-recurs-link <?= $estatFaseSis['fitxa']['completada'] ? 'tasca-recurs-resultat--completat' : 'tasca-recurs-resultat--activitat' ?> fw-semibold">
                                                    <i class="bi <?= $estatFaseSis['fitxa']['completada'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>" aria-hidden="true"></i> Fitxa pública del projecte
                                                </a>
                                            </div>

                                            <div>
                                                <?php if ($estatFaseSis['memoria_final']['completada']): ?>
                                                    <?php $memoriaPdfCta = $estatFaseSis['memoria_final']['pdf']; ?>
                                                    <?php include __DIR__ . '/informatica/fase-6_memoria_cta.php'; ?>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars($baseFaseSis . 'entrega-memoria', ENT_QUOTES, 'UTF-8') ?>" class="tasca-recurs-link tasca-recurs-resultat--activitat fw-semibold">
                                                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i> Entrega de la memòria
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ((int) $numeroFase === 7 && !$aparenca['bloquejada']): ?>
                                        <div class="mb-3">
                                            <?php if ($estatFaseSet['completada']): ?>
                                                <?php $presentacioPdfCta = $estatFaseSet['pdf_url']; ?>
                                                <?php include __DIR__ . '/informatica/fase-7_presentacio_cta.php'; ?>
                                            <?php else: ?>
                                                <a href="<?= htmlspecialchars($hrefPresentacioDefensa, ENT_QUOTES, 'UTF-8') ?>" class="tasca-recurs-link tasca-recurs-resultat--activitat fw-semibold">
                                                    <i class="bi bi-x-circle-fill" aria-hidden="true"></i> Presentació de la defensa
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($targeta['evidencies'] !== []): ?>
                                        <div class="d-flex flex-column gap-2 mb-3">
                                            <?php foreach ($targeta['evidencies'] as $evidencia): ?>
                                                <?php if ($evidencia['tipo'] === 'pdf' || $evidencia['tipo'] === 'link'): ?>
                                                    <a href="<?= htmlspecialchars($evidencia['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat">
                                                        <i class="bi <?= $evidencia['tipo'] === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-link-45deg' ?>" aria-hidden="true"></i> <?= htmlspecialchars($evidencia['text'], ENT_QUOTES, 'UTF-8') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <p class="fase-resultat-completat mb-0">
                                                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($evidencia['text'], ENT_QUOTES, 'UTF-8') ?>
                                                    </p>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$aparenca['bloquejada']): ?>
                                        <a href="<?= htmlspecialchars($hrefFase, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $targeta['classe_cta'] ?>">Entrar</a>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
