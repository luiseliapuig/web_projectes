<?php


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$idProyecto = (int) ($_POST['id_proyecto'] ?? 0);

if (!$idProyecto) {
    die('Projecte no vàlid.');
}

/* =========================
   VALIDAR PROYECTO
========================= */

$stmt = $pdo->prepare("
    SELECT
        id_proyecto,
        curso_academico,
        ciclo,
        grupo
    FROM app.proyectos
    WHERE id_proyecto = :id
");
$stmt->execute([
    ':id' => $idProyecto
]);

$proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proyecto) {
    die('Projecte no trobat.');
}

/* =========================
   VALIDAR ARCHIVO
========================= */

if (
    !isset($_FILES['presentacion_defensa']) ||
    $_FILES['presentacion_defensa']['error'] !== UPLOAD_ERR_OK
) {
    die('Error pujant el fitxer.');
}

$archivo = $_FILES['presentacion_defensa'];

$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

if ($extension !== 'pdf') {
    die('Només es permeten fitxers PDF.');
}

/* =========================
   VALIDAR MIME
========================= */

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $archivo['tmp_name']);
finfo_close($finfo);

if ($mime !== 'application/pdf') {
    die('El fitxer no és un PDF vàlid.');
}

/* =========================
   TAMAÑO MÁXIMO
========================= */

$maxSize = 20 * 1024 * 1024;

if ($archivo['size'] > $maxSize) {
    die('El PDF supera el límit de 20 MB.');
}

/* =========================
   CREAR CARPETA
========================= */

$directorio = __DIR__ . '/../../uploads/' .
    $proyecto['curso_academico'] . '/' .
    $proyecto['ciclo'] . '/' .
    $proyecto['id_proyecto'] . '/';

if (!is_dir($directorio)) {
    mkdir($directorio, 0775, true);
}

/* =========================
   NOMBRE ARCHIVO
========================= */

$nombreArchivo = 'presentacion-defensa-' . $idProyecto . '.pdf';

$rutaFisica = $directorio . $nombreArchivo;

$rutaBD =
    'uploads/' .
    $proyecto['curso_academico'] . '/' .
    $proyecto['ciclo'] . '/' .
    $proyecto['id_proyecto'] . '/' .
    $nombreArchivo;

/* =========================
   MOVER ARCHIVO
========================= */

if (!move_uploaded_file($archivo['tmp_name'], $rutaFisica)) {
    die('No s’ha pogut guardar el fitxer.');
}

/* =========================
   GUARDAR EN BD
========================= */

$stmt = $pdo->prepare("
    UPDATE app.proyectos
    SET presentacion_defensa = :ruta
    WHERE id_proyecto = :id
");

$stmt->execute([
    ':ruta' => $rutaBD,
    ':id'   => $idProyecto
]);

/* =========================
   REDIRECCIÓN
========================= */

header('Location: /projecte/' . $idProyecto);
exit;