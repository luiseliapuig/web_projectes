<?php
declare(strict_types=1);

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
if (!isset($proyectoAlumno) && !(require dirname(__DIR__) . '/projecte_context.php')) return;
require_once __DIR__ . '/fase-2_proposta_funcions.php';
if ($rolVisualitzacio !== 'professor') {
    if (!fase2PropostaObtenirEstat($pdo, (int) ($proyectoAlumno['id_proyecto'] ?? 0))['completada']) {
        http_response_code(403);
        echo '<div class="container-fluid py-4"><div class="alert alert-warning mb-0">Encara no pots accedir a aquesta tasca: primer cal completar la Fase 2.</div></div>';
        return;
    }
}
$classificacio = fase2ClassificacioObtenirEstat($pdo, (int) ($proyectoAlumno['id_proyecto'] ?? 0));
$esProjecteRecerca = in_array($classificacio['categoria_id'], [1, 5], true);
$nomDocumentFase3 = $esProjecteRecerca ? 'Pla de recerca' : 'Document funcional';
$descripcioDocumentFase3 = $esProjecteRecerca
    ? 'Prepareu el document que definirà els objectius, l’abast i la metodologia de la recerca abans de començar-ne el desenvolupament.'
    : 'Prepareu el document que definirà els requisits, l’abast i les funcionalitats del projecte abans de començar-ne el desenvolupament.';
$faseNumero = 3;
$faseTitulo = 'Definició del projecte';
$breadcrumbTasca = $nomDocumentFase3;
$faseContenidoArchivo = __DIR__ . '/fase-3_document_funcional_detall.php';
require __DIR__ . '/fase_base.php';
