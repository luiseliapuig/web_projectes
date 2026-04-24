<?php
// planificacio_accion.php
// Algoritme: cada grup (cicle+grup) té l'aula fixada per BD (grupos → ciclos).
// La distribució és homogènia entre dies, mantenint cada grup a la seva aula.
// Mai dos projectes del mateix grup coincideixen en dia+hora.

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
        ],
        'tarda' => [
            'inici' => $input['hora_inici_tarda'] ?? '15:00',
            'fi'    => $input['hora_fi_tarda']    ?? '18:00',
        ],
    ];
}

// Genera les hores disponibles per a un torn (array de strings 'HH:MM')
function generarHores(array $cfg, string $torn): array
{
    $durSecs   = $cfg['duracio'] * 60;
    $ref_inici = strtotime('2000-01-01 ' . $cfg[$torn]['inici']);
    $ref_fi    = strtotime('2000-01-01 ' . $cfg[$torn]['fi']);

    $hores = [];
    for ($t = $ref_inici; $t + $durSecs <= $ref_fi; $t += $durSecs) {
        $hores[] = date('H:i', $t);
    }
    return $hores;
}

// Assignació d'un grup: distribueix els seus projectes homogèniament entre dies,
// tots a la mateixa aula, sense repetir dia+hora dins del grup.
// Retorna ['assignats' => [...], 'sense_slot' => [...ids]]
function assignarGrup(array $projectes, int $aulaId, string $torn, array $cfg): array
{
    $assignats = [];
    $senseSlot = [];
    $dies      = $cfg['dies'];
    $hores     = generarHores($cfg, $torn);
    $nDies     = count($dies);
    $nHores    = count($hores);
    $nProj     = count($projectes);

    if ($nProj === 0) return ['assignats' => [], 'sense_slot' => []];

    // Repartiment equitatiu entre dies
    // Ex: 7 projectes, 3 dies → [3, 2, 2]
    $perDia   = [];
    $base     = (int)floor($nProj / $nDies);
    $sobrants = $nProj % $nDies;
    for ($i = 0; $i < $nDies; $i++) {
        $perDia[$dies[$i]] = $base + ($i < $sobrants ? 1 : 0);
    }

    $projIdx = 0;
    foreach ($dies as $dia) {
        $quota   = $perDia[$dia];
        $horaIdx = 0;

        for ($q = 0; $q < $quota; $q++) {
            if ($projIdx >= $nProj) break;

            if ($horaIdx >= $nHores) {
                // Esgotades les franges d'aquest dia — no hauria de passar si la simulació valida
                $senseSlot[] = $projectes[$projIdx]['id'];
                $projIdx++;
                continue;
            }

            $assignats[] = [
                'proj_id'    => $projectes[$projIdx]['id'],
                'dia'        => $dia,
                'hora_inici' => $hores[$horaIdx],
                'aula_id'    => $aulaId,
            ];
            $projIdx++;
            $horaIdx++;
        }
    }

    // Queden sense slot si hi havia més projectes que franges totals
    while ($projIdx < $nProj) {
        $senseSlot[] = $projectes[$projIdx]['id'];
        $projIdx++;
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

// 1. Carregar la configuració de grups des de BD (aula i torn per cicle+grup)
try {
    $stmtGrups = $pdo->query("
        SELECT c.abr AS cicle,
               g.grupo,
               g.id_aula,
               g.torn
        FROM app.grupos g
        JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    ");
    $configGrups = $stmtGrups->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir la configuració de grups.']);
    exit;
}

// Índex: $grupConfig['DAM']['A'] = ['aula_id' => X, 'torn' => 'Tarda']
//         $grupConfig['DEV'][null] = ['aula_id' => Y, 'torn' => 'Tarda']  (grup null → clau '')
$grupConfig = [];
foreach ($configGrups as $g) {
    $cicle = strtoupper($g['cicle']);
    $grup  = ($g['grupo'] !== null && $g['grupo'] !== '') ? strtoupper($g['grupo']) : '';
    $grupConfig[$cicle][$grup] = [
        'aula_id' => (int)$g['id_aula'],
        'torn'    => $g['torn'], // 'Matí' o 'Tarda'
    ];
}

// 2. Carregar projectes
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
    'cicle'           => strtoupper($p['ciclo'] ?? ''),
    'grup'            => ($p['grupo'] !== null && $p['grupo'] !== '') ? strtoupper($p['grupo']) : '',
    'defensa_fecha'   => $p['defensa_fecha']   ?? null,
    'defensa_aula_id' => $p['defensa_aula_id'] ?? null,
], $tots);

if (!$sobreescriure) {
    $tots = array_values(array_filter($tots, fn($p) =>
        empty($p['defensa_fecha']) || empty($p['defensa_aula_id'])
    ));
}

// 3. Agrupar projectes per cicle+grup
$perGrup = []; // ['DAM']['A'] = [projectes...]
foreach ($tots as $p) {
    $perGrup[$p['cicle']][$p['grup']][] = $p;
}

// 4. Assignar cada grup
$assignats = [];
$senseSlot = [];
$senseConfig = []; // projectes sense configuració de grup a BD

foreach ($perGrup as $cicle => $grups) {
    foreach ($grups as $grup => $projectes) {
        if (!isset($grupConfig[$cicle][$grup])) {
            // No hi ha configuració per a aquest cicle+grup — deixem sense assignar
            foreach ($projectes as $p) {
                $senseConfig[] = $p['id'];
            }
            continue;
        }

        $gc   = $grupConfig[$cicle][$grup];
        $torn = (stripos($gc['torn'], 'mat') !== false) ? 'mati' : 'tarda';
        $res  = assignarGrup($projectes, $gc['aula_id'], $torn, $cfg);

        $assignats = array_merge($assignats, $res['assignats']);
        $senseSlot = array_merge($senseSlot, $res['sense_slot']);
    }
}

// 5. Guardar a la BD
try {
    $pdo->beginTransaction();

    // Pas 1: netejar assignacions anteriors dels projectes que reassignarem
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
    'ok'              => true,
    'assignats'       => count($assignats),
    'sense_slot'      => count($senseSlot),
    'ids_sense_slot'  => $senseSlot,
    'sense_config'    => count($senseConfig),
    'ids_sense_config' => $senseConfig,
]);
exit;
