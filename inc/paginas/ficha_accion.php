<?php
declare(strict_types=1);

$idProyecto = isset($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : 0;

if ($idProyecto <= 0) {
    echo '<div class="alert alert-danger">Dades no vàlides.</div>';
    return;
}

function comprimirPdf(string $rutaAbs): void
{
    $rutaTmp = $rutaAbs . '.tmp.pdf';

    $cmd = sprintf(
        'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook ' .
        '-dNOPAUSE -dQUIET -dBATCH ' .
        '-sOutputFile=%s %s 2>/dev/null',
        escapeshellarg($rutaTmp),
        escapeshellarg($rutaAbs)
    );

    shell_exec($cmd);

    // Solo sustituir si la compresión generó un archivo válido y más pequeño
    if (
        file_exists($rutaTmp) &&
        filesize($rutaTmp) > 0 &&
        filesize($rutaTmp) < filesize($rutaAbs)
    ) {
        rename($rutaTmp, $rutaAbs);
    } else {
        @unlink($rutaTmp);
    }
}

function slugify(string $text): string
{
    $text = trim($text);
    $text = mb_strtolower($text, 'UTF-8');
    $replacements = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];
    $text = strtr($text, $replacements);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : 'proyecto';
}

function sanitizePathPart(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^A-Za-z0-9\-_]/', '', $value);
    return (string)$value;
}

function irAFicha(int $idProyecto, string $msg = ''): never
{
    $to = '/projecte/' . $idProyecto;
    if ($msg !== '') {
        $to .= '?msg=' . urlencode($msg);
    }
    echo '<script>location.href=' . json_encode($to) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES) . '"></noscript>';
    exit;
}

function detectarExtensionImagen(array $file): ?string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) return null;
    return match ($imageInfo[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        default        => null,
    };
}

function guardarImagenWeb(array $file, string $rutaDestinoAbs, int $maxAncho = 1600, int $maxAlto = 1200, int $calidadJpg = 85): bool
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return false;
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    $extension = detectarExtensionImagen($file);
    if ($extension === null) return false;
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) return false;
    [$anchoOriginal, $altoOriginal, $tipo] = $imageInfo;
    if ($anchoOriginal <= 0 || $altoOriginal <= 0) return false;
    $ratio = min($maxAncho / $anchoOriginal, $maxAlto / $altoOriginal, 1);
    $nuevoAncho = (int) round($anchoOriginal * $ratio);
    $nuevoAlto  = (int) round($altoOriginal * $ratio);
    $origen = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        default        => false,
    };
    if ($origen === false) return false;
    $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    $blanco  = imagecolorallocate($destino, 255, 255, 255);
    imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, $blanco);
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOriginal, $altoOriginal);
    $ok = imagejpeg($destino, $rutaDestinoAbs, $calidadJpg);
    imagedestroy($origen);
    imagedestroy($destino);
    return $ok;
}

function guardarPdf(array $file, string $rutaDestinoAbs): bool
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return false;
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    $extension = strtolower((string) pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($extension !== 'pdf') return false;
    if (!is_uploaded_file($file['tmp_name'])) return false;

    if (!move_uploaded_file($file['tmp_name'], $rutaDestinoAbs)) return false;

    comprimirPdf($rutaDestinoAbs);

    return true;
}

// Cargar proyecto
try {
    $stmt = $pdo->prepare("
        SELECT id_proyecto, uuid, curso_academico, ciclo
        FROM proyectos
        WHERE id_proyecto = :id_proyecto
        LIMIT 1
    ");
    $stmt->execute(['id_proyecto' => $idProyecto]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error al carregar el projecte.</div>';
    return;
}

if (!$proyecto) {
    echo '<div class="alert alert-danger">Projecte no trobat.</div>';
    return;
}

$cursoAcademico = sanitizePathPart((string)$proyecto['curso_academico']);
$ciclo          = sanitizePathPart((string)$proyecto['ciclo']);

if ($cursoAcademico === '' || $ciclo === '') {
    echo '<div class="alert alert-danger">El projecte no té curs acadèmic o cicle vàlids.</div>';
    return;
}

// Datos texto
$nombre      = trim($_POST['nombre']      ?? '');
$resumen     = trim($_POST['resumen']     ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$stack       = trim($_POST['stack']       ?? '');
$urlGithub   = trim($_POST['url_github']  ?? '');
$urlProyecto = trim($_POST['url_proyecto'] ?? '');

// Autoevaluació
$autoev1 = trim($_POST['autoev1'] ?? '');
$autoev2 = trim($_POST['autoev2'] ?? '');
$autoev3 = trim($_POST['autoev3'] ?? '');
$autoev4 = trim($_POST['autoev4'] ?? '');

if ($nombre === '') {
    echo '<div class="alert alert-danger">El nom del projecte és obligatori.</div>';
    return;
}
if ($urlGithub !== '' && filter_var($urlGithub, FILTER_VALIDATE_URL) === false) {
    echo '<div class="alert alert-danger">La URL del repositori no és vàlida.</div>';
    return;
}
if ($urlProyecto !== '' && filter_var($urlProyecto, FILTER_VALIDATE_URL) === false) {
    echo '<div class="alert alert-danger">La URL del projecte no és vàlida.</div>';
    return;
}

// Preparar carpeta
$uploadsBaseAbs  = dirname(__DIR__, 2) . '/uploads';
$rutaProyectoAbs = $uploadsBaseAbs . '/' . $cursoAcademico . '/' . $ciclo . '/' . $idProyecto;
$rutaProyectoRel = '/uploads/' . $cursoAcademico . '/' . $ciclo . '/' . $idProyecto;

if (!is_dir($rutaProyectoAbs)) {
    if (!mkdir($rutaProyectoAbs, 0775, true) && !is_dir($rutaProyectoAbs)) {
        echo '<div class="alert alert-danger">No s\'ha pogut crear la carpeta del projecte.</div>';
        return;
    }
}

$slugProyecto     = slugify($nombre);
$nombreImagen     = 'imagen.jpg';
$nombreMemoria    = $slugProyecto . '-' . $ciclo . '-memoria.pdf';
$nombreFuncional  = $slugProyecto . '-' . $ciclo . '-documento-funcional.pdf';
$nombreFichaEntrega = $slugProyecto . '-' . $ciclo . '-ficha-de-entrega.pdf';

// Datos a actualizar
$data = [
    'nombre'      => $nombre,
    'resumen'     => $resumen,
    'descripcion' => $descripcion,
    'stack'       => $stack,
    'url_github'  => $urlGithub,
    'url_proyecto' => $urlProyecto,
    'autoev1'     => $autoev1 !== '' ? $autoev1 : null,
    'autoev2'     => $autoev2 !== '' ? $autoev2 : null,
    'autoev3'     => $autoev3 !== '' ? $autoev3 : null,
    'autoev4'     => $autoev4 !== '' ? $autoev4 : null,
];

// Imagen
if (!empty($_FILES['imagen']['name'] ?? '')) {
    $rutaImagenAbs = $rutaProyectoAbs . '/' . $nombreImagen;
    if (!guardarImagenWeb($_FILES['imagen'], $rutaImagenAbs)) {
        echo '<div class="alert alert-danger">No s\'ha pogut processar la imatge. Usa JPG, PNG o WEBP.</div>';
        return;
    }
    $data['ruta_imagen'] = $rutaProyectoRel . '/' . $nombreImagen;
}

// Documento funcional
if (!empty($_FILES['funcional']['name'] ?? '')) {
    $rutaAbs = $rutaProyectoAbs . '/' . $nombreFuncional;
    if (!guardarPdf($_FILES['funcional'], $rutaAbs)) {
        echo '<div class="alert alert-danger">El document funcional ha de ser un PDF vàlid.</div>';
        return;
    }
    $data['ruta_funcional'] = $rutaProyectoRel . '/' . $nombreFuncional;
}

// Memoria
if (!empty($_FILES['memoria']['name'] ?? '')) {
    $rutaAbs = $rutaProyectoAbs . '/' . $nombreMemoria;
    if (!guardarPdf($_FILES['memoria'], $rutaAbs)) {
        echo '<div class="alert alert-danger">La memòria ha de ser un PDF vàlid.</div>';
        return;
    }
    $data['ruta_memoria'] = $rutaProyectoRel . '/' . $nombreMemoria;
}

// Ficha de entrega
if (!empty($_FILES['ficha_entrega']['name'] ?? '')) {
    $rutaAbs = $rutaProyectoAbs . '/' . $nombreFichaEntrega;
    if (!guardarPdf($_FILES['ficha_entrega'], $rutaAbs)) {
        echo '<div class="alert alert-danger">La fitxa d\'entrega ha de ser un PDF vàlid.</div>';
        return;
    }
    $data['ruta_ficha_entrega'] = $rutaProyectoRel . '/' . $nombreFichaEntrega;
}

// Update
$set    = [];
$params = [];

foreach ($data as $campo => $valor) {
    $set[]          = $campo . ' = :' . $campo;
    $params[$campo] = $valor;
}

$params['id_proyecto'] = $idProyecto;

$sql = "
    UPDATE proyectos
    SET " . implode(",\n        ", $set) . "
    WHERE id_proyecto = :id_proyecto
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">No s\'ha pogut guardar la fitxa del projecte.</div>';
    return;
}

irAFicha($idProyecto, 'ok');
