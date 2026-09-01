<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-7_funcions.php';
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
