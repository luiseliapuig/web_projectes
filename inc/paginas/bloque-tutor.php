<?php
// ── Dades del tutor ───────────────────────────────────────────────
$esTutor    = isset($_SESSION['professor_id'])
              && (int)($_SESSION['professor_id']) === (int)($proyecto['tutor_id'] ?? -1);
$puedeEditar = $esTutor || esSuperadmin();

$notasTutor = [
    'planificacion' => (int)($proyecto['nota_tutor_planificacion'] ?? 0),
    'gestion'       => (int)($proyecto['nota_tutor_gestion']       ?? 0),
    'memoria'       => (int)($proyecto['nota_tutor_memoria']        ?? 0),
    'proyecto'      => (int)($proyecto['nota_tutor_proyecto']       ?? 0),
    'compromiso'    => (int)($proyecto['nota_tutor_compromiso']     ?? 0),
];
$comentarioTutor = $proyecto['comentario_tutor'] ?? '';

$labelsTutor = [
    'planificacion' => 'Planificació',
    'gestion'       => 'Gestió',
    'memoria'       => 'Memòria',
    'proyecto'      => 'Projecte',
    'compromiso'    => 'Compromís',
];
?>

<div class=" mb-30 tutor">
  <div class="border rounded-4 overflow-hidden">

    <!-- Capçalera -->
    <div class="bg-tutor px-4 py-3 border-bottom d-flex justify-content-between align-items-start">
      <div>
        <h3 class="fw-semibold mb-1">Valoració del tutor</h3>
        <p class="text-muted small mb-0">Avaluació global del projecte per part del tutor</p>
      </div>
      <div class="text-end border-start ps-3 ms-3">
        <h5 class="fw-semibold"><?= h(trim($proyecto['tutor_nombre'] . ' ' . $proyecto['tutor_apellidos'])) ?></h5>
        <div class="text-muted small">Tutor</div>
      </div>
    </div>

    <!-- Cos -->
    <div class="bg-white p-4">
      <div class="row g-4">




        <!-- Estrellas -->
        <div class="col-md-4">
          <div class="rating-grid mx-auto">
            <?php foreach ($labelsTutor as $camp => $label): ?>
              <div class="rating-label"><?= h($label) ?></div>
              <div class="rating-stars"
                   <?php if ($puedeEditar): ?>
                   data-editable="1"
                   data-tipo="tutor"
                   data-proyecto="<?= (int)$idProyecto ?>"
                   data-camp="<?= h($camp) ?>"
                   <?php endif; ?>>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?= $i <= $notasTutor[$camp] ? 'filled' : '' ?>"
                        data-valor="<?= $i ?>">★</span>
                <?php endfor; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Comentari -->
        <div class="col-md-8">
          <p class="fw-semibold mb-2">Comentari del tutor</p>
          <div class="comentari-wrap"
               data-tipo="tutor"
               data-proyecto="<?= (int)$idProyecto ?>"
               data-editable="<?= $puedeEditar ? '1' : '0' ?>">
            <blockquote class="ps-3 border-start border-2 comentari-text" style="<?= $puedeEditar ? 'cursor:pointer;' : '' ?>">
              <p class="mb-0 text-dark comentari-display">
                <?= $comentarioTutor !== ''
                    ? nl2br(h($comentarioTutor))
                    : '<span class="text-muted fst-italic">' . ($puedeEditar ? 'Fes clic per afegir un comentari' : 'Sense comentari') . '</span>' ?>
              </p>
            </blockquote>
            <?php if ($puedeEditar): ?>
            <div class="comentari-editor d-none mt-2">
              <textarea class="form-control mb-2" rows="4"><?= h($comentarioTutor) ?></textarea>
              <button class="btn btn-puig btn-sm px-3 btn-guardar-comentari">Guardar</button>
              <button class="btn btn-sm btn-link text-muted btn-cancelar-comentari">Cancel·lar</button>
            </div>
            
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>
