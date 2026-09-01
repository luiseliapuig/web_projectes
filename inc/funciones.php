<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Curso académico
// El curso cambia el 1 de agosto y se representa con el formato 2026-27.
// -----------------------------------------------------------------------------

function cursoAcademicoActual(?DateTimeImmutable $fecha = null): string
{
    $zonaHoraria = new DateTimeZone('Europe/Madrid');
    $fecha = $fecha === null
        ? new DateTimeImmutable('now', $zonaHoraria)
        : $fecha->setTimezone($zonaHoraria);

    $year = (int) $fecha->format('Y');
    $month = (int) $fecha->format('n');
    $inicio = $month < 8 ? $year - 1 : $year;
    $fin = $inicio + 1;

    return sprintf('%d-%02d', $inicio, $fin % 100);
}

// -----------------------------------------------------------------------------
// Curso utilizado por el flujo de defensas.
// Se mantiene como función específica para que todas sus pantallas y acciones
// compartan siempre la misma regla automática de curso académico.
// -----------------------------------------------------------------------------

function cursoAcademicoDefensas(): string
{
    return cursoAcademicoActual();
}

// Dates naturals en català amb noms controlats per l'aplicació.
function dataCatalanaNatural(?string $valor): string
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return '';
    }

    $fragment = substr($valor, 0, 10);
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $fragment);
    if ($data === false || $data->format('Y-m-d') !== $fragment) {
        $data = DateTimeImmutable::createFromFormat('!d/m/Y', $fragment);
        if ($data === false || $data->format('d/m/Y') !== $fragment) {
            return $valor;
        }
    }

    $dies = ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];
    $mesos = [
        1 => 'de gener', 2 => 'de febrer', 3 => 'de març', 4 => 'd’abril',
        5 => 'de maig', 6 => 'de juny', 7 => 'de juliol', 8 => 'd’agost',
        9 => 'de setembre', 10 => 'd’octubre', 11 => 'de novembre', 12 => 'de desembre',
    ];

    return $dies[(int) $data->format('w')]
        . ', ' . (int) $data->format('j')
        . ' ' . $mesos[(int) $data->format('n')];
}

// -----------------------------------------------------------------------------
// Protección CSRF
// Un único token de sesión protege los formularios internos con escritura.
// -----------------------------------------------------------------------------

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validarTokenCsrf(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// -----------------------------------------------------------------------------
// Correo institucional
// MAIL_DOMAIN permite que los formularios internos soliciten solo la parte
// local, pero la aplicación siempre valida y persiste la dirección completa.
// -----------------------------------------------------------------------------

function dominioCorreoInstitucional(): string
{
    $dominio = strtolower(ltrim(trim((string) (getenv('MAIL_DOMAIN') ?: '')), '@'));
    return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $dominio)
        ? $dominio
        : '';
}

function normalizarCorreoInstitucional(string $valor): string
{
    $valor = strtolower(trim($valor));
    $dominio = dominioCorreoInstitucional();
    if ($dominio === '') {
        return $valor;
    }
    if (!str_contains($valor, '@')) {
        return $valor === '' ? '' : $valor . '@' . $dominio;
    }
    return str_ends_with($valor, '@' . $dominio) ? $valor : '';
}

function parteLocalCorreoInstitucional(string $email): string
{
    $email = strtolower(trim($email));
    $dominio = dominioCorreoInstitucional();
    $sufijo = '@' . $dominio;
    return $dominio !== '' && str_ends_with($email, $sufijo)
        ? substr($email, 0, -strlen($sufijo))
        : $email;
}

// -----------------------------------------------------------------------------
// Colores de ciclo
// La base de datos guarda una clave controlada y la interfaz decide sus clases.
// -----------------------------------------------------------------------------

function coloresCicloPermitidos(): array
{
    return ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
}

function clasesColorCiclo(string $color): string
{
    return match ($color) {
        'primary' => 'bg-primary-subtle text-primary border-primary-subtle',
        'success' => 'bg-success-subtle text-success border-success-subtle',
        'danger' => 'bg-danger-subtle text-danger border-danger-subtle',
        'warning' => 'bg-warning-subtle text-dark border-warning-subtle',
        'info' => 'bg-info-subtle text-dark border-info-subtle',
        default => 'bg-secondary-subtle text-dark border-secondary-subtle',
    };
}

// Variante sólida del mismo color de ciclo, para marcar una selección sin
// perder la información cromática del ciclo (p. ej. navegación por grupos).
// Bootstrap 5.3 ya resuelve el contraste del texto en text-bg-*.
function clasesColorCicloSolid(string $color): string
{
    return in_array($color, coloresCicloPermitidos(), true)
        ? 'text-bg-' . $color
        : 'text-bg-secondary';
}
