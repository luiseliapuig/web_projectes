<?php
declare(strict_types=1);

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
if (!isset($proyectoAlumno) && !(require dirname(__DIR__) . '/projecte_context.php')) return;
if ($rolVisualitzacio !== 'professor') {
    require_once __DIR__ . '/fase-2_proposta_funcions.php';
    if (!fase2PropostaObtenirEstat($pdo, (int) ($proyectoAlumno['id_proyecto'] ?? 0))['completada']) {
        http_response_code(403);
        echo '<div class="container-fluid py-4"><div class="alert alert-warning mb-0">Encara no pots accedir a aquesta tasca: primer cal completar la Fase 2.</div></div>';
        return;
    }
}
$faseNumero = 3;
$faseTitulo = 'Document funcional';
$breadcrumbTasca = 'Document funcional';
$faseContenidoArchivo = __DIR__ . '/fase-3_document_funcional_detall.php';
require __DIR__ . '/fase_base.php';
