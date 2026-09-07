<?php
declare(strict_types=1);

// Selecció editorial aleatòria entre les millors notes de tipus diferents.
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
    classificats AS (
        SELECT
            u.nivell,
            u.unitat_id,
            p.*,
            row_number() OVER (
                PARTITION BY u.nivell, u.unitat_id
                ORDER BY p.nota_final DESC NULLS LAST, p.id_proyecto ASC
            ) AS posicio_qualitat
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
    ),
    top_deu AS (
        SELECT *
        FROM classificats
        WHERE posicio_qualitat <= 10
    ),
    unitats_escollides AS (
        SELECT nivell, unitat_id
        FROM top_deu
        GROUP BY nivell, unitat_id
        ORDER BY random()
        LIMIT 3
    )
    SELECT projecte.*
    FROM unitats_escollides unitat
    INNER JOIN LATERAL (
        SELECT top_deu.*
        FROM top_deu
        WHERE top_deu.nivell = unitat.nivell
          AND top_deu.unitat_id = unitat.unitat_id
        ORDER BY random()
        LIMIT 1
    ) projecte ON true
    ORDER BY random()
";

$stmt = $pdo->prepare($sql);
$stmt->execute(projectesPublicsParametres());
$projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($projectes as &$projecte) {
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
}
unset($projecte);
?>

<?php if ($projectes !== []): ?>
    <section class="col-12 mb-5">
        <div class="mb-4">
            <h2 class="mb-1">Projectes destacats</h2>
            <p class="text-muted mb-0">Una selecció de projectes del curs.</p>
        </div>

        <div class="row g-5">
            <?php foreach ($projectes as $projecte): ?>
                <?php require __DIR__ . '/_projecte-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
