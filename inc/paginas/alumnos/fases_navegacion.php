<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/fases/funciones.php';

$faseActiva = isset($faseActiva) ? (int) $faseActiva : 0;

// El recorregut de fases pertany al projecte, no al rol: alumnat i
// professorat consulten la mateixa navegació. Només canvia la ruta de cada
// enllaç (vegeu $hrefFase més avall) i, quan no hi ha sessió d'alumne
// (context de professorat), la font de l'estat de completat.
//
// ESTAT i SELECCIÓ són conceptes independents (vegeu docs/codex/arquitectura.md):
// aquest sidebar SEMPRE calcula els estats reals i SEMPRE és navegable,
// també quan la vista actual és una tasca concreta (formulari, consulta de
// només lectura...). Entrar en una tasca mai bloqueja ni degrada el menú —
// abans hi havia un "mode formulari" ($fasesNavegacionBloqueada) que ho
// feia; ja no existeix cap camí que l'activi.
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';

// Les fases disponibles depenen de l'arquitectura del cicle del projecte
// (proyecto → ciclo → fases_clave → arquitectura registrada → fases.php).
// Si el cicle no en té cap (o la clau no es reconeix), no hi ha res a
// navegar: cap warning, cap fase inventada, cap directori carregat.
$fasesProyecto = obtenerFasesProyecto($proyectoAlumno ?? []);
if ($fasesProyecto === []) {
    return;
}

require_once __DIR__ . '/informatica/fase-1_funcions.php';

$idProjecteNavegacio = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$alumnoSessioId = (int) ($_SESSION['alumno_id'] ?? 0);

// Sessió d'alumne: el seu propi criteri personal (mai canviat). Context de
// professorat (sense alumne_id de sessió): el mateix criteri a nivell de
// projecte sencer, ja que no hi ha "l'alumne actual" al qual referir-se.
$faseUnoCompletada = $alumnoSessioId > 0
    ? fase1CompletadaAlumnoProyecto($pdo, $alumnoSessioId, $idProjecteNavegacio)
    : fase1CompletadaProyecte($pdo, $idProjecteNavegacio);

// Fase 2 (Proposta de projecte): estat de la seva única tasca real,
// reutilitzant el mateix helper que ja fa servir la targeta-resum i el
// detall (fase2PropostaObtenirEstat), en lloc de repetir o d'inventar un
// criteri paral·lel aquí. "Completada" exigeix validació + PDF definitiu;
// "atenció" cobreix tant una revisió oberta com una proposta ja validada
// que encara espera el PDF (vegeu el propi helper). Addició puntual sobre
// la lògica existent de Fase 1; aquest fitxer encara no és un motor
// genèric de fases.
require_once __DIR__ . '/informatica/fase-2_proposta_funcions.php';
require_once __DIR__ . '/informatica/fase-3_document_funcional_funcions.php';
require_once __DIR__ . '/informatica/fase-4_funcions.php';
require_once __DIR__ . '/informatica/fase-5_funcions.php';
require_once __DIR__ . '/informatica/fase-6_funcions.php';
require_once __DIR__ . '/informatica/fase-7_funcions.php';

$estatFaseDos = fase2PropostaObtenirEstat($pdo, $idProjecteNavegacio);
$faseDosCompletada = $estatFaseDos['completada'];
$faseDosAtencio = $estatFaseDos['atencion'];
$estatFaseTres = fase3DocumentFuncionalObtenirEstat($pdo, $idProjecteNavegacio);
$faseTresCompletada = $estatFaseTres['completada'];
$faseTresAtencio = $estatFaseTres['atencion'];
$estatFaseQuatre = fase4PlanificacioGestioObtenirEstat($pdo, $idProjecteNavegacio);
$faseQuatreCompletada = $estatFaseQuatre['completada'];
$estatFaseCinc = fase5ObtenirEstat($pdo, $idProjecteNavegacio);
$faseCincCompletada = $estatFaseCinc['completada'];
$estatFaseSis = fase6ObtenirEstat($pdo, $idProjecteNavegacio);
$faseSisCompletada = $estatFaseSis['completada'];
$estatFaseSet = fase7PresentacioDefensaObtenirEstat($pdo, $idProjecteNavegacio);
$faseSetCompletada = $estatFaseSet['completada'];
?>
<nav class="projecte-fases-nav" aria-label="Fases del projecte">
    <?php foreach ($fasesProyecto as $numeroFase => $fase): ?>
        <?php $titolFase = $fase['titulo']; ?>
        <?php
        // Mateixa derivació que reutilitza el resum gran de "Fases del
        // projecte" (fases_projecte.php): única font, perquè mai puguin
        // divergir en l'estat mostrat d'una mateixa fase (vegeu
        // fasesEstatAparenca() a fase-1_funcions.php).
        $aparencaFase = fasesEstatAparenca($numeroFase, $faseUnoCompletada, $faseDosCompletada, $faseDosAtencio, $faseTresCompletada, $faseTresAtencio, $faseQuatreCompletada, $faseCincCompletada, $faseSisCompletada, $faseSetCompletada);
        $faseBloqueadaApariencia = $aparencaFase['bloquejada'];
        $faseCompletadaApariencia = $aparencaFase['completada'];
        $faseAtencioApariencia = $aparencaFase['atencio'];
        ?>
        <?php
        // El professorat navega la mateixa infraestructura, però cada
        // enllaç ha de dur al context del PROJECTE que està consultant
        // (mai a "la meva fase N", que és el que representa $fase['ruta']
        // per a l'alumnat). Genèric per a qualsevol de les 7 fases: mai es
        // construeix un enllaç per fase individualment.
        $hrefFase = $rolVisualitzacio === 'professor'
            ? '/projecte/' . $idProjecteNavegacio . '/fases/fase-' . $numeroFase
            : $fase['ruta'];
        ?>
        <a
            class="projecte-fase-enllac <?= $faseActiva === $numeroFase ? 'active' : '' ?> <?= $faseBloqueadaApariencia ? 'projecte-fase-enllac-pendent' : '' ?> <?= $faseCompletadaApariencia ? 'projecte-fase-enllac-completada' : '' ?> <?= $faseAtencioApariencia ? 'projecte-fase-enllac-atencio' : '' ?>"
            href="<?= htmlspecialchars($hrefFase, ENT_QUOTES, 'UTF-8') ?>"
            <?= $faseActiva === $numeroFase ? 'aria-current="page"' : '' ?>
        >
            <span class="projecte-fase-fletxa"><i class="bi <?= $faseBloqueadaApariencia ? 'bi-lock-fill' : ($faseCompletadaApariencia ? 'bi-check-lg' : ($faseAtencioApariencia ? 'bi-exclamation-lg' : 'bi-chevron-right')) ?>" aria-hidden="true"></i></span>
            <span>
                <small>Fase <?= $numeroFase ?></small>
                <strong><?= nl2br(htmlspecialchars($titolFase, ENT_QUOTES, 'UTF-8'), false) ?></strong>
            </span>
        </a>
    <?php endforeach; ?>
</nav>
