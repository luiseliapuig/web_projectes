<!-- Presentació principal de la zona pública -->
<div class="col-12">
<section class="home-hero mt-40 mb-40">

    <div class="home-hero__content">

        <p class="home-hero__eyebrow">Mòdul de projecte</p>

        <h1 class="home-hero__title">
            Projectes del curs <?= htmlspecialchars($promocionTitulo, ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <p class="home-hero__subtitle">
            Aquest espai recull els projectes finals dels cicles d’informàtica del centre.
        </p>

        <p class="home-hero__text">
            Explora els projectes publicats i accedeix-hi per cicle.
        </p>

        <div class="home-hero__stats">
            <div class="home-hero__stat home-hero__stat--total">
                <span class="home-hero__stat-label">Total</span>
                <strong class="home-hero__stat-value"><?= (int) $total_projectes ?></strong>
                <span class="home-hero__stat-caption">
                    <?= (int) $total_projectes === 1 ? 'projecte' : 'projectes' ?>
                </span>
            </div>

            <?php foreach ($cicles as $cicle): ?>
                <a
                    class="home-hero__stat home-hero__stat--link"
                    href="/projectes/<?= rawurlencode((string) $cicle['ciclo']) ?>"
                >
                    <span class="home-hero__stat-label">
                        <?= htmlspecialchars((string) $cicle['ciclo'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <strong class="home-hero__stat-value"><?= (int) $cicle['total'] ?></strong>
                    <span class="home-hero__stat-caption">
                        <?= (int) $cicle['total'] === 1 ? 'projecte' : 'projectes' ?>
                    </span>
                    <span class="home-hero__stat-arrow" aria-hidden="true">→</span>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

</section>
</div>
