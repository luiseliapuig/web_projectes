<?php
declare(strict_types=1);

/**
 * Resol el grup actiu entre els grups que la consulta de la vista ja ha
 * autoritzat per al professor i el curs actual.
 *
 * Una selecció explícita (pill de grup) actualitza la sessió. Un grup
 * contextual, com el resolt des d'un deep link de projecte de Memòria, té
 * prioritat per a la petició actual però no altera l'última pill seleccionada.
 */
function resoldreGrupActiuTutor(
    array $grupsAutoritzats,
    int $grupIdExplicit = 0,
    int $grupIdContextual = 0
): int {
    $idsAutoritzats = array_map(
        static fn (array $grup): int => (int) $grup['id_grupo'],
        $grupsAutoritzats
    );

    if ($grupIdExplicit > 0 && in_array($grupIdExplicit, $idsAutoritzats, true)) {
        $_SESSION['tutor_grupo_activo_id'] = $grupIdExplicit;
        return $grupIdExplicit;
    }

    if ($grupIdContextual > 0 && in_array($grupIdContextual, $idsAutoritzats, true)) {
        return $grupIdContextual;
    }

    $grupIdSessio = isset($_SESSION['tutor_grupo_activo_id'])
        ? (int) $_SESSION['tutor_grupo_activo_id']
        : 0;
    if ($grupIdSessio > 0 && in_array($grupIdSessio, $idsAutoritzats, true)) {
        return $grupIdSessio;
    }

    unset($_SESSION['tutor_grupo_activo_id']);

    return $idsAutoritzats[0] ?? 0;
}
