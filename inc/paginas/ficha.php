<?php

$idProyecto = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idProyecto <= 0) {
    echo '<div class="alert alert-danger">No s\'ha indicat cap projecte vàlid.</div>';
    return;
}

if (!function_exists('h')) {
    function h(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
function absolutizePath(?string $ruta): string
{
    $ruta = trim((string)$ruta);

    if ($ruta === '') {
        return '';
    }

    if (
        str_starts_with($ruta, '/')
        || str_starts_with($ruta, 'http://')
        || str_starts_with($ruta, 'https://')
    ) {
        return $ruta;
    }

    return '/' . ltrim($ruta, '/');
}

try {
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            pr.nombre AS tutor_nombre,
            pr.apellidos AS tutor_apellidos
        FROM app.proyectos p
        LEFT JOIN app.profesores pr
            ON pr.id_profesor = p.tutor_id
        WHERE p.id_proyecto = :id_proyecto
        LIMIT 1
    ");
    $stmt->execute(['id_proyecto' => $idProyecto]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">S\'ha produït un error en carregar la fitxa del projecte.</div>';
    return;
}

if (!$proyecto) {
    echo '<div class="alert alert-warning">No s\'ha trobat cap projecte amb aquest identificador.</div>';
    return;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            a.nombre,
            a.apellidos
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a
            ON a.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id = :id_proyecto
        ORDER BY a.apellidos, a.nombre
    ");
    $stmt->execute(['id_proyecto' => $proyecto['id_proyecto']]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alumnos = [];
}

$nombreTutor = trim(((string)($proyecto['tutor_nombre'] ?? '')) . ' ' . ((string)($proyecto['tutor_apellidos'] ?? '')));

$tags = [];
if (!empty($proyecto['stack'])) {
    $tags = array_filter(array_map('trim', explode(',', (string)$proyecto['stack'])));
}

$rutaImagen = absolutizePath($proyecto['ruta_imagen'] ?? '');
$rutaFuncional = absolutizePath($proyecto['ruta_funcional'] ?? '');
$rutaMemoria = absolutizePath($proyecto['ruta_memoria'] ?? '');

// ── Adjunts extra ─────────────────────────────────────────────────
try {
    $stmtAdj = $pdo->prepare("
        SELECT id, tipo, nom, ruta
        FROM app.proyecto_adjuntos
        WHERE proyecto_id = ?
        ORDER BY created_at ASC
    ");
    $stmtAdj->execute([$idProyecto]);
    $adjuntos = $stmtAdj->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $adjuntos = [];
}
$adjuntsArxiu  = array_filter($adjuntos, fn($a) => $a['tipo'] === 'arxiu');
$adjuntsEnllac = array_filter($adjuntos, fn($a) => $a['tipo'] === 'enllaç');
?>

<script>
window.PAGE_TITLE = 'Ficha del projecte';
</script>



<div class="container-fluid ">
    <div class="row">
        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center breadcrum ">

                <a href="/projectes/<?= h($proyecto['ciclo'] ?? '-') ?>"
                   class="text-decoration-none small text-muted d-inline-flex align-items-center gap-2">
                    <span>🡰</span>
                    <span>Tornar als projectes de <?= h($proyecto['ciclo'] ?? '-') ?></span>
                </a>

                <?php if (esSuProyectoAlumno((int)$proyecto['id_proyecto']) && configuracion('permitir_editar')): ?>
                    <a href="/projecte/<?= (int)$proyecto['id_proyecto'] ?>/editar"
                       class="btn btn-puig px-3">
                        Editar fitxa
                    </a>
                <?php endif; ?>

            </div>





            <?php 


        


        // si se permiten ver las valoraciones, se muestran las de su proyecto
        if (esSuProyectoAlumno((int)$proyecto['id_proyecto']) && configuracion('mostrar_defensas') || esSuProyectoProfesor((int)$proyecto['id_proyecto']) && configuracion('mostrar_defensas')) {

            include('bloque-defensa.php'); 
        }
    

?>

            <div class="card-style mb-30 ">

                <div class="row g-4">

                    <div class="col-lg-7">

                        <div class="mb-4">
                            <?php if ($rutaImagen !== ''): ?>
                                <img
                                    src="<?= h($rutaImagen) ?>"
                                    alt="<?= h($proyecto['nombre'] ?? 'Proyecto') ?>"
                                    class="img-fluid rounded"
                                    style="width: 100%; max-height: 460px; object-fit: cover;"
                                >
                            <?php else: ?>
                                <div
                                    class="rounded d-flex align-items-center justify-content-center text-muted"
                                    style="width: 100%; height: 360px; background: #f3f4f6; border: 1px solid #e5e7eb;"
                                >
                                    Sense imatge
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <h1 style="font-size: 2rem; line-height: 1.2; margin-bottom: 0.75rem;">
                                <?= h($proyecto['nombre'] ?? '') ?>
                            </h1>

                            <?php if (!empty($proyecto['resumen'])): ?>
                                <h3 style="font-size: 1.2rem; line-height: 1.45; font-weight: 400; color: #6b7280; margin-bottom: 1.25rem;">
                                    <?= nl2br(h($proyecto['resumen'])) ?>
                                </h3>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-3">Descripció</h5>

                            <?php if (!empty($proyecto['descripcion'])): ?>
                                <div style="font-size: 1rem; line-height: 1.75; color: #374151;">
                                    <?= nl2br(h($proyecto['descripcion'])) ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Aquest projecte encara no té descripció.</p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <div class="col-lg-5">




                 



                    <!-- información general -->
                        <div class="puig-panel puig-panel-highlight mb-50">
                            <div class="puig-panel-header">
                                <h3 class="puig-panel-title c-puig">Informació general</h3>
                            </div>

                            <div class="puig-panel-body">
                                <div class="puig-panel-content">
                                    <div class="mb-2">
                                        <strong class="info-label">Autoria:</strong><br>
                                        <div class="alumnes">
                                            <?php if ($alumnos): ?>
                                                <?php foreach ($alumnos as $a): ?>
                                                    <?= h($a['nombre'] . ' ' . $a['apellidos']) ?><br>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sense alumnes assignats</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="container-fluid px-4 ml-0 mr-0">

                                        <div class="row g-4">

                                            <div class="col-auto">
                                                <div class="mb-2">
                                                    <strong class="info-label">Cicle:</strong><br>
                                                    <?= h($proyecto['ciclo'] ?? '-') ?>
                                                </div>
                                            </div>

                                            <div class="col-auto">
                                                <div class="mb-2">
                                                    <strong class="info-label">Grup:</strong><br>
                                                    <?= h($proyecto['grupo'] ?? '-') ?>
                                                </div>
                                            </div>

                                            <div class="col-auto">
                                                <div class="mb-2">
                                                    <strong class="info-label">Curs:</strong><br>
                                                    <?= h($proyecto['curso_academico'] ?? '-') ?>
                                                </div>
                                            </div>

                                            <div class="col-auto">
                                                <div class="mb-0">
                                                    <strong class="info-label">Tutor:</strong><br>
                                                    <?= $nombreTutor !== '' ? h($nombreTutor) : '-' ?>
                                                </div>
                                            </div>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="mb-50">
                            <h3 class="h3fichas">Tecnologies</h3>
                            <div class="inner-ficha">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($tags as $tag): ?>
                                        <span
                                            style="
                                            display:inline-block;
                                            padding: 0.45rem 0.8rem;
                                            border-radius: 999px;
                                            background: #e9e9e9;
                                            color: #555;
                                            font-size: .92rem;
                                            font-weight: 500;
                                            "
                                        >
                                            <?= h($tag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-50">
                            <h3 class="h3fichas">Documentació</h3>
                            <div class="inner-ficha">

                                <?php if ($rutaMemoria !== ''): ?>
                                  <div class="mb-10">
                                    <a href="<?= h($rutaMemoria) ?>" target="_blank" class="doc-item doc-item-primary enlace ">
                                        MEMÒRIA DEL PROJECTE
                                    </a>
                                </div>
                                <?php endif; ?>

                                <?php if ($rutaFuncional !== ''): ?>
                                    <a href="<?= h($rutaFuncional) ?>" target="_blank" class="enlace">
                                        Document funcional
                                    </a><br>
                                <?php endif; ?>

                                <?php foreach ($adjuntsArxiu as $adj): ?>
                                    <a href="<?= h($adj['ruta']) ?>" target="_blank" class="enlace">
                                        <?= h($adj['nom']) ?>
                                    </a><br>
                                <?php endforeach; ?>

                                <?php if ($rutaFuncional === '' && $rutaMemoria === '' && empty($adjuntsArxiu)): ?>
                                    <p class="text-muted mb-0">Encara no hi ha documentació disponible.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-30">
                            <h3 class="h3fichas">Enllaços</h3>
                            <div class="inner-ficha">
                                <?php if (!empty($proyecto['url_github'])): ?>
                                    <a href="<?= h((string)$proyecto['url_github']) ?>" target="_blank" class="enlace">
                                        Repositori Git
                                    </a><br>
                                <?php endif; ?>

                                <?php if (!empty($proyecto['url_proyecto'])): ?>
                                    <a href="<?= h((string)$proyecto['url_proyecto']) ?>" target="_blank" class="enlace">
                                        Web / demo del projecte
                                    </a><br>
                                <?php endif; ?>

                                <?php foreach ($adjuntsEnllac as $adj): ?>
                                    <a href="<?= h($adj['ruta']) ?>" target="_blank" class="enlace">
                                        <?= h($adj['nom']) ?>
                                    </a><br>
                                <?php endforeach; ?>

                                <?php if (empty($proyecto['url_github']) && empty($proyecto['url_proyecto']) && empty($adjuntsEnllac)): ?>
                                    <p class="text-muted mb-0">Aquest projecte encara no té enllaços publicats.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>

        <?php


// si es su proyecto, ven este bloque
 if (esSuProyectoProfesor((int)$proyecto['id_proyecto'])): 

        include('bloque-autoevaluacion.php');


        // si se permiten ver las valoraciones, se muestran las de su proyecto
        if (configuracion('alumnos_ver_valoraciones')) {

                include('bloque-tutor.php');

                include('bloque-tribunal.php');

                include('bloque-nota-final.php');
                include('eval_js.php');

        }

endif;

         

        ?>






    </div>
</div>