<?php
declare(strict_types=1);

// Navegació contextual del professorat pel recorregut de fases d'UN
// projecte concret. NO és una segona implementació de les fases: per a
// cada fase, reutilitza exactament el mateix controlador fase-N.php de
// l'alumnat (mateix fase_base.php, mateix fases_navegacion.php, mateixes
// vistes de contingut i de targetes de tasca) — només canvia com es resol
// $proyectoAlumno (context de professorat, autoritzat contra
// rel_profesores_grupos, no sessió d'alumne) i es marca
// $rolVisualitzacio = 'professor'. Substitueix l'anterior fase-2-tutor.php,
// que era específic d'una sola fase. Vegeu docs/codex/arquitectura.md
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

// Fases reals de l'arquitectura d'aquest projecte concret (proyecto →
// ciclo → fases_clave → arquitectura registrada → definició de fases) —
// mai una llista fixa de set fases. Si el cicle no en té (o la clau no es
// reconeix), no hi ha navegació que inventar.
$fasesProyecto = obtenerFasesProyecto($proyectoAlumno);
if ($fasesProyecto === []) {
    http_response_code(404);
    die('Aquest projecte no té cap arquitectura de fases definida.');
}

// La fase sol·licitada només s'accepta si pertany realment a l'arquitectura
// d'aquest projecte; en cas contrari es recorre a la primera fase definida.
$faseNumero = isset($_GET['fase']) ? (int) $_GET['fase'] : 0;
if (!isset($fasesProyecto[$faseNumero])) {
    $faseNumero = array_key_first($fasesProyecto);
}

$rolVisualitzacio = 'professor';

// El breadcrumb de fase_base.php ja inclou "Resum" com a primer element
// (vegeu fase_base.php): el botó de la capçalera hi seria redundant.
$capcaleraOcultarTornarResum = true;

require __DIR__ . '/fase-tutor_capcalera.php';

// Directori segur de l'arquitectura (mai construït amb el valor cru de BD):
// el mateix mecanisme que ja fa servir la resta de resolució de fases.
$directoriArquitectura = fasesDirectorioSeguro($proyectoAlumno['fases_clave'] ?? null);

// Reutilitza el controlador REAL de l'alumnat per a aquesta fase — mai una
// còpia paral·lela. $proyectoAlumno i $rolVisualitzacio ja fixats es
// propaguen a fase-N.php → fase_base.php → fases_navegacion.php i al
// contingut de la fase.
require dirname(__DIR__, 2) . '/alumnos/' . $directoriArquitectura . '/' . $fasesProyecto[$faseNumero]['archivo'];
