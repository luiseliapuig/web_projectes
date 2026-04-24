<?php 
declare(strict_types=1);
ob_start();
session_start();



ini_set('display_errors', '1');
error_reporting(E_ALL);



$pdo = require __DIR__ . '/config/db.php';
if (!$pdo instanceof PDO) {
  die('No hay conexión PDO');
}

require_once __DIR__ . '/login/seguridad.php';

$main = trim($_GET['main'] ?? '');
if ($main === '') {
    $main = 'main';
}


// se guarda la url para volver a ella tras el login
$raw  = isset($_GET['raw']) ? (int)$_GET['raw'] : 0;

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && $main !== 'login'
    && $main !== 'login_accion'
    && $raw !== 1
    && strpos($main, 'accio') === false
    && strpos($main, 'ajax') === false
) {
    $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
}


// para cuando quiero llamar a archivos de accion (no páginas de interfaz)
if (isset($_GET['raw']) && $_GET['raw'] === '1') {
    ob_end_clean();
    ini_set('display_errors', '0'); // ← añade esta línea
    $file = __DIR__ . '/inc/paginas/' . basename($main) . '.php';
    if (is_file($file)) {
        require $file;
    }
    exit;
}

$filePagina = __DIR__ . '/inc/paginas/' . basename($main) . '.php';
if (!is_file($filePagina)) {
    $filePagina = __DIR__ . '/inc/paginas/main.php';
}
?><!DOCTYPE html>
<html lang="ca">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="/assets/images/logo/favicon.ico" type="image/x-icon" />
    <title>Projectes. El Puig Castellar.</title>

    <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="/assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="/assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="/assets/css/fullcalendar.css" />
    <link rel="stylesheet" href="/assets/css/main.css" />
     <link rel="stylesheet" href="/assets/css/estilos.css" />
     <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  </head>
  <body>

   
      <?php include __DIR__ . '/inc/global/header.php';  ?>
       <main class="main-wrapper">
       <section>
            <?php  include $filePagina; ?>
      </section>
      
      </main>
      <?php include __DIR__ . '/inc/global/footer.php'; ?>


    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/Chart.min.js"></script>
    <script src="/assets/js/dynamic-pie-chart.js"></script>
    <script src="/assets/js/moment.min.js"></script>
    <script src="/assets/js/fullcalendar.js"></script>
    <script src="/assets/js/jvectormap.min.js"></script>
    <script src="/assets/js/world-merc.js"></script>
    <script src="/assets/js/polyfill.js"></script>
    <script src="/assets/js/main.js"></script>

    <script>
      if (window.PAGE_TITLE) {
        document.title = window.PAGE_TITLE + ' · Projectes Puig Castellar';
      }
    </script>
  </body>
</html>