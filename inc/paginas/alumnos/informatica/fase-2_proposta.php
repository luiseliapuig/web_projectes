<?php
declare(strict_types=1);

// Pàgina de detall de la tasca "Proposta de projecte", dins de la Fase 2.
// Mateix patró que fase-2.php (fixa les variables que espera fase_base.php:
// sidebar de fases + targeta), però amb el contingut de detall de la tasca
// en lloc del llistat de targetes-resum de la fase.

// La FASE sempre és consultable, però el DETALL d'una tasca (el seu espai
// de treball) només ho és quan la tasca està disponible: Proposta de
// projecte exigeix Fase 1 completada. Es resol el context aquí, abans de
// requerir fase_base.php, perquè el gate talli abans que comenci cap
// sortida pròpia de la fase (sidebar, targeta, capçalera). Mateix idioma
// que ja fa servir projecte_context.php quan denega l'accés: es respon amb
// 403 i es retorna (mai die/exit), perquè el layout general (capçalera/peu)
// es tanqui igualment en lloc de deixar la pàgina a mitges. Vegeu
// docs/codex/arquitectura.md ("Fases y tareas").
//
// El professorat hi entra com a "visitant amb drets": si qui inclou aquest
// fitxer ja arriba amb $proyectoAlumno resolt (context de professorat, no
// sessió d'alumne), no es torna a resoldre aquí, i el gate de Fase 1 —que
// és un requisit pedagògic de l'alumnat, no una prohibició de consulta— no
// aplica: el professorat pot consultar el detall encara que l'alumnat no
// hi pugui accedir.
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
if (!isset($proyectoAlumno)) {
    if (!(require dirname(__DIR__) . '/projecte_context.php')) {
        return;
    }
}
if ($rolVisualitzacio !== 'professor') {
    require_once __DIR__ . '/fase-1_funcions.php';
    if (!fase1CompletadaAlumnoProyecto($pdo, (int) ($_SESSION['alumno_id'] ?? 0), (int) ($proyectoAlumno['id_proyecto'] ?? 0))) {
        http_response_code(403);
        echo '<div class="container-fluid py-4"><div class="alert alert-warning mb-0">Encara no pots accedir a aquesta tasca: primer cal completar la Fase 1.</div></div>';
        return;
    }
}

$faseNumero = 2;
$faseTitulo = 'Proposta de projecte';
$breadcrumbTasca = 'Proposta de projecte';
$faseContenidoArchivo = __DIR__ . '/fase-2_proposta_detall.php';
require __DIR__ . '/fase_base.php';
