<?php
declare(strict_types=1);

const FASE6_FITXA_RESUM_MAX = 220;
const FASE6_FITXA_DESCRIPCIO_MIN = 800;

function fase6FitxaPublicaObtenirEstat(PDO $pdo, int $projecteId): array
{
    $dades = ['nombre' => '', 'resumen' => '', 'descripcion' => '', 'ruta_imagen' => '', 'curso_academico' => '', 'ciclo' => ''];
    if ($projecteId > 0) {
        $stmt = $pdo->prepare('SELECT p.nombre, p.resumen, p.descripcion, p.ruta_imagen, p.curso_academico, c.abr AS ciclo FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
        $stmt->execute([':id' => $projecteId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fila) {
            foreach ($dades as $camp => $_) $dades[$camp] = trim((string) ($fila[$camp] ?? ''));
        }
    }
    $dades['completada'] = $dades['nombre'] !== '' && $dades['resumen'] !== '' && $dades['descripcion'] !== '' && $dades['ruta_imagen'] !== '';
    $dades['imatge_url'] = fase6FitxaPublicaUrlImatge($dades['ruta_imagen']);
    return $dades;
}

function fase6FitxaPublicaUrlImatge(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') return '';
    $url = str_starts_with($ruta, '/') || preg_match('#^https?://#i', $ruta) ? $ruta : '/' . ltrim($ruta, '/');
    $camiUrl = (string) parse_url($url, PHP_URL_PATH);
    if (!str_starts_with($camiUrl, '/uploads/')) return $url;
    $arrel = dirname(__DIR__, 4);
    $fitxer = $arrel . str_replace('/', DIRECTORY_SEPARATOR, $camiUrl);
    $versio = is_file($fitxer) ? filemtime($fitxer) : false;
    return $versio === false ? $url : $url . (str_contains($url, '?') ? '&' : '?') . 'time=' . $versio;
}

function fase6FitxaPublicaPartRuta(string $valor): string
{
    return (string) preg_replace('/[^A-Za-z0-9_-]/', '', trim($valor));
}

function fase6FitxaPublicaFitxerAnterior(string $ruta): ?string
{
    $camiUrl = (string) parse_url(trim($ruta), PHP_URL_PATH);
    if (!str_starts_with($camiUrl, '/uploads/')) return null;
    $arrelUploads = realpath(dirname(__DIR__, 4) . '/uploads');
    $fitxer = realpath(dirname(__DIR__, 4) . str_replace('/', DIRECTORY_SEPARATOR, $camiUrl));
    if ($arrelUploads === false || $fitxer === false || !str_starts_with($fitxer, $arrelUploads . DIRECTORY_SEPARATOR)) return null;
    return $fitxer;
}
