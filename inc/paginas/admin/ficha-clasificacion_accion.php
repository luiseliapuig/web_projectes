<?php
declare(strict_types=1);

// Herramienta temporal de clasificación histórica para superadmin.
soloSuperadmin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    die('Mètode no permès');
}

if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    die('La sol·licitud no és vàlida o ha caducat.');
}

$proyectoEntrada = $_POST['id_proyecto'] ?? null;
$categoriaEntrada = $_POST['categoria_proyecto_id'] ?? null;
$tipoEntrada = $_POST['tipo_proyecto_id'] ?? null;

$proyectoId = is_string($proyectoEntrada)
    ? filter_var($proyectoEntrada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$categoriaId = is_string($categoriaEntrada)
    ? filter_var($categoriaEntrada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$tipoId = $tipoEntrada === null || $tipoEntrada === ''
    ? null
    : (is_string($tipoEntrada)
        ? filter_var($tipoEntrada, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false);

if ($proyectoId === false || $categoriaId === false || $tipoId === false) {
    http_response_code(400);
    die('Classificació no vàlida.');
}

$stmtProyecto = $pdo->prepare("\n    SELECT p.categoria_proyecto_id, p.tipo_proyecto_id, c.familia_ciclo_id\n    FROM app.proyectos p\n    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id\n    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo\n    WHERE p.id_proyecto = :proyecto_id\n    LIMIT 1\n");
$stmtProyecto->execute([':proyecto_id' => $proyectoId]);
$proyecto = $stmtProyecto->fetch(PDO::FETCH_ASSOC);

if (!$proyecto) {
    http_response_code(404);
    die('Projecte no trobat.');
}

$stmtCategoria = $pdo->prepare("\n    SELECT 1\n    FROM app.proyecto_categorias\n    WHERE id_categoria_proyecto = :categoria_id\n      AND familia_ciclo_id = :familia_id\n      AND (activo = true OR id_categoria_proyecto = :categoria_actual)\n    LIMIT 1\n");
$stmtCategoria->execute([
    ':categoria_id' => $categoriaId,
    ':familia_id' => (int) $proyecto['familia_ciclo_id'],
    ':categoria_actual' => (int) ($proyecto['categoria_proyecto_id'] ?? 0),
]);

if (!$stmtCategoria->fetchColumn()) {
    http_response_code(400);
    die('La categoria no és vàlida per a la família del projecte.');
}

$stmtTotalTipos = $pdo->prepare("\n    SELECT COUNT(*)\n    FROM app.proyecto_tipos\n    WHERE categoria_proyecto_id = :categoria_id\n      AND activo = true\n");
$stmtTotalTipos->execute([':categoria_id' => $categoriaId]);
$categoriaTieneTipos = (int) $stmtTotalTipos->fetchColumn() > 0;

if ($tipoId === null && $categoriaTieneTipos) {
    http_response_code(400);
    die('Cal seleccionar un tipus per a aquesta categoria.');
}

if ($tipoId !== null) {
    $stmtTipo = $pdo->prepare("\n        SELECT 1\n        FROM app.proyecto_tipos\n        WHERE id_tipo_proyecto = :tipo_id\n          AND categoria_proyecto_id = :categoria_id\n          AND (activo = true OR id_tipo_proyecto = :tipo_actual)\n        LIMIT 1\n    ");
    $stmtTipo->execute([
        ':tipo_id' => $tipoId,
        ':categoria_id' => $categoriaId,
        ':tipo_actual' => (int) ($proyecto['tipo_proyecto_id'] ?? 0),
    ]);

    if (!$stmtTipo->fetchColumn()) {
        http_response_code(400);
        die('El tipus no és vàlid per a la categoria seleccionada.');
    }
}

$stmtActualizar = $pdo->prepare("\n    UPDATE app.proyectos\n    SET categoria_proyecto_id = :categoria_id,\n        tipo_proyecto_id = :tipo_id\n    WHERE id_proyecto = :proyecto_id\n");
$stmtActualizar->execute([
    ':categoria_id' => $categoriaId,
    ':tipo_id' => $tipoId,
    ':proyecto_id' => $proyectoId,
]);

$url = '/projecte/' . $proyectoId;
echo '<script>location.href=' . json_encode($url) . ';</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
exit;
