<?php
declare(strict_types=1);

if (!(require __DIR__ . '/projecte_context.php')) {
    return;
}
$faseActiva = isset($faseNumero) ? (int) $faseNumero : 0;
?>
<script>window.PAGE_TITLE = <?= json_encode('Fase ' . $faseActiva . ' · ' . $faseTitulo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<div class="container-fluid py-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3"><?php include __DIR__ . '/fases_navegacion.php'; ?></aside>
        <section class="col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-lg-5">
                <header class="mb-4">
                    <span class="small fase-etiqueta">Fase <?= $faseActiva ?></span>
                    <h1 class="h3 mb-1 mt-1"><?= htmlspecialchars($faseTitulo, ENT_QUOTES, 'UTF-8') ?></h1>
                </header>

                <?php if (isset($faseContenidoArchivo) && is_string($faseContenidoArchivo) && is_file($faseContenidoArchivo)): ?>
                    <?php include $faseContenidoArchivo; ?>
                <?php else: ?>
                    <h2 class="h5 mb-2"><?= htmlspecialchars($faseTitulo, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="text-muted mb-0">Aquesta secció està preparada. En el següent pas definirem el contingut i les accions de la fase.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
