<?php
declare(strict_types=1);

function fase6MemoriaUrlValida(string $url): bool
{
    if ($url === '' || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function fase6MemoriaObtenirEstat(PDO $pdo, int $projecteId): array
{
    $url = '';
    if ($projecteId > 0) {
        $stmt = $pdo->prepare('SELECT memoria_url FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $projecteId]);
        $url = trim((string) $stmt->fetchColumn());
    }

    return [
        'url' => $url,
        'completada' => $url !== '',
    ];
}

function fase6MemoriaDefinitivaObtenirEstat(PDO $pdo, int $projecteId): array
{
    $pdf = '';
    if ($projecteId > 0) {
        $stmt = $pdo->prepare('SELECT memoria_pdf FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $projecteId]);
        $pdf = trim((string) $stmt->fetchColumn());
    }

    return [
        'pdf' => $pdf,
        'completada' => $pdf !== '',
    ];
}
