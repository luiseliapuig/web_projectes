<?php
declare(strict_types=1);

final class StudentInvitation
{
    public function __construct(private readonly PDO $pdo, private readonly EmailService $email)
    {
    }

    public function send(int $alumnoId): void
    {
        $stmt = $this->pdo->prepare('SELECT nombre, apellidos, email, password_hash FROM app.alumnos WHERE id_alumno=:id AND activo=true LIMIT 1');
        $stmt->execute([':id' => $alumnoId]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$alumno || !filter_var($alumno['email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El alumno no está activo o no tiene un email válido.');
        }
        if (is_string($alumno['password_hash']) && $alumno['password_hash'] !== '') {
            return;
        }

        $baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('APP_URL debe ser una URL HTTPS válida.');
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE app.alumno_password_reset SET usado_en=CURRENT_TIMESTAMP WHERE alumno_id=:id AND usado_en IS NULL')
                ->execute([':id' => $alumnoId]);
            $this->pdo->prepare("
                INSERT INTO app.alumno_password_reset (alumno_id, token_hash, expira_en, tipo)
                VALUES (:id, :hash, CURRENT_TIMESTAMP + INTERVAL '5 hours', 'invitacion')
            ")->execute([':id' => $alumnoId, ':hash' => $hash]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        require_once __DIR__ . '/templates/student_invitation.php';
        $nombre = trim((string) $alumno['nombre'] . ' ' . (string) $alumno['apellidos']);
        $body = emailStudentInvitation($nombre, $baseUrl . '/restablir-contrasenya?token=' . rawurlencode($token), 5);
        try {
            $this->email->send([
                'destinatario' => (string) $alumno['email'],
                'nombre_destinatario' => $nombre,
                'asunto' => 'Invitació a Web Projectes',
                'cuerpo_html' => $body['html'],
                'cuerpo_texto' => $body['text'],
            ]);
        } catch (Throwable $e) {
            $this->pdo->prepare('DELETE FROM app.alumno_password_reset WHERE token_hash=:hash')->execute([':hash' => $hash]);
            throw $e;
        }
    }
}
