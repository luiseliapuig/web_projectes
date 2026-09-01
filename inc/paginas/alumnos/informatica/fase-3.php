<?php
declare(strict_types=1);

// $faseIntroduccion ve de fases.php: és la mateixa descripció que reutilitza
// la targeta de Fase 3 a "Fases del projecte".
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$faseNumero = 3;
$faseTitulo = 'Document funcional';
$faseIntroduccion = obtenerFasesArquitectura('informatica')[$faseNumero]['descripcio'] ?? '';
$faseContenidoArchivo = __DIR__ . '/fase-3_tasques.php';
$permitirSinProyecto = true;
$contextoCursoActual = true;
require __DIR__ . '/fase_base.php';
