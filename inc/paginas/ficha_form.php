<?php
declare(strict_types=1);

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
            p.id_proyecto,
            p.uuid,
            p.nombre,
            p.resumen,
            p.descripcion,
            p.stack,
            p.url_github,
            p.url_proyecto,
            p.ruta_imagen,
            p.ruta_memoria,
            p.ruta_funcional,
            p.ruta_ficha_entrega,
            p.ciclo,
            p.grupo,
            p.curso_academico,
            pr.nombre AS tutor_nombre,
            pr.apellidos AS tutor_apellidos
        FROM proyectos p
        LEFT JOIN profesores pr ON pr.id_profesor = p.tutor_id
        WHERE p.id_proyecto = :id_proyecto
        LIMIT 1
    ");
    $stmt->execute(['id_proyecto' => $idProyecto]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Se ha producido un error al cargar la ficha del proyecto.</div>';
    return;
}

if (!$proyecto) {
    echo '<div class="alert alert-warning">No se ha encontrado ningún proyecto para ese identificador.</div>';
    return;
}

$stmt = $pdo->prepare("
    SELECT a.nombre, a.apellidos
    FROM rel_proyectos_alumnos r
    JOIN alumnos a ON a.id_alumno = r.alumno_id
    WHERE r.proyecto_id = :id
");
$stmt->execute(['id' => $proyecto['id_proyecto']]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nombreTutor = trim(((string)($proyecto['tutor_nombre'] ?? '')) . ' ' . ((string)($proyecto['tutor_apellidos'] ?? '')));

$rutaImagen = absolutizePath($proyecto['ruta_imagen'] ?? '');
$rutaFuncional = absolutizePath($proyecto['ruta_funcional'] ?? '');
$rutaMemoria = absolutizePath($proyecto['ruta_memoria'] ?? '');
$rutaFichaEntrega = absolutizePath($proyecto['ruta_ficha_entrega'] ?? '');
?>

<script>
window.PAGE_TITLE = 'Editar fitxa del projecte';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center breadcrum">
                <a href="/projecte/<?= (int)$proyecto['id_proyecto'] ?>"
                   class="text-decoration-none small text-muted d-inline-flex align-items-center gap-2">
                    <span>🡰</span>
                    <span>Tornar a la fitxa del projecte</span>
                </a>

                <button type="submit" form="form-ficha-proyecto" class="btn btn-puig-solid px-4">
                    Guardar canvis
                </button>
            </div>

            <form id="form-ficha-proyecto" action="/index.php?main=ficha_accion" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id_proyecto" value="<?= (int)$proyecto['id_proyecto'] ?>">
                <input type="hidden" name="uuid" value="<?= h($proyecto['uuid']) ?>">

                <div class="card-style mb-30">
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
                                    <div class="preview-box d-flex align-items-center justify-content-center text-muted">
                                        Sense imatge
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <div class="image-upload-box">
                                    <span class="edit-label-subtle">Imatge del projecte</span>
                                    <input
                                        type="file"
                                        class="form-control"
                                        id="imagen"
                                        name="imagen"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >
                                    <div class="form-text mt-2">Pots pujar una nova imatge en format JPG, JPEG, PNG o WEBP.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <span class="edit-label-subtle">Nom del projecte</span>
                                <input
                                    type="text"
                                    class="form-control input-title-like form-prin"
                                    id="nombre"
                                    name="nombre"
                                    value="<?= h($proyecto['nombre'] ?? '') ?>"
                                    placeholder="Nom del projecte"
                                    required
                                >
                            </div>

                            <div class="mb-4">
                                <span class="edit-label-subtle">Resum</span>
                                <textarea
                                    class="form-control input-summary-like js-autogrow form-prin"
                                    id="resumen"
                                    name="resumen"
                                    rows="2"
                                    placeholder="Afegeix un resum breu i clar del projecte"
                                ><?= h($proyecto['resumen'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-4">
                                <h5 class="mb-3">Descripció</h5>
                                <textarea
                                    class="form-control textarea-description-like js-autogrow"
                                    id="descripcion"
                                    name="descripcion"
                                    rows="8"
                                    placeholder="Explica el projecte amb més detall: objectiu, funcionalitats, enfocament, valor, estat actual..."
                                ><?= h($proyecto['descripcion'] ?? '') ?></textarea>
                            </div>

                        </div>

                        <div class="col-lg-5 d-flex flex-column">

                            <div class="puig-panel puig-panel-highlight mb-50">
                                <div class="puig-panel-header">
                                    <h3 class="puig-panel-title c-puig">Informació general</h3>
                                </div>

                                <div class="puig-panel-body">
                                    <div class="puig-panel-content">
                                        <div class="mb-2">
                                            <strong class="info-label">Autoria:</strong><br>
                                            <div class="alumnes">
                                                <?php if (!empty($alumnos)): ?>
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

                            <div class="section-block">
                                <h3 class="h3fichas">Tecnologies</h3>
                                <div class="inner-ficha">
                                    <div class="mb-0">
                                        <div class="link-muted-title">Separades per comes</div>
                                        <input
                                            type="text"
                                            class="form-control meta-input"
                                            id="stack"
                                            name="stack"
                                            value="<?= h($proyecto['stack'] ?? '') ?>"
                                            placeholder="PHP, PostgreSQL, Bootstrap, JavaScript"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="h3fichas">Documentació</h3>
                                <div class="inner-ficha">

                                    <div class="form-section-card">
                                        <label for="funcional" class="edit-label-subtle">Document funcional</label>
                                        <?php if ($rutaFuncional !== ''): ?>
                                            <a href="<?= h($rutaFuncional) ?>" target="_blank" class="doc-link-current">
                                                Veure document actual
                                            </a>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="funcional" name="funcional" accept=".pdf">
                                    </div>

                                    <div class="form-section-card">
                                        <label for="memoria" class="edit-label-subtle">Memòria</label>
                                        <?php if ($rutaMemoria !== ''): ?>
                                            <a href="<?= h($rutaMemoria) ?>" target="_blank" class="doc-link-current">
                                                Veure document actual
                                            </a>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="memoria" name="memoria" accept=".pdf">
                                    </div>

                                    <div class="form-section-card">
                                        <label for="ficha_entrega" class="edit-label-subtle">Fitxa d'entrega</label>
                                        <?php if ($rutaFichaEntrega !== ''): ?>
                                            <a href="<?= h($rutaFichaEntrega) ?>" target="_blank" class="doc-link-current">
                                                Veure document actual
                                            </a>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="ficha_entrega" name="ficha_entrega" accept=".pdf">
                                    </div>

                                </div>
                            </div>

                            <div class="section-block mb-4">
                                <h3 class="h3fichas">Enllaços</h3>
                                <div class="inner-ficha">

                                    <div class="mb-3">
                                        <label for="url_github" class="edit-label-subtle">Git / repositori</label>
                                        <input
                                            type="url"
                                            class="form-control meta-input"
                                            id="url_github"
                                            name="url_github"
                                            value="<?= h($proyecto['url_github'] ?? '') ?>"
                                            placeholder="https://github.com/..."
                                        >
                                    </div>

                                    <div class="mb-0">
                                        <label for="url_proyecto" class="edit-label-subtle">Web / demo del projecte</label>
                                        <input
                                            type="url"
                                            class="form-control meta-input"
                                            id="url_proyecto"
                                            name="url_proyecto"
                                            value="<?= h($proyecto['url_proyecto'] ?? '') ?>"
                                            placeholder="https://..."
                                        >
                                    </div>

                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-auto pt-3">
                                <button type="submit" class="btn btn-puig px-4">
                                    Guardar canvis
                                </button>
                            </div>

                        </div>

                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const autoGrowTextareas = document.querySelectorAll('.js-autogrow');

    const autoGrow = (textarea) => {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    };

    autoGrowTextareas.forEach((textarea) => {
        autoGrow(textarea);
        textarea.addEventListener('input', function () {
            autoGrow(textarea);
        });
    });
});
</script>