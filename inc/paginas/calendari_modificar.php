<?php
// calendari_modificar.php
// Actualitza la data, hora i aula d'un projecte. Redirigeix al calendari.

declare(strict_types=1);

if (!esSuperadmin()) {
    $to = '/index.php?main=calendari&msg=' . urlencode('Accés no autoritzat.');
    echo '<script>location.href=' . json_encode($to) . ';</script>';
    exit;
}

$id_proyecto = (int)($_POST['id_proyecto'] ?? 0);
$nova_data   = trim($_POST['nova_data']    ?? '');
$nova_hora   = trim($_POST['nova_hora']    ?? '');
$nova_aula   = (int)($_POST['nova_aula_id'] ?? 0);
$dia_retorn  = trim($_POST['dia_retorn']   ?? '');
$torn_retorn = in_array($_POST['torn_retorn'] ?? '', ['mati','tarda']) ? $_POST['torn_retorn'] : 'mati';

if (!$id_proyecto || !$nova_data || !$nova_hora || !$nova_aula) {
    $to = '/index.php?main=calendari&msg=' . urlencode('Dades incorrectes.');
    echo '<script>location.href=' . json_encode($to) . ';</script>';
    exit;
}

// Validar format data i hora
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nova_data) ||
    !preg_match('/^\d{2}:\d{2}$/', $nova_hora)) {
    $to = '/index.php?main=calendari&msg=' . urlencode('Format de data o hora incorrecte.');
    echo '<script>location.href=' . json_encode($to) . ';</script>';
    exit;
}

$nova_defensa_fecha = $nova_data . ' ' . $nova_hora . ':00';

try {
    $pdo->beginTransaction();

    // Netejar primer per evitar col·lisió UNIQUE (defensa_aula_id, defensa_fecha)
    $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha = NULL, defensa_aula_id = NULL
        WHERE id_proyecto = ?
    ")->execute([$id_proyecto]);

    $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha   = ?,
            defensa_aula_id = ?
        WHERE id_proyecto   = ?
    ")->execute([$nova_defensa_fecha, $nova_aula, $id_proyecto]);

    $pdo->commit();

    $msg = 'Defensa actualitzada correctament.';
} catch (PDOException $e) {
    $pdo->rollBack();
    $msg = 'Error en actualitzar la defensa: ' . $e->getMessage();
}

// Si és una petició AJAX, retornar JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $ok = isset($msg) && strpos($msg, 'correctament') !== false;
    echo json_encode(['ok' => $ok, 'missatge' => $msg ?? '']);
    exit;
}

// Fallback: redirecció clàssica
$params = 'msg=' . urlencode($msg ?? '');
if ($dia_retorn) $params .= '&dia=' . urlencode($dia_retorn);
$params .= '&torn=' . urlencode($torn_retorn);
$to = '/index.php?main=calendari&' . $params;
echo '<script>location.href=' . json_encode($to) . ';</script>';
exit;
