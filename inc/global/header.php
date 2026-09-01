<?php
// Estado general de navegación. Las reglas de configuración controlan la
// visibilidad; las relaciones del profesor siguen decidiendo qué enlaces aplican.
$esProfesorMenu = esProfesor();
$esSuperadminMenu = $esProfesorMenu && esSuperadmin();

// La inscripción en tribunales depende de su regla específica. El
// superadministrador conserva el acceso para supervisar incluso al cerrarla.
$mostrarCalendarioDefensas = $esProfesorMenu
    && (configuracion('seleccionar_defensas') || $esSuperadminMenu);

// El resto de enlaces docentes mantiene sus reglas y requisitos contextuales.
$mostrarMisDefensas = $esProfesorMenu
    && (configuracion('mostrar_mis_defensas') || $esSuperadminMenu);
$mostrarNotasFinales = $esProfesorMenu
    && (configuracion('mostrar_notes_finals') || $esSuperadminMenu);
$mostrarAdministrarProyectos = $esProfesorMenu && esTutor();
$mostrarAdministrarAlumnado = $mostrarAdministrarProyectos;
$mostrarProyectosTutorizados = $esProfesorMenu && esTutor();
$mostrarAutoseguimentTutor = $esProfesorMenu && esTutor();
$mostrarMemoriaTutor = $esProfesorMenu && esTutor();
$mostrarResumTutor = $esProfesorMenu && esTutor();
?>
<!-- ===== HEADER PROJECTES ===== -->
<header class="site-header border-bottom">

  <!-- Franja superior blanca -->
  <div class="top-header bg-white">
    <div class="container-fluid px-4 header-wrapper">
      <div class="d-flex align-items-center justify-content-between py-3">

        <!-- Logo -->
        <div class="header-logo">
          <a href="/" class="d-inline-flex align-items-center text-decoration-none">
            <img
              src="/assets/images/logo/logo-projectes.png"
              alt="Institut Puig Castellar - Projectes"
              class="img-fluid header-logo-img"
            >
          </a>
        </div>

        <!-- Zona derecha -->
        <div class="header-right d-flex align-items-center">

         
          <?php include('html-logica-login.php'); ?>


        </div>
      </div>
    </div>
  </div>

  <!-- Menú granate -->
  <div class="main-nav-wrapper  projectes-navbar">
    <nav class="navbar navbar-expand-lg navbar-dark py-0 header-wrapper">
      <div class="container-fluid px-4">

        <button
          class="navbar-toggler my-2"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNavbar"
          aria-controls="mainNavbar"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
          <ul class="navbar-nav mb-0">

            <li class="nav-item">
              <a class="nav-link active" href="/">Inici</a>
            </li>

            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                id="projectesDropdown"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                Projectes
              </a>
             <ul class="dropdown-menu" aria-labelledby="projectesDropdown">
                <li><a class="dropdown-item" href="/projectes/SMX">SMX</a></li>
                <li><a class="dropdown-item" href="/projectes/DAM">DAM</a></li>
                <li><a class="dropdown-item" href="/projectes/DAW">DAW</a></li>
                <li><a class="dropdown-item" href="/projectes/ASIX">ASIX</a></li>
                <li><a class="dropdown-item" href="/projectes/DEV">DEV</a></li>
            </ul>
            </li>

          </ul>
        </div>
      </div>
    </nav>
  </div>

  <?php if (($_SESSION['auth_tipo'] ?? '') === 'alumne'): ?>
    <?php include __DIR__ . '/../paginas/alumnos/menu.php'; ?>
  <?php elseif ($esSuperadminMenu): ?>
    <?php include __DIR__ . '/../paginas/admin/menu.php'; ?>
  <?php elseif (($_SESSION['auth_tipo'] ?? '') === 'professor'): ?>
    <?php include __DIR__ . '/../paginas/profesores/menu.php'; ?>
  <?php endif; ?>

</header>
<!-- ===== /HEADER PROJECTES ===== -->
