<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$faseNumero = 5;
$faseTitulo = 'Desenvolupament del projecte';
$faseIntroduccion = obtenerFasesArquitectura('informatica')[$faseNumero]['descripcio'] ?? '';
$faseContenidoArchivo = __DIR__ . '/fase-5_tasques.php';
$permitirSinProyecto = true;
$contextoCursoActual = true;
require __DIR__ . '/fase_base.php';
