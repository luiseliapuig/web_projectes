
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

             <?php if (esProfesor()) { 

               if ( configuracion('seleccionar_defensas')) { ?>
            <li class="nav-item">
              <a class="nav-link" href="/calendari-defenses">Calendari defenses</a>
            </li>
              <?php } ?>
            <li class="nav-item">
              <a class="nav-link" href="/les-meves-defenses">Les meves defenses</a>
            </li>
            <?php } ?>


            <?php if (esSuperadmin()): ?>
            <li class="nav-item dropdown">
              <a
                class="nav-link dropdown-toggle"
                href="#"
                id="adminDropdown"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                Administració
              </a>
              <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                <li><a class="dropdown-item" href="/index.php?main=configuracion">Configuració</a></li>
                <li><a class="dropdown-item" href="/index.php?main=planificacio">Planificación defensas</a></li>
                <hr>
                <li><a class="dropdown-item" href="/index.php?main=emails-profesores">Emails professorat</a></li>
                <li><a class="dropdown-item" href="/index.php?main=emails-alumnos">Emails alumnes</a></li>
                <hr>
                <li><a class="dropdown-item" href="/index.php?main=profesor">Professorat</a></li>
                <li><a class="dropdown-item" href="/index.php?main=aula">Aules</a></li>
                <hr>
                <li><a class="dropdown-item" href="/index.php?main=proyecto">Projectes</a></li>
                <li><a class="dropdown-item" href="/index.php?main=alumno">Alumnat</a></li>
                
                
              </ul>
            </li>
            <?php endif; ?>


          </ul>
        </div>
      </div>
    </nav>
  </div>

</header>
<!-- ===== /HEADER PROJECTES ===== -->