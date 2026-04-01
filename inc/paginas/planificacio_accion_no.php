<?php
// planificacio_accion.php
// Algoritme: cada cicle rep slots ordenats per hora→dia→aula.
// Mai dos projectes del mateix cicle coincideixen en dia+hora.

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'missatge' => 'Dades invàlides.']);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════════

function parseConfig(array $input): array
{
    return [
        'dies'    => array_values(array_filter([
            $input['dia1'] ?? '',
            $input['dia2'] ?? '',
            $input['dia3'] ?? '',
        ])),
        'duracio' => max(20, (int)($input['duracio_franja'] ?? 45)),
        'mati' => [
            'inici' => $input['hora_inici_mati']  ?? '08:30',
            'fi'    => $input['hora_fi_mati']      ?? '11:30',
            'aules' => array_map('intval', $input['aules_mati'] ?? []),
        ],
        'tarda' => [
            'inici' => $input['hora_inici_tarda'] ?? '15:00',
            'fi'    => $input['hora_fi_tarda']    ?? '18:00',
            'aules' => array_map('intval', $input['aules_tarda'] ?? []),
        ],
    ];
}

function esManyana(array $p): bool
{
    return strtoupper($p['cicle']) === 'SMX'
        && in_array(strtoupper($p['grup'] ?? ''), ['A', 'B'], true);
}

// Genera tots els slots d'un torn com a índex: $slots[$hora][$dia][] = aulaId
// Estructura que permet recórrer hora a hora, rotant entre dies.
function generarSlotsIndexats(array $cfg, string $torn): array
{
    $durSecs = $cfg['duracio'] * 60;

    $ref_inici = strtotime('2000-01-01 ' . $cfg[$torn]['inici']);
    $ref_fi    = strtotime('2000-01-01 ' . $cfg[$torn]['fi']);

    // $index[$hora][$dia] = [ ['aula_id'=>X, 'ocupat'=>false], ... ]
    $index = [];
    for ($t = $ref_inici; $t + $durSecs <= $ref_fi; $t += $durSecs) {
        $hora = date('H:i', $t);
        foreach ($cfg['dies'] as $dia) {
            foreach ($cfg[$torn]['aules'] as $aulaId) {
                $index[$hora][$dia][] = ['aula_id' => $aulaId, 'ocupat' => false];
            }
        }
    }

    return $index;
}

// Agrupa projectes per torn i cicle+grup
function agrupPerCicle(array $projectes): array
{
    $grups = ['mati' => [], 'tarda' => []];
    foreach ($projectes as $p) {
        $torn = esManyana($p) ? 'mati' : 'tarda';
        $clau = strtoupper($p['cicle']);
        $grups[$torn][$clau][] = $p;
    }

    // Dins de cada cicle, ordenem els projectes per grup (A→B→C→D→sense grup)
    // Així dins d'una aula es col·loquen primer tots els A, després els B, etc.
    foreach (['mati', 'tarda'] as $torn) {
        foreach ($grups[$torn] as $cicle => $projs) {
            usort($grups[$torn][$cicle], function($a, $b) {
                $ga = strtoupper(trim($a['grup'] ?? ''));
                $gb = strtoupper(trim($b['grup'] ?? ''));
                if ($ga === '' && $gb === '') return 0;
                if ($ga === '') return 1;  // sense grup va al final
                if ($gb === '') return -1;
                return strcmp($ga, $gb);
            });
        }
        ksort($grups[$torn]);
    }

    return $grups;
}

// Assignació principal:
// 1. Reparteix equitativament els projectes de cada cicle entre els dies
//    (ceil per als primers dies si no és divisible exacte)
// 2. Dins de cada dia, assigna en ordre de grup (A→B→C→D) a la mateixa aula
//    fins que s'omple; si s'omple passa a la següent aula del mateix dia.
// 3. Restricció: cap dos projectes del mateix cicle al mateix dia+hora.
function assignarTorn(array $cicles, array &$slotsIndex, array $dies): array
{
    $assignats = [];
    $senseSlot = [];
    $hores     = array_keys($slotsIndex);
    $nDies     = count($dies);

    foreach ($cicles as $clau => $projectes) {
        $nProj = count($projectes);
        if ($nProj === 0) continue;

        // Repartiment equitatiu entre dies
        // Ex: 21 projectes, 3 dies → [7, 7, 7]
        // Ex: 20 projectes, 3 dies → [7, 7, 6]
        $perDia   = [];
        $base     = (int)floor($nProj / $nDies);
        $sobrants = $nProj % $nDies;
        for ($i = 0; $i < $nDies; $i++) {
            $perDia[$dies[$i]] = $base + ($i < $sobrants ? 1 : 0);
        }

        // Recórrer els projectes en ordre (ja ordenats per grup A→B→C→D)
        $projIdx = 0;

        foreach ($dies as $dia) {
            $quota    = $perDia[$dia];
            $assignat = 0;

            // Per a cada projecte de la quota d'aquest dia,
            // busquem hora+aula respectant que no es repeteixi dia+hora per al cicle.
            // $diaHoraUsats ja no cal perquè cada projecte d'un cicle va a una hora diferent
            // (quota ≤ nombre d'hores disponibles en condicions normals).
            $horaIdx = 0;

            while ($assignat < $quota && $projIdx < $nProj) {
                if ($horaIdx >= count($hores)) {
                    // No hi ha més hores per a aquest dia → sense slot
                    $senseSlot[] = $projectes[$projIdx]['id'];
                    $projIdx++;
                    $assignat++;
                    continue;
                }

                $hora = $hores[$horaIdx];

                // Buscar aula lliure per a aquest dia+hora
                $aulaAssignada = null;
                if (isset($slotsIndex[$hora][$dia])) {
                    foreach ($slotsIndex[$hora][$dia] as &$slot) {
                        if (!$slot['ocupat']) {
                            $slot['ocupat'] = true;
                            $aulaAssignada  = $slot['aula_id'];
                            break;
                        }
                    }
                    unset($slot);
                }

                if ($aulaAssignada === null) {
                    // Hora plena per a aquest dia, prova la següent hora
                    $horaIdx++;
                    continue;
                }

                $p = $projectes[$projIdx];
                $assignats[] = [
                    'proj_id'    => $p['id'],
                    'dia'        => $dia,
                    'hora_inici' => $hora,
                    'aula_id'    => $aulaAssignada,
                ];
                $projIdx++;
                $assignat++;
                $horaIdx++;
            }
        }

        // Queden sense slot si hi havia més projectes que franges totals
        while ($projIdx < $nProj) {
            $senseSlot[] = $projectes[$projIdx]['id'];
            $projIdx++;
        }
    }

    return ['assignats' => $assignats, 'sense_slot' => $senseSlot];
}

// ═══════════════════════════════════════════════════════════════════
// EXECUCIÓ
// ═══════════════════════════════════════════════════════════════════

$cfg           = parseConfig($input);
$sobreescriure = ($input['sobreescriure'] ?? '0') === '1';

if (count($cfg['dies']) === 0) {
    echo json_encode(['ok' => false, 'missatge' => "No s'han indicat dies de defensa."]);
    exit;
}
if (count($cfg['mati']['aules']) === 0 || count($cfg['tarda']['aules']) === 0) {
    echo json_encode(['ok' => false, 'missatge' => 'Has de seleccionar aules per als dos torns.']);
    exit;
}

// Carregar projectes
try {
    $stmt = $pdo->query("
        SELECT id_proyecto, ciclo, grupo, defensa_fecha, defensa_aula_id
        FROM app.proyectos
    ");
    $tots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir projectes de la BD.']);
    exit;
}

$tots = array_map(fn($p) => [
    'id'              => (int)$p['id_proyecto'],
    'cicle'           => $p['ciclo']           ?? '',
    'grup'            => $p['grupo']           ?? '',
    'defensa_fecha'   => $p['defensa_fecha']   ?? null,
    'defensa_aula_id' => $p['defensa_aula_id'] ?? null,
], $tots);

if (!$sobreescriure) {
    $tots = array_values(array_filter($tots, fn($p) =>
        empty($p['defensa_fecha']) || empty($p['defensa_aula_id'])
    ));
} else {
    $tots = array_values($tots);
}

// Agrupar i assignar per torn
$cicles     = agrupPerCicle($tots);
$slotsMati  = generarSlotsIndexats($cfg, 'mati');
$slotsTarda = generarSlotsIndexats($cfg, 'tarda');

$resMati  = assignarTorn($cicles['mati'],  $slotsMati,  $cfg['dies']);
$resTarda = assignarTorn($cicles['tarda'], $slotsTarda, $cfg['dies']);

$assignats = array_merge($resMati['assignats'], $resTarda['assignats']);
$senseSlot = array_merge($resMati['sense_slot'], $resTarda['sense_slot']);

// Guardar a la BD
try {
    $pdo->beginTransaction();

    // Pas 1: netejar assignacions anteriors per evitar col·lisió UNIQUE
    $ids = array_column($assignats, 'proj_id');
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("
            UPDATE app.proyectos
            SET defensa_fecha = NULL, defensa_aula_id = NULL
            WHERE id_proyecto IN ($ph)
        ")->execute($ids);
    }

    // Pas 2: assignar nous valors
    $stmtUpdate = $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha   = :defensa_fecha,
            defensa_aula_id = :defensa_aula_id
        WHERE id_proyecto   = :id
    ");

    foreach ($assignats as $a) {
        $stmtUpdate->execute([
            'defensa_fecha'   => $a['dia'] . ' ' . $a['hora_inici'] . ':00',
            'defensa_aula_id' => $a['aula_id'],
            'id'              => $a['proj_id'],
        ]);
    }

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'missatge' => 'Error en guardar a la BD: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok'             => true,
    'assignats'      => count($assignats),
    'sense_slot'     => count($senseSlot),
    'ids_sense_slot' => $senseSlot,
]);
exit;
