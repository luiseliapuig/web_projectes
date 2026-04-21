<?php
declare(strict_types=1);

header('Content-Type: application/json');

function jsonOut(bool $ok, array $extra = [], string $missatge = ''): never
{
    echo json_encode(array_merge(['ok' => $ok, 'missatge' => $missatge], $extra));
    exit;
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

$accio      = trim($_POST['accio'] ?? '');
$idProjecte = (int)($_POST['proyecto_id'] ?? 0);

// Per eliminar, proyecto_id no ve al POST — l'obtenim de la BD
if ($accio === 'eliminar' && $idProjecte === 0) {
    $idAdj = (int)($_POST['id'] ?? 0);
    if ($idAdj > 0) {
        try {
            $stmtP = $pdo->prepare("SELECT proyecto_id FROM app.proyecto_adjuntos WHERE id = ?");
            $stmtP->execute([$idAdj]);
            $idProjecte = (int)($stmtP->fetchColumn() ?: 0);
        } catch (PDOException $e) {}
    }
}

if (!esSuperadmin() && !esSuProyectoAlumno($idProjecte)) {
    jsonOut(false, missatge: 'No autoritzat.');
}

// ── AFEGIR ────────────────────────────────────────────────────────
if ($accio === 'afegir') {

    $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
    $tipo       = trim($_POST['tipo'] ?? '');
    $nom        = trim($_POST['nom'] ?? '');

    if (!$proyectoId || !in_array($tipo, ['arxiu', 'enllac', 'planificacio', 'gestio'], true) || $nom === '') {
        jsonOut(false, missatge: 'Dades incorrectes.');
    }

    // Verificar que el proyecto existe
    try {
        $stmt = $pdo->prepare("SELECT ciclo, curso_academico FROM app.proyectos WHERE id_proyecto = ?");
        $stmt->execute([$proyectoId]);
        $proj = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en carregar el projecte.');
    }

    if (!$proj) jsonOut(false, missatge: 'Projecte no trobat.');

    if ($tipo === 'arxiu') {

        // Guardar PDF
        $file = $_FILES['fitxer'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            jsonOut(false, missatge: 'Error en la pujada del fitxer.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') jsonOut(false, missatge: 'Només s\'admeten fitxers PDF.');
        if (!is_uploaded_file($file['tmp_name'])) jsonOut(false, missatge: 'Fitxer no vàlid.');

        $ciclo          = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$proj['ciclo']);
        $curs           = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$proj['curso_academico']);
        $uploadsBaseAbs = dirname(__DIR__, 2) . '/uploads';
        $dirAbs         = $uploadsBaseAbs . '/' . $curs . '/' . $ciclo . '/' . $proyectoId;
        $dirRel         = '/uploads/' . $curs . '/' . $ciclo . '/' . $proyectoId;

        if (!is_dir($dirAbs)) mkdir($dirAbs, 0775, true);

        $nomFitxer = 'adjunt-' . time() . '-' . preg_replace('/[^a-z0-9\-_]/', '', strtolower($nom)) . '.pdf';
        $rutaAbs   = $dirAbs . '/' . $nomFitxer;
        $rutaRel   = $dirRel . '/' . $nomFitxer;

        if (!move_uploaded_file($file['tmp_name'], $rutaAbs)) {
            jsonOut(false, missatge: 'No s\'ha pogut guardar el fitxer.');
        }

        comprimirPdf($rutaAbs);

        try {
            $ins = $pdo->prepare("
                INSERT INTO app.proyecto_adjuntos (proyecto_id, tipo, nom, ruta)
                VALUES (?, 'arxiu', ?, ?)
                RETURNING id
            ");
            $ins->execute([$proyectoId, $nom, $rutaRel]);
            $id = (int)$ins->fetchColumn();
        } catch (PDOException $e) {
            jsonOut(false, missatge: 'Error en guardar a la base de dades.');
        }

        jsonOut(true, ['id' => $id, 'nom' => $nom, 'ruta' => $rutaRel]);

    } elseif ($tipo === 'gestio') {

        // Guardar captura de gestió (imatge)
        $file = $_FILES['fitxer'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            jsonOut(false, missatge: 'Error en la pujada de la imatge.');
        }
        if (!is_uploaded_file($file['tmp_name'])) jsonOut(false, missatge: 'Fitxer no vàlid.');

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) jsonOut(false, missatge: 'El fitxer no és una imatge vàlida.');

        $ciclo          = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$proj['ciclo']);
        $curs           = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$proj['curso_academico']);
        $uploadsBaseAbs = dirname(__DIR__, 2) . '/uploads';
        $dirAbs         = $uploadsBaseAbs . '/' . $curs . '/' . $ciclo . '/' . $proyectoId;
        $dirRel         = '/uploads/' . $curs . '/' . $ciclo . '/' . $proyectoId;

        if (!is_dir($dirAbs)) mkdir($dirAbs, 0775, true);

        // Numeració seqüencial gestio1.jpg, gestio2.jpg...
        $n = 1;
        while (file_exists($dirAbs . '/gestio' . $n . '.jpg')) $n++;
        $nomFitxer = 'gestio' . $n . '.jpg';
        $rutaAbs   = $dirAbs . '/' . $nomFitxer;
        $rutaRel   = $dirRel . '/' . $nomFitxer;

        // Processar i guardar com a JPG (màx 1600x1200)
        [$w, $h, $tipus] = $imageInfo;
        $ratio      = min(1600 / $w, 1200 / $h, 1);
        $nouW       = (int)round($w * $ratio);
        $nouH       = (int)round($h * $ratio);

        $origen = match ($tipus) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
            default        => false,
        };
        if ($origen === false) jsonOut(false, missatge: 'No s\'ha pogut processar la imatge.');

        $destino = imagecreatetruecolor($nouW, $nouH);
        $blanc   = imagecolorallocate($destino, 255, 255, 255);
        imagefilledrectangle($destino, 0, 0, $nouW, $nouH, $blanc);
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nouW, $nouH, $w, $h);
        $ok = imagejpeg($destino, $rutaAbs, 85);
        imagedestroy($origen);
        imagedestroy($destino);

        if (!$ok) jsonOut(false, missatge: 'No s\'ha pogut guardar la imatge.');

        try {
            $ins = $pdo->prepare("
                INSERT INTO app.proyecto_adjuntos (proyecto_id, tipo, nom, ruta)
                VALUES (?, 'gestio', ?, ?)
                RETURNING id
            ");
            $ins->execute([$proyectoId, $nom, $rutaRel]);
            $id = (int)$ins->fetchColumn();
        } catch (PDOException $e) {
            jsonOut(false, missatge: 'Error en guardar a la base de dades.');
        }

        jsonOut(true, ['id' => $id, 'nom' => $nom, 'ruta' => $rutaRel]);

    } else {

        // Enllaç
        $ruta = trim($_POST['ruta'] ?? '');
        if ($ruta === '' || (!str_starts_with($ruta, 'http://') && !str_starts_with($ruta, 'https://'))) {
            jsonOut(false, missatge: 'La URL ha de començar per http:// o https://');
        }

        try {
            $ins = $pdo->prepare("
                INSERT INTO app.proyecto_adjuntos (proyecto_id, tipo, nom, ruta)
                VALUES (?, ?, ?, ?)
                RETURNING id
            ");
            $ins->execute([$proyectoId, $tipo, $nom, $ruta]);
            $id = (int)$ins->fetchColumn();
        } catch (PDOException $e) {
            jsonOut(false, missatge: 'Error en guardar a la base de dades.');
        }

        jsonOut(true, ['id' => $id, 'nom' => $nom, 'ruta' => $ruta]);
    }
}

// ── ELIMINAR ──────────────────────────────────────────────────────
if ($accio === 'eliminar') {

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonOut(false, missatge: 'ID no vàlid.');

    try {
        $stmt = $pdo->prepare("SELECT ruta, tipo FROM app.proyecto_adjuntos WHERE id = ?");
        $stmt->execute([$id]);
        $adj = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en cercar l\'adjunt.');
    }

    if (!$adj) jsonOut(false, missatge: 'Adjunt no trobat.');

    // Eliminar fitxer físic si és arxiu o gestio
    if (in_array($adj['tipo'], ['arxiu', 'gestio'], true) && !empty($adj['ruta'])) {
        $absPath = dirname(__DIR__, 2) . $adj['ruta'];
        if (is_file($absPath)) @unlink($absPath);
    }

    try {
        $pdo->prepare("DELETE FROM app.proyecto_adjuntos WHERE id = ?")->execute([$id]);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en eliminar.');
    }

    jsonOut(true);
}

jsonOut(false, missatge: 'Acció no reconeguda.');
