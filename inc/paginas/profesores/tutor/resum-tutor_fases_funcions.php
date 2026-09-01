<?php
declare(strict_types=1);

/** Projecció read-only de les tasques canòniques a partir de dades en bloc. */
function resumTutorValorBoolea(mixed $valor): bool
{
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true';
}

function resumTutorTasquesProjecte(array $projecte, array $fase1): array
{
    $id = (int) $projecte['id_proyecto'];
    $base = '/projecte/' . $id . '/fases/';
    $informat = static fn (string $camp): bool => trim((string) ($projecte[$camp] ?? '')) !== '';

    return [
        1 => [
            ['nom' => 'Defineix el grup de treball', 'completada' => (bool) ($fase1['grup'] ?? false), 'href' => $base . 'fase-1'],
            ['nom' => 'Compromís de treball', 'completada' => (bool) ($fase1['compromis'] ?? false), 'href' => $base . 'fase-1/compromis'],
        ],
        2 => [['nom' => 'Proposta de projecte', 'completada' => ($projecte['propuesta_validada_en'] ?? null) !== null && $informat('propuesta_pdf'), 'pendent_tutor' => resumTutorValorBoolea($projecte['proposta_pendent_tutor'] ?? false), 'href' => $base . 'fase-2/proposta']],
        3 => [['nom' => 'Document funcional', 'completada' => ($projecte['funcional_validado_en'] ?? null) !== null && $informat('funcional_pdf'), 'pendent_tutor' => resumTutorValorBoolea($projecte['funcional_pendent_tutor'] ?? false), 'href' => $base . 'fase-3/document-funcional']],
        4 => [
            ['nom' => 'Planificació temporal del projecte', 'completada' => $informat('planificacion_url'), 'href' => $base . 'fase-4/planificacio-temporal'],
            ['nom' => 'Gestió del projecte', 'completada' => $informat('gestion_url'), 'href' => $base . 'fase-4/gestio-projecte'],
        ],
        5 => [
            ['nom' => 'Repositoris Git', 'completada' => $informat('git_url') || resumTutorValorBoolea($projecte['te_git_adjunt'] ?? false), 'href' => $base . 'fase-5/repositoris-git'],
            ['nom' => 'Tecnologies i eines', 'completada' => resumTutorValorBoolea($projecte['te_tecnologia'] ?? false), 'href' => $base . 'fase-5/tecnologies-eines'],
            ['nom' => 'Autoavaluació final', 'completada' => $informat('autoev1') && $informat('autoev2') && $informat('autoev3') && $informat('autoev4'), 'href' => $base . 'fase-5/autoavaluacio-final'],
            ['nom' => 'Entrega del projecte', 'completada' => $informat('url_proyecto'), 'href' => $base . 'fase-5/projecte-en-produccio'],
        ],
        6 => [
            ['nom' => 'Document viu de la memòria', 'completada' => $informat('memoria_url'), 'href' => $base . 'fase-6/document-memoria'],
            ['nom' => 'Fitxa pública del projecte', 'completada' => $informat('nombre') && $informat('resumen') && $informat('descripcion') && $informat('ruta_imagen'), 'href' => $base . 'fase-6/fitxa-publica'],
            ['nom' => 'Memòria final', 'completada' => $informat('memoria_pdf'), 'href' => $base . 'fase-6/entrega-memoria'],
        ],
        7 => [['nom' => 'Presentació de la defensa', 'completada' => $informat('presentacion_pdf'), 'href' => $base . 'fase-7/presentacio-defensa']],
    ];
}
