<?php
declare(strict_types=1);

require_once __DIR__ . '/projectes-publics_funcions.php';

$condicioPublica = projectesPublicsCondicioSql('p');
$stmtTaxonomiaHome = $pdo->prepare("
    WITH projectes_publics AS (
        SELECT
            p.id_proyecto,
            p.categoria_proyecto_id,
            p.tipo_proyecto_id
        FROM app.proyectos p
        WHERE {$condicioPublica}
    ),
    categories AS (
        SELECT
            cp.id_categoria_proyecto,
            cp.nombre,
            cp.orden,
            COUNT(DISTINCT pp.id_proyecto) AS total_projectes
        FROM app.proyecto_categorias cp
        LEFT JOIN projectes_publics pp
            ON pp.categoria_proyecto_id = cp.id_categoria_proyecto
        WHERE cp.activo = true
        GROUP BY cp.id_categoria_proyecto, cp.nombre, cp.orden
    ),
    tipus AS (
        SELECT
            tp.id_tipo_proyecto,
            tp.categoria_proyecto_id,
            tp.nombre,
            tp.orden,
            COUNT(DISTINCT pp.id_proyecto) AS total_projectes
        FROM app.proyecto_tipos tp
        LEFT JOIN projectes_publics pp
            ON pp.tipo_proyecto_id = tp.id_tipo_proyecto
        WHERE tp.activo = true
        GROUP BY tp.id_tipo_proyecto, tp.categoria_proyecto_id, tp.nombre, tp.orden
    )
    SELECT
        c.id_categoria_proyecto,
        c.nombre,
        c.orden,
        c.total_projectes,
        COALESCE(
            jsonb_agg(
                jsonb_build_object(
                    'id', tp.id_tipo_proyecto,
                    'nombre', tp.nombre,
                    'total_projectes', tp.total_projectes
                )
                ORDER BY tp.orden, tp.nombre, tp.id_tipo_proyecto
            ) FILTER (WHERE tp.id_tipo_proyecto IS NOT NULL),
            '[]'::jsonb
        ) AS tipus
    FROM categories c
    LEFT JOIN tipus tp
        ON tp.categoria_proyecto_id = c.id_categoria_proyecto
    GROUP BY c.id_categoria_proyecto, c.nombre, c.orden, c.total_projectes
    ORDER BY c.orden, c.nombre, c.id_categoria_proyecto
");
$stmtTaxonomiaHome->execute(projectesPublicsParametres());

$categoriaPrincipal = null;
$categoriesTerminals = [];

foreach ($stmtTaxonomiaHome->fetchAll(PDO::FETCH_ASSOC) as $categoria) {
    $categoria['tipus'] = json_decode((string) $categoria['tipus'], true, 512, JSON_THROW_ON_ERROR);

    if ($categoriaPrincipal === null && $categoria['tipus'] !== []) {
        $categoriaPrincipal = $categoria;
        continue;
    }

    if ($categoria['tipus'] === []) {
        $categoriesTerminals[] = $categoria;
    }
}
?>

<?php if ($categoriaPrincipal !== null || $categoriesTerminals !== []): ?>
<section class="col-12 mb-5">
    <div class="home-tipus-panel">
        <header class="home-tipus-panel__header">
            <p class="home-tipus-panel__eyebrow">Explora</p>
            <h2 class="home-tipus-panel__title">Tipus de projecte</h2>
            <p class="home-tipus-panel__intro">Descobreix els projectes segons la seva naturalesa.</p>
        </header>

        <div class="home-tipus-catalog">
            <?php if ($categoriaPrincipal !== null): ?>
                <a class="home-tipus-category home-tipus-category--principal"
                   href="/projectes/categoria/<?= (int) $categoriaPrincipal['id_categoria_proyecto'] ?>">
                    <span>
                        <span class="home-tipus-category__label">Categoria</span>
                        <span class="home-tipus-category__title"><?= htmlspecialchars((string) $categoriaPrincipal['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="home-tipus-category__copy">Projectes centrats en la creació d’un producte, servei o solució funcional.</span>
                    </span>
                    <span class="home-tipus-category__meta">
                        <span class="home-tipus-category__count">
                            <strong><?= (int) $categoriaPrincipal['total_projectes'] ?></strong>
                            <span>projectes</span>
                        </span>
                        <span class="home-tipus-arrow" aria-hidden="true">→</span>
                    </span>
                </a>

                <div class="home-tipus-types">
                    <div class="home-tipus-types__caption">Tipus de desenvolupament</div>
                    <?php foreach ($categoriaPrincipal['tipus'] as $tipus): ?>
                        <a class="home-tipus-type"
                           href="/projectes/tipus/<?= (int) $tipus['id'] ?>">
                            <span class="home-tipus-type__name"><?= htmlspecialchars((string) $tipus['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="home-tipus-type__count"><?= (int) $tipus['total_projectes'] ?> projectes</span>
                            <span class="home-tipus-arrow" aria-hidden="true">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($categoriesTerminals !== []): ?>
                <div class="home-tipus-categories-terminals">
                    <?php foreach ($categoriesTerminals as $categoria): ?>
                        <a class="home-tipus-category"
                           href="/projectes/categoria/<?= (int) $categoria['id_categoria_proyecto'] ?>">
                            <span>
                                <span class="home-tipus-category__label">Categoria</span>
                                <span class="home-tipus-category__title"><?= htmlspecialchars((string) $categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="home-tipus-category__meta">
                                <span class="home-tipus-category__count">
                                    <strong><?= (int) $categoria['total_projectes'] ?></strong>
                                    <span>projectes</span>
                                </span>
                                <span class="home-tipus-arrow" aria-hidden="true">→</span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
