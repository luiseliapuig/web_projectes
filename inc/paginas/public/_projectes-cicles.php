<?php
// Selector compartit de cicles dels catàlegs públics de projectes.
$cicleActiu = isset($cicleActiu) && is_string($cicleActiu) ? $cicleActiu : null;
?>
<div class="projectes-filter mb-4">
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (projectesPublicsCicles() as $itemCicle): ?>
            <a
                href="/projectes/<?= rawurlencode($itemCicle) ?>"
                class="projectes-filter-pill <?= $itemCicle === $cicleActiu ? 'active' : '' ?>"
            >
                <?= htmlspecialchars($itemCicle, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
