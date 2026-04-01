<style>

/* TUTOR */
.bg-tutor {
  background-color: #E7F0FB;
}
.tutor .fw-semibold {
  color: #1E3A8A;
  margin-bottom: 6px !important;
}
.tutor .border-start {
  border-left-color: #93C5FD !important;
}

/* TRIBUNAL */
.bg-tribunal {
  background-color: #f5f9ff;
}
.tribunal .fw-semibold {
  color: #515782;
  margin-bottom: 6px !important;
}
.tribunal .border-start {
  border-left-color: #CBD5F5 !important;
}

/* ESTRELLAS */
.star {
  font-size: 25px;
  color: #E5E7EB;
}
.star.filled {
  color: #FACC15;
}

.rating-grid {
  display: grid;
  grid-template-columns: 110px auto;
  column-gap: 12px;
  row-gap: 10px;
  margin-bottom:15px
}

.rating-label {
 display: flex;
  justify-content: flex-end;
  align-items: center;
  font-weight: 600;
  color: #334155;
}

.rating-stars {
  white-space: nowrap;
}

.rating-label,
.rating-stars {
  display: flex;
  align-items: center;
}

/* Separación vertical entre columnas */
.tribunal-col {

  position: relative;
}

.tribunal-col:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 10px;
  bottom: 10px;
  right: 0;
  width: 1px;
  background-color: #E5E7EB;
}

.t-col-1 { padding-right: 24px !important;}
.t-col-2 { padding-left: 24px !important; padding-right: 24px !important;}
.t-col-3 { padding-left: 24px !important;}

</style>

<div class="container mb-30 tutor">

  <div class="border rounded-4 overflow-hidden">

    <!-- Cabecera -->
    <div class="bg-tutor px-4 py-3 border-bottom d-flex justify-content-between align-items-start">
      
      <div>
        <h3 class="fw-semibold mb-1">Valoración del tutor</h3>
        <p class="text-muted small mb-0">
          Evaluación global del proyecto por parte del tutor
        </p>
      </div>

      <div class="text-end border-start ps-3 ms-3">
        <h5 class="fw-semibold ">Luis Elía</h5>
        <div class="text-muted small">Tutor</div>
      </div>

    </div>

    <!-- Cuerpo -->
    <div class="bg-white p-4">

      <div class="row g-4">

        <!-- IZQUIERDA: ESTRELLAS -->
        <div class="col-md-4">

                <!-- Estrellas -->
            <div class="rating-grid mx-auto">
              <div class="rating-label">Planificación</div>
              <div class="rating-stars">
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star">★</span>
              </div>

              <div class="rating-label">Gestión</div>
              <div class="rating-stars">
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
              </div>

              <div class="rating-label">Memoria</div>
              <div class="rating-stars">
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star">★</span>
              </div>

              <div class="rating-label">Proyecto</div>
              <div class="rating-stars">
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
              </div>

              <div class="rating-label">Compromiso</div>
              <div class="rating-stars">
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star filled">★</span>
                <span class="star">★</span>
              </div>
            </div>


        </div>

        <!-- DERECHA: COMENTARIO -->
        <div class="col-md-8">
          <p class="fw-semibold mb-2">Comentario del tutor</p>
          <blockquote class="ps-3 border-start border-2">
            <p class="mb-0 text-dark">
              Proyecto muy completo que destaca especialmente por la fase de análisis y planificación. El alumno ha demostrado una gran capacidad de estructuración y una ejecución sólida del producto final.
            </p>
          </blockquote>
        </div>

      </div>

    </div>

  </div>

</div>