<?php
declare(strict_types=1);

// Equivalent contextual, per al professorat, de "Fases del projecte"
// (vegeu alumnos/fases_projecte.php): el mateix recorregut general d'UN
// projecte concret, mai una pantalla duplicada. Reutilitza el controlador
// REAL de l'alumnat — mateix llistat de les 7 fases — només canvia com es
// resol $proyectoAlumno (context de professorat, autoritzat contra
// rel_profesores_grupos, no sessió d'alumne) i es marca
// $rolVisualitzacio = 'professor'. Mateix patró que fase-tutor.php. Vegeu
// docs/codex/arquitectura.md ("El recorregut de fases pertany al projecte,
// no al rol").
if (!esProfesor()) {
    http_response_code(403);
    die('Accés no permès');
}

require_once dirname(__DIR__, 3) . '/fases/funciones.php';

$profesorId = (int) $_SESSION['professor_id'];
$cursoAcademico = cursoAcademicoActual();
$proyectoId = isset($_GET['proyecto_id']) ? (int) $_GET['proyecto_id'] : 0;

// Autorització real: mai es confia només en el proyecto_id rebut per GET.
// fasesResolverContextTutor() ja verifica rel_profesores_grupos.
$proyectoAlumno = fasesResolverContextTutor($pdo, $profesorId, $cursoAcademico, $proyectoId);
if ($proyectoAlumno === null) {
    http_response_code(403);
    die('Accés no permès');
}

$rolVisualitzacio = 'professor';

require __DIR__ . '/fase-tutor_capcalera.php';

require dirname(__DIR__, 2) . '/alumnos/fases_projecte.php';
