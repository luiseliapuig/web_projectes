<?php
declare(strict_types=1);

$permitirSinProyecto = true;
if (!(require __DIR__ . '/projecte_context.php')) {
    return;
}
$faseActiva = 0;
$nombreProyecto = trim((string) ($proyectoAlumno['nombre'] ?? ''));
?>
<script>window.PAGE_TITLE = 'El meu projecte';</script>
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">El meu projecte</h1>
        <p class="text-muted mb-0">Segueix les fases del projecte i accedeix a cada espai de treball.</p>
    </div>
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3"><?php include __DIR__ . '/fases_navegacion.php'; ?></aside>
        <section class="col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-lg-5">
                <span class="text-uppercase text-muted small fw-semibold">Projecte</span>
                <h2 class="h4 mt-2 mb-2"><?= htmlspecialchars($nombreProyecto !== '' ? $nombreProyecto : 'El teu projecte', ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-muted mb-4"><?= htmlspecialchars(trim($proyectoAlumno['ciclo'] . ' ' . $proyectoAlumno['grupo']), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mb-0">Selecciona una fase del menú lateral per començar. Anirem incorporant aquí les eines i indicacions de cada etapa.</p>
            </div>
        </section>
    </div>
</div>
