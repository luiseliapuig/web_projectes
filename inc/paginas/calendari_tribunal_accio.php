<?php
// calendari_tribunal_accio.php
// Gestiona apuntar-se i desapuntar-se d'un tribunal.
// Rep JSON via POST. Retorna JSON.

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

// Netejar qualsevol output previ (warnings, etc.) per garantir JSON net
ob_start();

$input = json_decode(file_get_contents('php://input'), true);

$professor_id = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;

if (!$professor_id) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'missatge' => 'No identificat com a professor.']);
    exit;
}

$proj_id = (int)($input['proj_id'] ?? 0);
$accio   = trim($input['accio'] ?? '');

// El superadmin pot desapuntar qualsevol professor passant profesor_id
$target_professor_id = $professor_id;
if ($accio === 'desapuntar' && isset($input['profesor_id']) && esSuperadmin()) {
    $target_professor_id = (int)$input['profesor_id'];
}

if (!$proj_id || !in_array($accio, ['apuntar', 'desapuntar'], true)) {
    ob_end_clean();

    echo json_encode(['ok' => false, 'missatge' => 'Paràmetres incorrectes.']);
    exit;
}

try {
    if ($accio === 'apuntar') {

        // Comprovar que el projecte existeix i té defensa assignada
        $proj = $pdo->prepare("SELECT id_proyecto FROM app.proyectos WHERE id_proyecto = ? AND defensa_fecha IS NOT NULL");
        $proj->execute([$proj_id]);
        if (!$proj->fetch()) {
            ob_end_clean();

            echo json_encode(['ok' => false, 'missatge' => 'Projecte no trobat o sense data assignada.']);
            exit;
        }

        // Comprovar que el tribunal no està ple (màxim 3)
        $count = $pdo->prepare("SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE id_proyecto = ?");
        $count->execute([$proj_id]);
        $n = (int)$count->fetchColumn();

        if ($n >= 3) {
            ob_end_clean();

            echo json_encode(['ok' => false, 'tribunal_ple' => true, 'missatge' => 'Ho sento, aquest tribunal ja està complet.']);
            exit;
        }

        // Comprovar que el professor no hi és ja
        $exists = $pdo->prepare("SELECT 1 FROM app.rel_profesores_tribunal WHERE id_proyecto = ? AND profesor_id = ?");
        $exists->execute([$proj_id, $professor_id]);
        if ($exists->fetch()) {
            ob_end_clean();

            echo json_encode(['ok' => false, 'missatge' => 'Ja estàs apuntat a aquest tribunal.']);
            exit;
        }

        // Inserir
        $pdo->prepare("INSERT INTO app.rel_profesores_tribunal (id_proyecto, profesor_id) VALUES (?, ?)")
            ->execute([$proj_id, $professor_id]);

        ob_end_clean();


        echo json_encode(['ok' => true, 'accio' => 'apuntat']);

    } else {

        // Desapuntar
        $pdo->prepare("DELETE FROM app.rel_profesores_tribunal WHERE id_proyecto = ? AND profesor_id = ?")
            ->execute([$proj_id, $target_professor_id]);

        ob_end_clean();


        echo json_encode(['ok' => true, 'accio' => 'desapuntat']);
    }

} catch (PDOException $e) {
    ob_end_clean();

    echo json_encode(['ok' => false, 'missatge' => 'Error de base de dades: ' . $e->getMessage()]);
}
exit;
