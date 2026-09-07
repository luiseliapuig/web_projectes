<?php
// Targeta compartida pels catàlegs públics de projectes.
$classificacioNom = trim((string) ($projecte['tipo_proyecto_nombre'] ?? ''));
$classificacioUrl = null;

if ($classificacioNom !== '' && !empty($projecte['tipo_proyecto_id'])) {
    $classificacioUrl = '/projectes/tipus/' . (int) $projecte['tipo_proyecto_id'];
} else {
    $classificacioNom = trim((string) ($projecte['categoria_proyecto_nombre'] ?? ''));
    if ($classificacioNom !== '' && !empty($projecte['categoria_proyecto_id'])) {
        $classificacioUrl = '/projectes/categoria/' . (int) $projecte['categoria_proyecto_id'];
    }
}
?>
<div class="col-12 col-md-6 col-xl-4">
    <article class="project-card<?= $classificacioUrl !== null ? ' project-card--classified' : '' ?>">
    <a
        href="/projecte/<?= (int) $projecte['id_proyecto'] ?>"
        class="project-card-link"
    >
            <?php if (!empty($projecte['ruta_imagen_absoluta'])): ?>
                <img
                    src="<?= htmlspecialchars((string) $projecte['ruta_imagen_absoluta'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars((string) $projecte['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                    class="project-card-image"
                >
            <?php else: ?>
                <div class="project-card-image project-card-image-placeholder">
                    Sense imatge
                </div>
            <?php endif; ?>

            <div class="project-card-body">
                <div class="project-card-meta mb-2">
                    <span><?= htmlspecialchars((string) $projecte['ciclo'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="project-meta-separator">·</span>
                    <span><?= htmlspecialchars((string) $projecte['grupo'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="project-meta-separator">·</span>
                    <span><?= htmlspecialchars((string) $projecte['curso_academico'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <h2 class="project-card-title">
                    <?= htmlspecialchars((string) $projecte['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <?php if (!empty($projecte['resumen'])): ?>
                    <p class="project-card-summary">
                        <?= htmlspecialchars((string) $projecte['resumen'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

            </div>
    </a>

    <?php if ($classificacioUrl !== null): ?>
        <div class="project-card-classification">
            <a class="project-classification-pill"
               href="<?= htmlspecialchars($classificacioUrl, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($classificacioNom, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    <?php endif; ?>
    </article>
</div>
