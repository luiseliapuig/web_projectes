<?php
declare(strict_types=1);

// Deep-link contextual del professorat al detall de la tasca "Proposta de
// projecte" (Fase 2) d'un projecte concret. Dos punts d'entrada hi arriben:
// una sol·licitud de revisió pendent al Resum, o la targeta "Entrar" de
// la fase quan es navega en context professor. Reutilitza el controlador
// REAL de l'alumnat (fase-2_proposta.php → fase_base.php →
// fase-2_proposta_detall.php): mateix sidebar complet, mateix contingut;
// només canvia com es resol $proyectoAlumno i es marca $rolVisualitzacio.
// Vegeu docs/codex/arquitectura.md ("Fases y tareas").
if (!esProfesor()) {
    http_response_code(403);
    die('Accés no permès');
}

require_once dirname(__DIR__, 3) . '/fases/funciones.php';

$profesorId = (int) $_SESSION['professor_id'];
$cursoAcademico = cursoAcademicoActual();
$proyectoId = isset($_GET['proyecto_id']) ? (int) $_GET['proyecto_id'] : 0;

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

// Variables que espera fase-2_proposta_detall.php, exclusives d'aquesta
// vista contextual.
$rolVisualitzacio = 'professor';
$potValidar = esTutorFormalDelProyecto($proyectoId);

// El breadcrumb de fase_base.php ja inclou "Resum" com a primer element
// (vegeu fase_base.php): el botó de la capçalera hi seria redundant.
$capcaleraOcultarTornarResum = true;

require __DIR__ . '/fase-tutor_capcalera.php';

require dirname(__DIR__, 2) . '/alumnos/informatica/fase-2_proposta.php';
