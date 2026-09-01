<?php
declare(strict_types=1);

function fase5RepositoriUrlValida(string $url): bool
{
    if ($url === '' || mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function fase5RepositoriLiteral(?string $etiqueta): string
{
    $etiqueta = trim((string) $etiqueta);
    return $etiqueta === '' ? 'Repositori Git' : 'Repositori Git (' . $etiqueta . ')';
}

function fase5RepositorisObtenirEstat(PDO $pdo, int $idProjecte): array
{
    $principal = [];
    $addicionals = [];
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare('SELECT git_url, git_etiqueta, entorno_desarrollo_url, entorno_desarrollo_pdf, entorno_desarrollo_validado_en FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $idProjecte]);
        $principal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("SELECT id, nom, ruta FROM app.proyecto_adjuntos WHERE proyecto_id = :id AND tipo = 'git' ORDER BY id");
        $stmt->execute([':id' => $idProjecte]);
        $addicionals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $url = trim((string) ($principal['git_url'] ?? ''));
    $etiqueta = trim((string) ($principal['git_etiqueta'] ?? ''));
    $entornUrl = trim((string) ($principal['entorno_desarrollo_url'] ?? ''));
    $entornPdf = trim((string) ($principal['entorno_desarrollo_pdf'] ?? ''));
    $entornValidat = ($principal['entorno_desarrollo_validado_en'] ?? null) !== null;
    $solicitudOberta = null;
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare("SELECT id_revision_solicitud, solicitado_en FROM app.revisiones_solicitudes WHERE proyecto_id = :id AND tipo = 'entorn_desenvolupament' AND resuelto_en IS NULL LIMIT 1");
        $stmt->execute([':id' => $idProjecte]);
        $solicitudOberta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $repositoris = [];
    if ($url !== '') {
        $repositoris[] = ['url' => $url, 'literal' => fase5RepositoriLiteral($etiqueta)];
    }
    foreach ($addicionals as $repositori) {
        $repositoris[] = [
            'url' => trim((string) ($repositori['ruta'] ?? '')),
            'literal' => fase5RepositoriLiteral((string) ($repositori['nom'] ?? '')),
        ];
    }
    $repositoriInformat = $repositoris !== [];
    $documentCompletat = $entornPdf !== '';
    $documentAtencio = ($entornValidat && !$documentCompletat) || $solicitudOberta !== null;

    return [
        'principal_url' => $url,
        'principal_etiqueta' => $etiqueta,
        'principal_literal' => fase5RepositoriLiteral($etiqueta),
        'addicionals' => $addicionals,
        'repositoris' => $repositoris,
        'repositoris_informats' => $repositoriInformat,
        'entorn_url' => $entornUrl,
        'entorn_pdf' => $entornPdf,
        'entorn_validat' => $entornValidat,
        'entorn_solicitud_oberta' => $solicitudOberta,
        'entorn_completat' => $documentCompletat,
        'entorn_atencio' => $documentAtencio,
    ];
}

function fase5RepositorisData(?string $data): string
{
    if ($data === null || $data === '') return '';
    $marca = strtotime($data);
    return $marca !== false ? date('d/m/Y', $marca) : $data;
}
