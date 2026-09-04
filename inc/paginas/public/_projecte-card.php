<?php
// Targeta compartida pels catàlegs públics de projectes.
?>
<div class="col-12 col-md-6 col-xl-4">
    <a
        href="/projecte/<?= (int) $projecte['id_proyecto'] ?>"
        class="project-card-link"
    >
        <article class="project-card">
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

                <div class="project-card-alumnes">
                    <?php if (!empty($projecte['alumnos_array'])): ?>
                        <div class="project-card-students">
                            <?php foreach ($projecte['alumnos_array'] as $alumne): ?>
                                <span class="project-student-badge">
                                    <?= htmlspecialchars(trim((string) $alumne), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small">Sense alumnat assignat</div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </a>
</div>
