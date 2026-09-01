<?php
declare(strict_types=1);

// Contingut de la vista de Fase 2: únicament el llistat de targetes-resum de
// les seves tasques (de moment, una sola: Proposta de projecte). Mai
// desplega aquí instruccions, plantilles, formularis ni accions — això
// pertany al detall de cada tasca. Vegeu el contracte general a
// docs/codex/arquitectura.md ("Fases y tareas").

require_once __DIR__ . '/fase-1_funcions.php';
require_once __DIR__ . '/fase-2_proposta_funcions.php';

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$idProjecteTasques = (int) ($proyectoAlumno['id_proyecto'] ?? 0);

// La tasca "Proposta de projecte" exigeix Fase 1 completada. Això només
// aplica a l'alumnat (sessió amb el seu propi alumne_id); la vista
// contextual del professorat no en depèn — mateix comportament que ja
// tenia. Reutilitza fase1CompletadaAlumnoProyecto(), única font de veritat
// d'aquest criteri.
$tascaPropostaBloquejada = $rolVisualitzacio === 'alumne'
    && !fase1CompletadaAlumnoProyecto($pdo, (int) ($_SESSION['alumno_id'] ?? 0), $idProjecteTasques);

$estatProposta = $tascaPropostaBloquejada ? null : fase2PropostaObtenirEstat($pdo, $idProjecteTasques);
// La targeta-resum RESUMEIX (categoria/tipus ja tancats i el PDF definitiu),
// mai reprodueix el document de treball ni cap control operatiu: això
// pertany exclusivament al detall (Pas 1/2/3). Reutilitza el mateix helper
// que ja fa servir el detall — cap consulta ni derivació noves.
$classificacioTasca = $tascaPropostaBloquejada ? null : fase2ClassificacioObtenirEstat($pdo, $idProjecteTasques);

$enllacEntrarProposta = $rolVisualitzacio === 'professor'
    ? '/projecte/' . $idProjecteTasques . '/fases/fase-2/proposta'
    : '/fases-del-projecte/fase-2/proposta';

// Únic recurs documental de la targeta: el PDF definitiu (resultat de la
// tasca). L'enllaç viu (propuesta_url) és el document de treball del Pas 2 i
// mai es mostra aquí. Color segons l'estat de la PRÒPIA targeta (mai un
// document concret que "decideixi" el seu color).
$enllacPdfTasca = $estatProposta !== null && $estatProposta['pdf'] !== '' ? $estatProposta['pdf'] : null;
$classeResultatPdf = $estatProposta === null
    ? ''
    : ($estatProposta['completada']
        ? 'tasca-recurs-resultat--completat'
        : ($estatProposta['atencion'] ? 'tasca-recurs-resultat--atencio' : 'tasca-recurs-resultat--activitat'));
?>

<p class="fase-introduccio mb-4"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

<div class="d-grid gap-3">
    <?php if ($tascaPropostaBloquejada): ?>
        <!-- Tasca bloquejada: es manté visible (forma part del recorregut),
             però amb llenguatge neutre/bloquejat i sense cap acció ni enllaç
             operatiu. El gate real hi és al detall (fase-2_proposta.php) i a
             les accions (fase-2_accion.php); això només és la seva
             representació visual. -->
        <section class="bloc bloc-bloquejat mb-0">
            <div class="bloc-contingut">
                <div class="bloc-tipus mb-1">Bloquejada</div>
                <h2 class="h5 mb-2">Proposta de projecte</h2>
                <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar la Fase 1.</p>
            </div>
        </section>
    <?php else: ?>
        <section class="bloc <?= $estatProposta['classe_bloc'] ?> mb-0">
            <div class="bloc-contingut">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <div class="bloc-tipus mb-1">Tasca</div>
                        <h2 class="h5 mb-1">Proposta de projecte</h2>
                    </div>
                    <span class="badge rounded-pill px-3 py-2 <?= $estatProposta['classe_badge'] ?>"><?= htmlspecialchars($estatProposta['text'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <p class="text-muted mb-3">Definiu la idea inicial del projecte i feu-la validar pel tutor o tutora.</p>
                <?php if ($classificacioTasca !== null && $classificacioTasca['completat']): ?>
                    <!-- Classificació ja tancada (Pas 1): mateix llenguatge
                         que el detall (.fase-resultat-completat), mai un nom
                         fix — surt directament de categoria_proyecto_id /
                         tipo_proyecto_id. -->
                    <p class="mb-3 fase-resultat-completat">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <?= htmlspecialchars((string) $classificacioTasca['categoria_nombre'], ENT_QUOTES, 'UTF-8') ?><?= $classificacioTasca['tipo_nombre'] !== null ? ' › ' . htmlspecialchars((string) $classificacioTasca['tipo_nombre'], ENT_QUOTES, 'UTF-8') : '' ?>
                    </p>
                <?php endif; ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <a href="<?= htmlspecialchars($enllacEntrarProposta, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatProposta['classe_cta'] ?>">Entrar</a>
                    <?php if ($enllacPdfTasca !== null): ?>
                        <a href="<?= htmlspecialchars($enllacPdfTasca, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link <?= $classeResultatPdf ?>">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF definitiu
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
