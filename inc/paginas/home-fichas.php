<?php

// 🔧 IDs manuales (EDITAS AQUÍ)
$ids_home = [14, 6, 11, 10];

// Seguridad básica
$ids_home = array_map('intval', $ids_home);
$ids_home = array_filter($ids_home);

if (empty($ids_home)) {
    return;
}

// Para usar en SQL
$placeholders = implode(',', array_fill(0, count($ids_home), '?'));

$sql = "
    SELECT
        p.id_proyecto,
        p.nombre,
        p.resumen,
        p.ruta_imagen,
        p.ciclo,
        p.grupo,
        p.curso_academico,
        string_agg(
            a.nombre || ' ' || a.apellidos,
            '||' ORDER BY a.apellidos, a.nombre
        ) AS alumnos
    FROM app.proyectos p
    LEFT JOIN app.rel_proyectos_alumnos rpa
        ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos a
        ON a.id_alumno = rpa.alumno_id
    WHERE p.id_proyecto IN ($placeholders)
    GROUP BY
        p.id_proyecto,
        p.nombre,
        p.resumen,
        p.ruta_imagen,
        p.ciclo,
        p.grupo,
        p.curso_academico
";

// IMPORTANTE: mantener orden manual
$sql .= " ORDER BY array_position(ARRAY[" . implode(',', $ids_home) . "], p.id_proyecto)";

$stmt = $pdo->prepare($sql);
$stmt->execute($ids_home);

$projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesado (igual que tu listado)
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

?>

<div class=" mb-5">

    <div class="mb-4">
        <h2 class="mb-1">Exemples</h2>
        <p class="text-muted mb-0">
            Una petita mostra de com es veuran el projectes.
        </p>
    </div>

    <div class="row g-4">

        <?php foreach ($projectes as $projecte): ?>

            <div class="col-12 col-md-6 col-xl-3">

                <a
                    href="/projecte/<?= (int)$projecte['id_proyecto'] ?>"
                    class="project-card-link"
                >

                    <article class="project-card">

                        <?php if (!empty($projecte['ruta_imagen_absoluta'])): ?>
                            <img
                                src="<?= htmlspecialchars($projecte['ruta_imagen_absoluta']) ?>"
                                alt="<?= htmlspecialchars($projecte['nombre']) ?>"
                                class="project-card-image"
                            >
                        <?php else: ?>
                            <div class="project-card-image project-card-image-placeholder">
                                Sense imatge
                            </div>
                        <?php endif; ?>

                        <div class="project-card-body">

                          

                            <h3 class="project-card-title">
                                <?= htmlspecialchars($projecte['nombre']) ?>
                            </h3>

                            <?php if (!empty($projecte['resumen'])): ?>
                                <p class="project-card-summary">
                                    <?= htmlspecialchars($projecte['resumen']) ?>
                                </p>
                            <?php endif; ?>

                        </div>

                    </article>

                </a>

            </div>

        <?php endforeach; ?>

    </div>

</div>