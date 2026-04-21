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
$adjuntsArxiu        = array_filter($adjuntos, fn($a) => $a['tipo'] === 'arxiu');
$adjuntsEnllac       = array_filter($adjuntos, fn($a) => $a['tipo'] === 'enllac');
$adjuntsPlanificacio = array_filter($adjuntos, fn($a) => $a['tipo'] === 'planificacio');
$adjuntsGestio       = array_filter($adjuntos, fn($a) => $a['tipo'] === 'gestio');
?>

<script>
window.PAGE_TITLE = '<?= h($proyecto['nombre'] ?? '') ?> | <?= h($proyecto['ciclo'] ?? '') ?>';
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

                <?php 
                // solo pueden editar los alumnos propietarios y si está permitido
                if (esSuProyectoAlumno((int)$proyecto['id_proyecto']) && configuracion('permitir_editar')): ?>
                    <a href="/projecte/<?= (int)$proyecto['id_proyecto'] ?>/editar"
                       class="btn btn-puig px-3">
                        Editar fitxa
                    </a>
                <?php endif; ?>

            </div>





            <?php 


        


        // mostrar fecha de defensas a los alumnos interesados y a cualquier profesor si se deciden mostrar
        if (
            configuracion('mostrar_defensas') &&
            (esSuProyectoAlumno((int)$proyecto['id_proyecto']) || esProfesor())
        ) {
            include('bloque-defensa.php'); 
        }
    

?>

            <div class="card-style mb-30 ">

                <div class="row g-4">

                    <div class="col-lg-7 pb-10">

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

                         <?php
                        $hayDemo   = !empty($proyecto['url_proyecto']);
                        $hayGithub = !empty($proyecto['url_github']);
                        $hayMemoria = !empty($proyecto['ruta_memoria']);
                        ?>

                        <div class="mega-buttons mt-4 pb-30">

                            <!-- MEMORIA (siempre secundaria) -->
                            <?php if ($hayMemoria): ?>
                                <a href="<?= h($rutaMemoria) ?>" 
                                   target="_blank" 
                                   class="mega-btn mega-btn-outline">
                                    <span class="mega-icon icon-memoria">
                                   <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                                      <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/>
                                      <path d="M14 3v5h5"/>
                                      <path d="M9 13h6"/>
                                      <path d="M9 17h6"/>
                                    </svg>
                                    </span>
                                    <span class="mega-text">
                                        <strong>Memòria del projecte →</strong>
                                        <small>Consultar document complet</small>
                                    </span>
                                </a>
                            <?php endif; ?>


                            <!-- CASO 1: HAY DEMO -->
                            <?php if ($hayDemo): ?>

                                <a href="<?= h($proyecto['url_proyecto']) ?>" 
                                   target="_blank" 
                                   class="mega-btn mega-btn-solid">
                                    <span class="mega-icon">
                                    <svg viewBox="0 0 800 800" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                      <path d="M580.562,219.439c-12.721-12.723-29.637-19.728-47.623-19.728c-17.987,0-34.903,7.005-47.625,19.728c-12.72,12.72-19.725,29.634-19.725,47.621c0,17.99,7.005,34.904,19.725,47.625c12.722,12.721,29.633,19.723,47.618,19.726c0.007,0,0.007,0,0.007,0c17.986,0,34.902-7.005,47.623-19.726c12.721-12.723,19.726-29.636,19.726-47.625C600.286,249.073,593.281,232.16,580.562,219.439z M553.771,287.895c-5.566,5.568-12.96,8.636-20.834,8.636l0,0c-7.872-0.002-15.271-3.068-20.834-8.636c-5.566-5.562-8.633-12.96-8.633-20.834c0-7.868,3.065-15.269,8.633-20.834c5.563-5.565,12.967-8.63,20.834-8.63c7.868,0,15.268,3.063,20.834,8.63C565.263,257.715,565.263,276.407,553.771,287.895z" fill="currentColor"/>
                                      <path d="M62.282,627.218c-4.847,0-9.693-1.847-13.392-5.546c-7.398-7.397-7.398-19.395,0-26.79L158.42,485.35c7.398-7.397,19.392-7.397,26.79,0s7.398,19.395,0,26.792L75.676,621.672C71.978,625.371,67.131,627.218,62.282,627.218z" fill="currentColor"/>
                                      <path d="M86.774,732.172c-4.85,0-9.696-1.85-13.395-5.549c-7.398-7.397-7.398-19.389,0-26.786L187.545,585.67c7.398-7.398,19.392-7.398,26.787,0c7.398,7.398,7.398,19.393,0,26.79L100.168,726.623C96.47,730.322,91.62,732.172,86.774,732.172z" fill="currentColor"/>
                                      <path d="M191.725,756.661c-4.849,0-9.696-1.847-13.395-5.546c-7.398-7.397-7.398-19.393,0-26.789L287.863,614.79c7.396-7.394,19.392-7.396,26.787,0c7.398,7.397,7.398,19.395,0,26.793L205.12,751.115C201.421,754.813,196.574,756.661,191.725,756.661z" fill="currentColor"/>
                                      <path d="M751.113,48.891c-4.302-4.3-10.409-6.278-16.403-5.311c-2.202,0.357-54.705,8.98-126.25,36.316c-41.974,16.034-81.85,35.237-118.529,57.076c-45.039,26.814-85.356,57.721-119.899,91.871l-143.055,27.85c-3.693,0.718-7.086,2.524-9.753,5.177L87.618,391.06c-5.907,5.886-7.267,14.938-3.36,22.301c3.33,6.27,9.818,10.059,16.725,10.059c1.202,0,2.415-0.114,3.628-0.347l146.185-28.463c-9.516,18.672-18.419,38.055-26.683,58.144c-2.904,7.072-1.279,15.194,4.125,20.603l35.811,35.811l-33.27,33.27c-7.398,7.398-7.398,19.39,0,26.787c3.699,3.699,8.545,5.549,13.397,5.549c4.847,0,9.693-1.85,13.392-5.546l33.27-33.271l35.811,35.813c3.625,3.619,8.469,5.548,13.4,5.548c2.423,0,4.871-0.467,7.199-1.426c20.091-8.262,39.475-17.165,58.141-26.678l-28.455,146.186c-1.593,8.183,2.35,16.443,9.709,20.352c2.806,1.488,5.852,2.213,8.879,2.213c4.917,0,9.778-1.918,13.417-5.573l129.188-129.604c2.656-2.663,4.459-6.061,5.181-9.753l27.845-143.055c34.148-34.547,65.06-74.859,91.876-119.901c21.834-36.683,41.04-76.558,57.077-118.529c27.33-71.551,35.958-124.048,36.313-126.25C757.386,59.292,755.407,53.188,751.113,48.891z M158.393,374.001l81.489-81.224l87.674-17.069c-19.015,23.391-36.655,48.634-52.847,75.648L158.393,374.001z M507.219,560.121l-81.222,81.489l22.643-116.316c27.021-16.192,52.259-33.83,75.648-52.848L507.219,560.121z M684.359,178.936c-23.915,62.371-68.01,152.302-142.237,226.531c-34.171,34.168-73.96,64.54-118.89,90.838c-0.804,0.401-1.585,0.854-2.322,1.366c-24.049,13.943-49.566,26.728-76.476,38.302l-26.806-26.809l54.11-54.106c7.395-7.397,7.395-19.392,0-26.79c-7.398-7.397-19.392-7.396-26.79,0l-54.109,54.106l-26.806-26.809c11.578-26.913,24.361-52.433,38.308-76.488c0.508-0.732,0.951-1.5,1.35-2.295c26.298-44.938,56.672-84.732,90.849-118.909c74.225-74.225,164.156-118.319,226.527-142.235c37.897-14.537,70.522-23.601,92.09-28.797C707.959,108.412,698.894,141.038,684.359,178.936z" fill="currentColor"/>
                                    </svg>
                                    </span>
                                    <span class="mega-text">
                                        <strong>Veure demo del projecte →</strong>
                                        <small>Accedir a l'aplicació</small>
                                    </span>
                                </a>

                            <!-- CASO 2: NO HAY DEMO PERO SÍ GITHUB -->
                            <?php elseif ($hayGithub): ?>

                                <a href="<?= h($proyecto['url_github']) ?>" 
                                   target="_blank" 
                                   class="mega-btn mega-btn-solid">
                                    <span class="mega-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.44 9.8 8.2 11.39.6.11.82-.26.82-.58 
                                        0-.29-.01-1.05-.02-2.06-3.34.73-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76
                                        -1.09-.75.08-.73.08-.73 1.21.08 1.84 1.24 1.84 1.24 1.07 1.83 2.81 1.3 
                                        3.49.99.11-.78.42-1.3.76-1.6-2.67-.3-5.47-1.34-5.47-5.93 
                                        0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.17 0 0 1.01-.32 3.3 1.23 
                                        .96-.27 1.98-.4 3-.4s2.04.13 3 .4c2.28-1.55 3.29-1.23 
                                        3.29-1.23.66 1.65.24 2.87.12 3.17.77.84 1.24 1.91 
                                        1.24 3.22 0 4.6-2.8 5.62-5.48 5.92.43.37.82 1.1.82 
                                        2.22 0 1.6-.01 2.89-.01 3.29 0 .32.21.69.83.57C20.56 
                                        21.8 24 17.3 24 12c0-6.63-5.37-12-12-12z"/>
                                    </svg></span>
                                    <span class="mega-text">
                                        <strong>Veure codi del projecte →</strong>
                                        <small>Repositori GitHub</small>
                                    </span>
                                </a>

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

                        <?php if (!empty($adjuntsPlanificacio) || !empty($adjuntsGestio)): ?>
                        <div class="mb-50">
                            <h3 class="h3fichas">Planificació i gestió</h3>
                            <div class="inner-ficha">
                                <?php foreach ($adjuntsPlanificacio as $adj): ?>
                                    <a href="<?= h($adj['ruta']) ?>" target="_blank" class="enlace">
                                        <?= h($adj['nom']) ?>
                                    </a><br>
                                <?php endforeach; ?>

                                <?php if (!empty($adjuntsGestio)): ?>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <?php $gestioList = array_values($adjuntsGestio); ?>
                                    <?php foreach ($gestioList as $i => $adj): ?>
                                    <img src="<?= h($adj['ruta']) ?>"
                                         alt="<?= h($adj['nom']) ?>"
                                         title="<?= h($adj['nom']) ?>"
                                         class="img-thumbnail gestio-thumb"
                                         style="width:100px;height:70px;object-fit:cover;cursor:pointer;"
                                         data-index="<?= $i ?>"
                                         onclick="obrirModalGestio(<?= $i ?>)">
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Modal captures gestió -->
                        <?php if (!empty($adjuntsGestio)): ?>
                        <div class="modal fade" id="modalGestio" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content bg-dark border-0">
                                    <div class="modal-header border-0 pb-0">
                                        <span class="text-white small" id="modalGestioTitol"></span>
                                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-center p-3">
                                        <img id="modalGestioImg" src="" alt="" class="img-fluid rounded" style="max-height:70vh;">
                                    </div>
                                    <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                                        <button class="btn btn-outline-light btn-sm" onclick="navegarGestio(-1)">← Anterior</button>
                                        <button class="btn btn-outline-light btn-sm" onclick="navegarGestio(1)">Següent →</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                        const _gestioImatges = <?= json_encode(array_values(array_map(fn($a) => ['ruta' => $a['ruta'], 'nom' => $a['nom']], $adjuntsGestio))) ?>;
                        let _gestioActual = 0;
                        let _gestioModal  = null;

                        function obrirModalGestio(index) {
                            _gestioActual = index;
                            if (!_gestioModal) {
                                _gestioModal = new bootstrap.Modal(document.getElementById('modalGestio'));
                            }
                            actualitzarModalGestio();
                            _gestioModal.show();
                        }

                        function actualitzarModalGestio() {
                            const img = _gestioImatges[_gestioActual];
                            document.getElementById('modalGestioImg').src   = img.ruta;
                            document.getElementById('modalGestioImg').alt   = img.nom;
                            document.getElementById('modalGestioTitol').textContent =
                                img.nom + ' (' + (_gestioActual + 1) + ' / ' + _gestioImatges.length + ')';
                            // Amagar botons si només hi ha una imatge
                            const botons = document.querySelectorAll('#modalGestio .modal-footer button');
                            botons.forEach(b => b.style.display = _gestioImatges.length > 1 ? '' : 'none');
                        }

                        function navegarGestio(dir) {
                            _gestioActual = (_gestioActual + dir + _gestioImatges.length) % _gestioImatges.length;
                            actualitzarModalGestio();
                        }

                        // Navegació amb tecles
                        document.addEventListener('keydown', function(e) {
                            if (!document.getElementById('modalGestio').classList.contains('show')) return;
                            if (e.key === 'ArrowRight') navegarGestio(1);
                            if (e.key === 'ArrowLeft')  navegarGestio(-1);
                            if (e.key === 'Escape')     _gestioModal?.hide();
                        });
                        </script>
                        <?php endif; ?>

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
                            <h3 class="h3fichas">Enllaços del projecte</h3>
                            <div class="inner-ficha">
                                <?php if (!empty($proyecto['url_github'])): ?>
                                    <a href="<?= h((string)$proyecto['url_github']) ?>" target="_blank" class="enlace">
                                        Repositori Git
                                    </a><br>
                                <?php endif; ?>

                                <?php if (!empty($proyecto['url_proyecto'])): ?>
                                    <a href="<?= h((string)$proyecto['url_proyecto']) ?>" target="_blank" class="enlace">
                                        Demo del projecte
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




        // La autoevaluación la ven los alumnos si es su proyecto y los profesores
         if (esSuProyectoAlumno((int)$proyecto['id_proyecto']) || esProfesor()) { 
                include('bloque-autoevaluacion.php');
        }


        // las valoraciones las ven los alumnos si se permite y los profesores siempre
        if (
            (esSuProyectoAlumno((int)$proyecto['id_proyecto']) && configuracion('alumnos_ver_valoraciones'))
            || esProfesor()
        ) {
            include('bloque-tutor.php');
            include('bloque-tribunal.php');
        }

        // la nota final la ven los alumnos si se permite y los profesores siempre
        if (
            (esSuProyectoAlumno((int)$proyecto['id_proyecto']) && configuracion('nota_final'))
            || esProfesor()
        ) {
            include('bloque-nota-final.php');
            
        }

        include('eval_js.php');

        // el enlace de incidencias solo lo ven los profesores
        if(esProfesor()) {  echo '<p style="margin-top: 15px; font-size: 0.95rem; text-align:center">
                  Qualsevol observació o incidència durant els tribunals es pot registrar a <br>
                  <a href="https://docs.google.com/spreadsheets/d/1dy5COudIZpO7mBq6qlCjafnVfIden2zQb8pzIaLRsOM/edit?usp=sharing" target="_blank" style="color: #1E3A8A;">
                    <strong>Observacions / Incidències tribunals 2025-2026</strong>
                  </a>.
                </p>'; 
        }
         
        ?>






    </div>
</div>

<script>
// Fuera del DOMContentLoaded — función global
function initAutoGrow(container) {
    container = container || document;
    container.querySelectorAll('.js-autogrow').forEach(function (textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
        if (!textarea._autoGrowInit) {
            textarea._autoGrowInit = true;
            textarea.addEventListener('input', function () {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            });
        }
    });
}


</script>