<?php
declare(strict_types=1);

require_once __DIR__ . '/fase-5_repositoris_funcions.php';
require_once __DIR__ . '/fase-5_stack_funcions.php';
require_once __DIR__ . '/fase-5_autoavaluacio_funcions.php';
require_once __DIR__ . '/fase-5_produccio_funcions.php';

/**
 * Estat canònic de les tasques aplicables de Fase 5.
 * Git s'aplica a Desenvolupament (ID 2) i I+D (ID 5), però no a
 * Investigació (ID 1). Aquesta agrupació és pròpia de Fase 5.
 * La preparació de l'entorn es conserva operativa, però no forma part
 * d'aquest recorregut ni del seu criteri global de completat.
 */
function fase5ObtenirEstat(PDO $pdo, int $projecteId): array
{
    $repositoris = fase5RepositorisObtenirEstat($pdo, $projecteId);
    $gitAplica = in_array($repositoris['categoria_id'], [2, 5], true);
    $stack = fase5StackObtenirEstat($pdo, $projecteId);
    $autoavaluacio = fase5AutoavaluacioObtenirEstat($pdo, $projecteId);
    $produccio = fase5ProduccioObtenirEstat($pdo, $projecteId);

    return [
        'git_aplica' => $gitAplica,
        'repositoris' => $repositoris,
        'stack' => $stack,
        'autoavaluacio' => $autoavaluacio,
        'produccio' => $produccio,
        'completada' => (!$gitAplica || $repositoris['repositoris_informats'])
            && $stack['completada']
            && $autoavaluacio['completada']
            && $produccio['completada'],
    ];
}
