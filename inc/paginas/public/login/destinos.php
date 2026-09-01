<?php
declare(strict_types=1);

/** Destino inmediato tras autenticarse, decidido exclusivamente en servidor. */
function loginDestinoPostAutenticacion(string $tipo, ?string $rolProfesor = null): string
{
    if ($tipo === 'alumne') {
        return '/fases-del-projecte';
    }

    if ($tipo === 'professor' && $rolProfesor !== 'superadmin') {
        return '/resum';
    }

    // Superadmin conserva el aterrizaje previo mientras no exista uno canónico.
    return '/inici';
}
