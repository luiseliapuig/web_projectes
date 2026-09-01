<?php
declare(strict_types=1);

// Deep-link contextual del professorat al "Compromís de treball" (Fase 1)
// d'un projecte concret, únicament de consulta. Hi arriba des del CTA
// "Veure compromís" de fase-1_contingut.php quan el compromís ja està
// acceptat per tot el projecte. Reutilitza el controlador REAL de
// l'alumnat (fase-1_compromis_form.php): mateix contingut, mateix sidebar;
// només canvia com es resol $proyectoAlumno i es marca $rolVisualitzacio.
// Mateix patró que fase-2-tutor_proposta.php. Vegeu docs/codex/arquitectura.md
// ("El recorrido de fases pertenece al proyecto, no al rol").
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

// Aquesta vista contextual és específica de l'arquitectura 'informatica'.
if (!proyectoPerteneceArquitecturaFases($proyectoAlumno, 'informatica')) {
    http_response_code(403);
    die('Accés no permès');
}

$rolVisualitzacio = 'professor';

// El breadcrumb de fase_base.php ja inclou "Resum" com a primer element
// (vegeu fase_base.php): el botó de la capçalera hi seria redundant.
$capcaleraOcultarTornarResum = true;

require __DIR__ . '/fase-tutor_capcalera.php';

require dirname(__DIR__, 2) . '/alumnos/informatica/fase-1_compromis_form.php';
