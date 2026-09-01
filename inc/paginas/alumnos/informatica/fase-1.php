<?php
declare(strict_types=1);
// $faseIntroduccion ve de fases.php (única font, compartida amb la targeta
// resum de "Fases del projecte" — vegeu fases_projecte.php): mai es torna a
// redactar aquí mateix.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$faseNumero = 1;
$faseTitulo = 'Pluja d’idees i formació de grups';
$faseIntroduccion = obtenerFasesArquitectura('informatica')[$faseNumero]['descripcio'] ?? '';
$faseContenidoArchivo = __DIR__ . '/fase-1_contingut.php';
$permitirSinProyecto = true;
$contextoCursoActual = true;
require __DIR__ . '/fase_base.php';
