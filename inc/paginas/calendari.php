<?php
// calendari.php

declare(strict_types=1);


// ── Dies disponibles ─────────────────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT DISTINCT DATE(defensa_fecha) AS dia
        FROM app.proyectos
        WHERE defensa_fecha IS NOT NULL
        ORDER BY dia
    ");
    $dies = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $dies = [];
}

// ── Stats globals (Nivell 1) ─────────────────────────────────────
try {
    $stats = $pdo->query("
        SELECT
            COUNT(*)                                            AS total,
            COUNT(*) FILTER (WHERE defensa_fecha IS NOT NULL)  AS assignats,
            COUNT(*) FILTER (WHERE defensa_fecha IS NULL)      AS sense_slot,
            COUNT(DISTINCT DATE(defensa_fecha))                 AS dies,
            COUNT(DISTINCT defensa_aula_id)                    AS aules
        FROM app.proyectos
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total'=>0,'assignats'=>0,'sense_slot'=>0,'dies'=>0,'aules'=>0];
}

// Desglose per cicle
try {
    $per_cicle_raw = $pdo->query("
        SELECT
            ciclo,
            grupo,
            COUNT(*) AS total
        FROM app.proyectos
        WHERE defensa_fecha IS NOT NULL
        GROUP BY ciclo, grupo
        ORDER BY ciclo, grupo
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $per_cicle_raw = [];
}

// Agrupa: si el cicle té grup el mostrem separat (SMX A, SMX B...), sinó sol (DAM, DAW...)
$cicles = [];
foreach ($per_cicle_raw as $r) {
    $clau = strtoupper($r['ciclo']) . (!empty($r['grupo']) ? ' ' . strtoupper($r['grupo']) : '');
    $cicles[$clau] = (int)$r['total'];
}

// Noms dels dies en català
function nomDia(string $dia): string {
    $dies_ca  = ['diumenge','dilluns','dimarts','dimecres','dijous','divendres','dissabte'];
    $mesos_ca = ['','gener','febrer','març','abril','maig','juny','juliol','agost','setembre','octubre','novembre','desembre'];
    $ts  = strtotime($dia);
    $dow = (int)date('w', $ts);
    $d   = (int)date('j', $ts);
    $m   = (int)date('n', $ts);
    $y   = date('Y', $ts);
    return ucfirst($dies_ca[$dow]) . ' ' . $d . ' de ' . $mesos_ca[$m] . ' de ' . $y;
}

function nomDiaCurt(string $dia): string {
    $dies_ca  = ['Dg','Dl','Dm','Dc','Dj','Dv','Ds'];
    $mesos_ca = ['','gen','feb','mar','abr','mai','jun','jul','ago','set','oct','nov','des'];
    $ts  = strtotime($dia);
    $dow = (int)date('w', $ts);
    $d   = (int)date('j', $ts);
    $m   = (int)date('n', $ts);
    return $dies_ca[$dow] . ' ' . $d . ' ' . $mesos_ca[$m];
}

// Color per cicle (CSS class)
function colorCicleBadge(string $clau): string {
    $cicle = explode(' ', $clau)[0];
    $mapa = [
        'DAM'  => 'bg-primary-subtle text-primary border-primary-subtle',
        'DAW'  => 'bg-success-subtle text-success border-success-subtle',
        'ASIX' => 'bg-warning-subtle text-dark border-warning-subtle',
        'SMX'  => 'bg-info-subtle text-dark border-info-subtle',
        'DEV'  => 'bg-danger-subtle text-danger border-danger-subtle',
    ];
    return $mapa[$cicle] ?? 'bg-secondary-subtle text-secondary border-secondary-subtle';
}

// Ordre de visualització dels cicles
function ordreVisualCicles(array $cicles): array {
    $ordre = ['SMX', 'ASIX', 'DAM', 'DAW', 'DEV'];
    uksort($cicles, function($a, $b) use ($ordre) {
        $cicleA = explode(' ', $a)[0];
        $cicleB = explode(' ', $b)[0];
        $posA = array_search($cicleA, $ordre);
        $posB = array_search($cicleB, $ordre);
        if ($posA === false) $posA = 99;
        if ($posB === false) $posB = 99;
        if ($posA === $posB) return strcmp($a, $b);
        return $posA - $posB;
    });
    return $cicles;
}

// Si venim d'una redirecció de calendari_modificar, restaurem dia i torn
$diaActiu  = (isset($_GET['dia'])  && in_array($_GET['dia'],  $dies, true))
    ? $_GET['dia']
    : ($dies[0] ?? null);
$tornActiu = (isset($_GET['torn']) && in_array($_GET['torn'], ['mati','tarda'], true))
    ? $_GET['torn']
    : 'mati';

$pct_assignats = $stats['total'] > 0 ? round($stats['assignats'] / $stats['total'] * 100) : 0;

// Quota de tribunals per professor
$quota_min = 0;
$quota_max = 0;
if (isset($_SESSION['professor_id'])) {
    try {
        $n_grups = (int)$pdo->query("
            SELECT COUNT(*)
            FROM app.proyectos
            WHERE defensa_fecha IS NOT NULL
        ")->fetchColumn();

        $n_profs = (int)$pdo->query("
            SELECT COUNT(*) FROM app.profesores WHERE activo = true
        ")->fetchColumn();

        $stmt_ja = $pdo->prepare("SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE profesor_id = ?");
        $stmt_ja->execute([$_SESSION['professor_id']]);
        $n_ja = (int)$stmt_ja->fetchColumn();

        if ($n_profs > 0) {
            $total_places = $n_grups * 3;
            $quota_ideal  = $total_places / $n_profs;
            $quota_min    = max(0, (int)floor($quota_ideal) - $n_ja);
            $quota_max    = max(0, (int)ceil($quota_ideal)  - $n_ja);
        }
    } catch (PDOException $e) {
        $quota_min = $quota_max = 0;
    }
}
?>

<style>
.pill-btn {
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
}
.pill-btn.active {
    background-color: #0d6efd !important;
    color: #fff !important;
    border-color: #0d6efd !important;
}
.defense-table tbody tr { height: 180px; }
.defense-table td, .defense-table th { vertical-align: middle !important; }
.defense-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    height: 100%;
}
.defense-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.09);
}
.empty-slot {
    background: #f8f9fa;
    border: 1px dashed #ced4da !important;
    border-radius: .75rem;
    min-height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #adb5bd;
    font-size: .8rem;
    font-weight: 600;
}
#calendari-wrap { min-height: 300px; }
#calendari-loading {
    display: none;
    padding: 3rem;
    text-align: center;
    color: #6c757d;
}
.stat-card {
    border-radius: 1rem;
    border: 1px solid #e9ecef;
    padding: 1.25rem 1.5rem;
    background: #fff;
}
.stat-card .stat-num {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: .25rem;
}
.stat-card .stat-label {
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6c757d;
}
.dia-card {
    border-radius: .75rem;
    border: 1px solid #e9ecef;
    padding: .85rem 1rem;
    background: #fff;
    flex: 1 1 0;
    min-width: 160px;
}
.dia-card .dia-num {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
}
.dia-card .progress {
    height: 5px;
    border-radius: 99px;
    margin-top: .6rem;
}
.stats-divider {
    border: 0;
    border-top: 1px solid #f1f3f5;
    margin: 1.25rem 0;
}
/* Stats dia dinàmics */
#stats-dia-wrap .stat-dia-num {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1;
}
#stats-dia-wrap .stat-dia-label {
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6c757d;
}

/* Acordeó resum */
.resum-toggle { cursor: pointer; }
.resum-toggle:hover { opacity: .8; }
.resum-body {
    overflow: hidden;
    transition: max-height 0.35s ease, opacity 0.35s ease;
    max-height: 2000px;
    opacity: 1;
}
.resum-body.collapsed {
    max-height: 0;
    opacity: 0;
}
.link-apuntarme { height: 21px };
/* Modal modificar */
.modal-modificar .modal-content { border-radius: 1rem; border: 0; }
.modal-modificar .modal-header  { border-bottom: 1px solid #f1f3f5; padding: 1.25rem 1.5rem; }
.modal-modificar .modal-footer  { border-top: 1px solid #f1f3f5; padding: 1rem 1.5rem; }
</style>

<section class="container my-4">

    <!-- ══════════════════════════════════════════════════════════
         BLOC DE RESUM (acordeó)
    ══════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">

        <!-- Capçalera sempre visible -->
        <div class="card-body p-4 d-flex justify-content-between align-items-center resum-toggle" id="resum-header" onclick="toggleResum()">
            <div>
                <div class="text-uppercase small fw-semibold text-primary mb-1">Calendari de defenses</div>
                <h2 class="h5 fw-bold mb-0">Resum de distribució</h2>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3 d-flex align-items-center gap-2" id="btn-resum-toggle">
                <span id="resum-toggle-label">Veure resum</span>
                <svg id="resum-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" style="transition:transform .3s ease;">
                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                </svg>
            </button>
        </div>

        <!-- Cos plegable -->
        <div class="resum-body collapsed" id="resum-body">
        <div class="card-body p-4 p-lg-5 pt-0">

            <!-- Títol dia + badges -->
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1" id="titol-dia">
                        <?= $diaActiu ? h(nomDia($diaActiu)) : 'Sense dies assignats' ?>
                    </h1>
                    <p class="text-muted mb-0">Distribució per franges horàries i aules.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <span class="badge rounded-pill text-bg-light border px-3 py-2" id="badge-torn"><?= $tornActiu === 'mati' ? '☀ Matí' : '🌙 Tarda' ?></span>
                    <span class="badge rounded-pill <?= $stats['sense_slot'] > 0 ? 'bg-warning-subtle text-dark border-warning-subtle' : 'bg-success-subtle text-success border-success-subtle' ?> border px-3 py-2">
                        <?= $pct_assignats ?>% assignats
                    </span>
                </div>
            </div>

            <!-- NIVELL 1: Xifres globals -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card h-100">
                        <div class="stat-num text-primary"><?= $stats['assignats'] ?></div>
                        <div class="stat-label">Projectes assignats</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card h-100">
                        <div class="stat-num <?= $stats['sense_slot'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $stats['sense_slot'] ?>
                        </div>
                        <div class="stat-label">Sense slot</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card h-100">
                        <div class="stat-num text-dark"><?= $stats['dies'] ?></div>
                        <div class="stat-label">Dies de defensa</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card h-100">
                        <div class="stat-num text-dark"><?= $stats['aules'] ?></div>
                        <div class="stat-label">Aules en ús</div>
                    </div>
                </div>
            </div>

            <!-- Barra progress global -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-muted">Ocupació global</span>
                    <span class="small fw-bold"><?= $stats['assignats'] ?> / <?= $stats['total'] ?></span>
                </div>
                <div class="progress" style="height:8px; border-radius:99px;">
                    <div class="progress-bar bg-primary" style="width:<?= $pct_assignats ?>%; border-radius:99px;"></div>
                </div>
            </div>

            <hr class="stats-divider">

            <!-- NIVELL 1b: Desglose per dia (cards) -->
            <?php if (!empty($dies)): ?>
            <div class="mb-4">
                <div class="small fw-semibold text-muted text-uppercase mb-2" style="letter-spacing:.05em;">Distribució per dia</div>
                <div class="d-flex gap-3 flex-wrap">
                    <?php
                    // Comptar projectes per dia
                    try {
                        $per_dia = $pdo->query("
                            SELECT
                                DATE(defensa_fecha) AS dia,
                                COUNT(*) AS total
                            FROM app.proyectos
                            WHERE defensa_fecha IS NOT NULL
                            GROUP BY DATE(defensa_fecha)
                            ORDER BY dia
                        ")->fetchAll(PDO::FETCH_KEY_PAIR);
                    } catch (PDOException $e) {
                        $per_dia = [];
                    }
                    $max_dia = max(array_values($per_dia) ?: [1]);
                    foreach ($dies as $dia):
                        $n   = $per_dia[$dia] ?? 0;
                        $pct = $max_dia > 0 ? round($n / $max_dia * 100) : 0;
                    ?>
                    <div class="dia-card">
                        <div class="small text-muted fw-semibold mb-1"><?= h(nomDiaCurt($dia)) ?></div>
                        <div class="dia-num text-dark"><?= $n ?></div>
                        <div class="small text-muted">projectes</div>
                        <div class="progress">
                            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- NIVELL 1c: Desglose per cicle -->
            <?php if (!empty($cicles)): ?>
            <div class="mb-4">
                <div class="small fw-semibold text-muted text-uppercase mb-2" style="letter-spacing:.05em;">Per cicle</div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach (ordreVisualCicles($cicles) as $clau => $total): ?>
                    <span class="badge rounded-pill border px-3 py-2 fw-semibold <?= colorCicleBadge($clau) ?>">
                        <?= h($clau) ?> <span class="opacity-75 fw-normal ms-1"><?= $total ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <hr class="stats-divider">

            <!-- NIVELL 2: Stats del dia actiu (dinàmics, s'actualitzen amb el filtre) -->
            <div id="stats-dia-wrap">
                <div class="small fw-semibold text-muted text-uppercase mb-3" style="letter-spacing:.05em;">
                    Detall del dia seleccionat
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-card h-100">
                            <div class="stat-dia-num text-primary" id="sd-mati">—</div>
                            <div class="stat-dia-label">Defenses matí</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card h-100">
                            <div class="stat-dia-num text-info" id="sd-tarda">—</div>
                            <div class="stat-dia-label">Defenses tarda</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card h-100">
                            <div class="stat-dia-num text-dark" id="sd-aules">—</div>
                            <div class="stat-dia-label">Aules en ús</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card h-100">
                            <div class="stat-dia-num text-dark" id="sd-franges">—</div>
                            <div class="stat-dia-label">Franges horàries</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </div><!-- /resum-body -->
    </div>

    <?php if (empty($dies)): ?>
        <div class="alert alert-warning rounded-4">
            No hi ha defenses assignades. Executa primer l'assignació automàtica.
        </div>
    <?php else: ?>



    <!-- ══════════════════════════════════════════════════════════
         FILTRES
    ══════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 px-4">
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <span class="small fw-semibold text-muted text-uppercase" style="min-width:40px;">Dia</span>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach ($dies as $i => $dia): ?>
                    <span class="badge rounded-pill border px-3 py-2 pill-btn pill-dia <?= $i === 0 ? 'active' : 'text-bg-light' ?>"
                          data-dia="<?= h($dia) ?>"
                          data-nom="<?= h(nomDia($dia)) ?>">
                        <?= h(nomDia($dia)) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="small fw-semibold text-muted text-uppercase" style="min-width:40px;">Torn</span>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge rounded-pill border px-3 py-2 pill-btn pill-torn <?= $tornActiu === 'mati'  ? 'active' : 'text-bg-light' ?>" data-torn="mati">☀ Matí</span>
                    <span class="badge rounded-pill border px-3 py-2 pill-btn pill-torn <?= $tornActiu === 'tarda' ? 'active' : 'text-bg-light' ?>" data-torn="tarda">🌙 Tarda</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         TAULA
    ══════════════════════════════════════════════════════════ -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <div id="quota-wrap" class="mb-3"></div>
            <div id="calendari-loading">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                Carregant defenses...
            </div>
            <div id="calendari-wrap"></div>
        </div>
    </div>

    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════
         BLOC SENSE ASSIGNAR
    ══════════════════════════════════════════════════════════ -->
    <?php
    try {
        $sense = $pdo->query("
            SELECT
                p.id_proyecto, p.nombre, p.ciclo, p.grupo
            FROM app.proyectos p
            WHERE p.defensa_fecha IS NULL OR p.defensa_aula_id IS NULL
            ORDER BY p.ciclo, p.grupo, p.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $sense = [];
    }

    if (!empty($sense)):
        // Alumnes per projecte sense assignar
        $ids_sense = array_column($sense, 'id_proyecto');
        $ph_sense  = implode(',', array_fill(0, count($ids_sense), '?'));
        try {
            $stmt_al = $pdo->prepare("
                SELECT r.proyecto_id, a.nombre, a.apellidos
                FROM app.rel_proyectos_alumnos r
                JOIN app.alumnos a ON a.id_alumno = r.alumno_id
                WHERE r.proyecto_id IN ($ph_sense)
                ORDER BY a.apellidos
            ");
            $stmt_al->execute($ids_sense);
            $al_raw = $stmt_al->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $al_raw = [];
        }
        $al_per_proj = [];
        foreach ($al_raw as $a) {
            $al_per_proj[$a['proyecto_id']][] = trim($a['nombre'] . ' ' . $a['apellidos']);
        }
    ?>
    <div class="card border-0 shadow-sm rounded-4 mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="text-uppercase small fw-semibold text-danger mb-1">Atenció</div>
                    <h2 class="h5 fw-bold mb-0">Sense assignar
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill ms-2 fw-semibold" style="font-size:.8rem;">
                            <?= count($sense) ?>
                        </span>
                    </h2>
                </div>
                <p class="text-muted small mb-0">Projectes sense data ni aula de defensa. Assigna'ls manualment.</p>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-2">
            <div class="row g-3">
                <?php foreach ($sense as $proj):
                    $cicle    = strtoupper($proj['ciclo'] ?? '');
                    $grup     = strtoupper($proj['grupo'] ?? '');
                    $alumnes  = $al_per_proj[$proj['id_proyecto']] ?? [];
                    $label_al = count($alumnes) === 1 ? 'Alumne/a' : 'Alumnes';
                    $colorCicle = [
                        'DAM'  => 'bg-primary-subtle text-primary',
                        'DAW'  => 'bg-success-subtle text-success',
                        'ASIX' => 'bg-warning-subtle text-dark',
                        'SMX'  => 'bg-info-subtle text-dark',
                        'DEV'  => 'bg-danger-subtle text-danger',
                    ][$cicle] ?? 'bg-secondary-subtle text-secondary';
                ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="card border rounded-4 shadow-sm h-100">
                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            <div>
                                <div class="mb-2 text-center">
                                    <span class="badge rounded-pill <?= $colorCicle ?> px-3 py-1 fw-semibold">
                                        <?= h($cicle . ($grup ? ' ' . $grup : '')) ?>
                                    </span>
                                </div>
                                <h3 class="h6 fw-bold mb-3 text-center lh-sm"><?= h($proj['nombre']) ?></h3>
                                <div class="small text-secondary text-center">
                                    <div class="mb-2">
                                        <div class="fw-semibold text-dark mb-1"><?= h($label_al) ?></div>
                                        <?php if ($alumnes): ?>
                                            <?= h(implode(' · ', $alumnes)) ?>
                                        <?php else: ?>
                                            <span class="fst-italic">Sense alumnes</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (esSuperadmin()): ?>
                            <div class="mt-3 text-center">
                                <button
                                    type="button"
                                    class="btn btn-outline-danger btn-sm px-3"
                                    data-proj-id="<?= (int)$proj['id_proyecto'] ?>"
                                    data-dia=""
                                    data-hora=""
                                    data-aula-id=""
                                    onclick="obrirModalModificar(this)">
                                    ＋ Assignar data
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</section>

<script>
let diaActiu  = <?= json_encode($diaActiu) ?>;
let tornActiu = <?= json_encode($tornActiu) ?>;
const baseUrl = '<?= rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') ?>';

(function () {

    // ── Pills ────────────────────────────────────────────────────
    document.querySelectorAll('.pill-torn').forEach(el => {
        el.addEventListener('click', function () {
            document.querySelectorAll('.pill-torn').forEach(p => {
                p.classList.remove('active');
                p.classList.add('text-bg-light');
            });
            this.classList.add('active');
            this.classList.remove('text-bg-light');
            tornActiu = this.dataset.torn;
            document.getElementById('badge-torn').textContent =
                tornActiu === 'mati' ? '☀ Matí' : '🌙 Tarda';
            carregarCalendari();
        });
    });

    document.querySelectorAll('.pill-dia').forEach(el => {
        el.addEventListener('click', function () {
            document.querySelectorAll('.pill-dia').forEach(p => {
                p.classList.remove('active');
                p.classList.add('text-bg-light');
            });
            this.classList.add('active');
            this.classList.remove('text-bg-light');
            diaActiu = this.dataset.dia;
            document.getElementById('titol-dia').textContent = this.dataset.nom;
            carregarCalendari();
        });
    });

    <?php if ($diaActiu): ?>
    carregarCalendari();
    <?php endif; ?>

})();

// ── Stats dia (global) ───────────────────────────────────────────
function actualitzarStatsDia(stats) {
    document.getElementById('sd-mati').textContent    = stats.mati    ?? '—';
    document.getElementById('sd-tarda').textContent   = stats.tarda   ?? '—';
    document.getElementById('sd-aules').textContent   = stats.aules   ?? '—';
    document.getElementById('sd-franges').textContent = stats.franges ?? '—';
}

// ── Càrrega AJAX (global per poder cridar-la des d'altres funcions) ──
async function carregarCalendari() {
    if (!diaActiu) return;

    const wrap = document.getElementById('calendari-wrap');
    const loading = document.getElementById('calendari-loading');

    // Fixar l'alçada del body per evitar que el scroll salti
    document.body.style.minHeight = document.body.scrollHeight + 'px';

    // No amagarem el contingut vell: el loading apareix a sobre discreta
  //  loading.style.display = 'block';

    try {
        const resp = await fetch(
            `/index.php?main=calendari_dades&raw=1&dia=${encodeURIComponent(diaActiu)}&torn=${tornActiu}`
        );
        const data = await resp.json();

        // Substituir contingut directament sense passar per estat buit
        wrap.innerHTML = data.html;
        if (data.quota_html !== undefined) {
            document.getElementById('quota-wrap').innerHTML = data.quota_html;
        }
        actualitzarStatsDia(data.stats);
    } catch (e) {
        wrap.innerHTML =
            '<div class="alert alert-danger rounded-4">Error en carregar les defenses.</div>';
    } finally {
        loading.style.display = 'none';
        wrap.style.display = 'block';
        // Alliberar l'alçada fixada un cop el nou contingut ja ocupa el seu espai
        document.body.style.minHeight = '';
    }
}

// ── Acció tribunal (apuntar/desapuntar) ──────────────────────
async function accioTribunal(el) {
    const projId     = el.dataset.projId;
    const accio      = el.dataset.accio;
    const profesorId = el.dataset.profesorId ? parseInt(el.dataset.profesorId) : null;

    const payload = { proj_id: parseInt(projId), accio };
    if (profesorId) payload.profesor_id = profesorId;

    let data;
    try {
        const resp = await fetch('/index.php?main=calendari_tribunal_accio&raw=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        data = await resp.json();
    } catch (e) {
        alert('Error de connexió.');
        return;
    }

    if (data.tribunal_ple) {
        alert('Ho sento, aquest tribunal ja està complet.');
        carregarCalendari();
        return;
    }

    if (!data.ok) {
        alert(data.missatge || 'Error desconegut.');
        return;
    }

    // Recarregar el calendari mantenint el scroll
    carregarCalendari();
}

// ── Acordeó resum ─────────────────────────────────────────────
function toggleResum() {
    const body   = document.getElementById('resum-body');
    const label  = document.getElementById('resum-toggle-label');
    const arrow  = document.getElementById('resum-arrow');
    const obert  = !body.classList.contains('collapsed');

    if (obert) {
        body.classList.add('collapsed');
        label.textContent = 'Veure resum';
        arrow.style.transform = 'rotate(0deg)';
    } else {
        body.classList.remove('collapsed');
        label.textContent = 'Ocultar resum';
        arrow.style.transform = 'rotate(180deg)';
    }
}

// ── Modal modificar defensa ───────────────────────────────────
function obrirModalModificar(btn) {
    const projId    = btn.dataset.projId;
    const dia       = btn.dataset.dia;
    const hora      = btn.dataset.hora;
    const aulaId    = btn.dataset.aulaId;

    document.getElementById('mod-proj-id').value             = projId;
    document.getElementById('mod-dia-retorn').value           = diaActiu;
    document.getElementById('mod-torn-retorn').value          = tornActiu;
    document.getElementById('mod-data-actual').textContent   = dia + ' ' + hora;

    // Preseleccionar valors actuals als selects
    const selData = document.getElementById('mod-data');
    const selHora = document.getElementById('mod-hora');
    const selAula = document.getElementById('mod-aula');

    [...selData.options].forEach(o => { o.selected = o.value === dia; });
    [...selHora.options].forEach(o => { o.selected = o.value === hora; });
    [...selAula.options].forEach(o => { o.selected = o.value == aulaId; });

    // Nom de l'aula seleccionada per mostrar a "Assignació actual"
    const nomAula = selAula.options[selAula.selectedIndex]?.text ?? '';
    document.getElementById('mod-aula-actual').textContent = nomAula;

    new bootstrap.Modal(document.getElementById('modalModificar')).show();
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-guardar-modificar').addEventListener('click', async function () {
        const btn = this;
        const form = document.getElementById('form-modificar');
        const data = new FormData(form);

        btn.disabled = true;
        btn.textContent = 'Guardant...';

        try {
            const resp = await fetch('/index.php?main=calendari_modificar&raw=1', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            const result = await resp.json();

            bootstrap.Modal.getInstance(document.getElementById('modalModificar')).hide();

            if (!result.ok) {
                alert(result.missatge || 'Error en guardar.');
            }

            carregarCalendari();
        } catch (e) {
            alert('Error de connexió.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Guardar canvis';
        }
    });
});
</script>

<!-- ══════════════════════════════════════════════════════════
     MODAL MODIFICAR DEFENSA
══════════════════════════════════════════════════════════ -->
<?php if (esSuperadmin()): ?>
<?php
// Carregar dies, hores i aules disponibles per als selects del modal
try {
    $dies_modal = $pdo->query("
        SELECT DISTINCT DATE(defensa_fecha) AS dia
        FROM app.proyectos WHERE defensa_fecha IS NOT NULL ORDER BY dia
    ")->fetchAll(PDO::FETCH_COLUMN);

    $hores_modal = $pdo->query("
        SELECT DISTINCT TO_CHAR(defensa_fecha, 'HH24:MI') AS hora
        FROM app.proyectos WHERE defensa_fecha IS NOT NULL ORDER BY hora
    ")->fetchAll(PDO::FETCH_COLUMN);

    $aules_modal = $pdo->query("
        SELECT id_aula, codigo, nombre FROM app.aulas ORDER BY codigo
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dies_modal = $hores_modal = $aules_modal = [];
}
?>
<div class="modal fade modal-modificar" id="modalModificar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Modificar defensa</h5>
                    <div class="small text-muted mt-1" id="mod-nom-proj"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="form-modificar">
                <input type="hidden" name="id_proyecto" id="mod-proj-id">
                <input type="hidden" name="dia_retorn"  id="mod-dia-retorn">
                <input type="hidden" name="torn_retorn" id="mod-torn-retorn">

                <div class="modal-body p-4">

                    <div class="border rounded-3 p-3 mb-4 bg-light-subtle small">
                        <div class="text-muted mb-1">Assignació actual</div>
                        <div class="fw-semibold" id="mod-data-actual"></div>
                        <div class="text-muted" id="mod-aula-actual"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nova data</label>
                        <select class="form-select rounded-3" name="nova_data" id="mod-data" required>
                            <?php foreach ($dies_modal as $d): ?>
                            <option value="<?= h($d) ?>"><?= h(nomDia($d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nova hora</label>
                        <select class="form-select rounded-3" name="nova_hora" id="mod-hora" required>
                            <?php foreach ($hores_modal as $hora): ?>
                            <option value="<?= h($hora) ?>"><?= h($hora) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Nova aula</label>
                        <select class="form-select rounded-3" name="nova_aula_id" id="mod-aula" required>
                            <?php foreach ($aules_modal as $aula): ?>
                            <option value="<?= (int)$aula['id_aula'] ?>">
                                <?= h($aula['codigo'] . ' · ' . $aula['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel·lar</button>
                    <button type="button" class="btn btn-primary px-4" id="btn-guardar-modificar">Guardar canvis</button>
                </div>
            </form>

        </div>
    </div>
</div>
<?php endif; ?>
