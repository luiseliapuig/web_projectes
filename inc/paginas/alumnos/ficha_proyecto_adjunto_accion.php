<?php
declare(strict_types=1);

header('Content-Type: application/json');

function jsonOut(bool $ok, array $extra = [], string $missatge = ''): never
{
    echo json_encode(array_merge(['ok' => $ok, 'missatge' => $missatge], $extra));
    exit;
}

// La validació, el guardat i l'optimització dels PDF definitius viuen a
// inc/pdf/funciones.php: capa única compartida amb la resta de PDFs del
// projecte (document funcional, memòria, proposta). Aquest fitxer ja no en
// manté una còpia pròpia.
require_once dirname(__DIR__, 2) . '/pdf/funciones.php';

$accio      = trim($_POST['accio'] ?? '');
$idProjecte = (int)($_POST['proyecto_id'] ?? 0);

// Todas las operaciones exigen POST y un token generado en la sesión del alumno.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || !configuracion('permitir_editar')
) {
    jsonOut(false, missatge: 'Sol·licitud no vàlida.');
}

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

if (!esSuProyectoAlumno($idProjecte)) {
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
        $stmt = $pdo->prepare("
            SELECT c.abr AS ciclo, p.curso_academico
            FROM app.proyectos p
            INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
            INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
            WHERE p.id_proyecto = ?
        ");
        $stmt->execute([$proyectoId]);
        $proj = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        jsonOut(false, missatge: 'Error en carregar el projecte.');
    }

    if (!$proj) jsonOut(false, missatge: 'Projecte no trobat.');

    if ($tipo === 'arxiu') {

        // Guardar PDF — capa única (validació + optimització + guardat).
        $file = $_FILES['fitxer'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            jsonOut(false, missatge: 'Error en la pujada del fitxer.');
        }

        $nomFitxer = 'adjunt-' . time() . '-' . preg_replace('/[^a-z0-9\-_]/', '', strtolower($nom)) . '.pdf';
        $resultat = pdfGuardarDefinitiu($file, (string) $proj['curso_academico'], (string) $proj['ciclo'], $proyectoId, $nomFitxer);
        if (!$resultat['ok']) {
            jsonOut(false, missatge: $resultat['error'] ?? 'No s\'ha pogut guardar el fitxer.');
        }
        $rutaRel = $resultat['ruta_rel'];

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
        if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
            jsonOut(false, missatge: 'La imatge supera el límit de 20 MB.');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) jsonOut(false, missatge: 'El fitxer no és una imatge vàlida.');

        $ciclo          = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$proj['ciclo']);
        $curs           = preg_replace('/[^A-Za-z0-9\-_]/', '', (string)$proj['curso_academico']);
        $uploadsBaseAbs = dirname(__DIR__, 3) . '/uploads';
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
        $absPath = dirname(__DIR__, 3) . $adj['ruta'];
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
