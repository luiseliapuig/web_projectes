<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}

require_once dirname(__DIR__, 3) . '/memoria/funciones.php';
require_once __DIR__ . '/grup-actiu_funcions.php';

$profesorId = (int) $_SESSION['professor_id'];
$cursoAcademico = cursoAcademicoActual();

// -----------------------------------------------------------------------------
// 1. Grups que el professor tutoritza aquest curs. Mateix patró que
// autoseguiment-tutor.php: rel_profesores_grupos és l'única font d'autorització.
// -----------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.grupo, c.abr, c.orden, c.color
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo = rpg.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE rpg.profesor_id = :profesor_id
      AND rpg.curso_academico = :curso_academico
      AND c.activo = true
    ORDER BY c.orden, c.abr, g.grupo
");
$stmt->execute([':profesor_id' => $profesorId, ':curso_academico' => $cursoAcademico]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$idsGruposAutorizados = array_map(static fn (array $g): int => (int) $g['id_grupo'], $grupos);

// Pendents totals per grup (per a la pill de GRUP): agregació del mateix
// criteri de "pendent" que ja fa servir cada projecte (revision_solicitada),
// sumat per a tots els grups que tutoritza el professor, no només el
// seleccionat.
$pendientesPorGrupo = [];
if ($idsGruposAutorizados !== []) {
    $stmt = $pdo->prepare("
        SELECT p.grupo_id, COUNT(*) AS pendientes
        FROM app.memoria_seguimiento ms
        INNER JOIN app.proyectos p ON p.id_proyecto = ms.proyecto_id
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_id
        WHERE p.curso_academico = :curso_academico
          AND p.estado = 'activo'
          AND ms.estado = 'revision_solicitada'
          AND EXISTS (
              SELECT 1
              FROM app.rel_proyectos_profesores rpp
              WHERE rpp.proyecto_id = ms.proyecto_id
                AND rpp.profesor_id = :profesor_id_tutor
                AND rpp.rol = 'tutor'
          )
        GROUP BY p.grupo_id
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':profesor_id_tutor' => $profesorId,
        ':curso_academico' => $cursoAcademico,
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $pendientesPorGrupo[(int) $fila['grupo_id']] = (int) $fila['pendientes'];
    }
}

// -----------------------------------------------------------------------------
// 1b. Enllaç directe a un projecte concret (proyecto_id): resol quin grup li
// correspon i, si el professor el tutoritza aquest curs, l'agafa com a grup
// seleccionat, per sobre del paràmetre grupo_id. És el mecanisme estable que
// farà servir el futur correu de "Sol·licitar revisió" per obrir directament
// el projecte, sense dependre de cap estat de navegació.
// -----------------------------------------------------------------------------

$proyectoIdSolicitado = isset($_GET['proyecto_id']) ? (int) $_GET['proyecto_id'] : 0;
$grupoIdDesdeProyecto = 0;
if ($proyectoIdSolicitado > 0) {
    $stmt = $pdo->prepare("
        SELECT grupo_id FROM app.proyectos
        WHERE id_proyecto = :id AND estado = 'activo' AND curso_academico = :curso_academico
    ");
    $stmt->execute([':id' => $proyectoIdSolicitado, ':curso_academico' => $cursoAcademico]);
    $grupoCandidato = (int) ($stmt->fetchColumn() ?: 0);
    if ($grupoCandidato > 0 && in_array($grupoCandidato, $idsGruposAutorizados, true)) {
        $grupoIdDesdeProyecto = $grupoCandidato;
    }
}

$grupoIdSolicitat = $grupoIdDesdeProyecto === 0 && isset($_GET['grupo_id'])
    ? (int) $_GET['grupo_id']
    : 0;
$grupoId = resoldreGrupActiuTutor($grupos, $grupoIdSolicitat, $grupoIdDesdeProyecto);

// -----------------------------------------------------------------------------
// 2. Projectes actius del grup seleccionat. La JOIN amb rel_profesores_grupos
// torna a autoritzar aquí (defensa en profunditat), igual que fa
// autoseguiment-tutor.php amb el seu alumnat.
// -----------------------------------------------------------------------------

$proyectos = [];
if ($grupoId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.nombre, p.categoria_proyecto_id,
               EXISTS (
                   SELECT 1
                   FROM app.rel_proyectos_profesores rpp
                   WHERE rpp.proyecto_id = p.id_proyecto
                     AND rpp.profesor_id = :profesor_id_tutor
                     AND rpp.rol = 'tutor'
               ) AS es_tutor_formal
        FROM app.proyectos p
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_id
        WHERE p.grupo_id = :grupo_id
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
        ORDER BY p.nombre
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':profesor_id_tutor' => $profesorId,
        ':grupo_id' => $grupoId,
        ':curso_academico' => $cursoAcademico,
    ]);
    $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// -----------------------------------------------------------------------------
// 2b. Membres (alumnat) de cada projecte, per mostrar-los junts a la pill, i
// recompte de "revision_solicitada" per projecte (indicador de pendents).
// Dues consultes agregades, limitades sempre als projectes ja autoritzats.
// -----------------------------------------------------------------------------

$miembrosPorProyecto = [];
$tutorFormalPorProyecto = [];
$pendientesPorProyecto = [];
if ($proyectos !== []) {
    $proyectoIds = array_map(static fn (array $p): int => (int) $p['id_proyecto'], $proyectos);
    $marcadores = implode(',', array_fill(0, count($proyectoIds), '?'));
    foreach ($proyectos as $proyecto) {
        $tutorFormalPorProyecto[(int) $proyecto['id_proyecto']] = in_array(
            $proyecto['es_tutor_formal'],
            [true, 1, '1', 't', 'true'],
            true
        );
    }

    $stmt = $pdo->prepare("
        SELECT rpa.proyecto_id, a.nombre, a.apellidos
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id IN ($marcadores) AND a.activo = true
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute($proyectoIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $miembrosPorProyecto[(int) $fila['proyecto_id']][] = trim((string) $fila['nombre'] . ' ' . (string) $fila['apellidos']);
    }

    $stmt = $pdo->prepare("
        SELECT ms.proyecto_id, COUNT(*) AS pendientes
        FROM app.memoria_seguimiento ms
        WHERE ms.proyecto_id IN ($marcadores)
          AND ms.estado = 'revision_solicitada'
          AND EXISTS (
              SELECT 1
              FROM app.rel_proyectos_profesores rpp
              WHERE rpp.proyecto_id = ms.proyecto_id
                AND rpp.profesor_id = ?
                AND rpp.rol = 'tutor'
          )
        GROUP BY ms.proyecto_id
    ");
    $stmt->execute([...$proyectoIds, $profesorId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $pendientesPorProyecto[(int) $fila['proyecto_id']] = (int) $fila['pendientes'];
    }
}

// El projecte sol·licitat (per enllaç directe o per clic) només s'accepta si
// apareix a la llista ja autoritzada del grup resolt; si no, es recorre
// determinísticament al primer projecte disponible.
$proyectoIdSolicitat = $proyectoIdSolicitado > 0 ? $proyectoIdSolicitado : (isset($_GET['proyecto_id']) ? (int) $_GET['proyecto_id'] : 0);
$proyectoId = 0;
$categoriaId = 0;
foreach ($proyectos as $proyecto) {
    if ((int) $proyecto['id_proyecto'] === $proyectoIdSolicitat) {
        $proyectoId = $proyectoIdSolicitat;
        $categoriaId = $proyecto['categoria_proyecto_id'] !== null ? (int) $proyecto['categoria_proyecto_id'] : 0;
        break;
    }
}
if ($proyectoId === 0 && $proyectos !== []) {
    $proyectoId = (int) $proyectos[0]['id_proyecto'];
    $categoriaId = $proyectos[0]['categoria_proyecto_id'] !== null ? (int) $proyectos[0]['categoria_proyecto_id'] : 0;
}

// El professor només pot escriure si és el tutor formal del projecte concret.
$potEditar = $tutorFormalPorProyecto[$proyectoId] ?? false;

// -----------------------------------------------------------------------------
// 3. Apartats de memòria del projecte seleccionat. Si encara no hi ha files
// de seguiment (perquè el professor hi entra abans que l'alumnat), es
// garanteixen aquí amb la mateixa funció idempotent que fa servir la vista
// de l'alumnat: no es duplica la lògica.
// -----------------------------------------------------------------------------

$apartats = [];
$comentarisPerApartat = [];
if ($proyectoId > 0 && $categoriaId > 0) {
    memoriaGarantirSeguiment($pdo, $proyectoId, $categoriaId);
    $apartats = memoriaObtenerApartados($pdo, $proyectoId, $categoriaId);

    // Historial complet de comentaris (no només l'últim), agrupat per
    // id_memoria_seguimiento. Es carrega sempre juntament amb la pàgina; el
    // desplegat "Veure comentaris anteriors" és només visual en client.
    $idsSeguiment = array_values(array_filter(array_map(
        static fn (array $a): int => $a['id_memoria_seguimiento'] !== null ? (int) $a['id_memoria_seguimiento'] : 0,
        $apartats
    )));
    $comentarisPerApartat = memoriaObtenerComentarios($pdo, $idsSeguiment);
}
?>
<script>window.PAGE_TITLE = 'Memòria';</script>
<style>
.memoria-tutor-panell {
    background: #f7f8fa;
}
.memoria-tutor-nav-pill {
    font-size: .8125rem;
}
/* Mateix llenguatge neutre que .pill-neutral, un punt més marcat per a la
   selecció. Mateixos valors que ja fa servir autoseguiment-tutor.php, aquí
   duplicats perquè viuen en un <style> scoped a cada pàgina. */
.pill-neutral-seleccionada {
    background: #e4e7eb !important;
    border-color: #adb5bd !important;
    color: #495057 !important;
}
.memoria-pendents-badge {
    background: #F59E0B;
    color: #fff;
}
/* Enllaços secundaris/de consulta d'aquesta peça (guia, historial): mai el
   blau de Bootstrap. Mateix criteri que la vista de l'alumnat. */
.memoria-link-secundari,
.memoria-historial-toggle {
    color: #496B88 !important;
}
.memoria-link-secundari:hover,
.memoria-historial-toggle:hover {
    color: #35506b !important;
}
/* Data del comentari: metadada clarament secundària, mai competint amb el text. */
.memoria-comentari-data-secundaria {
    font-size: .75rem;
    color: #8a94a4;
}
</style>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Memòria</h1>
        <p class="text-muted mb-0">Revisió dels apartats de memòria per projecte.</p>
    </div>

    <?php if ($grupos === []): ?>
        <div class="alert alert-warning">No tens cap grup assignat aquest curs.</div>
    <?php else: ?>
        <?php if (count($grupos) >= 2): ?>
            <!-- ── Navegació per grups: color del cicle real (app.ciclos.color) ── -->
            <div class="mb-3">
                <div class="text-uppercase small fw-semibold text-muted mb-2">Grup</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($grupos as $grupo): ?>
                        <?php
                        $grupActiu = (int) $grupo['id_grupo'] === $grupoId;
                        $colorCiclo = (string) $grupo['color'];
                        $classesGrup = $grupActiu ? clasesColorCicloSolid($colorCiclo) : clasesColorCiclo($colorCiclo);
                        $pendentsGrup = $pendientesPorGrupo[(int) $grupo['id_grupo']] ?? 0;
                        ?>
                        <a href="/revisio-memoria/grup/<?= (int) $grupo['id_grupo'] ?>"
                           data-grupo-id="<?= (int) $grupo['id_grupo'] ?>"
                           class="badge rounded-pill border px-3 py-2 fw-semibold text-decoration-none memoria-tutor-nav-pill memoria-grup-pill <?= $classesGrup ?>">
                            <?= htmlspecialchars(trim((string) $grupo['abr'] . ' ' . (string) $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?>
                            <span class="memoria-grup-pendents-estat">
                                <?php if ($pendentsGrup > 0): ?>
                                    <span class="badge rounded-pill memoria-pendents-badge ms-1"><?= $pendentsGrup ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Navegació per projectes: cada projecte és una única unitat
             interactiva (mai enllaços individuals per alumne) ── -->
        <div class="mb-4">
            <div class="text-uppercase small fw-semibold text-muted mb-2">Projectes</div>
            <?php if ($proyectos === []): ?>
                <p class="text-muted mb-0">Aquest grup no té cap projecte actiu.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <?php foreach ($proyectos as $proyecto): ?>
                        <?php
                        $idProy = (int) $proyecto['id_proyecto'];
                        $seleccionat = $idProy === $proyectoId;
                        $pendents = $pendientesPorProyecto[$idProy] ?? 0;
                        $nomsMembres = $miembrosPorProyecto[$idProy] ?? [];
                        $esTutorFormal = $tutorFormalPorProyecto[$idProy] ?? false;
                        ?>
                        <a href="/revisio-memoria/projecte/<?= $idProy ?>"
                           data-proyecto-id="<?= $idProy ?>"
                           class="badge rounded-pill border px-3 py-2 fw-semibold text-decoration-none memoria-tutor-nav-pill memoria-projecte-pill pill-neutral <?= $seleccionat ? 'pill-neutral-seleccionada' : '' ?>">
                            <?= htmlspecialchars($nomsMembres !== [] ? implode(' · ', $nomsMembres) : (string) $proyecto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($esTutorFormal): ?>
                            <span class="memoria-pendents-estat">
                                <?php if ($pendents > 0): ?>
                                    <span class="badge rounded-pill memoria-pendents-badge ms-1"><?= $pendents ?></span>
                                <?php else: ?>
                                    <i class="bi bi-check-circle-fill text-success ms-1" aria-hidden="true"></i><span class="visually-hidden">Sense revisions pendents</span>
                                <?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Apartats de memòria del projecte seleccionat ── -->
        <?php if ($proyectoId > 0): ?>
            <div class="card memoria-tutor-panell shadow-sm border-0 rounded-4 p-4 p-lg-5">
                <?php if ($categoriaId <= 0): ?>
                    <section class="bloc bloc-informacio">
                        <div class="bloc-contingut">
                            <div class="bloc-tipus">Sense categoria</div>
                            <h2>Aquest projecte encara no té una categoria assignada</h2>
                            <p class="mb-0">No hi ha cap apartat de memòria per mostrar.</p>
                        </div>
                    </section>
                <?php elseif ($apartats === []): ?>
                    <section class="bloc bloc-informacio">
                        <div class="bloc-contingut">
                            <div class="bloc-tipus">Memòria</div>
                            <h2>Encara no hi ha cap apartat definit</h2>
                            <p class="mb-0">La categoria d’aquest projecte encara no té apartats de memòria actius.</p>
                        </div>
                    </section>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($apartats as $apartat): ?>
                            <?php
                            $estado = $apartat['estado'] !== null ? (string) $apartat['estado'] : 'pendiente';
                            $idSeguiment = $apartat['id_memoria_seguimiento'] !== null ? (int) $apartat['id_memoria_seguimiento'] : 0;
                            $comentari = trim((string) ($apartat['ultim_comentari'] ?? ''));
                            $fechaMetadatoRevision = $estado === 'revision_solicitada'
                                ? (string) ($apartat['fecha_solicitud_revision'] ?? '')
                                : (string) ($apartat['fecha_ultima_revision'] ?? '');
                            $etiquetaMetadatoRevision = $estado === 'revision_solicitada' ? 'Revisió sol·licitada' : 'Última revisió';
                            $historial = $comentarisPerApartat[$idSeguiment] ?? [];
                            ?>
                            <section id="apartat-<?= $idSeguiment ?>"
                                     class="bloc <?= memoriaEstatClasseBloc($estado) ?> mb-0 memoria-apartat-targeta"
                                     data-memoria-estado="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="bloc-contingut">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                                        <div class="bloc-tipus mb-0">Apartat <?= (int) $apartat['orden'] ?></div>
                                        <?php if (trim((string) ($apartat['enlace_guia'] ?? '')) !== ''): ?>
                                            <a href="<?= htmlspecialchars((string) $apartat['enlace_guia'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="memoria-link-secundari">
                                                Guia de l’apartat
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <h2 class="h5 mb-1"><?= htmlspecialchars((string) $apartat['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>
                                    <?php if (trim((string) ($apartat['descripcion'] ?? '')) !== ''): ?>
                                        <p class="mb-3"><?= nl2br(htmlspecialchars((string) $apartat['descripcion'], ENT_QUOTES, 'UTF-8'), false) ?></p>
                                    <?php endif; ?>

                                    <div class="row g-3">
                                        <div class="col-md-3 d-flex flex-column">
                                            <span class="badge rounded-pill px-3 py-2 align-self-start memoria-estat-badge <?= memoriaEstatClasseBadge($estado) ?>">
                                                <?= htmlspecialchars(memoriaEtiquetaEstat($estado), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if (trim($fechaMetadatoRevision) !== ''): ?>
                                                <div class="mt-auto pt-3 memoria-ultima-revisio">
                                                    <p class="memoria-ultima-revisio-etiqueta mb-1"><?= htmlspecialchars($etiquetaMetadatoRevision, ENT_QUOTES, 'UTF-8') ?></p>
                                                    <p class="small text-muted mb-0 memoria-fecha-revisio"><?= htmlspecialchars(memoriaData($fechaMetadatoRevision), ENT_QUOTES, 'UTF-8') ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-9 memoria-comentaris-columna">
                                            <p class="fw-semibold small text-uppercase text-muted mb-1 memoria-comentaris-cap">Comentaris del tutor</p>
                                            <div class="memoria-comentari-actual">
                                                <?php if ($comentari !== ''): ?>
                                                    <div class="memoria-comentari-item">
                                                        <div class="memoria-comentari-cos">
                                                            <span class="memoria-comentari-text"><?= nl2br(htmlspecialchars($comentari, ENT_QUOTES, 'UTF-8'), false) ?></span>
                                                            <p class="mb-0 mt-2 memoria-comentari-data-secundaria memoria-comentari-data"><?= htmlspecialchars(memoriaData((string) $apartat['ultim_comentari_data']), ENT_QUOTES, 'UTF-8') ?></p>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="memoria-comentari-item">
                                                        <div class="memoria-comentari-cos">
                                                            <span class="text-muted">Encara no hi ha cap comentari.</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php
                                            // El primer element de $historial és el mateix comentari ja
                                            // mostrat a la caixa d'amunt (és el més recent): el desplegat
                                            // "anteriors" mostra només la resta.
                                            $comentarisAnteriors = array_slice($historial, 1);
                                            ?>
                                            <?php if ($comentarisAnteriors !== []): ?>
                                                <div class="mt-2 memoria-historial-bloc">
                                                    <button type="button" class="btn btn-link btn-sm ps-0 memoria-historial-toggle">Comentaris previs</button>
                                                    <div class="d-none mt-2 memoria-historial-comentaris">
                                                        <?php foreach ($comentarisAnteriors as $c): ?>
                                                            <div class="memoria-comentari-item">
                                                                <div class="memoria-comentari-cos">
                                                                    <p class="mb-0"><?= nl2br(htmlspecialchars((string) $c['comentario'], ENT_QUOTES, 'UTF-8'), false) ?></p>
                                                                    <p class="mb-0 mt-1 memoria-comentari-data-secundaria"><?= htmlspecialchars(memoriaData((string) $c['creado_en']), ENT_QUOTES, 'UTF-8') ?></p>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($potEditar && $idSeguiment > 0 && $estado === 'revision_solicitada'): ?>
                                                <div class="mt-3 memoria-revisio-bloc" data-id-seguiment="<?= $idSeguiment ?>">
                                                    <div>
                                                        <textarea class="form-control form-control-sm textarea-neutral auto-grow memoria-comentari-nou" rows="3" maxlength="4000" placeholder="Comenta, si cal, aquesta revisió..."></textarea>
                                                    </div>
                                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <input type="radio" class="btn-check memoria-resultat-input" name="memoria_resultat_<?= $idSeguiment ?>" id="memoria-corregir-<?= $idSeguiment ?>" value="corregir">
                                                            <label class="memoria-resultat-opcio" for="memoria-corregir-<?= $idSeguiment ?>">Cal corregir</label>
                                                            <input type="radio" class="btn-check memoria-resultat-input" name="memoria_resultat_<?= $idSeguiment ?>" id="memoria-complet-<?= $idSeguiment ?>" value="completo">
                                                            <label class="memoria-resultat-opcio" for="memoria-complet-<?= $idSeguiment ?>">Apartat validat</label>
                                                            <span class="small align-self-center memoria-resultat-validacio d-none" aria-live="polite">‹ Has de seleccionar un estat</span>
                                                        </div>
                                                        <button type="button" class="btn btn-fase btn-puig memoria-guardar-btn">Guardar revisió</button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode(tokenCsrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const urlAccio = '/index.php?main=memoria-tutor_accion';
    const proyectoActualId = <?= (int) $proyectoId ?>;
    const grupoActualId = <?= (int) $grupoId ?>;
    const potEditar = <?= $potEditar ? 'true' : 'false' ?>;

    const etiquetes = {
        pendiente: 'Pendent',
        revision_solicitada: 'Revisió sol·licitada',
        corregir: 'Cal corregir',
        completo: 'Apartat validat',
    };
    // Mateixa semàntica que memoriaEstatClasseBadge()/memoriaEstatClasseBloc()
    // al servidor: pendiente=gris, revision_solicitada=groc, corregir=vermell,
    // completo=verd.
    const classesBadgeEstat = {
        pendiente: 'memoria-estat-pendent',
        revision_solicitada: 'memoria-estat-revisio',
        corregir: 'memoria-estat-corregir',
        completo: 'memoria-estat-complet',
    };
    const classesBlocEstat = {
        pendiente: 'bloc-informacio',
        revision_solicitada: 'bloc-memoria-revisio',
        corregir: 'bloc-memoria-corregir',
        completo: 'bloc-memoria-complet',
    };

    // ── Visibilitat i recompte: una sola font de veritat al DOM ────────────
    const actualitzarPresentacioApartats = () => {
        const apartats = Array.from(document.querySelectorAll('.memoria-apartat-targeta'));
        const pendents = apartats.filter((apartat) => apartat.dataset.memoriaEstado === 'revision_solicitada');
        const nomesPendents = potEditar && pendents.length > 0;

        apartats.forEach((apartat) => {
            apartat.classList.toggle(
                'd-none',
                nomesPendents && apartat.dataset.memoriaEstado !== 'revision_solicitada'
            );
        });

        const pill = document.querySelector('.memoria-projecte-pill[data-proyecto-id="' + proyectoActualId + '"]');
        const estat = pill ? pill.querySelector('.memoria-pendents-estat') : null;
        if (estat) {
            estat.innerHTML = pendents.length > 0
                ? '<span class="badge rounded-pill memoria-pendents-badge ms-1">' + pendents.length + '</span>'
                : '<i class="bi bi-check-circle-fill text-success ms-1" aria-hidden="true"></i><span class="visually-hidden">Sense revisions pendents</span>';
        }

        const pendentsGrup = Array.from(document.querySelectorAll('.memoria-projecte-pill .memoria-pendents-badge'))
            .reduce((total, badge) => total + (Number.parseInt(badge.textContent, 10) || 0), 0);
        const grup = document.querySelector('.memoria-grup-pill[data-grupo-id="' + grupoActualId + '"]');
        const estatGrup = grup ? grup.querySelector('.memoria-grup-pendents-estat') : null;
        if (estatGrup) {
            estatGrup.innerHTML = pendentsGrup > 0
                ? '<span class="badge rounded-pill memoria-pendents-badge ms-1">' + pendentsGrup + '</span>'
                : '';
        }
    };

    actualitzarPresentacioApartats();

    document.querySelectorAll('.memoria-revisio-bloc').forEach((bloc) => {
        const idSeguiment = bloc.dataset.idSeguiment;
        const textarea = bloc.querySelector('.memoria-comentari-nou');
        const boto = bloc.querySelector('.memoria-guardar-btn');
        const validacioResultat = bloc.querySelector('.memoria-resultat-validacio');
        const opcionsResultat = bloc.querySelectorAll('.memoria-resultat-input');
        const seccio = bloc.closest('section.bloc');
        const badge = seccio ? seccio.querySelector('.memoria-estat-badge') : null;
        const etiquetaRevisio = seccio ? seccio.querySelector('.memoria-ultima-revisio-etiqueta') : null;
        const fechaRevisio = seccio ? seccio.querySelector('.memoria-fecha-revisio') : null;
        const comentariActual = seccio ? seccio.querySelector('.memoria-comentari-actual') : null;

        opcionsResultat.forEach((opcio) => {
            opcio.addEventListener('change', () => validacioResultat?.classList.add('d-none'));
        });

        boto?.addEventListener('click', async () => {
            const resultatSeleccionat = bloc.querySelector('.memoria-resultat-input:checked');
            if (!idSeguiment) {
                return;
            }
            if (!resultatSeleccionat) {
                validacioResultat?.classList.remove('d-none');
                return;
            }
            boto.disabled = true;

            const dades = new FormData();
            dades.append('accio', 'revisar');
            dades.append('id_seguimiento', idSeguiment);
            dades.append('estado', resultatSeleccionat.value);
            dades.append('comentario', textarea ? textarea.value : '');
            dades.append('csrf_token', csrfToken);

            try {
                const resposta = await fetch(urlAccio, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: dades,
                });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    alert(resultat.missatge || 'No s’ha pogut guardar la revisió.');
                    boto.disabled = false;
                    return;
                }

                if (badge) {
                    badge.textContent = etiquetes[resultat.estado] || resultat.estado;
                    badge.classList.remove('memoria-estat-pendent', 'memoria-estat-revisio', 'memoria-estat-corregir', 'memoria-estat-complet');
                    badge.classList.add(classesBadgeEstat[resultat.estado] || 'memoria-estat-pendent');
                }
                if (seccio) {
                    seccio.classList.remove('bloc-informacio', 'bloc-memoria-revisio', 'bloc-memoria-corregir', 'bloc-memoria-complet');
                    seccio.classList.add(classesBlocEstat[resultat.estado] || 'bloc-informacio');
                    seccio.dataset.memoriaEstado = resultat.estado;
                }
                if (resultat.fecha_ultima_revision && fechaRevisio) {
                    fechaRevisio.textContent = resultat.fecha_ultima_revision;
                    if (etiquetaRevisio) {
                        etiquetaRevisio.textContent = 'Última revisió';
                    }
                } else if (resultat.fecha_ultima_revision && seccio) {
                    const meta = document.createElement('div');
                    meta.className = 'mt-auto pt-3 memoria-ultima-revisio';
                    const titol = document.createElement('p');
                    titol.className = 'memoria-ultima-revisio-etiqueta mb-1';
                    titol.textContent = 'Última revisió';
                    const data = document.createElement('p');
                    data.className = 'small text-muted mb-0 memoria-fecha-revisio';
                    data.textContent = resultat.fecha_ultima_revision;
                    meta.append(titol, data);
                    seccio.querySelector('.col-md-3')?.appendChild(meta);
                }
                if (resultat.comentario && comentariActual) {
                    const textEscapat = resultat.comentario
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/\n/g, '<br>');
                    comentariActual.innerHTML = '<div class="memoria-comentari-item"><div class="memoria-comentari-cos">'
                        + '<span class="memoria-comentari-text">' + textEscapat + '</span>'
                        + '<p class="mb-0 mt-2 memoria-comentari-data-secundaria memoria-comentari-data">' + (resultat.comentario_fecha || '') + '</p>'
                        + '</div></div>';
                    if (textarea) {
                        textarea.value = '';
                    }
                }

                bloc.remove();
                actualitzarPresentacioApartats();
            } catch (error) {
                alert('Error de connexió en guardar la revisió.');
            } finally {
                boto.disabled = false;
            }
        });
    });

    // ── Historial de comentaris: desplegat purament visual, sense cap
    // petició nova (els comentaris ja han arribat amb la pàgina). ──────────
    document.querySelectorAll('.memoria-historial-toggle').forEach((boto) => {
        const bloc = boto.closest('.memoria-historial-bloc');
        const panell = bloc ? bloc.querySelector('.memoria-historial-comentaris') : null;
        if (!panell) {
            return;
        }
        boto.addEventListener('click', () => {
            panell.classList.remove('d-none');
            boto.remove();
        });
    });
})();
</script>
