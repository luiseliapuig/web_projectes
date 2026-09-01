<?php
declare(strict_types=1);

require_once __DIR__ . '/fase-5_repositoris_funcions.php';
require_once __DIR__ . '/fase-5_stack_funcions.php';
require_once __DIR__ . '/fase-5_autoavaluacio_funcions.php';
require_once __DIR__ . '/fase-5_produccio_funcions.php';

/**
 * Estat canònic de les quatre tasques visibles de Fase 5.
 * La preparació de l'entorn es conserva operativa, però no forma part
 * d'aquest recorregut ni del seu criteri global de completat.
 */
function fase5ObtenirEstat(PDO $pdo, int $projecteId): array
{
    $repositoris = fase5RepositorisObtenirEstat($pdo, $projecteId);
    $stack = fase5StackObtenirEstat($pdo, $projecteId);
    $autoavaluacio = fase5AutoavaluacioObtenirEstat($pdo, $projecteId);
    $produccio = fase5ProduccioObtenirEstat($pdo, $projecteId);

    return [
        'repositoris' => $repositoris,
        'stack' => $stack,
        'autoavaluacio' => $autoavaluacio,
        'produccio' => $produccio,
        'completada' => $repositoris['repositoris_informats']
            && $stack['completada']
            && $autoavaluacio['completada']
            && $produccio['completada'],
    ];
}
