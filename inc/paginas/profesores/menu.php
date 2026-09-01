<?php
declare(strict_types=1);

$menuProfesorMain = isset($main) && is_string($main) ? $main : '';
$resumTutorActiu = $menuProfesorMain === 'resum-tutor';
$autoseguimentTutorActiu = $menuProfesorMain === 'autoseguiment-tutor';
$memoriaTutorActiva = $menuProfesorMain === 'memoria-tutor';
$calendarioActivo = $menuProfesorMain === 'assignar-defenses';
$defensasActivas = in_array($menuProfesorMain, ['les-meves-defenses', 'les-meves-defenses-lista'], true);
$notasActivas = $menuProfesorMain === 'notes-finals';

// Bloc principal (eines d'ús habitual) vs. bloc secundari (eines
// ocasionals/contextuals, per exemple lligades a un període concret com les
// defenses): el separador visual pertany a aquesta divisió, no a cap opció
// concreta, i només es mostra quan hi ha almenys una eina ocasional visible.
$hiHaEinesOcasionals = $mostrarCalendarioDefensas || $mostrarMisDefensas || $mostrarNotasFinales;
?>
<nav class="navbar navbar-expand-md py-0 area-nav" aria-label="Espai del professorat">
    <div class="container-fluid px-4 header-wrapper">
        <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#professorAreaNavbar" aria-controls="professorAreaNavbar" aria-expanded="false" aria-label="Obrir el menú del professorat">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="collapse navbar-collapse" id="professorAreaNavbar">
            <ul class="navbar-nav mb-0">
                <?php if ($mostrarResumTutor): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $resumTutorActiu ? 'active' : '' ?>" href="/resum">Resum</a>
                </li>
                <?php endif; ?>

                <?php if ($mostrarAutoseguimentTutor): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $autoseguimentTutorActiu ? 'active' : '' ?>" href="/seguiment-setmanal">Autoseguiment</a>
                </li>
                <?php endif; ?>

                <?php if ($mostrarMemoriaTutor): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $memoriaTutorActiva ? 'active' : '' ?>" href="/revisio-memoria">Memòria</a>
                </li>
                <?php endif; ?>

                <?php if ($hiHaEinesOcasionals): ?>
                <li class="nav-item area-nav-separador" role="separator" aria-hidden="true"></li>
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
