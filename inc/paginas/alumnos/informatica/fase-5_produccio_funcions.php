<?php
declare(strict_types=1);

function fase5ProduccioUrlValida(string $url): bool
{
    if ($url === '' || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function fase5ProduccioObtenirEstat(PDO $pdo, int $projecteId): array
{
    $url = '';
    if ($projecteId > 0) {
        $stmt = $pdo->prepare('SELECT url_proyecto FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $projecteId]);
        $url = trim((string) $stmt->fetchColumn());
    }
    return ['url' => $url, 'completada' => $url !== ''];
}
