<?php
declare(strict_types=1);

// Consolida una vegada al dia el feedback d'Autoseguiment i l'encola. No
// envia SMTP: aquesta responsabilitat continua sent d'inc/email/worker.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = require dirname(__DIR__, 2) . '/config/db.php';
require_once __DIR__ . '/../email/bootstrap.php';
require_once __DIR__ . '/../email/templates/autoseguiment_feedback.php';

$baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
    fwrite(STDERR, 'APP_URL debe ser una URL HTTPS válida.' . PHP_EOL);
    exit(1);
}
$urlAutoseguiment = $baseUrl . '/autoseguiment';

$stmt = $pdo->query("
    SELECT id_seguimiento
    FROM app.seguimiento_alumnos
    WHERE feedback_email_habilitado = true
      AND feedback_email_encolado_en IS NULL
      AND valoracion_tutor IS NOT NULL
    ORDER BY id_seguimiento
");
$idsCandidatos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$resultado = [
    'candidatos' => count($idsCandidatos),
    'encolados' => 0,
    'ya_encolados' => 0,
    'omitidos' => 0,
    'errores' => 0,
];
$queue = new EmailQueue($pdo);

foreach ($idsCandidatos as $idSeguimiento) {
    try {
        $pdo->beginTransaction();

        // La mateixa fila protegeix valoració, encolat i marca funcional. En
        // concurrència, el segon procés reavalua la condició després del lock.
        $stmt = $pdo->prepare("
            SELECT sa.id_seguimiento, sa.proyecto_id, sa.semana,
                   sa.fecha_inicio, sa.fecha_fin, sa.valoracion_tutor,
                   sa.comentario_tutor, a.id_alumno, a.nombre, a.apellidos,
                   a.email, a.activo
            FROM app.seguimiento_alumnos sa
            INNER JOIN app.alumnos a ON a.id_alumno = sa.alumno_id
            WHERE sa.id_seguimiento = :id
              AND sa.feedback_email_habilitado = true
              AND sa.feedback_email_encolado_en IS NULL
              AND sa.valoracion_tutor IS NOT NULL
            FOR UPDATE OF sa
        ");
        $stmt->execute([':id' => $idSeguimiento]);
        $seguimiento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$seguimiento) {
            $pdo->commit();
            $resultado['ya_encolados']++;
            continue;
        }

        $email = strtolower(trim((string) $seguimiento['email']));
        if (!in_array($seguimiento['activo'], [true, 1, '1', 't', 'true'], true)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo->commit();
            $resultado['omitidos']++;
            continue;
        }

        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $seguimiento['fecha_inicio']);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $seguimiento['fecha_fin']);
        if ($inicio === false || $fin === false) {
            throw new RuntimeException('Període de seguiment no vàlid.');
        }

        $nombre = trim((string) $seguimiento['nombre'] . ' ' . (string) $seguimiento['apellidos']);
        $periodo = $inicio->format('d/m/Y') . '–' . $fin->format('d/m/Y');
        $body = emailAutoseguimentFeedback(
            $nombre,
            (int) $seguimiento['semana'],
            $periodo,
            (int) $seguimiento['valoracion_tutor'],
            trim((string) ($seguimiento['comentario_tutor'] ?? '')),
            $urlAutoseguiment
        );

        $idEmail = $queue->enqueue([
            'destinatario' => $email,
            'nombre_destinatario' => $nombre,
            'asunto' => 'Feedback de l’Autoseguiment',
            'cuerpo_html' => $body['html'],
            'cuerpo_texto' => $body['text'],
            'tipo' => 'autoseguiment_feedback',
            'proyecto_id' => $seguimiento['proyecto_id'] !== null ? (int) $seguimiento['proyecto_id'] : null,
            'clave_idempotencia' => 'autoseguiment_feedback:' . $idSeguimiento,
        ]);

        $stmt = $pdo->prepare("
            UPDATE app.seguimiento_alumnos
            SET feedback_email_encolado_en = NOW()
            WHERE id_seguimiento = :id
              AND feedback_email_encolado_en IS NULL
        ");
        $stmt->execute([':id' => $idSeguimiento]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No s’ha pogut marcar el feedback com a encolat.');
        }

        $pdo->commit();
        $idEmail > 0 ? $resultado['encolados']++ : $resultado['ya_encolados']++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $resultado['errores']++;
        error_log('Error encolant feedback d’autoseguiment ' . $idSeguimiento . ': ' . $e->getMessage());
    }
}

echo "Candidats: {$resultado['candidatos']}; encolats: {$resultado['encolados']}; "
    . "ja encolats: {$resultado['ya_encolados']}; omesos: {$resultado['omitidos']}; "
    . "errors: {$resultado['errores']}" . PHP_EOL;
exit($resultado['errores'] > 0 ? 1 : 0);
