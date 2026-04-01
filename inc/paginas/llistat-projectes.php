<?php

$cicles_valids = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];

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
        p.ciclo,
        p.grupo,
        string_agg(
            a.nombre || ' ' || a.apellidos,
            '||' ORDER BY a.apellidos, a.nombre
        ) AS alumnos
    FROM app.proyectos p
    LEFT JOIN app.rel_proyectos_alumnos rpa
        ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos a
        ON a.id_alumno = rpa.alumno_id
    WHERE p.ciclo = :ciclo
    GROUP BY
        p.id_proyecto,
        p.uuid,
        p.nombre,
        p.resumen,
        p.ruta_imagen,
        p.curso_academico,
        p.ciclo,
        p.grupo
    ORDER BY
        p.grupo ASC,
        p.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':ciclo' => $cicle
]);

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

<div class="container-fluid ">

    <div class="projectes-header mb-4 mt-30">
        <h1 class="projectes-title mb-2">Projectes</h1>
        <p class="projectes-subtitle mb-0">
            Catàleg de projectes del cicle <strong><?= htmlspecialchars($cicle) ?></strong>.
        </p>
    </div>

    <div class="projectes-filter mb-4">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($cicles_valids as $item_cicle): ?>
                <a
                    href="/projectes/<?= urlencode($item_cicle) ?>"
                    class="projectes-filter-pill <?= $item_cicle === $cicle ? 'active' : '' ?>"
                >
                    <?= htmlspecialchars($item_cicle) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($projectes_per_grup)): ?>

        <?php foreach ($projectes_per_grup as $grup => $projectes_grup): ?>
            <section class="projectes-grup-section mb-5">

                <div class="projectes-grup-header mb-3">
                    <h3 class="projectes-grup-title mb-0">Grup <?= htmlspecialchars($grup) ?></h3>
                </div>

                <div class="row g-5">
                    <?php foreach ($projectes_grup as $projecte): ?>

                        <div class="col-12 col-md-6 col-xl-4">

                            <a
                                href="/projecte/<?= (int)$projecte['id_proyecto'] ?>"
                                class="project-card-link"
                            >

                                <article class="project-card">

                                    <?php if (!empty($projecte['ruta_imagen_absoluta'])): ?>
                                        <img
                                            src="<?= htmlspecialchars((string)$projecte['ruta_imagen_absoluta']) ?>"
                                            alt="<?= htmlspecialchars((string)$projecte['nombre']) ?>"
                                            class="project-card-image"
                                        >
                                    <?php else: ?>
                                        <div class="project-card-image project-card-image-placeholder">
                                            Sense imatge
                                        </div>
                                    <?php endif; ?>

                                    <div class="project-card-body">

                                        <div class="project-card-meta mb-2">
                                            <span><?= htmlspecialchars((string)$projecte['ciclo']) ?></span>
                                            <span class="project-meta-separator">·</span>
                                            <span><?= htmlspecialchars((string)$projecte['grupo']) ?></span>
                                            <span class="project-meta-separator">·</span>
                                            <span><?= htmlspecialchars((string)$projecte['curso_academico']) ?></span>
                                        </div>

                                        <h2 class="project-card-title">
                                            <?= htmlspecialchars((string)$projecte['nombre']) ?>
                                        </h2>

                                        <?php if (!empty($projecte['resumen'])): ?>
                                            <p class="project-card-summary">
                                                <?= htmlspecialchars((string)$projecte['resumen']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="project-card-alumnes">

                                            <?php if (!empty($projecte['alumnos_array'])): ?>
                                                <div class="project-card-students">
                                                    <?php foreach ($projecte['alumnos_array'] as $alumne): ?>
                                                        <span class="project-student-badge">
                                                            <?= htmlspecialchars(trim((string)$alumne)) ?>
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