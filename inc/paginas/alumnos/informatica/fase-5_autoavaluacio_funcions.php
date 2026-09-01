<?php
declare(strict_types=1);

function fase5AutoavaluacioPreguntes(): array
{
    return [
        'autoev1' => 'Què has après en aquest projecte?',
        'autoev2' => 'De quina part del projecte estàs més satisfet?',
        'autoev3' => "Quines parts no s'han pogut completar i per què?",
        'autoev4' => 'Què milloraries si tinguessis més temps?',
    ];
}

function fase5AutoavaluacioObtenirEstat(PDO $pdo, int $projecteId): array
{
    $respostes = array_fill_keys(array_keys(fase5AutoavaluacioPreguntes()), '');
    if ($projecteId > 0) {
        $stmt = $pdo->prepare('SELECT autoev1, autoev2, autoev3, autoev4 FROM app.proyectos WHERE id_proyecto=:id');
        $stmt->execute([':id' => $projecteId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($respostes as $camp => $_) $respostes[$camp] = trim((string) ($fila[$camp] ?? ''));
    }
    $completada = count(array_filter($respostes, static fn(string $valor): bool => $valor !== '')) === count($respostes);
    $iniciada = array_filter($respostes, static fn(string $valor): bool => $valor !== '') !== [];
    return ['respostes' => $respostes, 'completada' => $completada, 'iniciada' => $iniciada];
}
