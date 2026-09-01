<?php
declare(strict_types=1);

$menuAdminMain = isset($main) && is_string($main) ? $main : '';
$configuracionActiva = str_starts_with($menuAdminMain, 'configuracion');
$autoseguimentActiu = str_starts_with($menuAdminMain, 'autoseguiment-control');
$proyectosActivos = str_starts_with($menuAdminMain, 'proyectos');
$administracionActiva = in_array($menuAdminMain, [
    'professorat', 'professorat_form', 'alumnat', 'alumnat_form',
    'families-cicles', 'families-cicles_form', 'cicles', 'cicles_form',
    'grups-cicle', 'grups-cicle_form', 'aules', 'aules_form',
    'categories-projectes', 'categories-projectes_form',
    'tipus-projectes', 'tipus-projectes_form',
], true);
$defensasActivas = in_array($menuAdminMain, [
    'assignar-defenses', 'calendari_drag', 'planificacio', 'defensas_print',
], true);
$mensajeriaActiva = $menuAdminMain === 'emails'
    || str_starts_with($menuAdminMain, 'enviar-emails-')
    || str_starts_with($menuAdminMain, 'lista-emails-');
$memoriaActiva = str_starts_with($menuAdminMain, 'memoria-estructura');
$stackActiu = str_starts_with($menuAdminMain, 'stack-');
?>
<nav class="navbar navbar-expand-lg py-0 area-nav" aria-label="Administració">
    <div class="container-fluid px-4 header-wrapper">
        <button class="navbar-toggler my-2" type="button" data-bs-toggle="collapse" data-bs-target="#adminAreaNavbar" aria-controls="adminAreaNavbar" aria-expanded="false" aria-label="Obrir el menú d’administració">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="collapse navbar-collapse" id="adminAreaNavbar">
            <ul class="navbar-nav mb-0">
                <li class="nav-item"><a class="nav-link <?= $configuracionActiva ? 'active' : '' ?>" href="/index.php?main=configuracion">Configuració</a></li>
                <li class="nav-item"><a class="nav-link <?= $autoseguimentActiu ? 'active' : '' ?>" href="/index.php?main=autoseguiment-control">Autoseguiment</a></li>
                <li class="nav-item"><a class="nav-link <?= $proyectosActivos ? 'active' : '' ?>" href="/index.php?main=proyectos">Projectes</a></li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $administracionActiva ? 'active' : '' ?>" href="#" id="areaAdminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Administració</a>
                    <ul class="dropdown-menu" aria-labelledby="areaAdminDropdown">
                        <li class="dropdown-submenu">
                            <button type="button" class="dropdown-item dropdown-submenu-toggle" aria-haspopup="true">Comunitat educativa</button>
                            <ul class="dropdown-menu" aria-label="Comunitat educativa">
                                <li><a class="dropdown-item" href="/index.php?main=professorat">Professorat</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=alumnat">Alumnat</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <button type="button" class="dropdown-item dropdown-submenu-toggle" aria-haspopup="true">Organització acadèmica</button>
                            <ul class="dropdown-menu" aria-label="Organització acadèmica">
                                <li><a class="dropdown-item" href="/index.php?main=families-cicles">Famílies de cicles</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=cicles">Cicles</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=grups-cicle">Grups/cicle</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=aules">Aules</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <button type="button" class="dropdown-item dropdown-submenu-toggle" aria-haspopup="true">Classificació de projectes</button>
                            <ul class="dropdown-menu" aria-label="Classificació de projectes">
                                <li><a class="dropdown-item" href="/index.php?main=categories-projectes">Categories</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=tipus-projectes">Tipus</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $stackActiu ? 'active' : '' ?>" href="#" id="areaStackDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Stack</a>
                    <ul class="dropdown-menu" aria-labelledby="areaStackDropdown">
                        <li><a class="dropdown-item <?= str_starts_with($menuAdminMain, 'stack-tecnologies') ? 'active' : '' ?>" href="/index.php?main=stack-tecnologies">Tecnologies</a></li>
                        <li><a class="dropdown-item <?= str_starts_with($menuAdminMain, 'stack-eines') ? 'active' : '' ?>" href="/index.php?main=stack-eines">Eines</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $defensasActivas ? 'active' : '' ?>" href="#" id="areaDefensesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Defenses</a>
                    <ul class="dropdown-menu" aria-labelledby="areaDefensesDropdown">
                        <li><a class="dropdown-item" href="/assignar-defenses">Assignar defenses</a></li>
                        <li><a class="dropdown-item" href="/index.php?main=calendari_drag">Modificar defenses</a></li>
                        <li><a class="dropdown-item" href="/index.php?main=planificacio">Generar proposta</a></li>
                        <li><a class="dropdown-item" href="/index.php?main=defensas_print">Imprimir defenses</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $memoriaActiva ? 'active' : '' ?>" href="#" id="areaMemoriaDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Memòria</a>
                    <ul class="dropdown-menu" aria-labelledby="areaMemoriaDropdown">
                        <li><a class="dropdown-item" href="/index.php?main=memoria-estructura">Estructura de la memòria</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $mensajeriaActiva ? 'active' : '' ?>" href="#" id="areaMissatgeriaDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Missatgeria</a>
                    <ul class="dropdown-menu" aria-labelledby="areaMissatgeriaDropdown">
                        <li><a class="dropdown-item" href="/index.php?main=emails">Sistema de correu</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-submenu">
                            <button type="button" class="dropdown-item dropdown-submenu-toggle" aria-haspopup="true">Enviar emails</button>
                            <ul class="dropdown-menu" aria-label="Enviar emails">
                                <li><a class="dropdown-item" href="/index.php?main=enviar-emails-profesorado">Professorat</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=enviar-emails-tutores">Tutors</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=enviar-emails-alumnado">Alumnat</a></li>
                            </ul>
                        </li>
                        <li class="dropdown-submenu">
                            <button type="button" class="dropdown-item dropdown-submenu-toggle" aria-haspopup="true">Llistes d’emails</button>
                            <ul class="dropdown-menu" aria-label="Llistes d’emails">
                                <li><a class="dropdown-item" href="/index.php?main=lista-emails-profesorado">Professorat</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=lista-emails-tutores">Tutors</a></li>
                                <li><a class="dropdown-item" href="/index.php?main=lista-emails-alumnado">Alumnat</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
