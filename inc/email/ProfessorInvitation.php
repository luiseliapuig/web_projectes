<?php
declare(strict_types=1);

final class ProfessorInvitation
{
    public function __construct(private readonly PDO $pdo, private readonly EmailService $email)
    {
    }

    public function send(int $profesorId): void
    {
        $stmt = $this->pdo->prepare('SELECT nombre, apellidos, email FROM app.profesores WHERE id_profesor=:id AND activo=true LIMIT 1');
        $stmt->execute([':id' => $profesorId]);
        $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profesor || !filter_var($profesor['email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El profesor no está activo o no tiene un email válido.');
        }
        $baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('APP_URL debe ser una URL HTTPS válida.');
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE app.profesor_password_reset SET usado_en=CURRENT_TIMESTAMP WHERE profesor_id=:id AND usado_en IS NULL")
                ->execute([':id' => $profesorId]);
            $this->pdo->prepare("
                INSERT INTO app.profesor_password_reset (profesor_id, token_hash, expira_en, tipo)
                VALUES (:id, :hash, CURRENT_TIMESTAMP + INTERVAL '5 hours', 'invitacion')
            ")->execute([':id' => $profesorId, ':hash' => $hash]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        require_once __DIR__ . '/templates/professor_invitation.php';
        $nombre = trim((string) $profesor['nombre'] . ' ' . (string) $profesor['apellidos']);
        $body = emailProfessorInvitation($nombre, $baseUrl . '/restablir-contrasenya?token=' . rawurlencode($token), 5);
        try {
            $this->email->send([
                'destinatario' => (string) $profesor['email'],
                'nombre_destinatario' => $nombre,
                'asunto' => 'Invitació a Web Projectes',
                'cuerpo_html' => $body['html'],
                'cuerpo_texto' => $body['text'],
            ]);
        } catch (Throwable $e) {
            $this->pdo->prepare('DELETE FROM app.profesor_password_reset WHERE token_hash=:hash')->execute([':hash' => $hash]);
            throw $e;
        }
    }
}
