<?php
declare(strict_types=1);

$enlaceProyecto = '/el-meu-projecte';
$rutaActualAlumno = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$proyectoActivo = str_starts_with($rutaActualAlumno, '/el-meu-projecte')
    || (isset($main) && is_string($main) && str_starts_with($main, 'alumne-'));
?>
<nav class="navbar navbar-expand-md py-0 area-nav" aria-label="Espai de l’alumnat">
    <div class="container-fluid px-4 header-wrapper">
        <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#alumneAreaNavbar" aria-controls="alumneAreaNavbar" aria-expanded="false" aria-label="Obrir el menú de l’alumnat">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="collapse navbar-collapse" id="alumneAreaNavbar">
            <ul class="navbar-nav mb-0">
                <li class="nav-item">
                    <a class="nav-link <?= $proyectoActivo ? 'active' : '' ?>" href="<?= htmlspecialchars($enlaceProyecto, ENT_QUOTES, 'UTF-8') ?>">El meu projecte</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
