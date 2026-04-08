<?php

// 🔥 ORDEN QUE QUIERES (editable)
$orden_ciclos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];

$sql = "
    SELECT
        p.ciclo,
        COUNT(*) AS total
    FROM app.proyectos p
    WHERE p.estado = 'activo'
      AND p.curso_academico = '2025-26'
      AND p.ciclo IS NOT NULL
      AND TRIM(p.ciclo) <> ''
    GROUP BY p.ciclo
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Convertimos a mapa: ['DAM' => 21, ...]
$cicles_map = [];

foreach ($rows as $row) {
    $ciclo = trim((string)$row['ciclo']);
    $cicles_map[$ciclo] = (int)$row['total'];
}

// 🔹 Reconstruimos en tu orden
$cicles = [];

foreach ($orden_ciclos as $ciclo) {
    if (isset($cicles_map[$ciclo])) {
        $cicles[] = [
            'ciclo' => $ciclo,
            'total' => $cicles_map[$ciclo]
        ];
    }
}

// 🔹 (opcional PRO) añadir ciclos no definidos al final
foreach ($cicles_map as $ciclo => $total) {
    if (!in_array($ciclo, $orden_ciclos, true)) {
        $cicles[] = [
            'ciclo' => $ciclo,
            'total' => $total
        ];
    }
}

// 🔹 Total
$total_projectes = array_sum(array_column($cicles, 'total'));

if (!empty($cicles)):
?>
<div class="col-12">
<section class="home-cicles-panel mb-5">
    <div class="home-cicles-panel__header">
        <div>
            <p class="home-cicles-panel__eyebrow">Promoció 2025–26</p>
            <h2 class="home-cicles-panel__title">Projectes actius per cicle</h2>
            <p class="home-cicles-panel__subtitle">
                Visió general ràpida dels projectes actualment actius en cada cicle.
            </p>
        </div>

        <div class="home-cicles-panel__total">
            <span class="home-cicles-panel__total-label">Total</span>
            <span class="home-cicles-panel__total-value"><?= $total_projectes ?></span>
        </div>
    </div>

    <div
        class="home-cicles-grid"
        style="grid-template-columns: repeat(<?= count($cicles) ?>, minmax(0, 1fr));"
    >
        <?php foreach ($cicles as $cicle): ?>
            <a
                href="/projectes/<?= urlencode($cicle['ciclo']) ?>"
                class="home-cicle-card"
            >
                <div class="home-cicle-card__top">
                    <span class="home-cicle-card__code">
                        <?= htmlspecialchars($cicle['ciclo']) ?>
                    </span>
                    <span class="home-cicle-card__icon">↗</span>
                </div>

                <div class="home-cicle-card__count">
                    <?= (int)$cicle['total'] ?>
                </div>

                <div class="home-cicle-card__label">
                    <?= (int)$cicle['total'] === 1 ? 'projecte actiu' : 'projectes actius' ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
</div>

<?php endif; ?>