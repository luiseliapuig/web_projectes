<?php
declare(strict_types=1);

// Capçalera compartida per a totes les entrades contextuals del
// professorat al recorregut de fases d'un projecte (fase-tutor.php,
// fases-tutor.php, fase-2-tutor_proposta.php, fase-1-tutor_compromis.php):
// identifica el projecte/alumnat que s'està consultant. No pertany a la
// infraestructura de fases en si mateixa (sidebar/targetes/detall): és
// només l'embolcall propi del professorat, tal com estableix el contracte
// general (el professorat conserva la seva pròpia capçalera d'entrada al
// recorregut).
//
// El botó "Tornar al Resum" només es mostra quan qui inclou aquesta
// capçalera NO passa després per fase_base.php: aquell shell ja hi afegeix
// "Resum" com a primer element del breadcrumb (vegeu fase_base.php), i
// mantenir alhora el botó seria exactament la mateixa redundància que ja
// vam eliminar amb "Tornar a Fase N". fases-tutor.php (el llistat general
// de fases, que no passa per fase_base.php) el fixa a false explícitament
// perquè encara no té cap breadcrumb propi.
$capcaleraOcultarTornarResum = $capcaleraOcultarTornarResum ?? false;

// Espera $pdo, $proyectoAlumno i $proyectoId ja resolts pel fitxer que la
// inclou.
$nomsAlumnesCapcalera = fasesNomsAlumnesProjecte($pdo, $proyectoId);
$titolProjecteCapcalera = $nomsAlumnesCapcalera !== []
    ? implode(' · ', $nomsAlumnesCapcalera)
    : (string) ($proyectoAlumno['nombre'] ?? 'Projecte');
?>
<div class="container-fluid pt-4">
    <div class="mb-2 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h5 mb-1"><?= htmlspecialchars($titolProjecteCapcalera, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted small mb-0"><?= htmlspecialchars(trim((string) ($proyectoAlumno['ciclo'] ?? '') . ' ' . (string) ($proyectoAlumno['grupo'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php if (!$capcaleraOcultarTornarResum): ?>
            <a href="/resum" class="btn btn-outline-secondary btn-sm">Tornar al Resum</a>
        <?php endif; ?>
    </div>
</div>
