<?php
// ── Membres del tribunal i les seves valoracions ──────────────────
try {
    $stmtTrib = $pdo->prepare("
        SELECT
            et.id_evaluacion_tribunal,
            r.profesor_id,
            et.nota_memoria,
            et.nota_proyecto,
            et.nota_defensa,
            et.comentario,
            TRIM(pr.nombre || ' ' || pr.apellidos) AS nom_professor
        FROM app.rel_profesores_tribunal r
        JOIN app.profesores pr ON pr.id_profesor = r.profesor_id
        LEFT JOIN app.evaluacion_tribunal et
            ON et.proyecto_id = r.id_proyecto
            AND et.profesor_id = r.profesor_id
        WHERE r.id_proyecto = ?
        ORDER BY pr.apellidos, pr.nombre
    ");
    $stmtTrib->execute([$idProyecto]);
    $membres = $stmtTrib->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $membres = [];
}

$professorActualId = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;

$labelsTribunal = [
    'memoria'  => 'Memòria',
    'proyecto' => 'Projecte',
    'defensa'  => 'Defensa',
];

$colCols = ['t-col-1', 't-col-2', 't-col-3'];
?>

<?php if (!empty($membres)): ?>
<div id="bloque-tribunal-wrap">
<div class=" mb-30 tribunal">
  <div class="border rounded-4 overflow-hidden">

    <!-- Capçalera -->
    <div class="bg-tribunal px-4 py-3 border-bottom">
      <h3 class="fw-semibold mb-1">Valoració del tribunal</h3>
      <p class="text-muted small mb-0">Avaluació individual dels membres del tribunal</p>
    </div>

    <!-- Cos -->
    <div class="bg-white p-4">
      <div class="row g-4">

        <?php foreach ($membres as $i => $m):
            $esPropioMembre = $professorActualId && (int)$m['profesor_id'] === $professorActualId;
            $puedeEditar    = $esPropioMembre || esSuperadmin();
            $colClass       = $colCols[$i] ?? '';
            $notaMemoria    = (int)($m['nota_memoria']  ?? 0);
            $notaProyecto   = (int)($m['nota_proyecto'] ?? 0);
            $notaDefensa    = (int)($m['nota_defensa']  ?? 0);
            $notes = [
                'memoria'  => $notaMemoria,
                'proyecto' => $notaProyecto,
                'defensa'  => $notaDefensa,
            ];
        ?>
        <div class="col-md-4 px-3 tribunal-col <?= $colClass ?>">

          <div class="mb-3 border-bottom pb-2">
            <h5 class="fw-semibold"><?= h($m['nom_professor']) ?></h5>
          </div>

          <!-- Estrellas -->
          <div class="rating-grid mx-auto">
            <?php foreach ($labelsTribunal as $camp => $label): ?>
              <div class="rating-label"><?= h($label) ?></div>
              <div class="rating-stars"
                   <?php if ($puedeEditar): ?>
                   data-editable="1"
                   data-tipo="tribunal"
                   data-proyecto="<?= (int)$idProyecto ?>"
                   data-profesor="<?= (int)$m['profesor_id'] ?>"
                   data-camp="<?= h($camp) ?>"
                   <?php endif; ?>>
                <?php for ($i2 = 1; $i2 <= 5; $i2++): ?>
                  <span class="star <?= $i2 <= $notes[$camp] ? 'filled' : '' ?>"
                        data-valor="<?= $i2 ?>">★</span>
                <?php endfor; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Comentari -->
          <p class="fw-semibold mb-1 mt-2">Comentari</p>
          <div class="comentari-wrap"
               data-tipo="tribunal"
               data-proyecto="<?= (int)$idProyecto ?>"
               data-profesor="<?= (int)$m['profesor_id'] ?>"
               data-editable="<?= $puedeEditar ? '1' : '0' ?>">
            <blockquote class="ps-3 border-start border-2 comentari-text" style="<?= $puedeEditar ? 'cursor:pointer;' : '' ?>">
              <p class="mb-0 text-dark small comentari-display">
                <?php $com = $m['comentario'] ?? ''; ?>
                <?= $com !== ''
                    ? nl2br(h($com))
                    : '<span class="text-muted fst-italic">' . ($puedeEditar ? 'Fes clic per afegir un comentari' : 'Sense comentari') . '</span>' ?>
              </p>
            </blockquote>
            <?php if ($puedeEditar): ?>
            <div class="comentari-editor d-none mt-2">
              <textarea class="form-control mb-2" rows="3"><?= h($com) ?></textarea>
              <button class="btn btn-puig btn-sm px-3 btn-guardar-comentari">Guardar</button>
              <button class="btn btn-sm btn-link text-muted btn-cancelar-comentari">Cancel·lar</button>
            </div>
            <?php endif; ?>
          </div>

        </div>
        <?php endforeach; ?>

      </div>
    </div>

  </div>
</div>
</div><!-- /bloque-tribunal-wrap -->
<?php endif; ?>
