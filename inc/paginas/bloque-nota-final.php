<style>
.bloque-final {
  background: #0F172A; /* azul muy oscuro elegante */
  color: #E5E7EB;
}

.bloque-final .label {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9CA3AF;
}

.bloque-final .valor {
  font-size: 1.1rem;
  font-weight: 600;
  color: #F9FAFB;
}

.bloque-final .nota-final {
  font-size: 3rem;
  font-weight: 700;
  color: #F59E0B; /* naranja acento */
  line-height: 1;
}

.bloque-final .nota-max {
  font-size: 1rem;
  color: #9CA3AF;
}

.bloque-final .divider {
  border-left: 1px solid rgba(255,255,255,0.1);
}
.notafinal { color: #fff !important;
  font-size: 24px !important;
  font-weight: 600 !important;
}
</style>

<div class="container mb-30">

  <div class="bloque-final rounded-4 p-4 p-md-5">

    <div class="row align-items-center">

      <!-- IZQUIERDA: DESGLOSE -->
      <div class="col-md-7">

        <div class="row g-4">

          <div class="col-6">
            <div class="label">Tutor (20%)</div>
            <div class="valor">1,6 / 2</div>
          </div>

          <div class="col-6">
            <div class="label">Memoria (30%)</div>
            <div class="valor">2,4 / 3</div>
          </div>

          <div class="col-6">
            <div class="label">Proyecto (30%)</div>
            <div class="valor">2,7 / 3</div>
          </div>

          <div class="col-6">
            <div class="label">Defensa (20%)</div>
            <div class="valor">1,8 / 2</div>
          </div>

        </div>

      </div>

      <!-- DERECHA: NOTA FINAL -->
      <div class="col-md-5 mt-4 mt-md-0">

        <div class="divider ps-md-4  text-center">

          <div class="label mb-2 notafinal">Nota final </div>

          <div class="nota-final">
            8,5
          </div>

          <div class="nota-max">
            sobre 10
          </div>

        </div>

      </div>

    </div>

  </div>

</div>