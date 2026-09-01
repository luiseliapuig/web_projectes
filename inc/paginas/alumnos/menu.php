<?php
declare(strict_types=1);

// Tres àrees germanes, cadascuna amb el seu propi namespace públic (vegeu
// .htaccess): Autoseguiment i Memòria tenen entitat pròpia, no són subrutes
// del recorregut de fases.
$enlaceFasesProjecte = '/fases-del-projecte';
$enlaceAutoseguiment = '/autoseguiment';
$enlaceMemoria = '/memoria';
$menuAlumnoMain = isset($main) && is_string($main) ? $main : '';
$rutaActualAlumno = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$autoseguimentActiu = $menuAlumnoMain === 'alumne-autoseguiment';
$memoriaActiva = $menuAlumnoMain === 'alumne-memoria';
$fasesProjecteActiu = !$autoseguimentActiu && !$memoriaActiva && (
    str_starts_with($rutaActualAlumno, '/fases-del-projecte')
    || str_starts_with($menuAlumnoMain, 'alumne-')
);
?>
<nav class="navbar navbar-expand-md py-0 area-nav" aria-label="Espai de l’alumnat">
    <div class="container-fluid px-4 header-wrapper">
        <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#alumneAreaNavbar" aria-controls="alumneAreaNavbar" aria-expanded="false" aria-label="Obrir el menú de l’alumnat">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="collapse navbar-collapse" id="alumneAreaNavbar">
            <ul class="navbar-nav mb-0">
                <li class="nav-item">
                    <a class="nav-link <?= $fasesProjecteActiu ? 'active' : '' ?>" href="<?= htmlspecialchars($enlaceFasesProjecte, ENT_QUOTES, 'UTF-8') ?>">Fases del projecte</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $autoseguimentActiu ? 'active' : '' ?>" href="<?= htmlspecialchars($enlaceAutoseguiment, ENT_QUOTES, 'UTF-8') ?>">Autoseguiment</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $memoriaActiva ? 'active' : '' ?>" href="<?= htmlspecialchars($enlaceMemoria, ENT_QUOTES, 'UTF-8') ?>">Memòria</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
