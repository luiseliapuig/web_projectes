<?php
/**
 * defensas_print.php — Llistat de defenses per imprimir / exportar PDF
 * Sense funcionalitat interactiva. Només visualització.
 */



$curs = $_GET['curs'] ?? null;

// Cursos disponibles
$stmtCursos = $pdo->query("
    SELECT DISTINCT curso_academico
    FROM app.proyectos
    WHERE defensa_fecha IS NOT NULL
    ORDER BY 1 DESC
");
$cursos = $stmtCursos->fetchAll(PDO::FETCH_COLUMN);

// Defenses
$sql = "
    SELECT
        p.id_proyecto,
        p.nombre,
        p.ciclo,
        p.grupo,
        p.defensa_fecha,
        a.codigo  AS aula_codi,
        a.nombre  AS aula_nom,
        COALESCE(
            STRING_AGG(al.nombre || ' ' || al.apellidos, ' · ' ORDER BY al.apellidos, al.nombre),
            ''
        ) AS alumnes
    FROM app.proyectos p
    LEFT JOIN app.aulas a                   ON a.id_aula    = p.defensa_aula_id
    LEFT JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos al                ON al.id_alumno = rpa.alumno_id
    WHERE p.defensa_fecha IS NOT NULL
      " . ($curs ? "AND p.curso_academico = :curs" : "") . "
    GROUP BY p.id_proyecto, p.nombre, p.ciclo, p.grupo,
             p.defensa_fecha, a.codigo, a.nombre
    ORDER BY p.defensa_fecha ASC
";
$stmt = $pdo->prepare($sql);
if ($curs) $stmt->bindValue(':curs', $curs);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa per torn (matí = < 13:00) i dia
$torn = ['mati' => [], 'tarda' => []];
foreach ($rows as $r) {
    $dt  = new DateTime($r['defensa_fecha']);
    $dia = $dt->format('Y-m-d');
    $h   = (int)$dt->format('H');
    $t   = $h < 13 ? 'mati' : 'tarda';
    $torn[$t][$dia][] = array_merge($r, ['hora' => $dt->format('H:i')]);
}

// Per cada torn+dia, calcula max_cols per fer la taula
function maxCols(array $diaRows): int {
    $perHora = [];
    foreach ($diaRows as $r) {
        $perHora[$r['hora']][] = $r;
    }
    return max(array_map('count', $perHora) ?: [1]);
}

function horesUniques(array $diaRows): array {
    $h = array_unique(array_column($diaRows, 'hora'));
    sort($h);
    return $h;
}

function horaFi(string $hora, int $dur = 45): string {
    [$hh, $mm] = array_map('intval', explode(':', $hora));
    $t = $hh * 60 + $mm + $dur;
    return sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
}

function nomDiaLlarg(string $dia): string {
    $dt   = new DateTime($dia);
    $dies = ['Diumenge','Dilluns','Dimarts','Dimecres','Dijous','Divendres','Dissabte'];
    $mes  = ['gener','febrer','març','abril','maig','juny','juliol','agost','setembre','octubre','novembre','desembre'];
    return $dies[(int)$dt->format('w')] . ', ' . $dt->format('j') . ' de ' . $mes[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
}

$badgeClass = [
    'DAM'  => 'badge-DAM',
    'DAW'  => 'badge-DAW',
    'ASIX' => 'badge-ASIX',
    'SMX'  => 'badge-SMX',
    'DEV'  => 'badge-DEV',
];

$DUR = 45; // minuts per defensa (podria venir de config)
?>

<style>
/* ── Base ───────────────────────────────────────────────────────── */
body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    font-size: .88rem;
    background: #f4f7fb;
    color: #1e293b;
}
.page-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }

/* ── Toolbar (no s'imprimeix) ───────────────────────────────────── */
.toolbar {
    display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: .6rem; padding: .6rem .9rem;
    margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.toolbar select { font-size: .82rem; }
.btn-print {
    display: inline-flex; align-items: center; gap: .35rem;
    background: #1e3a8a; color: #fff; border: none;
    border-radius: .4rem; padding: .38rem .9rem;
    font-size: .82rem; font-weight: 600; cursor: pointer;
    margin-left: auto; transition: background .15s;
}
.btn-print:hover { background: #1e40af; }

/* ── Capçalera document ─────────────────────────────────────────── */
.doc-header {
    display: flex; justify-content: space-between; align-items: flex-end;
    border-bottom: 2.5px solid #1e3a8a; padding-bottom: .6rem; margin-bottom: 1.6rem;
}
.doc-header .titol    { font-size: 1.15rem; font-weight: 800; color: #1e3a8a; }
.doc-header .subtitol { font-size: .78rem; color: #64748b; margin-top: .15rem; }
.doc-header .meta     { font-size: .72rem; color: #94a3b8; text-align: right; line-height: 1.6; }

/* ── Etiquetes torn ─────────────────────────────────────────────── */
.torn-label {
    display: flex; align-items: center; gap: .5rem;
    font-size: .72rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase;
    margin: 1.4rem 0 .7rem;
}
.torn-label::after { content:''; flex:1; height:1.5px; }
.torn-mati  .torn-label { color: #92400e; }
.torn-mati  .torn-label::after { background: #fde68a; }
.torn-tarda .torn-label { color: #1e3a8a; }
.torn-tarda .torn-label::after { background: #bfdbfe; }

/* ── Bloc de dia ────────────────────────────────────────────────── */
.dia-bloc { margin-bottom: 1.2rem; }
.dia-cap {
    font-size: .68rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
    color: #6b7280; background: #f1f5f9; border: 1px solid #e2e8f0;
    border-bottom: none; border-radius: .35rem .35rem 0 0;
    padding: .28rem .7rem; display: flex; align-items: center; gap: .35rem;
}
.dia-cap::before { content:''; width:6px; height:6px; border-radius:50%; background:#cbd5e1; }

/* ── Taula ──────────────────────────────────────────────────────── */
.def-table {
    width: 100%; border-collapse: collapse;
    border: 1px solid #e2e8f0;
    border-radius: 0 0 .4rem .4rem; overflow: hidden;
    table-layout: fixed;
}
.def-table th, .def-table td { border: 1px solid #e2e8f0; }

.def-table thead th {
    background: #f8fafc; font-size: .65rem; font-weight: 700;
    color: #64748b; text-align: center; padding: .25rem .5rem;
    letter-spacing: .03em; text-transform: uppercase;
    border-bottom: 2px solid #cbd5e1; white-space: nowrap;
}
.def-table thead th.th-hora {
    width: 70px; min-width: 70px; text-align: left; background: #edf0f4;
}

.def-table td.td-hora {
    width: 70px; min-width: 70px;
    background: #f8fafc; text-align: center; vertical-align: middle;
    padding: .3rem .4rem; border-right: 2px solid #cbd5e1;
}
.hora-ini { display:block; font-size:.82rem; font-weight:800; color:#1e293b; }
.hora-fin { display:block; font-size:.62rem; color:#94a3b8; margin-top:.03rem; }

.def-table td.td-proj { vertical-align: top; padding: .45rem .5rem; }
.def-table td.td-buida {
    background: repeating-linear-gradient(135deg,transparent,transparent 5px,rgba(0,0,0,.02) 5px,rgba(0,0,0,.02) 6px);
}
.def-table tbody tr:nth-child(even) td         { background-color: #fafbfc; }
.def-table tbody tr:nth-child(even) td.td-hora { background-color: #f1f5f9; }
.def-table tbody tr:nth-child(even) td.td-buida {
    background-image: repeating-linear-gradient(135deg,rgba(0,0,0,.01),rgba(0,0,0,.01) 5px,rgba(0,0,0,.03) 5px,rgba(0,0,0,.03) 6px);
}

/* ── Contingut cel·la ───────────────────────────────────────────── */
.proj-pills { display:flex; flex-wrap:wrap; gap:.2rem; margin-bottom:.3rem; }

.trib-badge {
    display:inline-flex; align-items:center;
    font-size:.62rem; font-weight:700;
    padding:.07rem .38rem; border-radius:999px; line-height:1.4; white-space:nowrap;
}
.badge-DAM  { background:#dbeafe; color:#1d4ed8; }
.badge-DAW  { background:#dcfce7; color:#15803d; }
.badge-ASIX { background:#fef9c3; color:#854d0e; }
.badge-SMX  { background:#e0f2fe; color:#0369a1; }
.badge-DEV  { background:#fee2e2; color:#b91c1c; }
.badge-GEN  { background:#f3e8ff; color:#6b21a8; }

.trib-badge-meta {
    display:inline-flex; align-items:center; gap:.15rem;
    font-size:.62rem; font-weight:500;
    padding:.07rem .38rem; border-radius:999px; line-height:1.4;
    background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; white-space:nowrap;
}

.proj-nom {
    font-size:.77rem; font-weight:600; color:#111827;
    text-decoration:none; line-height:1.3; display:block; margin-bottom:.12rem;
}
.proj-alumnes {
    font-size:.67rem; color:#6b7280; line-height:1.4;
}

/* ── Impressió ──────────────────────────────────────────────────── */
@media print {
    body { background:#fff; font-size:.82rem; }
    .toolbar { display:none !important; }
    .site-header, .site-footer { display:none !important; }
    .page-wrap { padding:.3cm .4cm; max-width:100%; }

    .doc-header { border-bottom-color:#1e3a8a; margin-bottom:1rem; }
    .doc-header .titol { font-size:13pt; }

    .torn-label { margin:.8rem 0 .4rem; font-size:.65rem; }

    .dia-bloc { break-inside: avoid; margin-bottom:.8rem; }
    .dia-cap  { font-size:.62rem; }

    .def-table { font-size:.75rem; }
    .def-table thead th, .def-table td { border-color:#d1d5db; }
    .def-table thead th { font-size:.62rem; padding:.2rem .4rem; }
    .hora-ini  { font-size:.76rem; }
    .hora-fin  { font-size:.58rem; }
    .def-table td.td-proj { padding:.3rem .4rem; }

    .trib-badge, .trib-badge-meta { font-size:.6rem; padding:.05rem .3rem; }
    .proj-nom     { font-size:.72rem; }
    .proj-alumnes { font-size:.62rem; }

    .badge-DAM  { background:#dbeafe!important; color:#1d4ed8!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .badge-DAW  { background:#dcfce7!important; color:#15803d!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .badge-ASIX { background:#fef9c3!important; color:#854d0e!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .badge-SMX  { background:#e0f2fe!important; color:#0369a1!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .badge-DEV  { background:#fee2e2!important; color:#b91c1c!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .trib-badge-meta { background:#f1f5f9!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .def-table td.td-hora { background:#f8fafc!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .def-table thead th   { background:#f8fafc!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .dia-cap              { background:#f1f5f9!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    @page { size:A4 landscape; margin:1.2cm 1.5cm; }
}
</style>
</head>
<body>
<div class="page-wrap">

  <!-- Toolbar (no s'imprimeix) -->
  <div class="toolbar">
    <?php if ($cursos): ?>
    <form method="get" class="d-flex align-items-center gap-2 mb-0">
      <label class="text-muted small fw-semibold mb-0">Curs:</label>
      <select name="curs" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Tots els cursos</option>
        <?php foreach ($cursos as $c): ?>
        <option value="<?= h($c) ?>" <?= $c === $curs ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach ?>
      </select>
    </form>
    <?php endif ?>
    <span class="text-muted small"><?= count($rows) ?> defenses</span>
    <button class="btn-print" onclick="window.print()">
      <i class="bi bi-printer"></i> Imprimir / PDF
    </button>
  </div>

  <!-- Capçalera document -->
  <div class="doc-header">
    <div>
      <div class="titol">Institut Puig Castellar · Defenses de Projectes FP</div>
      <div class="subtitol"><?= $curs ? h($curs) : 'Tots els cursos' ?> · <?= count($rows) ?> defenses planificades</div>
    </div>
    <div class="meta">
      Generat el <?= date('d/m/Y H:i') ?>
    </div>
  </div>

  <?php
  $torns = [
    'mati'  => ['ico' => '🌅', 'label' => 'Torn de Matí',  'cls' => 'torn-mati'],
    'tarda' => ['ico' => '🌆', 'label' => 'Torn de Tarda', 'cls' => 'torn-tarda'],
  ];

  foreach ($torns as $tornKey => $tornMeta):
    if (empty($torn[$tornKey])) continue;
  ?>
  <div class="<?= $tornMeta['cls'] ?>">
    <div class="torn-label"><?= $tornMeta['ico'] ?> <?= $tornMeta['label'] ?></div>

    <?php foreach ($torn[$tornKey] as $dia => $diaRows):
      $hores   = horesUniques($diaRows);
      $maxCols = maxCols($diaRows);
      // Indexa per hora i ordena per cicle
      $ordreCicle = ['SMX' => 0, 'DAM' => 1, 'DAW' => 2, 'ASIX' => 3, 'DEV' => 4];
      $perHora = [];
      foreach ($diaRows as $r) $perHora[$r['hora']][] = $r;
      foreach ($perHora as &$projs) {
          usort($projs, function($a, $b) use ($ordreCicle) {
              $cmp = ($ordreCicle[$a['ciclo']] ?? 99) <=> ($ordreCicle[$b['ciclo']] ?? 99);
              return $cmp !== 0 ? $cmp : strcmp($a['grupo'] ?? '', $b['grupo'] ?? '');
          });
      }
      unset($projs);
    ?>
    <div class="dia-bloc">
      <div class="dia-cap"><?= h(nomDiaLlarg($dia)) ?></div>
      <div style="overflow-x:auto">
        <table class="def-table">
          <thead>
            <tr>
              <th class="th-hora">Hora</th>
              <?php for ($c = 1; $c <= $maxCols; $c++): ?>
              <th>Defensa <?= $c ?></th>
              <?php endfor ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hores as $hora):
              $projs = $perHora[$hora] ?? [];
            ?>
            <tr>
              <td class="td-hora">
                <span class="hora-ini"><?= h($hora) ?></span>
                <span class="hora-fin"><?= h(horaFi($hora, $DUR)) ?></span>
              </td>
              <?php for ($c = 0; $c < $maxCols; $c++):
                $proj = $projs[$c] ?? null;
              ?>
              <?php if ($proj): ?>
              <td class="td-proj">
                <div class="proj-pills">
                  <?php
                    $cicleGrup = h($proj['ciclo']) . ($proj['grupo'] ? ' ' . h($proj['grupo']) : '');
                    $cls = $badgeClass[$proj['ciclo']] ?? 'badge-GEN';
                    $aulaText = $proj['aula_nom']
                        ? h($proj['aula_codi']) . ' · ' . h($proj['aula_nom'])
                        : h($proj['aula_codi'] ?: '—');
                  ?>
                  <span class="trib-badge <?= $cls ?>"><?= $cicleGrup ?></span>
                  <span class="trib-badge-meta"><i class="bi bi-door-open" style="font-size:.55rem"></i> <?= $aulaText ?></span>
                </div>
                <?php if ($proj['nombre']): ?>
                <span class="proj-nom"><?= h($proj['nombre']) ?></span>
                <?php endif ?>
                <?php if ($proj['alumnes']): ?>
                <div class="proj-alumnes"><?= h($proj['alumnes']) ?></div>
                <?php endif ?>
              </td>
              <?php else: ?>
              <td class="td-buida"></td>
              <?php endif ?>
              <?php endfor ?>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endforeach ?>

  <?php if (empty($rows)): ?>
  <div class="text-center text-muted py-5">No hi ha defenses planificades.</div>
  <?php endif ?>

</div>
<script>
window.PAGE_TITLE = 'Calendari defenses';
</script>

