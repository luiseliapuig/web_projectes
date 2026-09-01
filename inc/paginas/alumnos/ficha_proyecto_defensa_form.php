<?php
declare(strict_types=1);

$idProyecto = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// La pantalla solo admite el proyecto asociado a la sesión del alumno.
if ($idProyecto <= 0 || !esSuProyectoAlumno($idProyecto)) {
    http_response_code(403);
    die('Accés no permès');
}
?>

<script>
window.PAGE_TITLE = 'Pujar presentació de defensa';
</script>

<div class="container-fluid">
  <div class="card-style mb-30 mt-30">

    <div class="mb-4">
      <h4 class="mb-2">Última peça del projecte</h4>

      <p class="text-muted mb-2">
        La defensa ja ha acabat. Ara només queda pujar el PDF de la presentació perquè la fitxa del projecte quedi completa.
      </p>

      <div class="alert alert-info mb-0">
        <strong>Abans de pujar el fitxer:</strong><br>
        Comprimeix el PDF si pesa massa. Pots fer-ho fàcilment amb
        <a href="https://www.ilovepdf.com/es/comprimir_pdf" target="_blank" rel="noopener">
          iLovePDF · Comprimir PDF
        </a>.
      </div>
    </div>

    <form action="/index.php?main=ficha_proyecto_defensa_accion" method="post" enctype="multipart/form-data">

      <input type="hidden" name="id_proyecto" value="<?= $idProyecto ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="mb-4">
        <label for="presentacion_pdf" class="form-label fw-semibold">
          PDF de la presentació de defensa
        </label>

        <input
          type="file"
          class="form-control"
          id="presentacion_pdf"
          name="presentacion_pdf"
          accept="application/pdf"
          required
        >

        <div class="form-text">
          Només s’accepten fitxers PDF. Puja la versió definitiva utilitzada a la defensa.
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-puig-solid px-4">
          Pujar presentació
        </button>

        <a href="/projecte/<?= $idProyecto ?>" class="main-btn light-btn btn-hover">
          Tornar a la fitxa
        </a>
      </div>

    </form>

  </div>
</div>
