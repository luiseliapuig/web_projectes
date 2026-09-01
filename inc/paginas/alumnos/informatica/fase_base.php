<?php
declare(strict_types=1);

// projecte_context.php i fases_navegacion.php són transversals de tota
// l'àrea d'alumnat i no s'han mogut a informatica/: es referencien des del
// directori pare.
//
// El professorat entra en aquesta mateixa infraestructura com a "visitant
// amb drets": quan qui inclou aquest fitxer ja arriba amb $proyectoAlumno
// resolt (autoritzat contra rel_profesores_grupos, no contra la sessió
// d'alumne — vegeu fase-tutor.php), no es torna a resoldre via
// projecte_context.php, que és estrictament de sessió d'alumne.
if (!isset($proyectoAlumno)) {
    if (!(require dirname(__DIR__) . '/projecte_context.php')) {
        return;
    }
}

// L'accés directe a una URL de fase no és suficient: el projecte ha de
// pertànyer realment a l'arquitectura 'informatica' (proyecto → ciclo →
// fases_clave). Amagar l'enllaç a la navegació no protegeix per si sol.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
if (!proyectoPerteneceArquitecturaFases($proyectoAlumno, 'informatica')) {
    http_response_code(403);
    die('Accés no permès');
}

$faseActiva = isset($faseNumero) ? (int) $faseNumero : 0;
// Mateix valor per defecte que ja fa servir fases_navegacion.php: cal
// disponible aquí també perquè el breadcrumb es construeix abans d'incloure
// aquell fitxer.
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';

// Estat real de la fase activa (ESTAT, no SELECCIÓ — vegeu
// fases_navegacion.php): mateixes fonts de veritat que ja fa servir el
// sidebar i el resum de "Fases del projecte" (fase1CompletadaAlumnoProyecto/
// Proyecte + fase2PropostaObtenirEstat), reduïdes amb el mateix
// fasesEstatAparenca() — mai un criteri paral·lel ni un cas especial per
// Fase 1. Serveix per acolorir l'eyebrow "Fase N" de la capçalera segons
// l'estat real, en lloc del granate fix que tenia abans.
require_once __DIR__ . '/fase-1_funcions.php';
require_once __DIR__ . '/fase-2_proposta_funcions.php';
require_once __DIR__ . '/fase-3_document_funcional_funcions.php';
require_once __DIR__ . '/fase-4_funcions.php';
require_once __DIR__ . '/fase-5_funcions.php';
require_once __DIR__ . '/fase-6_funcions.php';
require_once __DIR__ . '/fase-7_funcions.php';
$idProjecteEstatFase = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$alumnoSessioIdEstatFase = (int) ($_SESSION['alumno_id'] ?? 0);
$faseUnoCompletada = $alumnoSessioIdEstatFase > 0
    ? fase1CompletadaAlumnoProyecto($pdo, $alumnoSessioIdEstatFase, $idProjecteEstatFase)
    : fase1CompletadaProyecte($pdo, $idProjecteEstatFase);
$estatFaseDos = fase2PropostaObtenirEstat($pdo, $idProjecteEstatFase);
$estatFaseTres = fase3DocumentFuncionalObtenirEstat($pdo, (int) ($proyectoAlumno['id_proyecto'] ?? 0));
$estatFaseQuatre = fase4PlanificacioGestioObtenirEstat($pdo, $idProjecteEstatFase);
$estatFaseCinc = fase5ObtenirEstat($pdo, $idProjecteEstatFase);
$estatFaseSis = fase6ObtenirEstat($pdo, $idProjecteEstatFase);
$estatFaseSet = fase7PresentacioDefensaObtenirEstat($pdo, $idProjecteEstatFase);
$aparencaFaseActiva = fasesEstatAparenca($faseActiva, $faseUnoCompletada, $estatFaseDos['completada'], $estatFaseDos['atencion'], $estatFaseTres['completada'], $estatFaseTres['atencion'], $estatFaseQuatre['completada'], $estatFaseCinc['completada'], $estatFaseSis['completada'], $estatFaseSet['completada']);
$classeEtiquetaFaseActiva = $aparencaFaseActiva['completada']
    ? 'fase-etiqueta--completada'
    : ($aparencaFaseActiva['atencio']
        ? 'fase-etiqueta--atencio'
        : ($aparencaFaseActiva['bloquejada'] ? 'fase-etiqueta--bloquejada' : ''));

// Breadcrumb comú de les vistes internes: el shell resol el camí complet
// (i el context alumnat/professorat) aquí, un sol cop, perquè cap vista
// l'hagi de construir pel seu compte. Mateixa fórmula d'URL ja establerta a
// fases_navegacion.php / fases_projecte.php.
//
// Diferència deliberada per rol (vegeu docs/codex/arquitectura.md): a
// l'alumnat el sidebar + menú ja donen prou context al nivell de fase, així
// que el breadcrumb només apareix dins d'una tasca ($breadcrumbTasca
// declarat pel wrapper). Al professorat, en canvi, el breadcrumb també
// apareix al nivell de fase (sense l'últim segment de tasca), perquè
// necessita poder recórrer la jerarquia projecte/alumnat des de qualsevol
// punt — per això "Resum" hi substitueix el botó "Tornar al Resum" que
// abans oferien fase-tutor_capcalera.php i similars.
$breadcrumbTascaFinal = isset($breadcrumbTasca) && is_string($breadcrumbTasca) && $breadcrumbTasca !== ''
    ? $breadcrumbTasca
    : null;
$breadcrumbItems = [];
if ($rolVisualitzacio === 'professor' || $breadcrumbTascaFinal !== null) {
    $idProjecteBreadcrumb = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
    if ($rolVisualitzacio === 'professor') {
        // $titolProjecteCapcalera ja el resol fase-tutor_capcalera.php, que
        // sempre s'inclou abans que aquest fitxer en el context de
        // professorat: mai es torna a consultar aquí.
        $breadcrumbItems[] = ['label' => 'Resum', 'href' => '/resum'];
        $breadcrumbItems[] = [
            'label' => $titolProjecteCapcalera ?? 'Projecte',
            'href' => '/projecte/' . $idProjecteBreadcrumb . '/fases',
        ];
        $hrefFaseBreadcrumb = '/projecte/' . $idProjecteBreadcrumb . '/fases/fase-' . $faseActiva;
    } else {
        $breadcrumbItems[] = ['label' => 'Fases del projecte', 'href' => '/fases-del-projecte'];
        $hrefFaseBreadcrumb = '/fases-del-projecte/fase-' . $faseActiva;
    }
    // "Fase N" és clicable només si encara queda la tasca com a últim
    // element; si no n'hi ha, "Fase N" ÉS la pàgina actual.
    $breadcrumbItems[] = [
        'label' => 'Fase ' . $faseActiva,
        'href' => $breadcrumbTascaFinal !== null ? $hrefFaseBreadcrumb : null,
    ];
    if ($breadcrumbTascaFinal !== null) {
        $breadcrumbItems[] = ['label' => $breadcrumbTascaFinal, 'href' => null];
    }
}
?>
<script>window.PAGE_TITLE = <?= json_encode('Fase ' . $faseActiva . ' · ' . $faseTitulo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<div class="container-fluid py-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3"><?php include dirname(__DIR__) . '/fases_navegacion.php'; ?></aside>
        <section class="col-lg-9">
            <?php if ($breadcrumbItems !== []): ?>
                <nav class="fase-breadcrumb mb-2" aria-label="Camí de navegació">
                    <?php foreach ($breadcrumbItems as $index => $item): ?>
                        <?php if ($index > 0): ?><span class="fase-breadcrumb-separador" aria-hidden="true">›</span><?php endif; ?>
                        <?php if ($item['href'] !== null): ?>
                            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                            <span aria-current="page"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="card shadow-sm border-0 rounded-4 p-4 p-lg-5">
                <header class="mb-4">
                    <span class="small fase-etiqueta <?= $classeEtiquetaFaseActiva ?>">Fase <?= $faseActiva ?></span>
                    <h1 class="h3 mb-1 mt-1"><?= htmlspecialchars($faseTitulo, ENT_QUOTES, 'UTF-8') ?></h1>
                </header>

                <?php if (isset($faseContenidoArchivo) && is_string($faseContenidoArchivo) && is_file($faseContenidoArchivo)): ?>
                    <?php include $faseContenidoArchivo; ?>
                <?php else: ?>
                    <h2 class="h5 mb-2"><?= htmlspecialchars($faseTitulo, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-muted mb-0">Aquesta secció està preparada. En el següent pas definirem el contingut i les accions de la fase.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
