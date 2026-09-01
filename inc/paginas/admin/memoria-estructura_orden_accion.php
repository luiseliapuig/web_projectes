<?php
declare(strict_types=1);

// Ruta 'api' (vegeu index.php): respon en JSON i no renderitza el layout.
// Reordena per drag & drop els apartats de memòria DINS d'una mateixa
// categoria. Mai canvia la categoria d'un apartat (això només es fa des del
// formulari d'edició).
header('Content-Type: application/json; charset=utf-8');

if (!esSuperadmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'Accés no permès.']);
    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'La sol·licitud no és vàlida o ha caducat.']);
    exit;
}

$categoriaId = isset($_POST['categoria_proyecto_id']) ? (int) $_POST['categoria_proyecto_id'] : 0;
$ordreRebut = $_POST['ordre'] ?? null;

if ($categoriaId <= 0 || !is_array($ordreRebut) || $ordreRebut === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Dades no vàlides.']);
    exit;
}

$idsRebuts = [];
foreach ($ordreRebut as $valor) {
    $idValor = filter_var($valor, FILTER_VALIDATE_INT);
    if ($idValor === false || $idValor <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'missatge' => 'Dades no vàlides.']);
        exit;
    }
    $idsRebuts[] = $idValor;
}

// Cap id repetit: si n'hi hagués, la seqüència rebuda no seria fiable.
if (count($idsRebuts) !== count(array_unique($idsRebuts))) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Dades no vàlides.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Defensa en profunditat: els ids rebuts han de coincidir EXACTAMENT
    // (ni més, ni menys) amb el conjunt real d'apartats d'aquesta categoria,
    // perquè des d'aquí mai es pugui tocar l'ordre d'una altra categoria.
    $stmt = $pdo->prepare("
        SELECT id_memoria_estructura
        FROM app.memoria_estructura
        WHERE categoria_proyecto_id = :categoria_id
        FOR UPDATE
    ");
    $stmt->execute([':categoria_id' => $categoriaId]);
    $idsReals = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $idsRealsOrdenats = $idsReals;
    $idsRebutsOrdenats = $idsRebuts;
    sort($idsRealsOrdenats);
    sort($idsRebutsOrdenats);

    if ($idsReals === [] || $idsRealsOrdenats !== $idsRebutsOrdenats) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'missatge' => 'L’ordre rebut no coincideix amb els apartats reals d’aquesta categoria.']);
        exit;
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE app.memoria_estructura
        SET orden = :orden
        WHERE id_memoria_estructura = :id AND categoria_proyecto_id = :categoria_id
    ");
    foreach ($idsRebuts as $posicio => $idApartat) {
        $stmtUpdate->execute([
            ':orden' => $posicio + 1,
            ':id' => $idApartat,
            ':categoria_id' => $categoriaId,
        ]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error reordenant l’estructura de memòria: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut desar l’ordre.']);
}
