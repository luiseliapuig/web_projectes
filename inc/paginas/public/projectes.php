<?php
declare(strict_types=1);

require_once __DIR__ . '/projectes-publics_funcions.php';

$sql = "
    WITH unitats AS (
        " . projectesPublicsUnitatsSql() . "
    ),
    projectes_base AS (
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
        WHERE " . projectesPublicsCondicioSql('p') . "
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
    ),
    destacats AS (
        SELECT
            u.*,
            p.*,
            row_number() OVER (
                PARTITION BY u.nivell, u.unitat_id
                ORDER BY p.nota_final DESC NULLS LAST, p.id_proyecto ASC
            ) AS posicio
        FROM unitats u
        INNER JOIN projectes_base p
            ON (
                u.nivell = 'tipus'
                AND p.tipo_proyecto_id = u.unitat_id
                AND p.categoria_proyecto_id = u.categoria_id
            ) OR (
                u.nivell = 'categoria'
                AND p.categoria_proyecto_id = u.unitat_id
                AND p.tipo_proyecto_id IS NULL
            )
    )
    SELECT *
    FROM destacats
    WHERE posicio <= 3
    ORDER BY
        bloc_ordre,
        categoria_ordre,
        unitat_ordre,
        unitat_nom,
        unitat_id,
        posicio
";

$stmt = $pdo->prepare($sql);
$stmt->execute(projectesPublicsParametres());
$resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$seccions = [];
foreach ($resultats as $projecte) {
    $projecte['alumnos_array'] = !empty($projecte['alumnos'])
        ? explode('||', (string) $projecte['alumnos'])
        : [];

    $rutaImatge = trim((string) ($projecte['ruta_imagen'] ?? ''));
    if ($rutaImatge === '') {
        $projecte['ruta_imagen_absoluta'] = '';
    } elseif (
        str_starts_with($rutaImatge, '/')
        || str_starts_with($rutaImatge, 'http://')
        || str_starts_with($rutaImatge, 'https://')
    ) {
        $projecte['ruta_imagen_absoluta'] = $rutaImatge;
    } else {
        $projecte['ruta_imagen_absoluta'] = '/' . ltrim($rutaImatge, '/');
    }

    $clau = $projecte['nivell'] . ':' . $projecte['unitat_id'];
    if (!isset($seccions[$clau])) {
        $seccions[$clau] = [
            'nivell' => (string) $projecte['nivell'],
            'id' => (int) $projecte['unitat_id'],
            'nom' => (string) $projecte['unitat_nom'],
            'projectes' => [],
        ];
    }
    $seccions[$clau]['projectes'][] = $projecte;
}
?>

<script>
window.PAGE_TITLE = 'Projectes';
</script>

<div class="container-fluid">
    <div class="projectes-header mb-5 mt-30">
        <h1 class="projectes-title mb-2">Projectes</h1>
        <p class="projectes-subtitle mb-0">
            Descobreix alguns dels projectes més destacats realitzats per l’alumnat.
        </p>
    </div>

    <?php foreach ($seccions as $seccio): ?>
        <section class="projectes-grup-section mb-5">
            <div class="projectes-grup-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="projectes-grup-title mb-0">
                    <?= htmlspecialchars($seccio['nom'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <a
                    class="link-secundari-puig"
                    href="/projectes/<?= $seccio['nivell'] === 'tipus' ? 'tipus' : 'categoria' ?>/<?= (int) $seccio['id'] ?>"
                >
                    Veure tots →
                </a>
            </div>

            <div class="row g-5">
                <?php foreach ($seccio['projectes'] as $projecte): ?>
                    <?php require __DIR__ . '/_projecte-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($seccions === []): ?>
        <div class="projectes-empty-state">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-2">No hi ha projectes disponibles</h2>
                    <p class="mb-0 text-muted">Encara no hi ha projectes publicats amb una classificació disponible.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
