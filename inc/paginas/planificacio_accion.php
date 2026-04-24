<?php
// planificacio_accion.php
// Algoritme: assignació vertical per minimitzar solapaments d'hora.
// - Cada cicle reinicia el cursor (dia 0, hora 0).
// - Dins d'un cicle, els grups s'encadenen: el grup B continua
//   des d'on ha deixat el grup A (mateix dia i hora).
// - L'aula de cada grup ve determinada per la BD (grupos → ciclos).

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

// Genera l'array de franges horàries per a un torn: ['09:00', '09:45', ...]
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

// Assignació vertical: omple el dia sencer abans de passar al següent.
// El cursor és circular: quan arriba a l'últim dia torna al dia 0.
// Això permet escalonar el punt d'inici sense perdre slots.
// S'atura quan ha donat una volta completa sense poder assignar (tots els slots plens).
// $cursor és passat per referència i queda apuntant a la següent franja lliure.
function assignarVertical(array $projectes, int $aulaId, string $torn, array $cfg, array &$cursor): array
{
    $assignats = [];
    $senseSlot = [];

    $dies      = $cfg['dies'];
    $hores     = generarHores($cfg, $torn);
    $nDies     = count($dies);
    $nHores    = count($hores);
    $totalSlots = $nDies * $nHores;

    // Comptador de slots usats per detectar quan s'han exhaurit tots
    $slotsUsats = $cursor['slotsUsats'] ?? 0;

    foreach ($projectes as $p) {
        if ($slotsUsats >= $totalSlots) {
            $senseSlot[] = $p['id'];
            continue;
        }

        $assignats[] = [
            'proj_id'    => $p['id'],
            'dia'        => $dies[$cursor['diaIdx']],
            'hora_inici' => $hores[$cursor['horaIdx']],
            'aula_id'    => $aulaId,
        ];

        $slotsUsats++;

        // Avançar cursor circular: primer hora (vertical), després dia, i torna al principi
        $cursor['horaIdx']++;
        if ($cursor['horaIdx'] >= $nHores) {
            $cursor['horaIdx'] = 0;
            $cursor['diaIdx']  = ($cursor['diaIdx'] + 1) % $nDies;
        }
    }

    $cursor['slotsUsats'] = $slotsUsats;

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

// 1. Carregar configuració de grups des de BD
try {
    $stmtGrups = $pdo->query("
        SELECT c.abr AS cicle,
               g.grupo,
               g.id_aula,
               g.torn
        FROM app.grupos g
        JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        ORDER BY c.abr, g.grupo
    ");
    $configGrups = $stmtGrups->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir la configuració de grups.']);
    exit;
}

// Índex: $grupConfig['DAM']['A'] = ['aula_id' => X, 'torn' => 'Tarda']
$grupConfig = [];
foreach ($configGrups as $g) {
    $cicle = strtoupper($g['cicle']);
    $grup  = ($g['grupo'] !== null && $g['grupo'] !== '') ? strtoupper($g['grupo']) : '';
    $grupConfig[$cicle][$grup] = [
        'aula_id' => (int)$g['id_aula'],
        'torn'    => $g['torn'],
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

// 3. Agrupar per cicle → grup, ambdós en ordre alfabètic
$perCicle = [];
foreach ($tots as $p) {
    $perCicle[$p['cicle']][$p['grup']][] = $p;
}
ksort($perCicle);
foreach ($perCicle as $cicle => &$grups) {
    ksort($grups);
}
unset($grups);

// 4. Assignar verticalment
$assignats   = [];
$senseSlot   = [];
$senseConfig = [];

$nDies    = count($cfg['dies']);
$cicleIdx = 0;

foreach ($perCicle as $cicle => $grups) {
    // Cada cicle comença en un dia diferent (rotació) per distribuir homogèniament
    $diaInici    = $nDies > 0 ? $cicleIdx % $nDies : 0;
    $cursorMati  = ['diaIdx' => $diaInici, 'horaIdx' => 0, 'slotsUsats' => 0];
    $cursorTarda = ['diaIdx' => $diaInici, 'horaIdx' => 0, 'slotsUsats' => 0];
    $cicleIdx++;

    foreach ($grups as $grup => $projectes) {
        if (!isset($grupConfig[$cicle][$grup])) {
            foreach ($projectes as $p) {
                $senseConfig[] = $p['id'];
            }
            continue;
        }

        $gc   = $grupConfig[$cicle][$grup];
        $torn = (stripos($gc['torn'], 'mat') !== false) ? 'mati' : 'tarda';

        // El cursor es passa per referència i queda on ha deixat el grup anterior
        // PHP no permet fer referència a una expressió ternària directament
        if ($torn === 'mati') {
            $res = assignarVertical($projectes, $gc['aula_id'], $torn, $cfg, $cursorMati);
        } else {
            $res = assignarVertical($projectes, $gc['aula_id'], $torn, $cfg, $cursorTarda);
        }

        $assignats = array_merge($assignats, $res['assignats']);
        $senseSlot = array_merge($senseSlot, $res['sense_slot']);
    }
}

// 5. Guardar a la BD
try {
    $pdo->beginTransaction();

    $ids = array_column($assignats, 'proj_id');
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("
            UPDATE app.proyectos
            SET defensa_fecha = NULL, defensa_aula_id = NULL
            WHERE id_proyecto IN ($ph)
        ")->execute($ids);
    }

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
    'ok'               => true,
    'assignats'        => count($assignats),
    'sense_slot'       => count($senseSlot),
    'ids_sense_slot'   => $senseSlot,
    'sense_config'     => count($senseConfig),
    'ids_sense_config' => $senseConfig,
]);
exit;
