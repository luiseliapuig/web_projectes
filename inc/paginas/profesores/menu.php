<?php
declare(strict_types=1);

$menuProfesorMain = isset($main) && is_string($main) ? $main : '';
$proyectosActivos = in_array($menuProfesorMain, ['proyectos-tutorizados', 'proyectos-tutorizados-lista'], true);
$calendarioActivo = $menuProfesorMain === 'assignar-defenses';
$defensasActivas = in_array($menuProfesorMain, ['les-meves-defenses', 'les-meves-defenses-lista'], true);
$notasActivas = $menuProfesorMain === 'notes-finals';
?>
<nav class="navbar navbar-expand-md py-0 area-nav" aria-label="Espai del professorat">
    <div class="container-fluid px-4 header-wrapper">
        <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#professorAreaNavbar" aria-controls="professorAreaNavbar" aria-expanded="false" aria-label="Obrir el menú del professorat">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="collapse navbar-collapse" id="professorAreaNavbar">
            <ul class="navbar-nav mb-0">
                <?php if ($mostrarProyectosTutorizados): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $proyectosActivos ? 'active' : '' ?>" href="/projectes-tutoritzats">Els meus projectes</a>
                </li>
                <?php endif; ?>

                <?php if ($mostrarCalendarioDefensas): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $calendarioActivo ? 'active' : '' ?>" href="/assignar-defenses">Assignar defenses</a>
                </li>
                <?php endif; ?>

                <?php if ($mostrarMisDefensas): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $defensasActivas ? 'active' : '' ?>" href="/les-meves-defenses">Les meves defenses</a>
                </li>
                <?php endif; ?>

                <?php if ($mostrarNotasFinales): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $notasActivas ? 'active' : '' ?>" href="/notes-finals">Notes finals</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
