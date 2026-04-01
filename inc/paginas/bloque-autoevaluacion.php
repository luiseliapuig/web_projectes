<style>
.autoevaluacion .fw-semibold {color: #D97706; margin-bottom: 6px !important;}
.bg-orange {background: #FFF4E8}
.autoevaluacion .border-start {
  border-left-color: #F5C79A !important;
}
</style>
<div class="container mb-30 autoevaluacion">

  <div class="border rounded-4 overflow-hidden">

    <!-- Cabecera -->
    <div class="bg-orange px-4 py-3 border-bottom d-flex justify-content-between align-items-start">
      
      <div>
        <h3 class="fw-semibold mb-1">Reflexión final del proyecto</h3>
        <p class="text-muted small mb-0">
          Valoración del propio alumnado sobre el desarrollo del proyecto
        </p>
      </div>

      <div class="text-end border-start ps-3 ms-3">
        
        <div class="text-muted small">
          
                                            <?php foreach ($alumnos as $a): ?>
                                               <h5 class="fw-semibold "> <?= h($a['nombre'] . ' ' . $a['apellidos']) ?></h5>
                                            <?php endforeach; ?>

                                          </div>
                                          <div class=" small">Alumno/s</div>
      </div>

    </div>

    <!-- Cuerpo -->
    <div class="bg-white p-4">

      <div class="row g-4">

        <div class="col-md-6">
          <p class="fw-semibold mb-1">¿Qué has aprendido en este proyecto?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark">
              Hemos aprendido a trabajar con APIs externas, estructurar mejor el código y organizar el trabajo en equipo de forma más eficiente.
            </p>
          </blockquote>
        </div>

        <div class="col-md-6">
          <p class="fw-semibold mb-1">¿De qué parte del proyecto estás más satisfecho?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark">
              Del diseño de la interfaz y la estructura de navegación, ya que conseguimos una experiencia clara y usable.
            </p>
          </blockquote>
        </div>

        <div class="col-md-6">
          <p class="fw-semibold mb-1">¿Qué partes no se han podido completar y por qué?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark">
              No hemos podido implementar el sistema de autenticación completo por falta de tiempo y dificultades con la integración del backend.
            </p>
          </blockquote>
        </div>

        <div class="col-md-6">
          <p class="fw-semibold mb-1">¿Qué mejorarías si tuvieras más tiempo?</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark">
              Mejoraríamos el rendimiento general de la web y añadiríamos funcionalidades adicionales como notificaciones y gestión avanzada de usuarios.
            </p>
          </blockquote>
        </div>

      </div>

    </div>

  </div>

</div>
