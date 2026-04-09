

<?php
$teAutoevaluacio = !empty($proyecto['autoev1'])
                || !empty($proyecto['autoev2'])
                || !empty($proyecto['autoev3'])
                || !empty($proyecto['autoev4']);

if (!$teAutoevaluacio) return;
?>

<div class=" mb-30 autoevaluacion">
  <div class="border rounded-4 overflow-hidden">

    <!-- Cabecera -->
    <div class="bg-orange px-4 py-3 border-bottom d-flex justify-content-between align-items-start">
      <div>
        <h3 class="fw-semibold mb-1">Reflexió final del projecte</h3>
        <p class="text-muted small mb-0">
          Valoració del propi alumnat sobre el desenvolupament del projecte
        </p>
      </div>
      <div class="text-end border-start ps-3 ms-3">
        <div class="text-muted small">
          <?php foreach ($alumnos as $a): ?>
            <h5 class="fw-semibold"><?= h($a['nombre'] . ' ' . $a['apellidos']) ?></h5>
          <?php endforeach; ?>
        </div>
        <div class="small">Alumne/s</div>
      </div>
    </div>

    <!-- Cuerpo -->
    <div class="bg-white p-4">
      <div class="row g-4">

        <?php if (!empty($proyecto['autoev1'])): ?>
        <div class="col-md-6">
          <p class="fw-semibold mb-1">Què has après en aquest projecte?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark"><?= nl2br(h($proyecto['autoev1'])) ?></p>
          </blockquote>
        </div>
        <?php endif; ?>

        <?php if (!empty($proyecto['autoev2'])): ?>
        <div class="col-md-6">
          <p class="fw-semibold mb-1">De quina part del projecte estàs més satisfet?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark"><?= nl2br(h($proyecto['autoev2'])) ?></p>
          </blockquote>
        </div>
        <?php endif; ?>

        <?php if (!empty($proyecto['autoev3'])): ?>
        <div class="col-md-6">
          <p class="fw-semibold mb-1">Quines parts no s'han pogut completar i per què?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark"><?= nl2br(h($proyecto['autoev3'])) ?></p>
          </blockquote>
        </div>
        <?php endif; ?>

        <?php if (!empty($proyecto['autoev4'])): ?>
        <div class="col-md-6">
          <p class="fw-semibold mb-1">Què milloraries si tinguessis més temps?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark"><?= nl2br(h($proyecto['autoev4'])) ?></p>
          </blockquote>
        </div>
        <?php endif; ?>

      </div>
    </div>

  </div>
</div>
