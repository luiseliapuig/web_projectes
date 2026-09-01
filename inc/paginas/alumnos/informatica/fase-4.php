<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$faseNumero = 4;
$faseTitulo = 'Planificació i gestió';
$faseIntroduccion = obtenerFasesArquitectura('informatica')[$faseNumero]['descripcio'] ?? '';
$faseContenidoArchivo = __DIR__ . '/fase-4_tasques.php';
$permitirSinProyecto = true;
$contextoCursoActual = true;
require __DIR__ . '/fase_base.php';
