<?php
declare(strict_types=1);

ob_start();

header('Content-Type: application/json');

function jsonOut(bool $ok, array $extra = [], string $missatge = ''): never
{
    ob_end_clean();
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

if (!isset($_SESSION['professor_id']) && !esSuperadmin() && !isset($_SESSION['alumne_id'])) {
    jsonOut(false, missatge: 'No autoritzat.');
}

$accio = trim($_POST['accio'] ?? '');

// ── AFEGIR ────────────────────────────────────────────────────────
if ($accio === 'afegir') {

    $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
    $tipo       = trim($_POST['tipo'] ?? '');
    $nom        = trim($_POST['nom'] ?? '');

    if (!$proyectoId || !in_array($tipo, ['arxiu', 'enllac', 'planificacio'], true) || $nom === '') {
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

    // Eliminar fitxer físic si és arxiu
    if ($adj['tipo'] === 'arxiu' && !empty($adj['ruta'])) {
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
