<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}

require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once dirname(__DIR__, 3) . '/memoria/funciones.php';
require_once __DIR__ . '/resum-tutor_fases_funcions.php';
require_once __DIR__ . '/grup-actiu_funcions.php';

$profesorId = (int) $_SESSION['professor_id'];
$cursoAcademico = cursoAcademicoActual();

// -----------------------------------------------------------------------------
// 1. Grups que el professor tutoritza aquest curs, amb la fases_clave del seu
// cicle (necessària per resoldre l'arquitectura de fases). Mateix patró que
// autoseguiment-tutor.php / memoria-tutor.php: rel_profesores_grupos és
// l'única font d'autorització de grup.
// -----------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.grupo, c.abr, c.orden, c.color, c.fases_clave
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

$grupoIdSolicitat = isset($_GET['grupo_id']) ? (int) $_GET['grupo_id'] : 0;
$grupoId = resoldreGrupActiuTutor($grupos, $grupoIdSolicitat);
$grupoSeleccionado = null;
foreach ($grupos as $grupo) {
    if ((int) $grupo['id_grupo'] === $grupoId) {
        $grupoSeleccionado = $grupo;
        break;
    }
}

// -----------------------------------------------------------------------------
// 2. Arquitectura de fases del grup seleccionat: proyecto → ciclo →
// fases_clave → obtenerFasesProyecto(). Totes dues coses depenen únicament
// del cicle, i tots els projectes d'un mateix grup comparteixen cicle, així
// que es resol una sola vegada per al grup, no per projecte.
// -----------------------------------------------------------------------------

$fasesClaveGrupo = $grupoSeleccionado['fases_clave'] ?? null;
$fasesArquitectura = obtenerFasesProyecto(['fases_clave' => $fasesClaveGrupo]);

// -----------------------------------------------------------------------------
// 3. Projectes actius del grup seleccionat, amb els seus membres. La JOIN amb
// rel_profesores_grupos torna a autoritzar aquí (defensa en profunditat).
// -----------------------------------------------------------------------------

$proyectos = [];
if ($grupoId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.nombre, p.categoria_proyecto_id,
               p.propuesta_pdf, p.propuesta_validada_en,
               p.funcional_pdf, p.funcional_validado_en,
               p.planificacion_url, p.gestion_url,
               p.git_url, p.autoev1, p.autoev2, p.autoev3, p.autoev4,
               p.url_proyecto, p.memoria_url, p.resumen, p.descripcion,
               p.ruta_imagen, p.memoria_pdf, p.presentacion_pdf,
               rpp_formal.proyecto_id IS NOT NULL AS es_tutor_formal,
               EXISTS (
                   SELECT 1 FROM app.revisiones_solicitudes rs
                   WHERE rs.proyecto_id = p.id_proyecto AND rs.tipo = 'proposta' AND rs.resuelto_en IS NULL
               ) AND rpp_formal.proyecto_id IS NOT NULL AS proposta_pendent_tutor,
               EXISTS (
                   SELECT 1 FROM app.revisiones_solicitudes rs
                   WHERE rs.proyecto_id = p.id_proyecto AND rs.tipo = 'funcional' AND rs.resuelto_en IS NULL
               ) AND rpp_formal.proyecto_id IS NOT NULL AS funcional_pendent_tutor,
               EXISTS (SELECT 1 FROM app.rel_proyectos_alumnos rpa WHERE rpa.proyecto_id = p.id_proyecto)
                   AND NOT EXISTS (SELECT 1 FROM app.rel_proyectos_alumnos rpa WHERE rpa.proyecto_id = p.id_proyecto AND rpa.grupo_trabajo_confirmado_en IS NULL) AS fase1_grup,
               EXISTS (SELECT 1 FROM app.rel_proyectos_alumnos rpa WHERE rpa.proyecto_id = p.id_proyecto)
                   AND NOT EXISTS (SELECT 1 FROM app.rel_proyectos_alumnos rpa WHERE rpa.proyecto_id = p.id_proyecto AND rpa.compromiso_trabajo_aceptado = false) AS fase1_compromis,
               EXISTS (SELECT 1 FROM app.proyecto_adjuntos pa WHERE pa.proyecto_id = p.id_proyecto AND pa.tipo = 'git') AS te_git_adjunt,
               EXISTS (SELECT 1 FROM app.rel_proyectos_tecnologias rpt WHERE rpt.proyecto_id = p.id_proyecto) AS te_tecnologia
        FROM app.proyectos p
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_id
        LEFT JOIN app.rel_proyectos_profesores rpp_formal
            ON rpp_formal.proyecto_id = p.id_proyecto
           AND rpp_formal.profesor_id = :profesor_id_tutor
           AND rpp_formal.rol = 'tutor'
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

$miembrosPorProyecto = [];
if ($proyectos !== []) {
    $proyectoIds = array_map(static fn (array $p): int => (int) $p['id_proyecto'], $proyectos);
    $marcadores = implode(',', array_fill(0, count($proyectoIds), '?'));
    $stmt = $pdo->prepare("
        SELECT rpa.proyecto_id, a.nombre, a.apellidos
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id IN ($marcadores) AND a.activo = true
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute($proyectoIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $idProjecteFila = (int) $fila['proyecto_id'];
        $miembrosPorProyecto[$idProjecteFila][] = trim((string) $fila['nombre'] . ' ' . (string) $fila['apellidos']);
    }
}

// -----------------------------------------------------------------------------
// 4. Estat de fases: PROTOTIP. Encara no hi ha una definició homogènia de
// quan cada fase és pendent/en procés/completa, i no s'inventa aquí.
// L'única fase amb estat real i inequívoc a nivell de projecte és Memòria
// (app.memoria_seguimiento és per projecte, no per alumne, i el seu 'estado'
// ja té una semàntica pròpia i consolidada). La resta de fases es mostren
// com a marcador visual neutre, sense inventar cap lògica de progrés.
// -----------------------------------------------------------------------------

$tasquesPerProjecte = [];
foreach ($proyectos as $projecte) {
    $idProjecte = (int) $projecte['id_proyecto'];
    $estatFase1 = [
        'grup' => resumTutorValorBoolea($projecte['fase1_grup'] ?? false),
        'compromis' => resumTutorValorBoolea($projecte['fase1_compromis'] ?? false),
    ];
    $tasquesPerProjecte[$idProjecte] = resumTutorTasquesProjecte($projecte, $estatFase1);
}

usort($proyectos, static function (array $a, array $b) use ($miembrosPorProyecto): int {
    $membresA = $miembrosPorProyecto[(int) $a['id_proyecto']] ?? [];
    $membresB = $miembrosPorProyecto[(int) $b['id_proyecto']] ?? [];
    $esGrupA = count($membresA) !== 1;
    $esGrupB = count($membresB) !== 1;
    if ($esGrupA !== $esGrupB) return $esGrupA <=> $esGrupB;
    $comparacio = strcasecmp($membresA[0] ?? (string) $a['nombre'], $membresB[0] ?? (string) $b['nombre']);
    return $comparacio !== 0 ? $comparacio : ((int) $a['id_proyecto'] <=> (int) $b['id_proyecto']);
});

// Dades de la gestió ràpida de tutors. La pertinença al grup és la
// mateixa font de candidats que Administrar projectes; la JOIN amb rpp evita
// oferir una relació que aquesta acció, deliberadament, no ha de crear.
$tutorsPerProjecte = [];
$candidatsTutorPerProjecte = [];
if ($proyectos !== []) {
    $proyectoIds = array_map(static fn (array $p): int => (int) $p['id_proyecto'], $proyectos);
    $marcadores = implode(',', array_fill(0, count($proyectoIds), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id_proyecto, pr.id_profesor, pr.nombre, pr.apellidos, rpp.rol
        FROM app.proyectos p
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
        INNER JOIN app.profesores pr
            ON pr.id_profesor = rpg.profesor_id
           AND pr.activo = true
        INNER JOIN app.rel_proyectos_profesores rpp
            ON rpp.proyecto_id = p.id_proyecto
           AND rpp.profesor_id = pr.id_profesor
           AND rpp.rol IN ('tutor', 'cotutor')
        WHERE p.id_proyecto IN ($marcadores)
        ORDER BY pr.apellidos, pr.nombre, pr.id_profesor
    ");
    $stmt->execute($proyectoIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $idProjecteFila = (int) $fila['id_proyecto'];
        $candidat = [
            'id_profesor' => (int) $fila['id_profesor'],
            'nombre' => trim((string) $fila['nombre'] . ' ' . (string) $fila['apellidos']),
        ];
        $candidatsTutorPerProjecte[$idProjecteFila][] = $candidat;
        if ((string) $fila['rol'] === 'tutor') {
            $tutorsPerProjecte[$idProjecteFila][] = $candidat;
        }
    }
}

$projectesSenseTutor = array_values(array_filter(
    $proyectos,
    static fn (array $p): bool => ($tutorsPerProjecte[(int) $p['id_proyecto']] ?? []) === []
));
$modeTutorsManual = isset($_GET['tutors']) && (string) $_GET['tutors'] === '1';
$projectesGestioTutors = $modeTutorsManual ? $proyectos : $projectesSenseTutor;
$mostrarGestioTutors = $modeTutorsManual || $projectesSenseTutor !== [];
$feedbackTutorActualitzat = isset($_GET['tutor_actualitzat']) && (string) $_GET['tutor_actualitzat'] === '1';
$errorGestioTutors = isset($_SESSION['resum_tutors_error']) ? (string) $_SESSION['resum_tutors_error'] : '';
unset($_SESSION['resum_tutors_error']);

// -----------------------------------------------------------------------------
// 5. Autoseguiments pendents del grup seleccionat: mateixa definició exacta
// que ja fa servir Autoseguiment (setmana tancada, valoracion_tutor NULL, 0
// compta com a revisada).
// -----------------------------------------------------------------------------

$autoseguimentPendents = 0;
if ($grupoId > 0) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM app.seguimiento_alumnos sa
        INNER JOIN app.proyectos p ON p.id_proyecto = sa.proyecto_id
        INNER JOIN app.rel_alumnos_grupos rag
            ON rag.alumno_id = sa.alumno_id AND rag.curso_academico = p.curso_academico
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        WHERE rag.grupo_id = :grupo_id
          AND rag.curso_academico = :curso_academico
          AND a.activo = true
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
          AND sa.fecha_fin < CURRENT_DATE
          AND sa.valoracion_tutor IS NULL
          AND EXISTS (
              SELECT 1
              FROM app.rel_proyectos_profesores rpp
              WHERE rpp.proyecto_id = sa.proyecto_id
                AND rpp.profesor_id = :profesor_id_tutor
                AND rpp.rol = 'tutor'
          )
    ");
    $stmt->execute([
        ':grupo_id' => $grupoId,
        ':curso_academico' => $cursoAcademico,
        ':profesor_id_tutor' => $profesorId,
    ]);
    $autoseguimentPendents = (int) $stmt->fetchColumn();
}

// -----------------------------------------------------------------------------
// 6. Sol·licituds pendents: es mesclen les dues fonts reals. Memòria usa
// memoria_seguimiento.estado = 'revision_solicitada'; les revisions puntuals
// de fases usen app.revisiones_solicitudes i el seu tipus estructural.
// -----------------------------------------------------------------------------

$solicitudsMemoria = [];
$solicitudsProposta = [];
if ($proyectos !== []) {
    $proyectoIds = array_map(static fn (array $p): int => (int) $p['id_proyecto'], $proyectos);
    $marcadores = implode(',', array_fill(0, count($proyectoIds), '?'));

    $stmt = $pdo->prepare("
        SELECT ms.id_memoria_seguimiento, ms.proyecto_id, ms.fecha_solicitud_revision,
               me.orden, me.titulo
        FROM app.memoria_seguimiento ms
        INNER JOIN app.memoria_estructura me ON me.id_memoria_estructura = ms.memoria_estructura_id
        WHERE ms.proyecto_id IN ($marcadores)
          AND ms.estado = 'revision_solicitada'
          AND EXISTS (
              SELECT 1
              FROM app.rel_proyectos_profesores rpp
              WHERE rpp.proyecto_id = ms.proyecto_id
                AND rpp.profesor_id = ?
                AND rpp.rol = 'tutor'
          )
        ORDER BY ms.fecha_solicitud_revision DESC
    ");
    $stmt->execute([...$proyectoIds, $profesorId]);
    $solicitudsMemoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT rs.id_revision_solicitud, rs.proyecto_id, rs.solicitado_en, rs.titulo, rs.tipo
        FROM app.revisiones_solicitudes rs
        WHERE rs.proyecto_id IN ($marcadores)
          AND rs.tipo IN ('proposta', 'funcional', 'entorn_desenvolupament')
          AND rs.resuelto_en IS NULL
          AND (
              rs.tipo = 'entorn_desenvolupament'
              OR EXISTS (
                  SELECT 1
                  FROM app.rel_proyectos_profesores rpp
                  WHERE rpp.proyecto_id = rs.proyecto_id
                    AND rpp.profesor_id = ?
                    AND rpp.rol = 'tutor'
              )
          )
        ORDER BY rs.solicitado_en DESC
    ");
    $stmt->execute([...$proyectoIds, $profesorId]);
    $solicitudsProposta = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Llista unificada, ordenada per data (més recent primer). Cada element ja
// porta l'enllaç final resolt (mai es construeix cap ruta a partir d'una
// clau crua): Memòria manté el seu enllaç d'ancora existent i cada revisió
// de fase enllaça a la vista contextual corresponent del professor.
$solicitudsPendents = [];
$revisionsMemoriaPendentsPerProjecte = [];
foreach ($solicitudsMemoria as $s) {
    $idProySolicitud = (int) $s['proyecto_id'];
    $revisionsMemoriaPendentsPerProjecte[$idProySolicitud] = true;
    $solicitudsPendents[] = [
        'fecha' => (string) $s['fecha_solicitud_revision'],
        'origen' => 'Memòria',
        'linea2' => (int) $s['orden'] . '. ' . (string) $s['titulo'],
        'es_memoria' => true,
        'href' => '/revisio-memoria/projecte/' . $idProySolicitud . '#apartat-' . (int) $s['id_memoria_seguimiento'],
        'proyecto_id' => $idProySolicitud,
    ];
}
foreach ($solicitudsProposta as $s) {
    $idProySolicitud = (int) $s['proyecto_id'];
    $esFuncional = $s['tipo'] === 'funcional';
    $esEntorn = $s['tipo'] === 'entorn_desenvolupament';
    $solicitudsPendents[] = [
        'fecha' => (string) $s['solicitado_en'],
        'origen' => $esFuncional ? 'Document funcional' : ($esEntorn ? 'Preparació de l’entorn' : 'Proposta'),
        'linea2' => (string) $s['titulo'],
        'es_memoria' => false,
        'href' => $esFuncional
            ? '/projecte/' . $idProySolicitud . '/fases/fase-3/document-funcional'
            : ($esEntorn
                ? '/projecte/' . $idProySolicitud . '/fases/fase-5/preparacio-entorn'
                : '/projecte/' . $idProySolicitud . '/fases/fase-2/proposta'),
        'proyecto_id' => $idProySolicitud,
    ];
}
usort($solicitudsPendents, static fn (array $a, array $b): int => strcmp($b['fecha'], $a['fecha']));

function resumTutorData(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $marca = strtotime($data);
    return $marca !== false ? date('d/m', $marca) : $data;
}

// -----------------------------------------------------------------------------
// 7. Pendents agregats per pill de GRUP: suma d'autoseguiments pendents +
// sol·licituds de memòria pendents + sol·licituds de proposta pendents,
// calculada per a TOTS els grups del professor (no només el seleccionat),
// amb els mateixos criteris de dalt.
// És una xifra específica d'aquest tauler (barreja dues fonts a propòsit,
// perquè aquí representen conjuntament "feina pendent del tutor"); les
// pills d'Autoseguiment i de Memòria mantenen cadascuna la seva pròpia xifra
// d'un sol origen, sense cap canvi.
// -----------------------------------------------------------------------------

$pendientesPorGrupo = [];
if ($idsGruposAutorizados !== []) {
    $stmt = $pdo->prepare("
        SELECT rag.grupo_id, COUNT(*) AS pendientes
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = rag.grupo_id
           AND rpg.curso_academico = rag.curso_academico
           AND rpg.profesor_id = :profesor_id
        INNER JOIN app.seguimiento_alumnos sa ON sa.alumno_id = rag.alumno_id
        INNER JOIN app.proyectos p
            ON p.id_proyecto = sa.proyecto_id
           AND p.curso_academico = rag.curso_academico
           AND p.estado = 'activo'
        WHERE rag.curso_academico = :curso_academico
          AND a.activo = true
          AND sa.fecha_fin < CURRENT_DATE
          AND sa.valoracion_tutor IS NULL
          AND EXISTS (
              SELECT 1
              FROM app.rel_proyectos_profesores rpp
              WHERE rpp.proyecto_id = sa.proyecto_id
                AND rpp.profesor_id = :profesor_id_tutor
                AND rpp.rol = 'tutor'
          )
        GROUP BY rag.grupo_id
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':profesor_id_tutor' => $profesorId,
        ':curso_academico' => $cursoAcademico,
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $pendientesPorGrupo[(int) $fila['grupo_id']] = (int) $fila['pendientes'];
    }

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
        $idGrupoFila = (int) $fila['grupo_id'];
        $pendientesPorGrupo[$idGrupoFila] = ($pendientesPorGrupo[$idGrupoFila] ?? 0) + (int) $fila['pendientes'];
    }

    $stmt = $pdo->prepare("
        SELECT p.grupo_id, COUNT(*) AS pendientes
        FROM app.revisiones_solicitudes rs
        INNER JOIN app.proyectos p ON p.id_proyecto = rs.proyecto_id
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_id
        WHERE p.curso_academico = :curso_academico
          AND p.estado = 'activo'
          AND rs.tipo IN ('proposta', 'funcional', 'entorn_desenvolupament')
          AND rs.resuelto_en IS NULL
          AND (
              rs.tipo = 'entorn_desenvolupament'
              OR EXISTS (
                  SELECT 1
                  FROM app.rel_proyectos_profesores rpp
                  WHERE rpp.proyecto_id = rs.proyecto_id
                    AND rpp.profesor_id = :profesor_id_tutor
                    AND rpp.rol = 'tutor'
              )
          )
        GROUP BY p.grupo_id
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':profesor_id_tutor' => $profesorId,
        ':curso_academico' => $cursoAcademico,
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $idGrupoFila = (int) $fila['grupo_id'];
        $pendientesPorGrupo[$idGrupoFila] = ($pendientesPorGrupo[$idGrupoFila] ?? 0) + (int) $fila['pendientes'];
    }
}
?>
<script>window.PAGE_TITLE = 'Resum';</script>
<style>
.resum-tutor-nav-pill {
    font-size: .8125rem;
}
.resum-pendents-badge {
    background: #F59E0B;
    color: #fff;
}
.resum-panell {
    background: #fff;
    overflow: hidden;
}
.resum-panell-cap {
    padding: 1.5rem 1.5rem 1rem;
}
.resum-panell-contingut {
    padding: 0 1.5rem 1.5rem;
}
.resum-fases-table th, .resum-fases-table td {
    font-size: .82rem;
    vertical-align: middle;
}
.resum-fases-table > :not(caption) > * > * {
    padding: .75rem 1rem;
}
.resum-fases-table > :not(caption) > * > :first-child {
    padding-left: 1.5rem;
}
.resum-fases-table > :not(caption) > * > :last-child {
    padding-right: 1.5rem;
}
.resum-fases-table thead > tr > th {
    --bs-table-bg: #f7e6ea;
    padding-top: .45rem;
    padding-bottom: .45rem;
    background-color: var(--bs-table-bg);
    color: #970a2c;
    font-weight: 600;
    white-space: nowrap;
}
.resum-fases-table thead > tr > th:last-child > span {
    display: inline-block;
}
.resum-fases-table thead > tr > th:last-child > span,
.resum-fases-table tbody > tr > td:last-child .resum-fase-indicadors {
    transform: translateX(-8px);
}
.resum-fases-table td.text-muted {
    font-size: 1rem;
}
.resum-fases-table tbody tr:nth-child(odd) > * {
    --bs-table-bg: #fff;
    background-color: var(--bs-table-bg);
}
.resum-fases-table tbody tr:nth-child(even) > * {
    --bs-table-bg: #f8f9fa;
    background-color: var(--bs-table-bg);
}
.resum-fases-table th:first-child,
.resum-fases-table td:first-child {
    position: sticky;
    left: 0;
    z-index: 1;
}
.resum-fases-table thead th:first-child {
    z-index: 2;
    background: #f7e6ea;
}
.resum-fase-indicadors {
    display: flex;
    justify-content: center;
    align-items: center;
    align-content: center;
    flex-wrap: wrap;
    gap: .3rem;
    min-width: 2rem;
}
.resum-fase-indicador {
    display: inline-flex;
    width: 1.25rem;
    height: 1.25rem;
    align-items: center;
    justify-content: center;
    border: 1px solid #adb5bd;
    border-radius: 50%;
    background: #f1f3f5;
    color: #6c757d;
    font-size: .7rem;
    line-height: 1;
    text-decoration: none;
    transition: background-color .15s ease, border-color .15s ease;
}
.resum-fase-indicador:hover,
.resum-fase-indicador:focus-visible {
    background: #e2e6ea;
    border-color: #6c757d;
    color: #495057;
}
.resum-fase-indicador--completat {
    border-color: #198754;
    background: #198754;
    color: #fff;
}
.resum-fase-indicador--completat:hover,
.resum-fase-indicador--completat:focus-visible {
    border-color: #146c43;
    background: #146c43;
    color: #fff;
}
.resum-fase-indicador--atencio {
    border-color: #e0b94a;
    background: #ffc107;
    color: #4a3a0a;
}
.resum-fase-indicador--atencio:hover,
.resum-fase-indicador--atencio:focus-visible {
    border-color: #cc9a06;
    background: #e0a800;
    color: #4a3a0a;
}
.resum-projecte-membres {
    display: grid;
    align-content: center;
    gap: .3rem;
    line-height: 1.35;
}
.resum-fases-table tbody tr.resum-projecte-fila--grup > td {
    padding-top: 1rem;
    padding-bottom: 1rem;
}
.resum-caixa-titol {
    color: #970A2C;
}
.resum-link-secundari {
    color: #496B88;
    text-decoration: none;
    transition: color .15s ease;
}
.resum-link-secundari:hover,
.resum-link-secundari:focus-visible {
    color: #35506b;
    text-decoration: underline;
}
.resum-tutors-component { overflow: hidden; }
.resum-tutors-cap { padding: .65rem 1rem; }
.resum-tutors-cap--pendent {
    background: var(--bs-warning-bg-subtle, #fff3cd);
    color: var(--bs-warning-text-emphasis, #664d03);
}
.resum-tutors-cap--manual {
    background: #f8f9fa;
    color: #495057;
}
.resum-tutors-fila {
    display: grid;
    grid-template-columns: minmax(12rem, .8fr) minmax(16rem, 1.2fr);
    align-items: stretch;
}
.resum-tutors-fila:nth-child(even) { background: #f8f9fa; }
.resum-tutors-identitat,
.resum-tutors-opcions {
    display: flex;
    align-items: center;
    min-width: 0;
    padding: .7rem 1rem;
}
.resum-tutors-opcions { border-left: 1px solid #dee2e6; }
@media (max-width: 767.98px) {
    .resum-tutors-fila { grid-template-columns: 1fr; }
    .resum-tutors-identitat { padding-bottom: .35rem; }
    .resum-tutors-opcions {
        border-top: 1px solid #dee2e6;
        border-left: 0;
        padding-top: .55rem;
    }
}
</style>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Resum</h1>
        <p class="text-muted mb-0">Mapa general dels teus projectes i la feina pendent, per grup.</p>
    </div>

    <?php if ($grupos === []): ?>
        <div class="alert alert-warning">No tens cap grup assignat aquest curs.</div>
    <?php else: ?>
        <?php if (count($grupos) >= 2): ?>
            <!-- ── Navegació per grups: color del cicle real (app.ciclos.color) ── -->
            <div class="mb-4">
                <div class="text-uppercase small fw-semibold text-muted mb-2">Grup</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($grupos as $grupo): ?>
                        <?php
                        $grupActiu = (int) $grupo['id_grupo'] === $grupoId;
                        $colorCiclo = (string) $grupo['color'];
                        $classesGrup = $grupActiu ? clasesColorCicloSolid($colorCiclo) : clasesColorCiclo($colorCiclo);
                        $pendentsGrup = $pendientesPorGrupo[(int) $grupo['id_grupo']] ?? 0;
                        ?>
                        <a href="/resum?grupo_id=<?= (int) $grupo['id_grupo'] ?>"
                           class="badge rounded-pill border px-3 py-2 fw-semibold text-decoration-none resum-tutor-nav-pill <?= $classesGrup ?>">
                            <?= htmlspecialchars(trim((string) $grupo['abr'] . ' ' . (string) $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($pendentsGrup > 0): ?>
                                <span class="badge rounded-pill resum-pendents-badge ms-1"><?= $pendentsGrup ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- ══════════════════════════════════════════════════════════
                 ZONA PRINCIPAL: mapa de projectes i fases
            ══════════════════════════════════════════════════════════ -->
            <div class="col-lg-9">
                <div class="card resum-panell shadow-sm border-0 rounded-4">
                    <div class="resum-panell-cap">
                        <h2 class="h6 text-uppercase resum-caixa-titol mb-1">
                            Projectes · <?= htmlspecialchars(trim((string) ($grupoSeleccionado['abr'] ?? '') . ' ' . (string) ($grupoSeleccionado['grupo'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                    </div>
                    <?php if ($proyectos === []): ?>
                        <div class="resum-panell-contingut">
                            <p class="text-muted mb-0">Aquest grup no té cap projecte actiu.</p>
                        </div>
                    <?php elseif ($fasesArquitectura === []): ?>
                        <div class="resum-panell-contingut">
                            <p class="text-muted mb-3">Aquest cicle encara no té un recorregut de fases definit.</p>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($proyectos as $proyecto): ?>
                                    <?php $nomsMembres = $miembrosPorProyecto[(int) $proyecto['id_proyecto']] ?? []; ?>
                                    <li class="mb-1"><?= htmlspecialchars($nomsMembres !== [] ? implode(' · ', $nomsMembres) : (string) $proyecto['nombre'], ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <?php
                        $etiquetesFasesResum = [
                            1 => 'Idees',
                            2 => 'Proposta',
                            3 => 'Funcional',
                            4 => 'Gestió',
                            5 => 'Desenvolupament',
                            6 => 'Memòria',
                            7 => 'Defensa',
                        ];
                        ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 resum-fases-table">
                                <thead class="table-light">
                                    <tr>
                                        <th aria-label="Projecte"></th>
                                        <?php foreach ($fasesArquitectura as $numeroFase => $fase): ?>
                                            <th class="text-center" title="<?= htmlspecialchars('Fase ' . $numeroFase . ' · ' . str_replace("\n", ' ', $fase['titulo']), ENT_QUOTES, 'UTF-8') ?>"><span><?= htmlspecialchars($etiquetesFasesResum[(int) $numeroFase] ?? (string) $fase['titulo'], ENT_QUOTES, 'UTF-8') ?></span></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proyectos as $proyecto): ?>
                                        <?php
                                        $idProy = (int) $proyecto['id_proyecto'];
                                        $nomsMembres = $miembrosPorProyecto[$idProy] ?? [];
                                        ?>
                                        <tr<?= count($nomsMembres) > 1 ? ' class="resum-projecte-fila--grup"' : '' ?>>
                                            <!-- Entrada general al recorregut del projecte: el mateix nivell
                                                 contextual que "Fases del projecte" per a l'alumnat (mai
                                                 directament a la primera fase) — el recorregut de fases
                                                 pertany al projecte, no al rol. -->
                                            <td class="fw-semibold">
                                                <a href="/projecte/<?= $idProy ?>/fases" class="resum-link-secundari">
                                                    <?php if ($nomsMembres !== []): ?>
                                                        <span class="resum-projecte-membres">
                                                            <?php foreach ($nomsMembres as $nomMembre): ?>
                                                                <span><?= htmlspecialchars($nomMembre, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endforeach; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars((string) $proyecto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                            <?php foreach ($fasesArquitectura as $numeroFase => $fase): ?>
                                                <td class="text-center">
                                                    <div class="resum-fase-indicadors">
                                                        <?php foreach ($tasquesPerProjecte[$idProy][(int) $numeroFase] ?? [] as $indexTasca => $tasca): ?>
                                                            <?php
                                                            $pendentTutor = !$tasca['completada'] && ($tasca['pendent_tutor'] ?? false);
                                                            $estatText = $tasca['completada'] ? 'Completada' : ($pendentTutor ? 'Pendent del tutor' : 'Pendent');
                                                            $classeEstat = $tasca['completada']
                                                                ? ' resum-fase-indicador--completat'
                                                                : ($pendentTutor ? ' resum-fase-indicador--atencio' : '');
                                                            ?>
                                                            <a href="<?= htmlspecialchars($tasca['href'], ENT_QUOTES, 'UTF-8') ?>"
                                                               class="resum-fase-indicador<?= $classeEstat ?>"
                                                               title="<?= htmlspecialchars($tasca['nom'] . ' · ' . $estatText, ENT_QUOTES, 'UTF-8') ?>"
                                                               aria-label="<?= htmlspecialchars($tasca['nom'] . ' · ' . $estatText, ENT_QUOTES, 'UTF-8') ?>">
                                                                <?php if ($tasca['completada']): ?>
                                                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                                <?php elseif ($pendentTutor): ?>
                                                                    <i class="bi bi-exclamation-lg" aria-hidden="true"></i>
                                                                <?php else: ?>
                                                                    <span class="visually-hidden">Pendent</span>
                                                                <?php endif; ?>
                                                            </a>
                                                            <?php if ((int) $numeroFase === 6 && $indexTasca === 0 && isset($revisionsMemoriaPendentsPerProjecte[$idProy])): ?>
                                                                <a href="/revisio-memoria/projecte/<?= $idProy ?>"
                                                                   class="resum-fase-indicador resum-fase-indicador--atencio"
                                                                   title="Revisió de memòria pendent"
                                                                   aria-label="Revisió de memòria pendent · Acció del tutor">
                                                                    <i class="bi bi-exclamation-lg" aria-hidden="true"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($proyectos !== []): ?>
                    <div class="text-end mt-2">
                        <a href="/resum?grupo_id=<?= $grupoId ?><?= $modeTutorsManual ? '' : '&amp;tutors=1' ?>" class="small resum-link-secundari">
                            <?= $modeTutorsManual ? 'Tancar tutors' : 'Tutors' ?>
                        </a>
                    </div>
                    <?php if (!$mostrarGestioTutors && $feedbackTutorActualitzat): ?>
                        <p class="small text-success fw-semibold text-end mt-2 mb-0" role="status">Tutor actualitzat</p>
                    <?php elseif (!$mostrarGestioTutors && $errorGestioTutors !== ''): ?>
                        <p class="small text-danger fw-semibold text-end mt-2 mb-0" role="alert"><?= htmlspecialchars($errorGestioTutors, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($mostrarGestioTutors): ?>
                        <?php require __DIR__ . '/resum-tutor_tutors.php'; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 COLUMNA DRETA: pendents
            ══════════════════════════════════════════════════════════ -->
            <div class="col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                    <h2 class="h6 text-uppercase resum-caixa-titol mb-3">Autoseguiments pendents</h2>
                    <?php if ($autoseguimentPendents > 0): ?>
                        <a href="/seguiment-setmanal/grup/<?= $grupoId ?>" class="resum-solicitud-item text-decoration-none">
                            <span class="h3 mb-0 text-body"><?= $autoseguimentPendents ?></span>
                            <span class="text-muted"> pendents de revisar</span>
                        </a>
                    <?php else: ?>
                        <p class="py-2 mb-0">
                            <span class="h3 mb-0">0</span>
                            <span class="text-muted"> pendents de revisar</span>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="card shadow-sm border-0 rounded-4 p-4">
                    <h2 class="h6 text-uppercase resum-caixa-titol border-bottom pb-2 mb-2">Sol·licituds pendents</h2>
                    <?php if ($solicitudsPendents === []): ?>
                        <p class="text-muted mb-0">No tens cap sol·licitud pendent.</p>
                    <?php else: ?>
                        <div class="resum-solicituds-llista">
                            <?php foreach ($solicitudsPendents as $solicitud): ?>
                                <?php $nomsMembres = $miembrosPorProyecto[$solicitud['proyecto_id']] ?? []; ?>
                                <div class="resum-solicitud-fila">
                                    <a href="<?= htmlspecialchars($solicitud['href'], ENT_QUOTES, 'UTF-8') ?>" class="resum-solicitud-item text-decoration-none resum-link-secundari">
                                        <?php if ($solicitud['es_memoria']): ?>
                                            <div class="resum-solicitud-context small text-uppercase text-muted fw-semibold mb-1">Memòria</div>
                                        <?php endif; ?>
                                        <div class="fw-semibold mb-1"><?= htmlspecialchars($solicitud['linea2'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="small">
                                            <span class="fw-semibold text-body"><?= htmlspecialchars($nomsMembres !== [] ? implode(' / ', $nomsMembres) : 'Projecte', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-muted"> · <?= htmlspecialchars(resumTutorData($solicitud['fecha']), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
