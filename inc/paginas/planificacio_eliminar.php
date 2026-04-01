<?php
// planificacio_eliminar.php
// Esborra defensa_fecha i defensa_aula_id de tots els projectes.

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

try {
    $stmt = $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha = NULL, defensa_aula_id = NULL
        WHERE defensa_fecha IS NOT NULL OR defensa_aula_id IS NOT NULL
    ");
    $stmt->execute();
    $afectats = $stmt->rowCount();

    echo json_encode(['ok' => true, 'projectes' => $afectats]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en eliminar les dates: ' . $e->getMessage()]);
}
exit;
