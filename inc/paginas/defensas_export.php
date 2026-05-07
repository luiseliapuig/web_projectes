<?php
/**
 * defensas_export.php — Llistat de defenses per exportar a PDF
 * Stack: PHP clàssic + PostgreSQL (esquema app) + Bootstrap 5
 */



// Filtre curs acadèmic (opcional, per querystring)
$curs = $_GET['curs'] ?? null;

// Consulta: totes les defenses planificades, agrupades per data i hora
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
            STRING_AGG(al.nombre || ' ' || al.apellidos, ', ' ORDER BY al.apellidos, al.nombre),
            '—'
        ) AS alumnes
    FROM app.proyectos p
    LEFT JOIN app.aulas      a  ON a.id_aula   = p.defensa_aula_id
    LEFT JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos    al ON al.id_alumno = rpa.alumno_id
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

// Agrupa per dia
$perDia = [];
foreach ($rows as $r) {
    $dia = (new DateTime($r['defensa_fecha']))->format('Y-m-d');
    $perDia[$dia][] = $r;
}

// Cursos disponibles per al selector
$stmtCursos = $pdo->query("SELECT DISTINCT curso_academico FROM app.proyectos WHERE defensa_fecha IS NOT NULL ORDER BY 1 DESC");
$cursos = $stmtCursos->fetchAll(PDO::FETCH_COLUMN);

$titol = 'Llistat de Defenses' . ($curs ? ' — ' . h($curs) : '');
?>
<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="UTF-8">
<title><?= $titol ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
/* ── Pantalla ─────────────────────────────────────────────────────────────── */
:root {
    --brand: #1a3a5c;
    --brand-light: #e8f0f9;
    --accent: #2e7dd4;
    --muted: #6c757d;
    --border: #dee2e6;
    --radius: 10px;
}

body {
    background: #f4f7fb;
    font-family: 'Segoe UI', system-ui, sans-serif;
    color: #212529;
}

.page-header {
    background: var(--brand);
    color: #fff;
    padding: 2rem 0 1.5rem;
    margin-bottom: 2rem;
}
.page-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0; letter-spacing: .02em; }
.page-header .sub { opacity: .7; font-size: .9rem; margin-top: .25rem; }

.controls { margin-bottom: 1.5rem; }

/* Targeta de dia */
.dia-card {
    background: #fff;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.dia-header {
    background: var(--brand);
    color: #fff;
    padding: .65rem 1.25rem;
    font-weight: 700;
    font-size: 1rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: .6rem;
}
.dia-header .badge-dia {
    background: rgba(255,255,255,.18);
    border-radius: 6px;
    padding: .1rem .5rem;
    font-size: .8rem;
    font-weight: 400;
    letter-spacing: .02em;
}

/* Fila de defensa */
.defensa-row {
    display: grid;
    grid-template-columns: 5rem 1fr 1fr 1fr;
    align-items: center;
    padding: .7rem 1.25rem;
    gap: .75rem;
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.defensa-row:last-child { border-bottom: none; }
.defensa-row:hover { background: var(--brand-light); }

.hora {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--accent);
    white-space: nowrap;
}
.cicle-grup {
    font-weight: 600;
    font-size: .95rem;
}
.cicle-grup .grup {
    font-weight: 400;
    color: var(--muted);
    font-size: .85rem;
}
.aula-pill {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: var(--brand-light);
    color: var(--brand);
    border-radius: 20px;
    padding: .2rem .75rem;
    font-size: .82rem;
    font-weight: 600;
    white-space: nowrap;
}
.alumnes {
    font-size: .82rem;
    color: var(--muted);
    line-height: 1.4;
}
.nom-projecte {
    font-size: .8rem;
    color: #888;
    font-style: italic;
    margin-top: .15rem;
}

.btn-export {
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: .5rem 1.25rem;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    transition: background .2s;
}
.btn-export:hover { background: #1a6abf; }

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--muted);
}
.empty-state svg { margin-bottom: 1rem; opacity: .3; }

/* ── Impressió / PDF ──────────────────────────────────────────────────────── */
@media print {
    body { background: #fff; font-size: 11pt; }
    .controls, .btn-export, .no-print { display: none !important; }
    .page-header {
        background: #fff !important;
        color: #000 !important;
        border-bottom: 2px solid #000;
        padding: .5rem 0;
        margin-bottom: 1rem;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .page-header h1 { font-size: 14pt; color: #000; }
    .page-header .sub { color: #555; }

    .dia-card {
        box-shadow: none;
        border: 1px solid #ccc;
        break-inside: avoid;
        margin-bottom: 1rem;
    }
    .dia-header {
        background: #1a3a5c !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        padding: .4rem 1rem;
        font-size: 10pt;
    }
    .dia-header .badge-dia { background: rgba(255,255,255,.25) !important; }

    .defensa-row {
        padding: .45rem 1rem;
        grid-template-columns: 4.5rem 1fr 1fr 1fr;
    }
    .defensa-row:hover { background: transparent; }
    .hora { font-size: 10.5pt; color: #1a3a5c; }
    .cicle-grup { font-size: 10pt; }
    .aula-pill { background: #e8f0f9 !important; color: #1a3a5c !important; font-size: 9pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .alumnes { font-size: 8.5pt; }
    .nom-projecte { font-size: 8pt; }

    @page {
        size: A4;
        margin: 1.5cm 1.5cm 2cm;
    }
}
</style>
</head>
<body>

<div class="page-header no-print">
  <div class="container">
    <h1>📋 <?= $titol ?></h1>
    <div class="sub">Institut Puig Castellar — Defenses de Projectes FP</div>
  </div>
</div>

<!-- Capçalera visible només a l'imprimir -->
<div class="container d-none" id="print-header" style="display:none">
  <div style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2px solid #1a3a5c; padding-bottom:.5rem; margin-bottom:1rem;">
    <div>
      <div style="font-size:14pt; font-weight:700; color:#1a3a5c;">Institut Puig Castellar</div>
      <div style="font-size:11pt; color:#555;">Llistat de Defenses de Projectes FP<?= $curs ? ' — ' . h($curs) : '' ?></div>
    </div>
    <div style="font-size:9pt; color:#888; text-align:right;">
      Generat el <?= date('d/m/Y H:i') ?><br>
      <?= count($rows) ?> defenses planificades
    </div>
  </div>
</div>

<div class="container">

  <!-- Controls -->
  <div class="controls d-flex flex-wrap align-items-center gap-3 no-print">
    <?php if ($cursos): ?>
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="fw-600 text-muted small">Curs acadèmic:</label>
      <select name="curs" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Tots</option>
        <?php foreach ($cursos as $c): ?>
        <option value="<?= h($c) ?>" <?= $c === $curs ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>

    <button class="btn-export ms-auto" onclick="imprimirPdf()">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
        <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
      </svg>
      Exportar PDF
    </button>

    <span class="text-muted small"><?= count($rows) ?> defenses</span>
  </div>

  <!-- Llistat -->
  <?php if (empty($perDia)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#1a3a5c" viewBox="0 0 16 16">
        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
      </svg>
      <p class="mb-0 fw-semibold">No hi ha defenses planificades</p>
      <p class="small">Assigna data i aula als projectes per veure-les aquí.</p>
    </div>
  <?php else: ?>
    <?php foreach ($perDia as $dia => $defenses):
      $dt = new DateTime($dia);
      $diesCat = ['diumenge','dilluns','dimarts','dimecres','dijous','divendres','dissabte'];
      $mesoCat = ['gener','febrer','març','abril','maig','juny','juliol','agost','setembre','octubre','novembre','desembre'];
      $nomDia = $diesCat[(int)$dt->format('w')];
      $nomMes = $mesoCat[(int)$dt->format('n') - 1];
      $labelDia = ucfirst($nomDia) . ', ' . $dt->format('j') . ' de ' . $nomMes . ' de ' . $dt->format('Y');
      $nDef = count($defenses);
    ?>
    <div class="dia-card">
      <div class="dia-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16" style="opacity:.8">
          <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
        </svg>
        <?= h($labelDia) ?>
        <span class="badge-dia"><?= $nDef ?> defensa<?= $nDef !== 1 ? 'es' : '' ?></span>
      </div>

      <?php foreach ($defenses as $d):
        $hora = (new DateTime($d['defensa_fecha']))->format('H:i');
        $aula = $d['aula_codi'] ? $d['aula_codi'] : ($d['aula_nom'] ?: '—');
      ?>
      <div class="defensa-row">
        <div class="hora"><?= h($hora) ?></div>
        <div class="cicle-grup">
          <?= h($d['ciclo']) ?>
          <?php if ($d['grupo']): ?>
            <div class="grup"><?= h($d['grupo']) ?></div>
          <?php endif; ?>
        </div>
        <div>
          <span class="aula-pill">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8.277.084a.5.5 0 0 0-.554 0l-7.5 5A.5.5 0 0 0 .5 6h1.875v7H1.5a.5.5 0 0 0 0 1h13a.5.5 0 0 0 0-1h-.875V6H15.5a.5.5 0 0 0 .277-.916l-7.5-5zM12.375 6v7h-1.25V6h1.25zm-2.5 0v7h-3.75V6h3.75zm-5 0v7h-1.25V6h1.25zM8 4a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
            </svg>
            <?= h($aula) ?>
          </span>
        </div>
        <div>
          <div class="alumnes"><?= h($d['alumnes']) ?></div>
          <?php if ($d['nombre']): ?>
            <div class="nom-projecte"><?= h($d['nombre']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div><!-- /container -->

<script>
function imprimirPdf() {
    // Mostra capçalera d'impressió i amaga controls
    document.getElementById('print-header').style.display = 'block';
    window.print();
    document.getElementById('print-header').style.display = 'none';
}
</script>
</body>
</html>
