<?php
declare(strict_types=1);

require_once __DIR__ . '/projectes-publics_funcions.php';

$tecnologiesHome = projectesPublicsTecnologiesDestacades(
    projectesPublicsTecnologies($pdo)
);
?>

<section class="col-12 mb-5">
    <div class="home-tecnologies">
        <header class="home-tecnologies__header">
            <p class="home-tecnologies__eyebrow">Explora</p>
            <h2 class="home-tecnologies__title">Tecnologies</h2>
            <p class="home-tecnologies__intro">Descobreix els projectes a partir de les tecnologies que utilitzen.</p>
        </header>

        <div class="home-tecnologies__content">
            <div class="home-tecnologies__cloud">
                <?php foreach ($tecnologiesHome as $tecnologia): ?>
                    <a class="home-tecnologies__tag"
                       href="/tecnologies/<?= (int) $tecnologia['id'] ?>">
                        <span><?= htmlspecialchars((string) $tecnologia['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="home-tecnologies__count"><?= (int) $tecnologia['projectes_publics'] ?> projectes</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <a class="home-tecnologies__more" href="/tecnologies">
                <span>
                    <span class="home-tecnologies__more-label">Catàleg</span>
                    <strong class="home-tecnologies__more-title">Totes les tecnologies</strong>
                </span>
                <span class="home-tecnologies__more-bottom">
                    <span>Consulta el llistat complet i cerca per tecnologia.</span>
                    <span class="home-tecnologies__arrow" aria-hidden="true">→</span>
                </span>
            </a>
        </div>
    </div>
</section>
