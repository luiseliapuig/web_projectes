<?php
declare(strict_types=1);

/**
 * Condició SQL canònica dels llistats públics de projectes.
 *
 * El curs es compara pel seu any inicial després de validar el format YYYY-YY;
 * el paràmetre procedeix sempre de cursoAcademicoActual().
 */
function projectesPublicsCondicioSql(string $alias = 'p'): string
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $alias)) {
        throw new InvalidArgumentException('Àlies SQL no vàlid.');
    }

    return "$alias.publicado = true
        AND $alias.curso_academico ~ '^[0-9]{4}-[0-9]{2}$'
        AND substring($alias.curso_academico FROM 1 FOR 4)::integer < :curs_public_actual_inici";
}

function projectesPublicsParametres(): array
{
    $cursActual = cursoAcademicoActual();
    if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $cursActual)) {
        throw new RuntimeException('El curs acadèmic actual no té el format canònic.');
    }

    return [':curs_public_actual_inici' => (int) substr($cursActual, 0, 4)];
}
