<?php
declare(strict_types=1);

// Fase 2 sempre és consultable, encara que Fase 1 no estigui completada: la
// fase en si mateixa mai es bloqueja, només ho fan les seves tasques (vegeu
// fase-2_tasques.php per a la targeta bloquejada i fase-2_proposta.php per
// al gate real del detall/espai de treball). Vegeu docs/codex/arquitectura.md
// ("Fases y tareas").
// $faseIntroduccion ve de fases.php (única font, compartida amb la targeta
// resum de "Fases del projecte" — vegeu fases_projecte.php): mai es torna a
// redactar aquí mateix.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$faseNumero = 2;
$faseTitulo = 'Proposta de projecte';
$faseIntroduccion = obtenerFasesArquitectura('informatica')[$faseNumero]['descripcio'] ?? '';
$faseContenidoArchivo = __DIR__ . '/fase-2_tasques.php';
$permitirSinProyecto = true;
$contextoCursoActual = true;
require __DIR__ . '/fase_base.php';
