<?php
declare(strict_types=1);

require_once __DIR__ . '/fase-6_memoria_funcions.php';
require_once __DIR__ . '/fase-6_fitxa_publica_funcions.php';

function fase6Completada(bool $documentCompletat, bool $fitxaCompletada, bool $memoriaFinalCompletada): bool
{
    return $documentCompletat && $fitxaCompletada && $memoriaFinalCompletada;
}

function fase6ObtenirEstat(PDO $pdo, int $projecteId): array
{
    $document = fase6MemoriaObtenirEstat($pdo, $projecteId);
    $fitxa = fase6FitxaPublicaObtenirEstat($pdo, $projecteId);
    $memoriaFinal = fase6MemoriaDefinitivaObtenirEstat($pdo, $projecteId);

    return [
        'document' => $document,
        'fitxa' => $fitxa,
        'memoria_final' => $memoriaFinal,
        'completada' => fase6Completada(
            $document['completada'],
            $fitxa['completada'],
            $memoriaFinal['completada']
        ),
    ];
}
