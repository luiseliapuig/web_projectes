<?php
declare(strict_types=1);

$idProyecto = isset($_POST['id_proyecto']) ? (int)$_POST['id_proyecto'] : 0;

// La acción repite método, CSRF y propiedad del proyecto antes de escribir.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || $idProyecto <= 0
    || !configuracion('permitir_editar')
    || !esSuProyectoAlumno($idProyecto)
) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Dades no vàlides.</div>';
    return;
}

// La validació, el guardat i l'optimització dels PDF definitius (document
// funcional, memòria, fitxa d'entrega) viuen a inc/pdf/funciones.php: capa
// única compartida amb la resta de PDFs del projecte (vegeu Proposta a
// fase-2_accion.php). Aquest fitxer ja no en manté una còpia pròpia.
require_once dirname(__DIR__, 2) . '/pdf/funciones.php';
require_once dirname(__DIR__, 2) . '/imagenes/funciones.php';

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

// Cargar proyecto
try {
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.uuid, p.curso_academico, c.abr AS ciclo
        FROM proyectos p
        INNER JOIN grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.id_proyecto = :id_proyecto
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
   // echo '<div class="alert alert-danger">El nom del projecte és obligatori.</div>';
   // return;
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
$uploadsBaseAbs  = dirname(__DIR__, 3) . '/uploads';
$rutaProyectoAbs = $uploadsBaseAbs . '/' . $cursoAcademico . '/' . $ciclo . '/' . $idProyecto;
$rutaProyectoRel = '/uploads/' . $cursoAcademico . '/' . $ciclo . '/' . $idProyecto;

if (!is_dir($rutaProyectoAbs)) {
    if (!mkdir($rutaProyectoAbs, 0775, true) && !is_dir($rutaProyectoAbs)) {
        echo '<div class="alert alert-danger">No s\'ha pogut crear la carpeta del projecte.</div>';
        return;
    }
}

$slugProyecto     = slugify($nombre);
$nombreImagen     = 'imagen-' . time() . '.jpg';
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
    $resultat = pdfGuardarDefinitiu($_FILES['funcional'], $cursoAcademico, $ciclo, $idProyecto, $nombreFuncional);
    if (!$resultat['ok']) {
        echo '<div class="alert alert-danger">El document funcional ha de ser un PDF vàlid.</div>';
        return;
    }
    $data['funcional_pdf'] = $resultat['ruta_rel'] . '?v=' . time();
}

// Memoria
if (!empty($_FILES['memoria']['name'] ?? '')) {
    $resultat = pdfGuardarDefinitiu($_FILES['memoria'], $cursoAcademico, $ciclo, $idProyecto, $nombreMemoria);
    if (!$resultat['ok']) {
        echo '<div class="alert alert-danger">La memòria ha de ser un PDF vàlid.</div>';
        return;
    }
    $data['memoria_pdf'] = $resultat['ruta_rel'] . '?v=' . time();
}

// Ficha de entrega
if (!empty($_FILES['ficha_entrega']['name'] ?? '')) {
    $resultat = pdfGuardarDefinitiu($_FILES['ficha_entrega'], $cursoAcademico, $ciclo, $idProyecto, $nombreFichaEntrega);
    if (!$resultat['ok']) {
        echo '<div class="alert alert-danger">La fitxa d\'entrega ha de ser un PDF vàlid.</div>';
        return;
    }
    $data['ruta_ficha_entrega'] = $resultat['ruta_rel'] . '?v=' . time();
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
