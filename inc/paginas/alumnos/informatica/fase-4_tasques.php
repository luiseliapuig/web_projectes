<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-4_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$faseBloquejada = $rolVisualitzacio === 'alumne' && !empty($aparencaFaseActiva['bloquejada']);
$estat = fase4PlanificacioGestioObtenirEstat($pdo, $idProjecte);
$tasques = [
    ['titol' => 'Planificació temporal del projecte', 'descripcio' => 'Organitzeu les principals etapes i tasques del projecte i distribuïu-les en el temps.', 'completada' => $estat['planificacio_completada'], 'url' => $estat['planificacio_url'], 'evidencia' => 'Planificació temporal', 'slug' => 'planificacio-temporal'],
    ['titol' => 'Gestió del projecte', 'descripcio' => 'Prepareu el tauler que fareu servir per organitzar i seguir el treball durant el desenvolupament.', 'completada' => $estat['gestio_completada'], 'url' => $estat['gestio_url'], 'evidencia' => 'Tauler de gestió', 'slug' => 'gestio-projecte'],
];
?>
<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>
    <?php foreach ($tasques as $tasca): ?>
        <section class="bloc <?= $faseBloquejada ? 'bloc-bloquejat' : ($tasca['completada'] ? 'bloc-completat' : 'bloc-activitat') ?>">
            <div class="bloc-contingut">
                <div class="bloc-tipus"><?= $faseBloquejada ? 'Bloquejada' : ($tasca['completada'] ? 'Completada' : 'Activitat') ?></div>
                <h2><?= htmlspecialchars($tasca['titol'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mb-3"><?= htmlspecialchars($tasca['descripcio'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($faseBloquejada): ?>
                    <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar la Fase 3.</p>
                <?php else: ?>
                    <?php if ($tasca['completada']): ?>
                        <div class="mb-3"><a href="<?= htmlspecialchars($tasca['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link tasca-recurs-resultat--completat"><i class="bi bi-link-45deg" aria-hidden="true"></i> <?= htmlspecialchars($tasca['evidencia'], ENT_QUOTES, 'UTF-8') ?></a></div>
                    <?php endif; ?>
                    <?php $href = $rolVisualitzacio === 'professor' ? '/projecte/' . $idProjecte . '/fases/fase-4/' . $tasca['slug'] : '/fases-del-projecte/fase-4/' . $tasca['slug']; ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $tasca['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
