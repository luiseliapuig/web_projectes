<?php
declare(strict_types=1);
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
if (!isset($proyectoAlumno) && !(require dirname(__DIR__) . '/projecte_context.php')) return;
require_once __DIR__ . '/fase-5_funcions.php';
require_once __DIR__ . '/fase-6_funcions.php';
$projecteId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
if ($rolVisualitzacio !== 'professor' && (!fase5ObtenirEstat($pdo, $projecteId)['completada'] || !fase6ObtenirEstat($pdo, $projecteId)['completada'])) {
    http_response_code(403);
    echo '<div class="container-fluid py-4"><div class="alert alert-warning mb-0">Encara no pots accedir a aquesta tasca: primer cal completar les Fases 5 i 6.</div></div>';
    return;
}
$faseNumero = 7;
$faseTitulo = 'Defensa';
$breadcrumbTasca = 'Presentació de la defensa';
$faseContenidoArchivo = __DIR__ . '/fase-7_presentacio_defensa_detall.php';
require __DIR__ . '/fase_base.php';
