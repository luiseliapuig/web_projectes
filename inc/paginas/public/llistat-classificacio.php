<?php
declare(strict_types=1);

require_once __DIR__ . '/projectes-publics_funcions.php';

$nivell = isset($_GET['classificacio']) && is_string($_GET['classificacio'])
    ? $_GET['classificacio']
    : '';
$classificacioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$classificacio = null;

if ($classificacioId && $nivell === 'categoria') {
    $stmt = $pdo->prepare('
        SELECT id_categoria_proyecto AS id, nombre
        FROM app.proyecto_categorias
        WHERE id_categoria_proyecto = :id
    ');
    $stmt->execute([':id' => $classificacioId]);
    $classificacio = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif ($classificacioId && $nivell === 'tipus') {
    $stmt = $pdo->prepare('
        SELECT
            tp.id_tipo_proyecto AS id,
            tp.nombre,
            cp.id_categoria_proyecto AS categoria_id,
            cp.nombre AS categoria_nombre
        FROM app.proyecto_tipos tp
        INNER JOIN app.proyecto_categorias cp
            ON cp.id_categoria_proyecto = tp.categoria_proyecto_id
        WHERE tp.id_tipo_proyecto = :id
    ');
    $stmt->execute([':id' => $classificacioId]);
    $classificacio = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$projectes = [];
if ($classificacio !== null) {
    $columnaFiltre = $nivell === 'categoria' ? 'p.categoria_proyecto_id' : 'p.tipo_proyecto_id';
    $condicioJerarquia = $nivell === 'tipus'
        ? 'AND p.categoria_proyecto_id = :categoria_id'
        : '';
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
        WHERE $columnaFiltre = :classificacio_id
          $condicioJerarquia
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
            c.abr,
            g.grupo,
            p.nombre,
            p.id_proyecto
    ";

    $stmt = $pdo->prepare($sql);
    $parametres = [':classificacio_id' => (int) $classificacio['id']];
    if ($nivell === 'tipus') {
        $parametres[':categoria_id'] = (int) $classificacio['categoria_id'];
    }
    $stmt->execute(array_merge($parametres, projectesPublicsParametres()));
    $projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

$nomClassificacio = (string) ($classificacio['nombre'] ?? 'Classificació no trobada');
$titolNivell = $nivell === 'tipus' ? 'Tipus de projecte' : 'Categoria de projecte';
?>

<script>
window.PAGE_TITLE = <?= json_encode($nomClassificacio, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<div class="container-fluid">
    <div class="projectes-header mb-4 mt-30">
        <h1 class="projectes-title mb-2">Projectes</h1>
        <?php if ($classificacio !== null): ?>
            <p class="projectes-subtitle mb-0"><?= htmlspecialchars($titolNivell, ENT_QUOTES, 'UTF-8') ?>.</p>
        <?php else: ?>
            <p class="projectes-subtitle mb-0">La classificació sol·licitada no existeix.</p>
        <?php endif; ?>
    </div>

    <?php if ($classificacio !== null): ?>
        <section class="projectes-grup-section mb-5">
            <div class="projectes-grup-header mb-3">
                <div>
                    <h2 class="projectes-grup-title mb-0"><?= htmlspecialchars($nomClassificacio, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($nivell === 'tipus'): ?>
                        <p class="projectes-subtitle mt-3 mb-0">
                            <a class="link-secundari-puig" href="/projectes/categoria/<?= (int) $classificacio['categoria_id'] ?>">
                                <?= htmlspecialchars((string) $classificacio['categoria_nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($projectes !== []): ?>
                <div class="row g-5">
                    <?php foreach ($projectes as $projecte): ?>
                        <?php require __DIR__ . '/_projecte-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="projectes-empty-state mt-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 mb-2">No hi ha projectes disponibles</h3>
                            <p class="mb-0 text-muted">Encara no hi ha projectes publicats amb aquesta classificació.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="projectes-empty-state">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-2">Classificació no trobada</h2>
                    <p class="mb-0 text-muted">Revisa l’enllaç i torna-ho a provar.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
