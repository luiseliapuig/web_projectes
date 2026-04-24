<?php
// calendari_dades.php
// Rep ?dia=YYYY-MM-DD&torn=mati|tarda. Retorna JSON: { html, stats }

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

ob_start(); // Capturar qualsevol output espuri (warnings) per garantir JSON net

$dia  = trim($_GET['dia']  ?? '');
$torn = trim($_GET['torn'] ?? 'mati');

if (!$dia || !in_array($torn, ['mati', 'tarda'], true)) {
    echo json_encode(['html' => '<div class="alert alert-danger rounded-4">Paràmetres incorrectes.</div>', 'stats' => []]);
    exit;
}

// Professor actual (si n'hi ha)
$professor_id_actual = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;
$professor_nom_actual = $_SESSION['professor_nom'] ?? null;

function colorCicle(string $cicle): string {
    $mapa = [
        'DAM'  => 'bg-primary-subtle text-primary',
        'DAW'  => 'bg-success-subtle text-success',
        'ASIX' => 'bg-warning-subtle text-dark',
        'SMX'  => 'bg-info-subtle text-dark',
        'DEV'  => 'bg-danger-subtle text-danger',
    ];
    return $mapa[strtoupper($cicle)] ?? 'bg-secondary-subtle text-secondary';
}

function horaFi(string $hora_inici, int $min): string {
    return date('H:i', strtotime('2000-01-01 ' . $hora_inici) + $min * 60);
}

// ── Stats Nivell 2 ────────────────────────────────────────────────
try {
    $st = $pdo->prepare("
        SELECT
            COUNT(*) FILTER (WHERE EXTRACT(HOUR FROM defensa_fecha) < 13)  AS mati,
            COUNT(*) FILTER (WHERE EXTRACT(HOUR FROM defensa_fecha) >= 13) AS tarda,
            COUNT(DISTINCT defensa_aula_id)                                AS aules,
            COUNT(DISTINCT TO_CHAR(defensa_fecha, 'HH24:MI'))              AS franges
        FROM app.proyectos
        WHERE DATE(defensa_fecha) = :dia
    ");
    $st->execute(['dia' => $dia]);
    $stats_dia = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats_dia = ['mati' => 0, 'tarda' => 0, 'aules' => 0, 'franges' => 0];
}

// ── Aules ─────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.id_aula, a.codigo, a.nombre
        FROM app.proyectos p
        JOIN app.aulas a ON a.id_aula = p.defensa_aula_id
        WHERE DATE(p.defensa_fecha) = :dia
        ORDER BY a.codigo
    ");
    $stmt->execute(['dia' => $dia]);
    $aules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['html' => '<div class="alert alert-danger rounded-4">Error en carregar les aules.</div>', 'stats' => $stats_dia]);
    exit;
}

if (empty($aules)) {
    echo json_encode(['html' => '<div class="alert alert-warning rounded-4">No hi ha defenses assignades per a aquest dia.</div>', 'stats' => $stats_dia]);
    exit;
}

// ── Projectes del torn ────────────────────────────────────────────
$hora_cond = $torn === 'mati'
    ? 'AND EXTRACT(HOUR FROM p.defensa_fecha) < 13'
    : 'AND EXTRACT(HOUR FROM p.defensa_fecha) >= 13';

try {
    $stmt = $pdo->prepare("
        SELECT
            p.id_proyecto, p.nombre, p.ciclo, p.grupo,
            p.defensa_fecha, p.defensa_aula_id,
            TO_CHAR(p.defensa_fecha, 'HH24:MI') AS hora_inici
        FROM app.proyectos p
        WHERE DATE(p.defensa_fecha) = :dia $hora_cond
        ORDER BY p.defensa_fecha, p.defensa_aula_id
    ");
    $stmt->execute(['dia' => $dia]);
    $projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['html' => '<div class="alert alert-danger rounded-4">Error en carregar els projectes.</div>', 'stats' => $stats_dia]);
    exit;
}

if (empty($projectes)) {
    $torn_label = $torn === 'mati' ? 'matí' : 'tarda';
    echo json_encode(['html' => '<div class="alert alert-info rounded-4">No hi ha defenses de ' . $torn_label . ' per a aquest dia.</div>', 'stats' => $stats_dia]);
    exit;
}

// ── Alumnes ───────────────────────────────────────────────────────
$ids = array_column($projectes, 'id_proyecto');
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $stmt = $pdo->prepare("
        SELECT r.proyecto_id, a.nombre, a.apellidos
        FROM app.rel_proyectos_alumnos r
        JOIN app.alumnos a ON a.id_alumno = r.alumno_id
        WHERE r.proyecto_id IN ($placeholders)
        ORDER BY a.apellidos
    ");
    $stmt->execute($ids);
    $alumnes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alumnes_raw = [];
}

$alumnes_per_proj = [];
foreach ($alumnes_raw as $a) {
    $alumnes_per_proj[$a['proyecto_id']][] = trim($a['nombre'] . ' ' . $a['apellidos']);
}

// ── Tribunal real ─────────────────────────────────────────────────
$tribunal_raw = [];
if (!empty($ids)) {
    $ids_int = implode(',', array_map('intval', $ids));
    try {
        $stmt = $pdo->query("
            SELECT r.id_proyecto, p.id_profesor,
                   TRIM(p.nombre || ' ' || p.apellidos) AS nom
            FROM app.rel_profesores_tribunal r
            JOIN app.profesores p ON p.id_profesor = r.profesor_id
            WHERE r.id_proyecto IN ($ids_int)
            ORDER BY r.id_proyecto
        ");
        $tribunal_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tribunal_raw = [];
        error_log('TRIBUNAL PDO ERROR: ' . $e->getMessage());
    }
}

// Indexar tribunal per projecte: $tribunal_per_proj[$proj_id] = [['id'=>X,'nom'=>'...'], ...]
$tribunal_per_proj = [];
foreach ($tribunal_raw as $t) {
    $tribunal_per_proj[$t['id_proyecto']][] = [
        'id'  => (int)$t['id_profesor'],
        'nom' => $t['nom'],
    ];
}

// ── Matriu hora → aula → projecte ────────────────────────────────
$hores_set = [];
foreach ($projectes as $p) { $hores_set[$p['hora_inici']] = true; }
ksort($hores_set);
$hores = array_keys($hores_set);

$matriu = [];
foreach ($projectes as $p) {
    $matriu[$p['hora_inici']][$p['defensa_aula_id']] = $p;
}

// Filtrar les aules que no tenen cap projecte en el torn actiu
// (pot passar que una aula tingui defenses un dia però en un altre torn)
$aules_amb_proj = [];
foreach ($projectes as $p) {
    $aules_amb_proj[$p['defensa_aula_id']] = true;
}
$aules = array_values(array_filter($aules, fn($a) => isset($aules_amb_proj[$a['id_aula']])));

$duracio_min = 45;
if (count($hores) >= 2) {
    $duracio_min = (strtotime('2000-01-01 ' . $hores[1]) - strtotime('2000-01-01 ' . $hores[0])) / 60;
}

// ── Generar HTML ──────────────────────────────────────────────────
ob_start();
?>
<div class="table-responsive">
    <table class="table table-bordered align-middle mb-0 defense-table">
        <thead class="table-light">
            <tr>
                <th class="fw-semibold ps-3 py-3" style="width:150px;">Hora</th>
                <?php foreach ($aules as $aula): ?>
                <th class="fw-semibold text-center py-3">
                    <?= h($aula['codigo']) ?>
                    <div class="small text-muted fw-normal"><?= h($aula['nombre']) ?></div>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($hores as $hora): ?>
            <tr>
                <th class="bg-body-tertiary fw-semibold ps-3" style="white-space:nowrap;">
                    <?= h($hora) ?> – <?= h(horaFi($hora, (int)$duracio_min)) ?>
                </th>
                <?php foreach ($aules as $aula): ?>
                <?php $proj = $matriu[$hora][$aula['id_aula']] ?? null; ?>
                <td class="p-3">
                    <?php if ($proj):
                        $alumnes   = $alumnes_per_proj[$proj['id_proyecto']] ?? [];
                        $label_al  = count($alumnes) === 1 ? 'Alumne/a' : 'Alumnes';
                        $cicle     = strtoupper($proj['ciclo'] ?? '');
                        $grup      = strtoupper($proj['grupo'] ?? '');
                        $tribunal  = $tribunal_per_proj[$proj['id_proyecto']] ?? [];
                        $n_trib    = count($tribunal);
                        $ple       = $n_trib >= 3;
                        $ja_apuntat = $professor_id_actual
                            ? in_array($professor_id_actual, array_column($tribunal, 'id'))
                            : false;
                    ?>
                    <div class="card border rounded-4 shadow-sm defense-card">
                        <div class="card-body p-3 d-flex flex-column justify-content-center">

                            <div class="mb-2 text-center">
                                <span class="badge rounded-pill <?= colorCicle($cicle) ?> px-3 py-1 fw-semibold">
                                    <?= h($cicle . ($grup ? ' ' . $grup : '')) ?>
                                </span>
                            </div>

                            <h3 class="h6 fw-bold mb-3 text-center lh-sm mt-10"><a href="/projecte/<?= (int)$proj['id_proyecto'] ?>" target="_blank"><?= h($proj['nombre']) ?></a></h3>

                            <div class="small text-secondary ">
                                <div class="mb-2">
                                    <div class="fw-semibold text-dark mb-1"><?= h($label_al) ?></div>
                                    <?php if ($alumnes): ?>
                                        <?= h(implode(' · ', $alumnes)) ?>
                                    <?php else: ?>
                                        <span class="fst-italic">Sense alumnes</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Tribunal -->
                                <div class="mt-2">
                                    <div class="fw-semibold text-dark mb-1">
                                        Tribunal
                                        <span class="fw-normal text-muted">(<?= $n_trib ?>/3)</span>
                                    </div>

                                    <?php if (empty($tribunal)): ?>
                                        
                                    <?php else: ?>
                                        <?php foreach ($tribunal as $membre):
                                            $es_jo       = $professor_id_actual && $membre['id'] === $professor_id_actual;
                                            $pot_treure  = $es_jo || esSuperadmin();
                                        ?>
                                        <div class="tribunal-membre d-inline-block"
                                             style="<?= $pot_treure ? 'cursor:pointer;' : '' ?>">
                                            <?php if ($pot_treure): ?>
                                            <span
                                                class="membre-nom"
                                                data-proj-id="<?= (int)$proj['id_proyecto'] ?>"
                                                data-accio="desapuntar"
                                                data-profesor-id="<?= (int)$membre['id'] ?>"
                                                onclick="accioTribunal(this)"
                                                title="<?= $es_jo ? 'Clic per desapuntar-te' : 'Clic per treure del tribunal' ?>">
                                                <span class="text-normal">👤 <?= h($membre['nom']) ?></span>
                                                <span class="text-danger d-none"><?= $es_jo ? '✕ Desapuntar-me' : '✕ Treure' ?></span>
                                            </span>
                                            <?php else: ?>
                                            <span>👤 <?= h($membre['nom']) ?></span>
                                            <?php endif; ?>
                                        </div><br>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- Slots lliures -->
                                    <?php for ($i = $n_trib; $i < 3; $i++): ?>
                                    <div class="text-muted fst-italic">— plaça lliure —</div>
                                    <?php endfor; ?>
                                </div>


                                <!-- Botó apuntar-me -->
                                <div class="link-apuntarme">
                                <?php if ($professor_id_actual && !$ja_apuntat && !$ple): ?>
                                <div class="mt-2 ">

                                    <a href="#"
                                       data-proj-id="<?= (int)$proj['id_proyecto'] ?>"
                                       data-accio="apuntar"
                                       onclick="accioTribunal(this); return false;">
                                       + Apuntar-me
                                    </a>
                                </div>
                                <?php endif; ?>
                                </div>

                            </div>

                            <!-- Botó modificar data (superadmin) -->
                            <?php if (esSuperadmin()): ?>
                            <div class="mt-3 text-center">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm px-3"
                                    data-proj-id="<?= (int)$proj['id_proyecto'] ?>"
                                    data-dia="<?= h($dia) ?>"
                                    data-hora="<?= h($hora) ?>"
                                    data-aula-id="<?= (int)$aula['id_aula'] ?>"
                                    onclick="obrirModalModificar(this)">
                                    ✏ Modificar data
                                </button>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-slot">Sense defensa assignada</div>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th class="fw-semibold ps-3 py-3" style="width:150px;">Hora</th>
                <?php foreach ($aules as $aula): ?>
                <th class="fw-semibold text-center py-3">
                    <?= h($aula['codigo']) ?>
                    <div class="small text-muted fw-normal"><?= h($aula['nombre']) ?></div>
                </th>
                <?php endforeach; ?>
            </tr>
        </tfoot>
    </table>
</div>

<style>
.tribunal-membre .text-normal { display: inline; }
.tribunal-membre .text-danger  { display: none; }
.tribunal-membre:hover .text-normal { display: none; }
.tribunal-membre:hover .text-danger  { display: inline; }
</style>
<?php
$html = ob_get_clean();

// ── Quota tribunals ───────────────────────────────────────────────
$quota_html = '';
if ($professor_id_actual) {
    try {
        $n_grups = (int)$pdo->query("SELECT COUNT(*) FROM app.proyectos WHERE defensa_fecha IS NOT NULL")->fetchColumn();
        $n_profs = (int)$pdo->query("SELECT COUNT(*) FROM app.profesores WHERE activo = true")->fetchColumn();
        $n_ja    = (int)$pdo->prepare("SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE profesor_id = ?")->execute([$professor_id_actual]) ? 0 : 0;
        $stmt_ja = $pdo->prepare("SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE profesor_id = ?");
        $stmt_ja->execute([$professor_id_actual]);
        $n_ja = (int)$stmt_ja->fetchColumn();

        if ($n_profs > 0 && $n_grups > 0) {
            $quota_ideal = ($n_grups * 3) / $n_profs;
            $quota_min   = max(0, (int)floor($quota_ideal) - $n_ja);
            $quota_max   = max(0, (int)ceil($quota_ideal)  - $n_ja);
        } else {
            $quota_min = $quota_max = 0;
        }

        if ($quota_min > 0 || $quota_max > 0) {
            $text = $quota_min === $quota_max
                ? $quota_min . ' tribunal' . ($quota_min !== 1 ? 's' : '')
                : $quota_min . '–' . $quota_max . ' tribunals';
            $quota_html = '
            <div class="card border-0 bg-primary-subtle rounded-4 mb-3">
                <div class="card-body px-4 py-3 d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 text-primary" style="font-size:1.5rem;">🎓</div>
                    <div>
                        <div class="fw-semibold">Et queden per apuntar-te
                            <span class="text-primary fw-bold ms-1">' . h($text) . '</span>
                        </div>
                        <div class="small text-muted">Basat en ' . $n_grups . ' projectes × 3 places / ' . $n_profs . ' professors actius.</div>
                    </div>
                </div>
            </div>';
        } elseif ($quota_min <= 0) {
            $quota_html = '
            <div class="card border-0 bg-success-subtle rounded-4 mb-3">
                <div class="card-body px-4 py-3 d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 text-success" style="font-size:1.5rem;">✅</div>
                    <div class="fw-semibold text-success">Ja tens els tribunals assignats. Gràcies!</div>
                </div>
            </div>';
        }
    } catch (PDOException $e) {
        $quota_html = '';
    }
}

ob_end_clean(); // Descartar qualsevol output espuri abans del JSON
echo json_encode([
    'html'       => $html,
    'quota_html' => $quota_html,
    'stats' => [
        'mati'    => (int)($stats_dia['mati']    ?? 0),
        'tarda'   => (int)($stats_dia['tarda']   ?? 0),
        'aules'   => (int)($stats_dia['aules']   ?? 0),
        'franges' => (int)($stats_dia['franges'] ?? 0),
    ],
]);
exit;
