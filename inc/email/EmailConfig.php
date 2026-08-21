<?php
declare(strict_types=1);

final class EmailConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $encryption,
        public readonly string $username,
        public readonly string $password,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $replyToAddress,
        public readonly string $replyToName,
        public readonly int $batchSize,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $value = static fn (string $key, string $default = ''): string => trim((string) (getenv($key) ?: $default));
        $port = filter_var($value('MAIL_PORT', '587'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $batchSize = filter_var($value('MAIL_BATCH_SIZE', '20'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $host = $value('MAIL_HOST');
        $password = $value('MAIL_PASSWORD');
        if (strcasecmp($host, 'smtp.gmail.com') === 0) {
            $password = preg_replace('/\s+/', '', $password) ?? $password;
        }

        return new self(
            $host,
            $port !== false ? $port : 587,
            strtolower($value('MAIL_ENCRYPTION', 'tls')),
            $value('MAIL_USERNAME'),
            $password,
            strtolower($value('MAIL_FROM_ADDRESS')),
            $value('MAIL_FROM_NAME', 'Web Projectes'),
            strtolower($value('MAIL_REPLY_TO_ADDRESS')),
            $value('MAIL_REPLY_TO_NAME'),
            $batchSize !== false ? $batchSize : 20,
        );
    }

    public function validationErrors(): array
    {
        $errors = [];
        if ($this->host === '') $errors[] = 'MAIL_HOST';
        if ($this->username === '') $errors[] = 'MAIL_USERNAME';
        if ($this->password === '') $errors[] = 'MAIL_PASSWORD';
        if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) $errors[] = 'MAIL_FROM_ADDRESS';
        if ($this->replyToAddress !== '' && !filter_var($this->replyToAddress, FILTER_VALIDATE_EMAIL)) $errors[] = 'MAIL_REPLY_TO_ADDRESS';
        if (!in_array($this->encryption, ['tls', 'ssl', 'none'], true)) $errors[] = 'MAIL_ENCRYPTION';
        return $errors;
    }

    public function isReady(): bool
    {
        return $this->validationErrors() === [];
    }
}
