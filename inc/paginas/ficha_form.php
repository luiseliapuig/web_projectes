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
    if ($ruta === '') return '';
    if (str_starts_with($ruta, '/') || str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
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
            p.ciclo,
            p.grupo,
            p.curso_academico,
            p.autoev1,
            p.autoev2,
            p.autoev3,
            p.autoev4,
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

$rutaImagen      = absolutizePath($proyecto['ruta_imagen'] ?? '');
$rutaFuncional   = absolutizePath($proyecto['ruta_funcional'] ?? '');
$rutaMemoria     = absolutizePath($proyecto['ruta_memoria'] ?? '');

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
                                    <input type="file" class="form-control" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp">
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
                                <h3 class="h3fichas">Planificació i gestió</h3>
                                <div class="inner-ficha">

                                    <!-- Adjunts planificació existents -->
                                    <div id="llista-planificacio">
                                        <?php foreach ($adjuntsPlanificacio as $adj): ?>
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2" id="adj-<?= (int)$adj['id'] ?>">
                                            <a href="<?= h($adj['ruta']) ?>" target="_blank" class="doc-link-current flex-grow-1"><?= h($adj['nom']) ?></a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunt(<?= (int)$adj['id'] ?>)">✕</button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Formulari nou enllaç planificació -->
                                    <div id="form-nou-planificacio" class="d-none">
                                        <label class="edit-label-subtle">Nom de l'enllaç</label>
                                        <input type="text" class="form-control meta-input mb-2" id="nou-planificacio-nom" placeholder="Trello, Notion, Jira...">
                                        <input type="url" class="form-control meta-input mb-2" id="nou-planificacio-url" placeholder="https://...">
                                        <button type="button" class="btn btn-puig btn-sm px-3" onclick="afegirPlanificacio()">+ Afegir</button>
                                        <button type="button" class="btn btn-sm btn-link text-muted" onclick="cancelarNou('planificacio')">Cancel·lar</button>
                                    </div>

                                    <button type="button" id="btn-nou-planificacio" class="btn btn-sm btn-link text-muted ps-0 mt-1" onclick="mostrarNou('planificacio')">
                                        + Afegir nou enllaç
                                    </button>

                                </div>
                            </div>

                            <div class="section-block">
                                <h3 class="h3fichas">Documentació</h3>
                                <div class="inner-ficha">

                                    <div class="form-section-card  doc-block-primary">
                                        <label for="memoria" class="edit-label-subtle label-doc-prin">MEMÒRIA DEL PROJECTE</label>
                                        
                                        <input type="file" class="form-control" id="memoria" name="memoria" accept=".pdf">
                                        <?php if ($rutaMemoria !== ''): ?>
                                            <a href="<?= h($rutaMemoria) ?>" target="_blank" class="doc-link-current">Veure document actual</a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="form-section-card doc-block-secondary">
                                        <label for="funcional" class="edit-label-subtle">Document funcional</label>
                                        
                                        <input type="file" class="form-control" id="funcional" name="funcional" accept=".pdf">
                                        <?php if ($rutaFuncional !== ''): ?>
                                            <a href="<?= h($rutaFuncional) ?>" target="_blank" class="doc-link-current">Veure document actual</a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Adjunts arxiu existents -->
                                    <div id="llista-arxius" class="mt-15">
                                        <?php foreach ($adjuntsArxiu as $adj): ?>
                                        <div class="form-section-card d-flex align-items-center justify-content-between gap-2" id="adj-<?= (int)$adj['id'] ?>">
                                            <a href="<?= h($adj['ruta']) ?>" target="_blank" class="doc-link-current flex-grow-1"><?= h($adj['nom']) ?></a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunt(<?= (int)$adj['id'] ?>)">✕</button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Formulari nou arxiu -->
                                    <div id="form-nou-arxiu" class="d-none form-section-card mt-15">
                                        <label class="edit-label-subtle">Nom del document</label>
                                        <input type="text" class="form-control meta-input mb-2" id="nou-arxiu-nom" placeholder="Nom que apareixerà">
                                        <input type="file" class="form-control mb-2" id="nou-arxiu-fitxer" accept=".pdf">
                                        <button type="button" class="btn btn-puig btn-sm px-3" onclick="afegirArxiu()">Guardar</button>
                                        <button type="button" class="btn btn-sm btn-link text-muted" onclick="cancelarNou('arxiu')">Cancel·lar</button>
                                    </div>

                                    <button type="button" id="btn-nou-arxiu" class="btn btn-sm btn-link text-muted ps-0 mt-1" onclick="mostrarNou('arxiu')">
                                        + Afegir nou document
                                    </button>

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

                                    <div class="mb-3">
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

                                    <!-- Adjunts enllaç existents -->
                                    <div id="llista-enllacos" class="mt-15">
                                        <?php foreach ($adjuntsEnllac as $adj): ?>
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2" id="adj-<?= (int)$adj['id'] ?>">
                                            <a href="<?= h($adj['ruta']) ?>" target="_blank" class="doc-link-current flex-grow-1"><?= h($adj['nom']) ?></a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunt(<?= (int)$adj['id'] ?>)">✕</button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Formulari nou enllaç -->
                                    <div id="form-nou-enllac" class="d-none">
                                        <label class="edit-label-subtle">Nom de l'enllaç</label>
                                        <input type="text" class="form-control meta-input mb-2" id="nou-enllac-nom" placeholder="Nom que apareixerà">
                                        <input type="url" class="form-control meta-input mb-2" id="nou-enllac-url" placeholder="https://...">
                                        <button type="button" class="btn btn-puig btn-sm px-3" onclick="afegirEnllac()">Guardar</button>
                                        <button type="button" class="btn btn-sm btn-link text-muted" onclick="cancelarNou('enllac')">Cancel·lar</button>
                                    </div>

                                    <button type="button" id="btn-nou-enllac" class="btn btn-sm btn-link text-muted ps-0 mt-1" onclick="mostrarNou('enllac')">
                                        + Afegir nou enllaç
                                    </button>

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

                <!-- ══════════════════════════════════════════════
                     BLOC AUTOEVALUACIÓ
                ══════════════════════════════════════════════ -->
                <div class="mb-20 autoevaluacion">
                    <div class="border rounded-4 overflow-hidden">

                        <div class="bg-orange px-4 py-3 border-bottom d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="fw-semibold mb-1">Reflexió final del projecte</h3>
                                <p class="text-muted small mb-0">
                                    Valoració del propi alumnat sobre el desenvolupament del projecte
                                </p>
                            </div>
                            <div class="text-end border-start ps-3 ms-3">
                                <div class="text-muted small">
                                    <?php foreach ($alumnos as $a): ?>
                                        <h5 class="fw-semibold"><?= h($a['nombre'] . ' ' . $a['apellidos']) ?></h5>
                                    <?php endforeach; ?>
                                </div>
                                <div class="small">Alumne/s</div>
                            </div>
                        </div>

                        <div class="bg-white p-4">
                            <div class="row g-4">

                                <div class="col-md-6">
                                    <p class="fw-semibold mb-2">Què has après en aquest projecte?</p>
                                    <textarea
                                        class="form-control js-autogrow"
                                        name="autoev1"
                                        rows="4"
                                        placeholder="Descriu els principals aprenentatges del projecte..."
                                    ><?= h($proyecto['autoev1'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <p class="fw-semibold mb-2">De quina part del projecte estàs més satisfet?</p>
                                    <textarea
                                        class="form-control js-autogrow"
                                        name="autoev2"
                                        rows="4"
                                        placeholder="Explica la part que et genera més orgull o satisfacció..."
                                    ><?= h($proyecto['autoev2'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <p class="fw-semibold mb-2">Quines parts no s'han pogut completar i per què?</p>
                                    <textarea
                                        class="form-control js-autogrow"
                                        name="autoev3"
                                        rows="4"
                                        placeholder="Explica els aspectes que han quedat pendents i les causes..."
                                    ><?= h($proyecto['autoev3'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <p class="fw-semibold mb-2">Què milloraries si tinguessis més temps?</p>
                                    <textarea
                                        class="form-control js-autogrow"
                                        name="autoev4"
                                        rows="4"
                                        placeholder="Descriu millores o funcionalitats que t'hagués agradat implementar..."
                                    ><?= h($proyecto['autoev4'] ?? '') ?></textarea>
                                </div>

                            </div>
                        </div>



                    </div>
                </div>


 <div class="d-flex justify-content-between align-items-center breadcrum">
                <a href="/projecte/<?= (int)$proyecto['id_proyecto'] ?>"
                   class="text-decoration-none small text-muted d-inline-flex align-items-center gap-2">
                    <span>🡰</span>
                    <span>Tornar a la fitxa del projecte</span>
                </a>

                <button type="submit" form="form-ficha-proyecto" class="btn btn-orange px-4">
                    Guardar canvis
                </button>
            </div>

            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const autoGrow = (textarea) => {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    };

    document.querySelectorAll('.js-autogrow').forEach((textarea) => {
        autoGrow(textarea);
        textarea.addEventListener('input', function () {
            autoGrow(textarea);
        });
    });
});

// ── Adjunts (globals per poder cridar-les des d'onclick inline) ───
const _adjIdProyecto = <?= (int)$idProyecto ?>;
const _adjuntUrl     = '/index.php?main=proyecto_adjunt_accio&raw=1';

function mostrarNou(tipus) {
    if (tipus === 'arxiu') {
        document.getElementById('form-nou-arxiu').classList.remove('d-none');
        document.getElementById('btn-nou-arxiu').classList.add('d-none');
    } else if (tipus === 'planificacio') {
        document.getElementById('form-nou-planificacio').classList.remove('d-none');
        document.getElementById('btn-nou-planificacio').classList.add('d-none');
    } else {
        document.getElementById('form-nou-enllac').classList.remove('d-none');
        document.getElementById('btn-nou-enllac').classList.add('d-none');
    }
}

function cancelarNou(tipus) {
    if (tipus === 'arxiu') {
        document.getElementById('form-nou-arxiu').classList.add('d-none');
        document.getElementById('btn-nou-arxiu').classList.remove('d-none');
        document.getElementById('nou-arxiu-nom').value = '';
        document.getElementById('nou-arxiu-fitxer').value = '';
    } else if (tipus === 'planificacio') {
        document.getElementById('form-nou-planificacio').classList.add('d-none');
        document.getElementById('btn-nou-planificacio').classList.remove('d-none');
        document.getElementById('nou-planificacio-nom').value = '';
        document.getElementById('nou-planificacio-url').value = '';
    } else {
        document.getElementById('form-nou-enllac').classList.add('d-none');
        document.getElementById('btn-nou-enllac').classList.remove('d-none');
        document.getElementById('nou-enllac-nom').value = '';
        document.getElementById('nou-enllac-url').value = '';
    }
}

async function afegirArxiu() {
    const nom    = document.getElementById('nou-arxiu-nom').value.trim();
    const fitxer = document.getElementById('nou-arxiu-fitxer').files[0];
    if (!nom || !fitxer) { alert('Cal indicar un nom i seleccionar un fitxer.'); return; }

    const fd = new FormData();
    fd.append('accio',       'afegir');
    fd.append('tipo',        'arxiu');
    fd.append('proyecto_id', _adjIdProyecto);
    fd.append('nom',         nom);
    fd.append('fitxer',      fitxer);

    const res  = await fetch(_adjuntUrl, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert(data.missatge || 'Error en afegir.'); return; }

    const div = document.createElement('div');
    div.className = 'form-section-card d-flex align-items-center justify-content-between gap-2';
    div.id = 'adj-' + data.id;
    div.innerHTML = `<a href="${data.ruta}" target="_blank" class="doc-link-current flex-grow-1">${data.nom}</a>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunt(${data.id})">✕</button>`;
    document.getElementById('llista-arxius').appendChild(div);
    cancelarNou('arxiu');
}

async function afegirEnllac() {
    const nom = document.getElementById('nou-enllac-nom').value.trim();
    const url = document.getElementById('nou-enllac-url').value.trim();
    if (!nom || !url) { alert('Cal indicar un nom i una URL.'); return; }

    const fd = new FormData();
    fd.append('accio',       'afegir');
    fd.append('tipo',        'enllac');
    fd.append('proyecto_id', _adjIdProyecto);
    fd.append('nom',         nom);
    fd.append('ruta',        url);

    const res  = await fetch(_adjuntUrl, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert(data.missatge || 'Error en afegir.'); return; }

    const div = document.createElement('div');
    div.className = 'd-flex align-items-center justify-content-between gap-2 mb-2';
    div.id = 'adj-' + data.id;
    div.innerHTML = `<a href="${data.ruta}" target="_blank" class="doc-link-current flex-grow-1">${data.nom}</a>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunt(${data.id})">✕</button>`;
    document.getElementById('llista-enllacos').appendChild(div);
    cancelarNou('enllac');
}

async function afegirPlanificacio() {
    const nom = document.getElementById('nou-planificacio-nom').value.trim();
    const url = document.getElementById('nou-planificacio-url').value.trim();
    if (!nom || !url) { alert('Cal indicar un nom i una URL.'); return; }

    const fd = new FormData();
    fd.append('accio',       'afegir');
    fd.append('tipo',        'planificacio');
    fd.append('proyecto_id', _adjIdProyecto);
    fd.append('nom',         nom);
    fd.append('ruta',        url);

    const res  = await fetch(_adjuntUrl, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert(data.missatge || 'Error en afegir.'); return; }

    const div = document.createElement('div');
    div.className = 'd-flex align-items-center justify-content-between gap-2 mb-2';
    div.id = 'adj-' + data.id;
    div.innerHTML = `<a href="${data.ruta}" target="_blank" class="doc-link-current flex-grow-1">${data.nom}</a>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarAdjunt(${data.id})">✕</button>`;
    document.getElementById('llista-planificacio').appendChild(div);
    cancelarNou('planificacio');
}

async function eliminarAdjunt(id) {
    if (!confirm('Eliminar aquest adjunt?')) return;

    const fd = new FormData();
    fd.append('accio', 'eliminar');
    fd.append('id',    id);

    const res  = await fetch(_adjuntUrl, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert(data.missatge || 'Error en eliminar.'); return; }

    document.getElementById('adj-' + id)?.remove();
}
</script>
