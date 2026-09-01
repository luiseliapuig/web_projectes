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

// El document viu és la font de veritat de l'accés operatiu a Memòria per a
// l'alumnat. El menú es renderitza abans que la pàgina i encara no disposa de
// $proyectoAlumno, per això es consulta una sola vegada el projecte de sessió,
// comprovant també que continua pertanyent a l'alumne autenticat.
$mostrarMemoria = false;
$projecteMenuId = (int) ($_SESSION['projecte_id'] ?? 0);
$alumneMenuId = (int) ($_SESSION['alumno_id'] ?? 0);
if ($projecteMenuId > 0 && $alumneMenuId > 0) {
    $stmtMemoriaMenu = $pdo->prepare("
        SELECT p.memoria_url
        FROM app.proyectos p
        INNER JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = p.id_proyecto
        WHERE p.id_proyecto = :projecte_id
          AND rpa.alumno_id = :alumne_id
          AND p.estado = 'activo'
        LIMIT 1
    ");
    $stmtMemoriaMenu->execute([
        ':projecte_id' => $projecteMenuId,
        ':alumne_id' => $alumneMenuId,
    ]);
    $mostrarMemoria = trim((string) $stmtMemoriaMenu->fetchColumn()) !== '';
}
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
                <?php if ($mostrarMemoria): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $memoriaActiva ? 'active' : '' ?>" href="<?= htmlspecialchars($enlaceMemoria, ENT_QUOTES, 'UTF-8') ?>">Memòria</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
