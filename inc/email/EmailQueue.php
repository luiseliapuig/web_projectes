<?php
declare(strict_types=1);

final class EmailQueue
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enqueue(array $message): int
    {
        $recipient = strtolower(trim((string) ($message['destinatario'] ?? '')));
        $subject = trim((string) ($message['asunto'] ?? ''));
        $html = trim((string) ($message['cuerpo_html'] ?? ''));
        $text = trim((string) ($message['cuerpo_texto'] ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject === '' || mb_strlen($subject) > 255 || $html === '' || $text === '') {
            throw new InvalidArgumentException('El mensaje no contiene destinatario, asunto o cuerpo válidos.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO app.email_outbox (
                destinatario, nombre_destinatario, asunto, cuerpo_html, cuerpo_texto,
                tipo, proyecto_id, creado_por, clave_idempotencia
            ) VALUES (
                :destinatario, :nombre, :asunto, :html, :texto,
                :tipo, :proyecto_id, :creado_por, :clave
            )
            ON CONFLICT (clave_idempotencia) DO NOTHING
            RETURNING id_email
        ");
        $stmt->execute([
            ':destinatario' => $recipient,
            ':nombre' => ($name = trim((string) ($message['nombre_destinatario'] ?? ''))) !== '' ? $name : null,
            ':asunto' => $subject,
            ':html' => $html,
            ':texto' => $text,
            ':tipo' => trim((string) ($message['tipo'] ?? 'manual')) ?: 'manual',
            ':proyecto_id' => isset($message['proyecto_id']) ? (int) $message['proyecto_id'] : null,
            ':creado_por' => isset($message['creado_por']) ? (int) $message['creado_por'] : null,
            ':clave' => ($key = trim((string) ($message['clave_idempotencia'] ?? ''))) !== '' ? $key : null,
        ]);
        $id = $stmt->fetchColumn();
        return $id === false ? 0 : (int) $id;
    }

    public function claimBatch(int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("
                UPDATE app.email_outbox
                SET estado = 'pendiente', bloqueado_en = NULL, actualizado_en = CURRENT_TIMESTAMP
                WHERE estado = 'enviando'
                  AND bloqueado_en < CURRENT_TIMESTAMP - INTERVAL '15 minutes'
            ");
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM app.email_outbox
                WHERE estado = 'pendiente' AND disponible_desde <= CURRENT_TIMESTAMP
                ORDER BY disponible_desde, id_email
                FOR UPDATE SKIP LOCKED
                LIMIT :limite
            ");
            $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($messages !== []) {
                $ids = array_map('intval', array_column($messages, 'id_email'));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $update = $this->pdo->prepare("
                    UPDATE app.email_outbox
                    SET estado = 'enviando', bloqueado_en = CURRENT_TIMESTAMP,
                        intentos = intentos + 1, actualizado_en = CURRENT_TIMESTAMP
                    WHERE id_email IN ($placeholders)
                ");
                $update->execute($ids);
            }
            $this->pdo->commit();
            return $messages;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function markSent(int $id): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE app.email_outbox
            SET estado = 'enviado', enviado_en = CURRENT_TIMESTAMP, bloqueado_en = NULL,
                error_ultimo = NULL, actualizado_en = CURRENT_TIMESTAMP
            WHERE id_email = :id AND estado = 'enviando'
        ");
        $stmt->execute([':id' => $id]);
    }

    public function markFailed(int $id, int $attempt, int $maxAttempts, string $error): void
    {
        $retry = $attempt < $maxAttempts;
        $delayMinutes = min(360, 2 ** max(0, $attempt - 1));
        $nextAttemptSql = $retry
            ? "CURRENT_TIMESTAMP + (:minutos * INTERVAL '1 minute')"
            : 'disponible_desde';
        $stmt = $this->pdo->prepare("
            UPDATE app.email_outbox
            SET estado = :estado, bloqueado_en = NULL, error_ultimo = :error,
                disponible_desde = $nextAttemptSql,
                actualizado_en = CURRENT_TIMESTAMP
            WHERE id_email = :id AND estado = 'enviando'
        ");
        $parameters = [
            ':estado' => $retry ? 'pendiente' : 'error',
            ':error' => mb_substr($error, 0, 1000),
            ':id' => $id,
        ];
        if ($retry) {
            $parameters[':minutos'] = $delayMinutes;
        }
        $stmt->execute($parameters);
    }
}
