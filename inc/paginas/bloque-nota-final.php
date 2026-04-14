<?php
// ── Permisos ajust nota individual ───────────────────────────────
$permiso_tutor    = false;
$permiso_tribunal = true;
$salto            = 0.5;

// ── Determinar si el professor actual pot ajustar ─────────────────
$professorActualId = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;

$potAjustar = false;
if ($professorActualId) {
    if ($permiso_tutor && (int)($proyecto['tutor_id'] ?? -1) === $professorActualId) {
        $potAjustar = true;
    }
    if ($permiso_tribunal && !$potAjustar) {
        try {
            $stmtPerm = $pdo->prepare("
                SELECT 1 FROM app.rel_profesores_tribunal
                WHERE id_proyecto = ? AND profesor_id = ?
            ");
            $stmtPerm->execute([$idProyecto, $professorActualId]);
            if ($stmtPerm->fetch()) $potAjustar = true;
        } catch (PDOException $e) {}
    }
    if (esSuperadmin()) $potAjustar = true;
}

// ── Càlcul de la nota final ───────────────────────────────────────
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

$sumaFinal    = 0;
$pesosTenemos = 0;
if ($notaTutor10 !== null) { $sumaFinal += $notaTutor10 * 0.20; $pesosTenemos += 0.20; }
if ($avgMem      !== null) { $sumaFinal += $avgMem      * 0.30; $pesosTenemos += 0.30; }
if ($avgProj     !== null) { $sumaFinal += $avgProj     * 0.30; $pesosTenemos += 0.30; }
if ($avgDef      !== null) { $sumaFinal += $avgDef      * 0.20; $pesosTenemos += 0.20; }

$notaFinal   = $pesosTenemos > 0 ? round($sumaFinal, 2) : null;
$notaParcial = $pesosTenemos > 0 && $pesosTenemos < 1.0;

// ── Ajustos individuals per alumne ────────────────────────────────
try {
    $stmtAlumnes = $pdo->prepare("
        SELECT
            a.id_alumno,
            a.nombre,
            a.apellidos,
            COALESCE(aj.ajuste, 0) AS ajuste
        FROM app.rel_proyectos_alumnos rpa
        JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        LEFT JOIN app.ajustes_nota_individual aj
            ON aj.proyecto_id = rpa.proyecto_id AND aj.alumno_id = a.id_alumno
        WHERE rpa.proyecto_id = ?
        ORDER BY a.apellidos, a.nombre
    ");
    $stmtAlumnes->execute([$idProyecto]);
    $alumnesNota = $stmtAlumnes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alumnesNota = [];
}

function fmtNota(?float $v, float $max): string {
    if ($v === null) return '<span class="text-muted">—</span>';
    return number_format($v, 1, ',', '.') . ' <span style="opacity:.5;font-size:.8em">/ ' . number_format($max, 0) . '</span>';
}

function notaAmbAjust(?float $base, float $ajust): string {
    if ($base === null) return '—';
    $nota = max(0, min(10, round($base + $ajust, 1)));
    return number_format($nota, 1, ',', '.');
}
?>

<div class="mb-30" id="bloque-nota-final">
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

      <!-- Nota final + ajustos individuals -->
      <div class="col-md-5 mt-4 mt-md-0">
        <div class="divider ps-md-4 text-center">

          <div class="label mb-2 notafinal">Nota final</div>

          <div class="nota-final" data-slot="final">
            <?= $notaFinal !== null ? number_format($notaFinal, 1, ',', '.') : '—' ?>
          </div>

          <div class="nota-max mb-3">
            sobre 10<?= $notaParcial ? ' <span style="font-size:.75em;opacity:.7">(parcial)</span>' : '' ?>
          </div>

          <?php if (!empty($alumnesNota)): ?>
          <div class="mt-4 text-start">
            <?php foreach ($alumnesNota as $al):
                $ajust     = (float)$al['ajuste'];
                $notaAlumne = $notaFinal !== null ? max(0, min(10, round($notaFinal + $ajust, 1))) : null;
            ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="label">
                <?= h(trim($al['nombre'] . ' ' . $al['apellidos'])) ?>
                <?php if ($ajust != 0): ?>
                <span class="etiqueta-ajust ms-1"
                      style="font-size:.75em;opacity:.85;cursor:<?= $potAjustar ? 'pointer' : 'default' ?>;color:#F59E0B;"
                      data-alumno="<?= (int)$al['id_alumno'] ?>"
                      data-proyecto="<?= (int)$idProyecto ?>"
                      data-ajust="<?= $ajust ?>">
                  (<?= ($ajust > 0 ? '+' : '') . number_format($ajust, 1, ',', '.') ?>)
                </span>
                <?php endif; ?>
              </div>
              <div class="d-flex align-items-center gap-2">
                <?php if ($potAjustar && $notaFinal !== null): ?>
                <span class="btn-ajuste-circulo"
                      style="cursor:pointer;user-select:none;"
                      data-alumno="<?= (int)$al['id_alumno'] ?>"
                      data-proyecto="<?= (int)$idProyecto ?>"
                      data-step="-<?= $salto ?>">−</span>
                <?php endif; ?>

                <span class="valor nota-alumne"
                      data-alumno="<?= (int)$al['id_alumno'] ?>"
                      data-ajust="<?= $ajust ?>"
                      style="<?= ($potAjustar && $notaFinal !== null) ? 'cursor:pointer;' : '' ?>">
                  <?= $notaAlumne !== null ? number_format($notaAlumne, 1, ',', '.') : '—' ?>
                </span>

                <?php if ($potAjustar && $notaFinal !== null): ?>
                <span class="btn-ajuste-circulo"
                      style="cursor:pointer;user-select:none;"
                      data-alumno="<?= (int)$al['id_alumno'] ?>"
                      data-proyecto="<?= (int)$idProyecto ?>"
                      data-step="<?= $salto ?>">+</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
    let notaBase = <?= $notaFinal !== null ? $notaFinal : 'null' ?>;
    window._notaBaseProjecte = notaBase;
    const salto    = <?= (float)$salto ?>;
    if (notaBase === null) return;

    // ── Ajust ─────────────────────────────────────────────────────
    document.querySelectorAll('.btn-ajuste-circulo').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const alumnoId   = parseInt(btn.dataset.alumno);
            const proyectoId = parseInt(btn.dataset.proyecto);
            const step       = parseFloat(btn.dataset.step);

            const span        = document.querySelector('.nota-alumne[data-alumno="' + alumnoId + '"]');
            const ajustActual = parseFloat(span?.dataset.ajust ?? '0');
            const nouAjust    = Math.round((ajustActual + step) * 10) / 10;
            const notaResultant = Math.round((window._notaBaseProjecte + nouAjust) * 10) / 10;
            if (notaResultant < 0 || notaResultant > 10) return;

            // ── Actualització visual immediata ────────────────────
            span.dataset.ajust = nouAjust;
            span.textContent   = notaResultant.toFixed(1).replace('.', ',');
            actualitzarEtiquetaAjust(alumnoId, nouAjust);

            const fd = new FormData();
            fd.append('proyecto_id', proyectoId);
            fd.append('alumno_id',   alumnoId);
            fd.append('ajuste',      nouAjust);

            try {
                const res  = await fetch('/index.php?main=ajust_nota_guardar&raw=1', { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.ok) {
                    alert(data.missatge || 'Error en guardar.');
                    span.dataset.ajust = ajustActual;
                    span.textContent   = (Math.round((window._notaBaseProjecte + ajustActual) * 10) / 10).toFixed(1).replace('.', ',');
                    actualitzarEtiquetaAjust(alumnoId, ajustActual);
                }
            } catch (e) {
                alert('Error de connexió: ' + e.message);
            }
        });
    });

    const potAjustar = <?= $potAjustar ? 'true' : 'false' ?>;

    // ── Hover sobre l'ajust (−1,0) → mostrar "reset" ─────────────
    document.querySelectorAll('.etiqueta-ajust').forEach(afegirListenersEtiqueta);

    // ── Helper: actualitzar etiqueta (±X) al costat del nom ───────
    function actualitzarEtiquetaAjust(alumnoId, ajust) {
        const span = document.querySelector('.nota-alumne[data-alumno="' + alumnoId + '"]');
        if (!span) return;
        // El .label está en el div padre (justify-content-between), no en el mismo div que el span
        const row   = span.closest('.d-flex.justify-content-between');
        const label = row?.querySelector('.label');
        if (!label) return;

        let etiqueta = label.querySelector('.etiqueta-ajust');
        if (ajust == 0) {
            etiqueta?.remove();
        } else {
            if (!etiqueta) {
                etiqueta = document.createElement('span');
                etiqueta.className = 'etiqueta-ajust ms-1';
                etiqueta.style.cssText = 'font-size:.75em;opacity:.85;cursor:pointer;color:#F59E0B;';
                afegirListenersEtiqueta(etiqueta);
                label.appendChild(etiqueta);
            }
            const signe = ajust > 0 ? '+' : '';
            etiqueta.dataset.ajust    = ajust;
            etiqueta.dataset.alumno   = alumnoId;
            etiqueta.dataset.proyecto = document.querySelector('.btn-ajuste-circulo[data-alumno="' + alumnoId + '"]')?.dataset.proyecto ?? '';
            etiqueta.textContent = '(' + signe + ajust.toFixed(1).replace('.', ',') + ')';
        }
    }

    function afegirListenersEtiqueta(etq) {
        if (!potAjustar) return;
        etq.addEventListener('mouseenter', function () {
            etq.dataset.textSaved = etq.textContent.trim();
            etq.textContent = 'reset';
        });
        etq.addEventListener('mouseleave', function () {
            if (etq.dataset.textSaved) etq.textContent = etq.dataset.textSaved;
        });
        etq.addEventListener('click', async function () {
            const alumnoId   = parseInt(etq.dataset.alumno);
            const proyectoId = parseInt(etq.dataset.proyecto);
            const fd = new FormData();
            fd.append('proyecto_id', proyectoId);
            fd.append('alumno_id',   alumnoId);
            fd.append('accio',       'reset');
            try {
                const res  = await fetch('/index.php?main=ajust_nota_guardar&raw=1', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    const span = document.querySelector('.nota-alumne[data-alumno="' + alumnoId + '"]');
                    if (span) { span.dataset.ajust = '0'; span.textContent = window._notaBaseProjecte.toFixed(1).replace('.', ','); }
                    etq.remove();
                } else {
                    alert(data.missatge || 'Error en reset.');
                    if (etq.dataset.textSaved) etq.textContent = etq.dataset.textSaved;
                }
            } catch (e) {
                alert('Error de connexió: ' + e.message);
                if (etq.dataset.textSaved) etq.textContent = etq.dataset.textSaved;
            }
        });
    }
})();
</script>
