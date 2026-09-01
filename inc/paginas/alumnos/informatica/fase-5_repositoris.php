<?php
declare(strict_types=1);
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
if (!isset($proyectoAlumno) && !(require dirname(__DIR__) . '/projecte_context.php')) return;
require_once __DIR__ . '/fase-4_funcions.php';
if ($rolVisualitzacio !== 'professor' && !fase4PlanificacioGestioObtenirEstat($pdo, (int) ($proyectoAlumno['id_proyecto'] ?? 0))['completada']) {
    http_response_code(403);
    echo '<div class="container-fluid py-4"><div class="alert alert-warning mb-0">Encara no pots accedir a aquesta tasca: primer cal completar la Fase 4.</div></div>';
    return;
}
$faseNumero = 5;
$faseTitulo = 'Repositoris Git';
$breadcrumbTasca = 'Repositoris Git';
$fase5Seccio = 'git';
$faseContenidoArchivo = __DIR__ . '/fase-5_preparacio_entorn_detall.php';
require __DIR__ . '/fase_base.php';
