<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}

require_once __DIR__ . '/grup-actiu_funcions.php';

$profesorId = (int) $_SESSION['professor_id'];
$cursoAcademico = cursoAcademicoActual();

// ── Etiquetes i format de dates: mateixa lògica que fa servir
// l'Autoseguiment de l'alumnat, duplicada aquí perquè aquell fitxer no es toca. ──
function autoseguimentTutorEtiquetaCompliment(?int $valor): string
{
    return match ($valor) {
        0 => 'No',
        1 => 'Parcialment',
        2 => 'Sí',
        default => '',
    };
}

// Classe de color de la pill de compliment, mateixos tons que el selector
// interactiu de l'Autoseguiment de l'alumnat (vegeu comentari de sincronització
// al <style> d'aquesta pàgina).
function autoseguimentTutorClasseCompliment(?int $valor): string
{
    return match ($valor) {
        0 => 'autoseguiment-pill-no',
        1 => 'autoseguiment-pill-parcial',
        2 => 'autoseguiment-pill-si',
        default => '',
    };
}

// Etiquetes completes de la valoració del tutor. Mai s'abreugen a la interfície.
function autoseguimentTutorEtiquetaValoracioTutor(?int $valor): string
{
    return match ($valor) {
        0 => 'Sense avanç',
        1 => 'Poc avanç',
        2 => 'Avanç adequat',
        3 => 'Avanç destacat',
        default => '',
    };
}

function autoseguimentTutorData(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $marca = strtotime($data);
    return $marca !== false ? date('d/m/Y', $marca) : $data;
}

function autoseguimentTutorIntervalData(?string $dataInici, ?string $dataFi): string
{
    $inici = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $dataInici, 0, 10));
    $fi = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $dataFi, 0, 10));
    if ($inici === false || $fi === false) {
        return '';
    }

    $mesos = [
        1 => 'de gener', 2 => 'de febrer', 3 => 'de març', 4 => 'd’abril',
        5 => 'de maig', 6 => 'de juny', 7 => 'de juliol', 8 => 'd’agost',
        9 => 'de setembre', 10 => 'd’octubre', 11 => 'de novembre', 12 => 'de desembre',
    ];
    $diaInici = (int) $inici->format('j');
    $diaFi = (int) $fi->format('j');
    $mesInici = (int) $inici->format('n');
    $mesFi = (int) $fi->format('n');

    return $mesInici === $mesFi
        ? 'Del ' . $diaInici . ' al ' . $diaFi . ' ' . $mesos[$mesFi]
        : 'Del ' . $diaInici . ' ' . $mesos[$mesInici] . ' al ' . $diaFi . ' ' . $mesos[$mesFi];
}

// ── Fitxa de només lectura d'una setmana ja tancada, amb la zona afegida de
// valoració/comentari del tutor al peu. Es reutilitza per a totes les fitxes
// tancades de l'alumne seleccionat. ─────────────────────────────────────────
function autoseguimentTutorFitxa(array $fila, string $classeBloc, string $objectiuAnteriorText, bool $potEditar): void
{
    $complimentValor = $fila['cumplimiento_objetivo_anterior'] !== null ? (int) $fila['cumplimiento_objetivo_anterior'] : null;
    $complimentText = autoseguimentTutorEtiquetaCompliment($complimentValor);
    // El valor emmagatzemat es llegeix explícitament com NULL/no-NULL: 0
    // ("Sense avanç") és una valoració vàlida i mai s'ha de confondre amb "sense valorar".
    $valoracionActual = $fila['valoracion_tutor'] !== null ? (int) $fila['valoracion_tutor'] : null;
    $valoracioBlocClasse = match ($valoracionActual) {
        0 => 'autoseguiment-valoracio-sense',
        1 => 'autoseguiment-valoracio-poc',
        2 => 'autoseguiment-valoracio-adequat',
        3 => 'autoseguiment-valoracio-destacat',
        default => '',
    };
    $comentariTutor = trim((string) ($fila['comentario_tutor'] ?? ''));
    $valoracioText = autoseguimentTutorEtiquetaValoracioTutor($valoracionActual);
    $idSeguimiento = (int) $fila['id_seguimiento'];
    ?>
    <section class="bloc <?= htmlspecialchars($classeBloc, ENT_QUOTES, 'UTF-8') ?> mb-0 <?= $valoracioBlocClasse ?>">
        <div class="bloc-contingut">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div class="bloc-tipus mb-0">Setmana <?= (int) $fila['semana'] ?></div>
                <span class="pill-neutral autoseguiment-data-pill <?= $valoracioBlocClasse ?>">
                    <i class="bi bi-calendar-week" aria-hidden="true"></i>
                    <?= htmlspecialchars(autoseguimentTutorIntervalData($fila['fecha_inicio'], $fila['fecha_fin']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <?php if ($objectiuAnteriorText !== ''): ?>
                <p class="mb-2"><strong class="autoseguiment-apartat-titol">Objectius de la setmana:</strong>
                    <br><?= nl2br(htmlspecialchars($objectiuAnteriorText, ENT_QUOTES, 'UTF-8'), false) ?>
                </p>
            <?php endif; ?>

            <p class="mb-2"><strong class="autoseguiment-apartat-titol">Treball realitzat:</strong>
                <?php if (trim((string) ($fila['trabajo_realizado'] ?? '')) !== ''): ?>
                    <br><?= nl2br(htmlspecialchars((string) $fila['trabajo_realizado'], ENT_QUOTES, 'UTF-8'), false) ?>
                <?php else: ?>
                    <span class="text-muted fst-italic">No es va indicar.</span>
                <?php endif; ?>
            </p>

            <p class="mb-2"><strong class="autoseguiment-apartat-titol">Incidències:</strong>
                <?php if (trim((string) ($fila['incidencias'] ?? '')) !== ''): ?>
                    <br><?= nl2br(htmlspecialchars((string) $fila['incidencias'], ENT_QUOTES, 'UTF-8'), false) ?>
                <?php else: ?>
                    <span class="text-muted fst-italic">No es va indicar.</span>
                <?php endif; ?>
            </p>

            <?php if ($objectiuAnteriorText !== ''): ?>
                <p class="mb-3">
                    <strong class="autoseguiment-apartat-titol">Objectius assolits?</strong><br>
                    <?php if ($complimentText !== ''): ?>
                        <?= htmlspecialchars($complimentText, ENT_QUOTES, 'UTF-8') ?>.
                    <?php else: ?>
                        <span class="text-muted fst-italic">Encara no ha respost.</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════════════════════
                 ZONA DEL TUTOR: separada del contingut de l'alumnat.
                 Els controls només es mostren quan $potEditar és cert. El
                 servidor torna a comprovar-ho a cada escriptura.
            ══════════════════════════════════════════════════════════ -->
            <?php if ($potEditar): ?>
            <div class="tutor-valoracio-bloc mt-3 pt-3 border-top" data-id-seguiment="<?= $idSeguimiento ?>">
                <div>
                    <strong class="autoseguiment-apartat-titol">Valoració del tutor:</strong>
                    <div class="d-flex flex-wrap gap-2 mt-1 tutor-valoracio-pills">
                        <button type="button"
                                class="tutor-valoracio-pill tutor-valoracio-sense <?= $valoracionActual === 0 ? 'seleccionada' : '' ?>"
                                data-valor="0" <?= $potEditar ? '' : 'disabled' ?>>Sense avanç</button>
                        <button type="button"
                                class="tutor-valoracio-pill tutor-valoracio-poc <?= $valoracionActual === 1 ? 'seleccionada' : '' ?>"
                                data-valor="1" <?= $potEditar ? '' : 'disabled' ?>>Poc avanç</button>
                        <button type="button"
                                class="tutor-valoracio-pill tutor-valoracio-adequat <?= $valoracionActual === 2 ? 'seleccionada' : '' ?>"
                                data-valor="2" <?= $potEditar ? '' : 'disabled' ?>>Avanç adequat</button>
                        <button type="button"
                                class="tutor-valoracio-pill tutor-valoracio-destacat <?= $valoracionActual === 3 ? 'seleccionada' : '' ?>"
                                data-valor="3" <?= $potEditar ? '' : 'disabled' ?>>Avanç destacat</button>
                    </div>
                </div>

                <div class="tutor-comentari mt-3">
                    <div class="tutor-comentari-lectura <?= $comentariTutor === '' ? 'd-none' : '' ?>">
                        <p class="mb-1 tutor-comentari-text"><?= nl2br(htmlspecialchars($comentariTutor, ENT_QUOTES, 'UTF-8'), false) ?></p>
                        <?php if ($potEditar): ?>
                            <button type="button" class="btn btn-link btn-sm ps-0 tutor-comentari-editar-btn">Editar comentari</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($potEditar): ?>
                        <button type="button" class="btn btn-link btn-sm ps-0 tutor-comentari-afegir-btn <?= $comentariTutor !== '' ? 'd-none' : '' ?>">+ Afegir comentari</button>
                        <div class="tutor-comentari-editor d-none">
                            <textarea class="form-control form-control-sm textarea-neutral auto-grow tutor-comentari-textarea" rows="3" maxlength="4000"><?= htmlspecialchars($comentariTutor, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-fase btn-puig tutor-comentari-guardar-btn">Guardar comentari</button>
                                <button type="button" class="btn btn-fase btn-outline-secondary tutor-comentari-cancelar-btn">Cancel·lar</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($valoracioText !== '' || $comentariTutor !== ''): ?>
                <div class="mt-3 pt-3 border-top">
                    <?php if ($valoracioText !== ''): ?>
                        <p class="mb-0"><strong class="autoseguiment-apartat-titol">Valoració del tutor:</strong><br>
                            <?= htmlspecialchars($valoracioText, ENT_QUOTES, 'UTF-8') ?>.
                        </p>
                    <?php endif; ?>
                    <?php if ($comentariTutor !== ''): ?>
                        <p class="mb-0 <?= $valoracioText !== '' ? 'mt-2' : '' ?>"><?= nl2br(htmlspecialchars($comentariTutor, ENT_QUOTES, 'UTF-8'), false) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

// -----------------------------------------------------------------------------
// 1. Grups que el professor tutoritza aquest curs. Mateix patró que
// projectes-grup.php i alumnat-tutor.php: rel_profesores_grupos és l'única
// font d'autorització de grup.
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

// Pendents totals per grup (per a la pill de GRUP): agregació del mateix
// criteri de "pendent" que ja fa servir cada alumne (setmana tancada sense
// valoracion_tutor), sumat per a tots els grups que tutoritza el professor,
// no només el seleccionat. Mateixos filtres de curs/projecte actiu/alumnat
// actiu que la resta de la pàgina.
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
$pendientesPorGrupo = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
    $pendientesPorGrupo[(int) $fila['grupo_id']] = (int) $fila['pendientes'];
}

$grupoIdSolicitat = isset($_GET['grupo_id']) ? (int) $_GET['grupo_id'] : 0;
$grupoId = resoldreGrupActiuTutor($grupos, $grupoIdSolicitat);

// -----------------------------------------------------------------------------
// 2. Alumnat del grup seleccionat. La mateixa JOIN amb rel_profesores_grupos
// és qui autoritza: si el grup no fos del professor, la consulta no
// retornaria cap fila encara que $grupoId fos manipulat.
// -----------------------------------------------------------------------------

$alumnos = [];
if ($grupoId > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, a.nombre, a.apellidos
        FROM app.rel_alumnos_grupos rag
        INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = rag.grupo_id
           AND rpg.curso_academico = rag.curso_academico
           AND rpg.profesor_id = :profesor_id
        WHERE rag.grupo_id = :grupo_id
          AND rag.curso_academico = :curso_academico
          AND a.activo = true
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':grupo_id' => $grupoId,
        ':curso_academico' => $cursoAcademico,
    ]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// L'alumne sol·licitat només s'accepta si pertany a la llista ja autoritzada
// del grup resolt; si no, es recorre determinísticament al primer alumne.
$alumnoIdSolicitat = isset($_GET['alumno_id']) ? (int) $_GET['alumno_id'] : 0;
$alumnoId = 0;
foreach ($alumnos as $alumno) {
    if ((int) $alumno['id_alumno'] === $alumnoIdSolicitat) {
        $alumnoId = $alumnoIdSolicitat;
        break;
    }
}
if ($alumnoId === 0 && $alumnos !== []) {
    $alumnoId = (int) $alumnos[0]['id_alumno'];
}

// -----------------------------------------------------------------------------
// 2b. Projecte actual i capacitat tutor/cotutor de cada alumne del grup, a més
// del recompte de setmanes pendents de valorar. Les consultes es limiten als
// alumnes ja autoritzats de $alumnos: mai a identificadors arribats per GET.
// Les consultes agregades eviten repetir-les per alumne (N+1).
// -----------------------------------------------------------------------------

$proyectoPorAlumno = [];
$tutorizaPorAlumno = [];
$pendientesPorAlumno = [];
if ($alumnos !== []) {
    $alumnoIds = array_map(static fn (array $a): int => (int) $a['id_alumno'], $alumnos);
    $marcadores = implode(',', array_fill(0, count($alumnoIds), '?'));

    // Projecte actiu de cada alumne en el curs actual (mateix criteri que el
    // punt 3): serveix únicament per agrupar visualment, no per navegar-hi.
    $stmt = $pdo->prepare("
        SELECT rpa.alumno_id, rpa.proyecto_id,
               EXISTS (
                   SELECT 1
                   FROM app.rel_proyectos_profesores rpp
                   WHERE rpp.proyecto_id = rpa.proyecto_id
                     AND rpp.profesor_id = ?
                     AND rpp.rol = 'tutor'
               ) AS pot_editar
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
        WHERE rpa.alumno_id IN ($marcadores)
          AND p.curso_academico = ?
          AND p.estado = 'activo'
    ");
    $stmt->execute([$profesorId, ...$alumnoIds, $cursoAcademico]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $idAlumnoFila = (int) $fila['alumno_id'];
        $proyectoPorAlumno[$idAlumnoFila] = (int) $fila['proyecto_id'];
        $tutorizaPorAlumno[$idAlumnoFila] = in_array($fila['pot_editar'], [true, 1, '1', 't', 'true'], true);
    }

    // Setmanes tancades sense valorar (fecha_fin < avui i valoracion_tutor
    // NULL), agregades per alumne. La setmana actual i les futures precreades
    // pel worker queden fora perquè no compleixen fecha_fin < CURRENT_DATE.
    $stmt = $pdo->prepare("
        SELECT sa.alumno_id, COUNT(*) AS pendientes
        FROM app.seguimiento_alumnos sa
        INNER JOIN app.proyectos p ON p.id_proyecto = sa.proyecto_id
        WHERE sa.alumno_id IN ($marcadores)
          AND p.curso_academico = ?
          AND p.estado = 'activo'
          AND sa.fecha_fin < CURRENT_DATE
          AND sa.valoracion_tutor IS NULL
          AND EXISTS (
              SELECT 1
              FROM app.rel_proyectos_profesores rpp
              WHERE rpp.proyecto_id = sa.proyecto_id
                AND rpp.profesor_id = ?
                AND rpp.rol = 'tutor'
          )
        GROUP BY sa.alumno_id
    ");
    $stmt->execute([...$alumnoIds, $cursoAcademico, $profesorId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $pendientesPorAlumno[(int) $fila['alumno_id']] = (int) $fila['pendientes'];
    }
}

// Membres per projecte, per saber quins projectes tenen 2 o més alumnes dins
// d'aquest grup i han de mostrar-se dins d'un contenidor visual comú.
$miembrosPorProyecto = [];
foreach ($alumnos as $alumno) {
    $idAl = (int) $alumno['id_alumno'];
    if (isset($proyectoPorAlumno[$idAl])) {
        $miembrosPorProyecto[$proyectoPorAlumno[$idAl]][] = $idAl;
    }
}

// Llista final a renderitzar: cada element és un projecte compartit (2+
// alumnes, sempre junts i en l'ordre estable ja donat per $alumnos) o un
// alumne individual (sense projecte, o projecte d'un sol membre).
$elementosNavegacionAlumnos = [];
$idsYaColocados = [];
foreach ($alumnos as $alumno) {
    $idAl = (int) $alumno['id_alumno'];
    if (in_array($idAl, $idsYaColocados, true)) {
        continue;
    }
    $proyId = $proyectoPorAlumno[$idAl] ?? 0;
    if ($proyId > 0 && count($miembrosPorProyecto[$proyId]) > 1) {
        $idsMiembros = $miembrosPorProyecto[$proyId];
        $miembros = array_values(array_filter(
            $alumnos,
            static fn (array $a): bool => in_array((int) $a['id_alumno'], $idsMiembros, true)
        ));
        $elementosNavegacionAlumnos[] = ['tipo' => 'proyecto', 'alumnos' => $miembros];
        foreach ($miembros as $miembro) {
            $idsYaColocados[] = (int) $miembro['id_alumno'];
        }
    } else {
        $elementosNavegacionAlumnos[] = ['tipo' => 'individual', 'alumnos' => [$alumno]];
        $idsYaColocados[] = $idAl;
    }
}

// -----------------------------------------------------------------------------
// 3. Projecte actiu de l'alumne seleccionat en el curs actual. alumnoId ja
// ha estat validat contra la llista autoritzada anterior.
// -----------------------------------------------------------------------------

$proyectoId = 0;
if ($alumnoId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto
        FROM app.proyectos p
        INNER JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
        WHERE rpa.alumno_id = :alumno_id
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
        LIMIT 1
    ");
    $stmt->execute([':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
    $proyectoId = (int) ($stmt->fetchColumn() ?: 0);
}

// El professor només pot escriure valoració/comentari si és el tutor formal
// d'aquest projecte concret (rel_proyectos_profesores.rol = 'tutor').
$potEditarValoracio = $tutorizaPorAlumno[$alumnoId] ?? false;

// -----------------------------------------------------------------------------
// 4. Setmanes ja tancades de l'alumne (fecha_fin < avui). La setmana actual i
// qualsevol setmana futura precreada pel worker queden fora d'aquesta vista:
// el professor només treballa amb setmanes finalitzades. No es toca la
// lògica temporal de l'Autoseguiment de l'alumnat.
// -----------------------------------------------------------------------------

$setmanasTancades = [];
$objectiuAnteriorPerSeguiment = [];
if ($proyectoId > 0) {
    $stmt = $pdo->prepare("
        SELECT id_seguimiento, semana, fecha_inicio, fecha_fin,
               cumplimiento_objetivo_anterior, trabajo_realizado, incidencias,
               objetivo_siguiente, valoracion_tutor, comentario_tutor
        FROM app.seguimiento_alumnos
        WHERE proyecto_id = :proyecto_id
          AND alumno_id = :alumno_id
          AND fecha_fin < CURRENT_DATE
        ORDER BY fecha_inicio DESC
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoId]);
    $setmanasTancades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $setmanasCronologiques = array_values($setmanasTancades);
    usort($setmanasCronologiques, static function (array $a, array $b): int {
        $comparacioData = strcmp((string) $a['fecha_inicio'], (string) $b['fecha_inicio']);
        return $comparacioData !== 0
            ? $comparacioData
            : ((int) $a['id_seguimiento'] <=> (int) $b['id_seguimiento']);
    });
    $setmanaAnterior = null;
    foreach ($setmanasCronologiques as $setmanaTancada) {
        if ($setmanaAnterior !== null) {
            $objectiuAnteriorPerSeguiment[(int) $setmanaTancada['id_seguimiento']] = trim((string) ($setmanaAnterior['objetivo_siguiente'] ?? ''));
        }
        $setmanaAnterior = $setmanaTancada;
    }
}
?>
<script>window.PAGE_TITLE = 'Autoseguiment';</script>
<style>
/* Mateixos tons que assets del Autoseguiment de l'alumnat. Definits aquí
   perquè allà viuen en un <style> scoped a aquella pàgina i aquesta tasca no
   la toca; els valors s'han de mantenir sincronitzats si canvien. */
.autoseguiment-panell {
    background: #f7f8fa;
}
.autoseguiment-apartat-titol {
    color: #496B88 !important;
}
.autoseguiment-objectiu-anterior {
    background: #f3f6fa;
    border: 1px solid #d9e2ec;
}

/* Pill de compliment (versió estàtica, no interactiva): mateixa forma i
   mateixos tons que .autoseguiment-pill-si/-parcial/-no del selector
   interactiu de l'alumnat. */
.autoseguiment-pill-opcio {
    display: inline-flex;
    align-items: center;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: .88rem;
    font-weight: 600;
    border: 1px solid #dee2e6;
    background: #f1f3f5;
    color: #7a838d;
    cursor: default;
}
.autoseguiment-pill-si {
    background: #e8f5e9;
    color: #08751c;
    border-color: #c8e6c9;
}
.autoseguiment-pill-parcial {
    background: #fff4e5;
    color: #a65f00;
    border-color: #ffd08a;
}
.autoseguiment-pill-no {
    background: #fdecea;
    color: #c62828;
    border-color: #f5c6cb;
}

/* Pills compactes de grup/alumnes: mateixa escala que .badge (rounded-pill
   border px-3 py-2) ja usada a cicles.php/grups-cicle.php, en lloc dels
   botons grans anteriors. */
.autoseguiment-tutor-nav-pill {
    font-size: .8125rem;
}

/* Alumne seleccionat: mateix llenguatge neutre de .pill-neutral, un punt més
   marcat. Cap relació amb el taronja de pendents ni amb el color de cicle. */
.pill-neutral-seleccionada {
    background: #e4e7eb !important;
    border-color: #adb5bd !important;
    color: #495057 !important;
}

/* Contenidor merament visual: alumnes que comparteixen projecte. No té cap
   comportament interactiu ni estat actiu. El fons i el marc es dibuixen amb
   un pseudo-element absolut que s'expandeix cap enfora (inset negatiu), en
   lloc de padding, perquè les pills agrupades quedin exactament alineades
   amb les pills individuals veïnes. */
.tutor-projecte-grup {
    position: relative;
    margin-inline: .25rem;
}
.tutor-projecte-grup::before {
    content: '';
    position: absolute;
    inset: -.5rem;
    background: #f1f3f5;
    border: 1px dashed #ced4da;
    border-radius: .75rem;
    z-index: -1;
}

/* Badge de setmanes pendents de valorar (taronja "accent" ja usat al
   projecte). Independent del color de cicle i del contenidor de projecte. */
.tutor-pendents-badge {
    background: #F59E0B;
    color: #fff;
}

/* Granate corporatiu ja usat a .btn-puig-solid (#970A2C / hover #7e0825),
   reutilitzat aquí per al rètol i l'enllaç d'afegir comentari. */
.autoseguiment-tutor-titol {
    color: #970A2C;
}
.tutor-comentari-afegir-btn,
.tutor-comentari-editar-btn {
    color: #970A2C !important;
}
.tutor-comentari-afegir-btn:hover,
.tutor-comentari-editar-btn:hover {
    color: #7e0825 !important;
}
/* Pills de valoració del tutor. Neutre = mateixos tons que .pill-neutral. */
.tutor-valoracio-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #dee2e6;
    background: #f1f3f5;
    color: #7a838d;
    transition: background-color .15s, border-color .15s, color .15s;
}
.tutor-valoracio-pill:disabled {
    cursor: default;
    opacity: .85;
}
.tutor-valoracio-pill:not(:disabled):hover {
    border-color: #c7ced6;
}
/* Sense avanç seleccionat: vermell suau, reservat a l'absència d'avanç. */
.tutor-valoracio-sense.seleccionada {
    background: #fdecea;
    color: #c62828;
    border-color: #f5c6cb;
}
/* Poc avanç seleccionat: groc suau. */
.tutor-valoracio-poc.seleccionada {
    background: #fff4e5;
    color: #a65f00;
    border-color: #ffd08a;
}
/* Avanç adequat seleccionat: verd suau. */
.tutor-valoracio-adequat.seleccionada {
    background: #e8f5e9;
    color: #08751c;
    border-color: #c8e6c9;
}
/* Avanç destacat seleccionat: verd una mica més intens que l'anterior. */
.tutor-valoracio-destacat.seleccionada {
    background: #c8e6c9;
    color: #0b5d17;
    border-color: #9dd0a2;
}
</style>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Autoseguiment</h1>
        <p class="text-muted mb-0">Consulta i valora el seguiment setmanal de l’alumnat dels teus grups.</p>
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
                        <a href="/seguiment-setmanal/grup/<?= (int) $grupo['id_grupo'] ?>"
                           class="badge rounded-pill border px-3 py-2 fw-semibold text-decoration-none autoseguiment-tutor-nav-pill <?= $classesGrup ?>">
                            <?= htmlspecialchars(trim((string) $grupo['abr'] . ' ' . (string) $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($pendentsGrup > 0): ?>
                                <span class="badge rounded-pill tutor-pendents-badge ms-1"><?= $pendentsGrup ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Navegació per alumnes: pills neutres, agrupades per projecte ── -->
        <div class="mb-4">
            <div class="text-uppercase small fw-semibold text-muted mb-2">Alumnes</div>
            <?php if ($alumnos === []): ?>
                <p class="text-muted mb-0">Aquest grup no té alumnat actiu matriculat.</p>
            <?php else: ?>
                <?php
                // Renderitza una única pill d'alumne. L'estat operatiu només
                // acompanya els alumnes que el professor tutoritza.
                $renderPillAlumne = static function (array $alumno) use ($grupoId, $alumnoId, $pendientesPorAlumno, $tutorizaPorAlumno): void {
                    $idAl = (int) $alumno['id_alumno'];
                    $seleccionat = $idAl === $alumnoId;
                    $pendents = $pendientesPorAlumno[$idAl] ?? 0;
                    $esTutorand = $tutorizaPorAlumno[$idAl] ?? false;
                    ?>
                    <a href="/seguiment-setmanal/grup/<?= $grupoId ?>/alumne/<?= $idAl ?>"
                       data-alumno-id="<?= $idAl ?>"
                       class="badge rounded-pill border px-3 py-2 fw-semibold text-decoration-none autoseguiment-tutor-nav-pill tutor-seguiment-alumne-pill pill-neutral <?= $seleccionat ? 'pill-neutral-seleccionada' : '' ?>">
                        <?= htmlspecialchars(trim((string) $alumno['nombre'] . ' ' . (string) $alumno['apellidos']), ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($esTutorand): ?>
                            <span class="tutor-pendents-estat">
                                <?php if ($pendents > 0): ?>
                                    <span class="badge rounded-pill tutor-pendents-badge ms-1"><?= $pendents ?></span>
                                <?php else: ?>
                                    <i class="bi bi-check-circle-fill text-success ms-1" aria-hidden="true"></i><span class="visually-hidden">Sense pendents</span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <?php
                };
                ?>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <?php foreach ($elementosNavegacionAlumnos as $elemento): ?>
                        <?php if ($elemento['tipo'] === 'proyecto'): ?>
                            <!-- Contenidor merament visual: NO és clicable ni selecciona res. -->
                            <div class="tutor-projecte-grup d-flex flex-wrap gap-2">
                                <?php foreach ($elemento['alumnos'] as $alumno): ?>
                                    <?php $renderPillAlumne($alumno); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php $renderPillAlumne($elemento['alumnos'][0]); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Seguiment de l'alumne seleccionat ── -->
        <?php if ($alumnoId > 0): ?>
            <div class="card autoseguiment-panell shadow-sm border-0 rounded-4 p-4 p-lg-5">
                <?php if ($proyectoId === 0): ?>
                    <section class="bloc bloc-informacio">
                        <div class="bloc-contingut">
                            <div class="bloc-tipus">Sense projecte</div>
                            <h2>Aquest alumne encara no té un projecte actiu</h2>
                            <p class="mb-0">No hi ha cap seguiment per mostrar mentre no formi part d’un projecte.</p>
                        </div>
                    </section>
                <?php elseif ($setmanasTancades === []): ?>
                    <section class="bloc bloc-informacio">
                        <div class="bloc-contingut">
                            <div class="bloc-tipus">Historial</div>
                            <h2>Encara no hi ha cap setmana tancada</h2>
                            <p class="mb-0">Aquest alumne encara no té cap setmana d’autoseguiment finalitzada.</p>
                        </div>
                    </section>
                <?php else: ?>
                    <h2 class="h5 mb-3">Historial</h2>
                    <div class="d-grid gap-3">
                        <?php foreach ($setmanasTancades as $setmana): ?>
                            <?php
                            $objectiuSetmana = $objectiuAnteriorPerSeguiment[(int) $setmana['id_seguimiento']] ?? '';
                            autoseguimentTutorFitxa($setmana, 'bloc-informacio', $objectiuSetmana, $potEditarValoracio);
                            ?>
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
    const urlAccio = '/index.php?main=autoseguiment-tutor_accion';
    const alumnoActualId = <?= (int) $alumnoId ?>;

    // ── Recompte de pendents de l'alumne actual a partir del propi DOM: totes
    // les seves setmanes tancades ja estan a la pàgina, no cal cap petició
    // addicional per mantenir sincronitzat el badge de la navegació. ────────
    const actualitzarBadgePendents = () => {
        const blocs = document.querySelectorAll('.tutor-valoracio-bloc');
        let pendents = 0;
        blocs.forEach((bloc) => {
            if (!bloc.querySelector('.tutor-valoracio-pill.seleccionada')) {
                pendents++;
            }
        });
        const pill = document.querySelector('.tutor-seguiment-alumne-pill[data-alumno-id="' + alumnoActualId + '"]');
        const estat = pill ? pill.querySelector('.tutor-pendents-estat') : null;
        if (!estat) {
            return;
        }
        estat.innerHTML = pendents > 0
            ? '<span class="badge rounded-pill tutor-pendents-badge ms-1">' + pendents + '</span>'
            : '<i class="bi bi-check-circle-fill text-success ms-1" aria-hidden="true"></i><span class="visually-hidden">Sense pendents</span>';
    };

    // ── Valoració: guardat immediat en clicar una pill ──────────────────
    document.querySelectorAll('.tutor-valoracio-pill').forEach((boto) => {
        boto.addEventListener('click', async () => {
            if (boto.disabled) {
                return;
            }
            const grup = boto.closest('.tutor-valoracio-bloc');
            const idSeguiment = grup?.dataset.idSeguiment;
            const valor = boto.dataset.valor;
            if (!idSeguiment || valor === undefined) {
                return;
            }

            const dades = new FormData();
            dades.append('accio', 'valoracion');
            dades.append('id_seguimiento', idSeguiment);
            dades.append('valor', valor);
            dades.append('csrf_token', csrfToken);

            try {
                const resposta = await fetch(urlAccio, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: dades,
                });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    alert(resultat.missatge || 'No s’ha pogut guardar la valoració.');
                    return;
                }
                grup.querySelectorAll('.tutor-valoracio-pill').forEach((altre) => altre.classList.remove('seleccionada'));
                boto.classList.add('seleccionada');
                const targeta = grup.closest('.bloc');
                if (targeta) {
                    targeta.classList.remove(
                        'autoseguiment-valoracio-sense',
                        'autoseguiment-valoracio-poc',
                        'autoseguiment-valoracio-adequat',
                        'autoseguiment-valoracio-destacat'
                    );
                    const classeValoracio = {
                        '0': 'autoseguiment-valoracio-sense',
                        '1': 'autoseguiment-valoracio-poc',
                        '2': 'autoseguiment-valoracio-adequat',
                        '3': 'autoseguiment-valoracio-destacat',
                    }[valor];
                    if (classeValoracio) {
                        targeta.classList.add(classeValoracio);
                    }
                    const dataPill = targeta.querySelector('.autoseguiment-data-pill');
                    if (dataPill) {
                        dataPill.classList.remove(
                            'autoseguiment-valoracio-sense',
                            'autoseguiment-valoracio-poc',
                            'autoseguiment-valoracio-adequat',
                            'autoseguiment-valoracio-destacat'
                        );
                        if (classeValoracio) {
                            dataPill.classList.add(classeValoracio);
                        }
                    }
                }
                actualitzarBadgePendents();
            } catch (error) {
                alert('Error de connexió en guardar la valoració.');
            }
        });
    });

    // ── Comentari: desplegar / cancel·lar / guardar ─────────────────────
    document.querySelectorAll('.tutor-comentari').forEach((zona) => {
        const bloc = zona.closest('.tutor-valoracio-bloc');
        const idSeguiment = bloc?.dataset.idSeguiment;
        const lectura = zona.querySelector('.tutor-comentari-lectura');
        const textLectura = zona.querySelector('.tutor-comentari-text');
        const botoAfegir = zona.querySelector('.tutor-comentari-afegir-btn');
        const botoEditar = zona.querySelector('.tutor-comentari-editar-btn');
        const editor = zona.querySelector('.tutor-comentari-editor');
        const textarea = editor ? editor.querySelector('textarea') : null;
        const botoGuardar = zona.querySelector('.tutor-comentari-guardar-btn');
        const botoCancelar = zona.querySelector('.tutor-comentari-cancelar-btn');
        if (!editor || !textarea) {
            return;
        }

        const obrirEditor = () => {
            editor.classList.remove('d-none');
            if (botoAfegir) botoAfegir.classList.add('d-none');
            textarea.focus();
        };
        const tancarEditor = () => {
            editor.classList.add('d-none');
            if (botoAfegir && lectura.classList.contains('d-none')) {
                botoAfegir.classList.remove('d-none');
            }
        };

        botoAfegir?.addEventListener('click', obrirEditor);
        botoEditar?.addEventListener('click', obrirEditor);
        botoCancelar?.addEventListener('click', () => {
            textarea.value = textLectura ? textLectura.textContent.trim() : '';
            tancarEditor();
        });

        botoGuardar?.addEventListener('click', async () => {
            if (!idSeguiment) {
                return;
            }
            const dades = new FormData();
            dades.append('accio', 'comentario');
            dades.append('id_seguimiento', idSeguiment);
            dades.append('comentario', textarea.value);
            dades.append('csrf_token', csrfToken);

            try {
                const resposta = await fetch(urlAccio, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: dades,
                });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    alert(resultat.missatge || 'No s’ha pogut guardar el comentari.');
                    return;
                }
                const nouText = (resultat.comentario || '').toString();
                if (nouText === '') {
                    lectura.classList.add('d-none');
                    if (botoAfegir) botoAfegir.classList.remove('d-none');
                } else {
                    if (textLectura) {
                        textLectura.innerHTML = nouText
                            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                            .replace(/\n/g, '<br>');
                    }
                    lectura.classList.remove('d-none');
                    if (botoAfegir) botoAfegir.classList.add('d-none');
                    if (!botoEditar && lectura) {
                        // Primer comentari afegit: cal un botó "Editar" per a properes vegades.
                        const nouBoto = document.createElement('button');
                        nouBoto.type = 'button';
                        nouBoto.className = 'btn btn-link btn-sm ps-0 tutor-comentari-editar-btn';
                        nouBoto.textContent = 'Editar comentari';
                        nouBoto.addEventListener('click', obrirEditor);
                        lectura.appendChild(nouBoto);
                    }
                }
                tancarEditor();
            } catch (error) {
                alert('Error de connexió en guardar el comentari.');
            }
        });
    });
})();
</script>
