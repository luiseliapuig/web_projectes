<?php
// config/db.php
declare(strict_types=1);

/**
 * Devuelve una conexión PDO a PostgreSQL usando credenciales en .env
 * Soporta DB_SCHEMA opcional para configurar search_path automáticamente.
 */

$envPath = __DIR__ . '/../.env';
if (!is_file($envPath)) {
    throw new RuntimeException("No encuentro el .env en: $envPath");
}

$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
if ($env === false) {
    throw new RuntimeException("No puedo leer el .env (parse_ini_file falló).");
}

/**
 * Exporta variables del .env a:
 * - getenv()
 * - $_ENV
 * - $_SERVER
 */
foreach ($env as $k => $v) {
    if (!is_string($k)) continue;
    if ($v === null) $v = '';
    $v = (string)$v;

    // Normaliza: quita comillas envolventes si existen
    $vv = trim($v);
    if ($vv !== '' && (
        ($vv[0] === '"' && substr($vv, -1) === '"') ||
        ($vv[0] === "'" && substr($vv, -1) === "'")
    )) {
        $vv = substr($vv, 1, -1);
    }

    putenv($k . '=' . $vv);
    $_ENV[$k] = $vv;
    $_SERVER[$k] = $vv;
    $env[$k] = $vv;
}

$required = ['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS'];
foreach ($required as $k) {
    if (!isset($env[$k]) || $env[$k] === '') {
        throw new RuntimeException("Falta $k en .env");
    }
}

$sslmode = $env['DB_SSLMODE'] ?? 'disable';
$schema  = $env['DB_SCHEMA'] ?? null;

// Validación básica de schema (seguridad + evitar sustos)
if ($schema !== null && $schema !== '') {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $schema)) {
        throw new RuntimeException("DB_SCHEMA contiene caracteres no válidos.");
    }
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
    $env['DB_HOST'],
    $env['DB_PORT'],
    $env['DB_NAME'],
    $sslmode
);

try {
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Aplicar search_path si procede (sin tocar tus consultas)
    if ($schema !== null && $schema !== '') {
        // Identificador seguro: ya validado con regex
        $pdo->exec('SET search_path TO ' . $schema);
    }

} catch (PDOException $e) {
    throw new RuntimeException('Error conectando a PostgreSQL: ' . $e->getMessage());
}

return $pdo;