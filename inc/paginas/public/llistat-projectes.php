<?php
// Catálogo público de proyectos del ciclo solicitado.
require_once __DIR__ . '/projectes-publics_funcions.php';

$cicles_valids = projectesPublicsCicles();

$cicle = $_GET['cicle'] ?? 'DAM';
$cicle = in_array($cicle, $cicles_valids, true) ? $cicle : 'DAM';

$sql = "
    SELECT
        p.id_proyecto,
        p.uuid,
        p.nombre,
        p.resumen,
        p.ruta_imagen,
        p.curso_academico,
        p.categoria_proyecto_id,
        p.tipo_proyecto_id,
        cp.nombre AS categoria_proyecto_nombre,
        tp.nombre AS tipo_proyecto_nombre,
        p.nota_final,
        c.abr AS ciclo,
        g.grupo,
        string_agg(
            a.nombre || ' ' || a.apellidos,
            '||' ORDER BY a.apellidos, a.nombre
        ) AS alumnos
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    LEFT JOIN app.proyecto_categorias cp
        ON cp.id_categoria_proyecto = p.categoria_proyecto_id
    LEFT JOIN app.proyecto_tipos tp
        ON tp.id_tipo_proyecto = p.tipo_proyecto_id
       AND tp.categoria_proyecto_id = p.categoria_proyecto_id
    LEFT JOIN app.rel_proyectos_alumnos rpa
        ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos a
        ON a.id_alumno = rpa.alumno_id
    WHERE c.abr = :ciclo
      AND " . projectesPublicsCondicioSql('p') . "
    GROUP BY
        p.id_proyecto,
        p.uuid,
        p.nombre,
        p.resumen,
        p.ruta_imagen,
        p.curso_academico,
        p.categoria_proyecto_id,
        p.tipo_proyecto_id,
        cp.nombre,
        tp.nombre,
        p.nota_final,
        c.abr,
        g.grupo
    ORDER BY
        p.nota_final DESC NULLS LAST,
        g.grupo ASC,
        p.nombre ASC,
        p.id_proyecto ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge(
    [':ciclo' => $cicle],
    projectesPublicsParametres()
));

$projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($projectes as &$projecte) {
    $projecte['alumnos_array'] = [];

    if (!empty($projecte['alumnos'])) {
        $projecte['alumnos_array'] = explode('||', $projecte['alumnos']);
    }

    $rutaImatge = trim((string)($projecte['ruta_imagen'] ?? ''));

    if ($rutaImatge !== '') {
        if (
            str_starts_with($rutaImatge, '/')
            || str_starts_with($rutaImatge, 'http://')
            || str_starts_with($rutaImatge, 'https://')
        ) {
            $projecte['ruta_imagen_absoluta'] = $rutaImatge;
        } else {
            $projecte['ruta_imagen_absoluta'] = '/' . ltrim($rutaImatge, '/');
        }
    } else {
        $projecte['ruta_imagen_absoluta'] = '';
    }
}
unset($projecte);

$projectes_per_grup = [];

foreach ($projectes as $projecte) {
    $grup = trim((string)($projecte['grupo'] ?? ''));

    if ($grup === '') {
        $grup = 'Sense grup';
    }

    $projectes_per_grup[$grup][] = $projecte;
}

if (!empty($projectes_per_grup)) {
    uksort($projectes_per_grup, 'strnatcasecmp');
}
?>

<script>
window.PAGE_TITLE = '<?= htmlspecialchars($cicle) ?>';
</script>

<div class="container-fluid ">

    <div class="projectes-header mb-4 mt-30">
        <h1 class="projectes-title mb-2">Projectes</h1>
        <p class="projectes-subtitle mb-0">
            Catàleg de projectes del cicle <strong><?= htmlspecialchars($cicle) ?></strong>.
        </p>
    </div>

    <?php $cicleActiu = $cicle; ?>
    <?php require __DIR__ . '/_projectes-cicles.php'; ?>

    <?php if (!empty($projectes_per_grup)): ?>

        <?php foreach ($projectes_per_grup as $grup => $projectes_grup): ?>
            <section class="projectes-grup-section mb-5">

                <div class="projectes-grup-header mb-3">
                    <h3 class="projectes-grup-title mb-0">Grup <?= htmlspecialchars($grup) ?></h3>
                </div>

                <div class="row g-5">
                    <?php foreach ($projectes_grup as $projecte): ?>
                        <?php require __DIR__ . '/_projecte-card.php'; ?>
                    <?php endforeach; ?>
                </div>

            </section>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="projectes-empty-state">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="h5 mb-2">No hi ha projectes disponibles</h3>
                    <p class="mb-0 text-muted">
                        Encara no hi ha projectes publicats per al cicle <?= htmlspecialchars($cicle) ?>.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

