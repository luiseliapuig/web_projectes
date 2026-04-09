<?php
// ── Càlcul de la nota final ───────────────────────────────────────
// Pesos: Tutor 20%, Memòria 30%, Projecte 30%, Defensa 20%
// Cada dimensió és sobre 5 → normalitzem a 10

// Nota tutor: mitjana de les 5 dimensions → sobre 10
$notaTutorCamps = [
    $proyecto['nota_tutor_planificacion'] ?? null,
    $proyecto['nota_tutor_gestion']       ?? null,
    $proyecto['nota_tutor_memoria']        ?? null,
    $proyecto['nota_tutor_proyecto']       ?? null,
    $proyecto['nota_tutor_compromiso']     ?? null,
];
$notaTutorVals = array_filter($notaTutorCamps, fn($v) => $v !== null);
$notaTutor10   = count($notaTutorVals) > 0
    ? round(array_sum($notaTutorVals) / count($notaTutorVals) * 2, 2)
    : null;

// Nota tribunal: mitjana de cada dimensió entre tots els membres → sobre 10
try {
    $stmtNF = $pdo->prepare("
        SELECT
            AVG(nota_memoria)  AS avg_memoria,
            AVG(nota_proyecto) AS avg_proyecto,
            AVG(nota_defensa)  AS avg_defensa
        FROM app.evaluacion_tribunal
        WHERE proyecto_id = ?
          AND (nota_memoria IS NOT NULL OR nota_proyecto IS NOT NULL OR nota_defensa IS NOT NULL)
    ");
    $stmtNF->execute([$idProyecto]);
    $avgTrib = $stmtNF->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $avgTrib = ['avg_memoria' => null, 'avg_proyecto' => null, 'avg_defensa' => null];
}

$avgMem  = $avgTrib['avg_memoria']  !== null ? round((float)$avgTrib['avg_memoria']  * 2, 2) : null;
$avgProj = $avgTrib['avg_proyecto'] !== null ? round((float)$avgTrib['avg_proyecto'] * 2, 2) : null;
$avgDef  = $avgTrib['avg_defensa']  !== null ? round((float)$avgTrib['avg_defensa']  * 2, 2) : null;

// Nota final: suma directa dels parcials ponderats (opció B — nota parcial)
$sumaFinal    = 0;
$pesosTenemos = 0;

if ($notaTutor10 !== null) { $sumaFinal += $notaTutor10 * 0.20; $pesosTenemos += 0.20; }
if ($avgMem      !== null) { $sumaFinal += $avgMem      * 0.30; $pesosTenemos += 0.30; }
if ($avgProj     !== null) { $sumaFinal += $avgProj     * 0.30; $pesosTenemos += 0.30; }
if ($avgDef      !== null) { $sumaFinal += $avgDef      * 0.20; $pesosTenemos += 0.20; }

$notaFinal  = $pesosTenemos > 0 ? round($sumaFinal, 2) : null;
$notaParcial = $pesosTenemos > 0 && $pesosTenemos < 1.0;

function fmtNota(?float $v, float $max): string {
    if ($v === null) return '<span class="text-muted">—</span>';
    return number_format($v, 1, ',', '.') . ' <span style="opacity:.5;font-size:.8em">/ ' . number_format($max, 0) . '</span>';
}
?>

<div class=" mb-30" id="bloque-nota-final">
  <div class="bloque-final rounded-4 p-4 p-md-5">
    <div class="row align-items-center">

      <!-- Desglose -->
      <div class="col-md-7">
        <div class="row g-4">
          <div class="col-6">
            <div class="label">Tutor (20%)</div>
            <div class="valor" data-slot="tutor"><?= fmtNota($notaTutor10 !== null ? $notaTutor10 * 0.20 : null, 2) ?></div>
          </div>
          <div class="col-6">
            <div class="label">Memòria (30%)</div>
            <div class="valor" data-slot="memoria"><?= fmtNota($avgMem !== null ? $avgMem * 0.30 : null, 3) ?></div>
          </div>
          <div class="col-6">
            <div class="label">Projecte (30%)</div>
            <div class="valor" data-slot="proyecto"><?= fmtNota($avgProj !== null ? $avgProj * 0.30 : null, 3) ?></div>
          </div>
          <div class="col-6">
            <div class="label">Defensa (20%)</div>
            <div class="valor" data-slot="defensa"><?= fmtNota($avgDef !== null ? $avgDef * 0.20 : null, 2) ?></div>
          </div>
        </div>
      </div>

      <!-- Nota final -->
      <div class="col-md-5 mt-4 mt-md-0">
        <div class="divider ps-md-4 text-center">
          <div class="label mb-2 notafinal">Nota final</div>
          <div class="nota-final" data-slot="final">
            <?= $notaFinal !== null ? number_format($notaFinal, 1, ',', '.') : '—' ?>
          </div>
          <div class="nota-max">
            sobre 10<?= $notaParcial ? ' <span style="font-size:.75em;opacity:.7">(parcial)</span>' : '' ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
