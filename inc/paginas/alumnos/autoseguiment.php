<?php
declare(strict_types=1);

// L'Autoseguiment és de l'alumne i del curs; el context acadèmic també es
// resol quan encara no existeix cap projecte.
$permitirSinProyecto = true;
$contextoCursoActual = true;
if (!(require __DIR__ . '/projecte_context.php')) {
    return;
}

$alumnoId = (int) $_SESSION['alumno_id'];
$cursoAcademico = cursoAcademicoActual();

// ── Etiquetes dels valors numèrics (definides pel model de dades) ─────────
function autoseguimentEtiquetaCompliment(?int $valor): string
{
    return match ($valor) {
        0 => 'No',
        1 => 'Parcialment',
        2 => 'Sí',
        default => '',
    };
}

// Classe de color de la pill de compliment (mateixos tons que .autoseguiment-pill-si/
// -parcial/-no del selector interactiu), per mostrar el valor ja registrat a l'historial.
function autoseguimentClasseCompliment(?int $valor): string
{
    return match ($valor) {
        0 => 'autoseguiment-pill-no',
        1 => 'autoseguiment-pill-parcial',
        2 => 'autoseguiment-pill-si',
        default => '',
    };
}

function autoseguimentEtiquetaValoracioTutor(?int $valor): string
{
    return match ($valor) {
        0 => 'Sense avanç',
        1 => 'Poc avanç',
        2 => 'Avanç adequat',
        3 => 'Avanç destacat',
        default => '',
    };
}

function autoseguimentData(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $marca = strtotime($data);
    return $marca !== false ? date('d/m/Y', $marca) : $data;
}

function autoseguimentIntervalData(?string $dataInici, ?string $dataFi): string
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

// ── Totes les setmanes de l'alumnat en aquest projecte, de la més recent a
// la més antiga. D'aquí es dedueix quina és l'actual i quina és l'anterior,
// sense necessitat de consultes addicionals.
$stmt = $pdo->prepare("
    SELECT id_seguimiento, semana, fecha_inicio, fecha_fin,
           cumplimiento_objetivo_anterior, trabajo_realizado, incidencias,
           objetivo_siguiente, valoracion_tutor, comentario_tutor
    FROM app.seguimiento_alumnos
    WHERE alumno_id = :alumno_id AND curso_academico = :curso_academico
    ORDER BY fecha_inicio DESC
");
$stmt->execute([':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
$totsSeguiments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avui = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');

$seguimentActual = null;
$indexActual = null;
foreach ($totsSeguiments as $index => $fila) {
    if ($fila['fecha_inicio'] <= $avui && $fila['fecha_fin'] >= $avui) {
        $seguimentActual = $fila;
        $indexActual = $index;
        break;
    }
}

// L'objectiu de la setmana anterior es llegeix del registre previ, mai es
// copia al registre actual: només hi viu com a "objetivo_siguiente" d'aquell.
$objectiuAnterior = '';
if ($indexActual !== null && isset($totsSeguiments[$indexActual + 1])) {
    $objectiuAnterior = trim((string) ($totsSeguiments[$indexActual + 1]['objetivo_siguiente'] ?? ''));
}

$historial = $totsSeguiments;
if ($indexActual !== null) {
    unset($historial[$indexActual]);
}

$seguimentsCronologics = array_values($totsSeguiments);
usort($seguimentsCronologics, static function (array $a, array $b): int {
    $comparacioData = strcmp((string) $a['fecha_inicio'], (string) $b['fecha_inicio']);
    return $comparacioData !== 0
        ? $comparacioData
        : ((int) $a['id_seguimiento'] <=> (int) $b['id_seguimiento']);
});
$objectiuAnteriorPerSeguiment = [];
$seguimentAnterior = null;
foreach ($seguimentsCronologics as $seguiment) {
    if ($seguimentAnterior !== null) {
        $objectiuAnteriorPerSeguiment[(int) $seguiment['id_seguimiento']] = trim((string) ($seguimentAnterior['objetivo_siguiente'] ?? ''));
    }
    $seguimentAnterior = $seguiment;
}

$mensaje = isset($_SESSION['alumne_autoseguiment_mensaje']) && is_string($_SESSION['alumne_autoseguiment_mensaje'])
    ? $_SESSION['alumne_autoseguiment_mensaje']
    : '';
$error = isset($_SESSION['alumne_autoseguiment_error']) && is_string($_SESSION['alumne_autoseguiment_error'])
    ? $_SESSION['alumne_autoseguiment_error']
    : '';
unset($_SESSION['alumne_autoseguiment_mensaje'], $_SESSION['alumne_autoseguiment_error']);
?>
<script>window.PAGE_TITLE = 'Autoseguiment';</script>
<style>
/* Fons gris molt suau per diferenciar el contenidor general de les targetes
   blanques interiors (setmana actual i historial). Mateix to que .bloc-bloquejat
   ja existent a estilos.css. */
.autoseguiment-panell {
    background: #f7f8fa;
}

/* Compliment de l'objectiu anterior com a selector neutre: el color només
   diferencia l'opció seleccionada, no el significat de la resposta. */
.autoseguiment-pill-opcio {
    display: inline-flex;
    align-items: center;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: .88rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #dee2e6;
    background: #fff;
    color: #6c757d;
    user-select: none;
    transition: background-color .15s, border-color .15s, color .15s;
}
.autoseguiment-pill-opcio:hover {
    border-color: #c7ced6;
}
.btn-check:checked + .autoseguiment-pill-opcio {
    background: #e9ecef;
    color: #343a40;
    border-color: #adb5bd;
}
.btn-check:focus-visible + .autoseguiment-pill-opcio {
    outline: 2px solid #86b7fe;
    outline-offset: 2px;
}
.autoseguiment-pill-si.autoseguiment-pill-fixa {
    background: #e8f5e9;
    color: #08751c;
    border-color: #c8e6c9;
}
.autoseguiment-pill-parcial.autoseguiment-pill-fixa {
    background: #fff4e5;
    color: #a65f00;
    border-color: #ffd08a;
}
.autoseguiment-pill-no.autoseguiment-pill-fixa {
    background: #fdecea;
    color: #c62828;
    border-color: #f5c6cb;
}
/* Versió estàtica de la pill (historial): mateixos tons, sense cap
   comportament interactiu. */
.autoseguiment-pill-fixa {
    cursor: default;
}

/* Accent discret als rètols dels apartats (Treball realitzat / Incidències /
   Objectiu), per distingir-los del contingut sense afegir caixes ni línies.
   No afecta els rètols "Setmana X", que conserven el seu color habitual. */
.autoseguiment-apartat-titol {
    color: #496B88 !important;
}

.autoseguiment-feedback-titol {
    text-transform: uppercase;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    opacity: .85;
}
.autoseguiment-feedback-pill {
    display: inline-flex;
    align-items: center;
    padding: 3px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, .55);
    border: 1px solid currentColor;
    font-weight: 700;
    font-size: .85rem;
}
.autoseguiment-feedback-poc {
    background: #fff4e5;
    border-color: #ffd08a;
    color: #a65f00;
}
.autoseguiment-feedback-adequat {
    background: #e8f5e9;
    border-color: #c8e6c9;
    color: #08751c;
}
.autoseguiment-feedback-destacat {
    background: #c8e6c9;
    border-color: #9dd0a2;
    color: #0b5d17;
}
</style>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Autoseguiment</h1>
        <p class="text-muted mb-0">Fes el seguiment setmanal del teu treball al projecte.</p>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card autoseguiment-panell shadow-sm border-0 rounded-4 p-4 p-lg-5">

        <!-- ══════════════════════════════════════════════════════════
             1. SEGUIMENT DE LA SETMANA ACTUAL
        ══════════════════════════════════════════════════════════ -->
        <?php if ($seguimentActual === null): ?>
            <section class="bloc bloc-informacio">
                <div class="bloc-contingut">
                    <div class="bloc-tipus">Setmana actual</div>
                    <h2>Encara no hi ha cap seguiment obert</h2>
                    <p class="mb-0">Torna-ho a comprovar més endavant. El seguiment de la setmana actual s’habilitarà automàticament.</p>
                </div>
            </section>
        <?php else: ?>
            <section class="bloc bloc-activitat mb-4">
                <div class="bloc-contingut">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                        <div class="bloc-tipus mb-0">Setmana <?= (int) $seguimentActual['semana'] ?></div>
                        <span class="pill-neutral autoseguiment-setmana-activa">
                            <i class="bi bi-calendar-week" aria-hidden="true"></i>
                            <?= htmlspecialchars(autoseguimentIntervalData($seguimentActual['fecha_inicio'], $seguimentActual['fecha_fin']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <h2>Seguiment d’aquesta setmana</h2>
                    <p class="mb-3">
                        Pots modificar-lo tantes vegades com vulguis mentre duri aquesta setmana; en acabar, quedarà tancat i formarà part del teu historial.
                    </p>

                    <form method="post" action="/index.php?main=alumne-autoseguiment-accion">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id_seguimiento" value="<?= (int) $seguimentActual['id_seguimiento'] ?>">

                        <!-- ── 2. Objectiu de la setmana anterior (només si n'hi ha) ── -->
                        <?php if ($objectiuAnterior !== ''): ?>
                            <div class="mb-4">
                                <p class="fw-semibold mb-1 autoseguiment-apartat-titol">Objectius de la setmana</p>
                                <p class="mb-3"><?= nl2br(htmlspecialchars($objectiuAnterior, ENT_QUOTES, 'UTF-8'), false) ?></p>
                                <p class="form-label fw-semibold mb-2 autoseguiment-apartat-titol">Els has complert?</p>
                                <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="Compliment dels objectius de la setmana anterior">
                                    <input type="radio" class="btn-check" name="cumplimiento_objetivo_anterior" id="compliment-si" value="2" <?= (int) ($seguimentActual['cumplimiento_objetivo_anterior'] ?? -1) === 2 ? 'checked' : '' ?>>
                                    <label class="autoseguiment-pill-opcio" for="compliment-si">Sí</label>

                                    <input type="radio" class="btn-check" name="cumplimiento_objetivo_anterior" id="compliment-parcial" value="1" <?= (int) ($seguimentActual['cumplimiento_objetivo_anterior'] ?? -1) === 1 ? 'checked' : '' ?>>
                                    <label class="autoseguiment-pill-opcio" for="compliment-parcial">Parcialment</label>

                                    <input type="radio" class="btn-check" name="cumplimiento_objetivo_anterior" id="compliment-no" value="0" <?= (int) ($seguimentActual['cumplimiento_objetivo_anterior'] ?? -1) === 0 ? 'checked' : '' ?>>
                                    <label class="autoseguiment-pill-opcio" for="compliment-no">No</label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="trabajo_realizado" class="form-label fw-semibold autoseguiment-apartat-titol">Treball realitzat aquesta setmana</label>
                            <textarea id="trabajo_realizado" name="trabajo_realizado" class="form-control textarea-neutral auto-grow" rows="4" maxlength="4000"><?= htmlspecialchars((string) ($seguimentActual['trabajo_realizado'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="incidencias" class="form-label fw-semibold autoseguiment-apartat-titol">Incidències</label>
                            <textarea id="incidencias" name="incidencias" class="form-control textarea-neutral auto-grow" rows="3" maxlength="4000"><?= htmlspecialchars((string) ($seguimentActual['incidencias'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="objetivo_siguiente" class="form-label fw-semibold autoseguiment-apartat-titol">Objectius per a la setmana següent</label>
                            <textarea id="objetivo_siguiente" name="objetivo_siguiente" class="form-control textarea-neutral auto-grow" rows="3" maxlength="4000"><?= htmlspecialchars((string) ($seguimentActual['objetivo_siguiente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-puig-solid px-4">Guardar</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════════
             3. HISTORIAL
        ══════════════════════════════════════════════════════════ -->
        <h2 class="h5 mt-4 mb-3">Historial</h2>
        <?php if ($historial === []): ?>
            <p class="text-muted mb-0">Encara no hi ha cap setmana anterior.</p>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($historial as $setmana): ?>
                    <?php
                    $objectiuSetmana = $objectiuAnteriorPerSeguiment[(int) $setmana['id_seguimiento']] ?? '';
                    $complimentValor = $setmana['cumplimiento_objetivo_anterior'] !== null ? (int) $setmana['cumplimiento_objetivo_anterior'] : null;
                    $complimentText = autoseguimentEtiquetaCompliment($complimentValor);
                    $valoracioValor = $setmana['valoracion_tutor'] !== null ? (int) $setmana['valoracion_tutor'] : null;
                    $valoracioText = autoseguimentEtiquetaValoracioTutor($valoracioValor);
                    $valoracioBlocClasse = match ($valoracioValor) {
                        0 => 'autoseguiment-valoracio-sense',
                        1 => 'autoseguiment-valoracio-poc',
                        2 => 'autoseguiment-valoracio-adequat',
                        3 => 'autoseguiment-valoracio-destacat',
                        default => '',
                    };
                    $comentariTutor = trim((string) ($setmana['comentario_tutor'] ?? ''));
                    ?>
                    <section class="bloc bloc-informacio mb-0 <?= $valoracioBlocClasse ?>">
                        <div class="bloc-contingut">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div class="bloc-tipus mb-0">Setmana <?= (int) $setmana['semana'] ?></div>
                                <span class="pill-neutral autoseguiment-data-pill <?= $valoracioBlocClasse ?>">
                                    <i class="bi bi-calendar-week" aria-hidden="true"></i>
                                    <?= htmlspecialchars(autoseguimentIntervalData($setmana['fecha_inicio'], $setmana['fecha_fin']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <?php if ($objectiuSetmana !== ''): ?>
                                <p class="mb-2"><strong class="autoseguiment-apartat-titol">Objectius de la setmana:</strong>
                                    <br><?= nl2br(htmlspecialchars($objectiuSetmana, ENT_QUOTES, 'UTF-8'), false) ?>
                                </p>
                            <?php endif; ?>

                            <p class="mb-2"><strong class="autoseguiment-apartat-titol">Treball realitzat:</strong>
                                <?php if (trim((string) ($setmana['trabajo_realizado'] ?? '')) !== ''): ?>
                                    <br><?= nl2br(htmlspecialchars((string) $setmana['trabajo_realizado'], ENT_QUOTES, 'UTF-8'), false) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">No es va indicar.</span>
                                <?php endif; ?>
                            </p>

                            <p class="mb-2"><strong class="autoseguiment-apartat-titol">Incidències:</strong>
                                <?php if (trim((string) ($setmana['incidencias'] ?? '')) !== ''): ?>
                                    <br><?= nl2br(htmlspecialchars((string) $setmana['incidencias'], ENT_QUOTES, 'UTF-8'), false) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">No es va indicar.</span>
                                <?php endif; ?>
                            </p>

                            <?php if ($objectiuSetmana !== ''): ?>
                                <p class="mb-2">
                                    <strong class="autoseguiment-apartat-titol">Objectius assolits?</strong><br>
                                    <?php if ($complimentText !== ''): ?>
                                        <?= htmlspecialchars($complimentText, ENT_QUOTES, 'UTF-8') ?>.
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Encara no ha respost.</span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($valoracioText !== ''): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <p class="mb-0"><strong class="autoseguiment-apartat-titol">Valoració del tutor:</strong><br>
                                        <span class="autoseguiment-valoracio-linia"><span class="autoseguiment-valoracio-dot" aria-hidden="true"></span><?= htmlspecialchars($valoracioText, ENT_QUOTES, 'UTF-8') ?>.</span>
                                    </p>
                                    <?php if ($comentariTutor !== ''): ?>
                                        <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($comentariTutor, ENT_QUOTES, 'UTF-8'), false) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
