<?php
declare(strict_types=1);
if (!esProfesor()) { http_response_code(403); die('Accés no permès'); }
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$proyectoId = isset($_GET['proyecto_id']) ? (int) $_GET['proyecto_id'] : 0;
$proyectoAlumno = fasesResolverContextTutor($pdo, (int) $_SESSION['professor_id'], cursoAcademicoActual(), $proyectoId);
if ($proyectoAlumno === null || !proyectoPerteneceArquitecturaFases($proyectoAlumno, 'informatica')) { http_response_code(403); die('Accés no permès'); }
$rolVisualitzacio = 'professor';
$capcaleraOcultarTornarResum = true;
require __DIR__ . '/fase-tutor_capcalera.php';
require dirname(__DIR__, 2) . '/alumnos/informatica/fase-5_tecnologies_eines.php';
