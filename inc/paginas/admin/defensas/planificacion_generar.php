<?php
// planificacio_accion.php
// Algoritmo de reparto equilibrado entre los días seleccionados.
// Cada grupo conserva su aula y turno; los descansos no generan franjas y
// nunca se asignan más de tres defensas globales a la misma fecha y hora.

declare(strict_types=1);

soloSuperadmin();

$input = json_decode(file_get_contents('php://input'), true);
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !is_array($input)
    || !validarTokenCsrf($input['csrf_token'] ?? null)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'Dades invàlides.']);
    exit;
}

$cursoAcademico = isset($input['curso_academico']) && is_string($input['curso_academico'])
    ? trim($input['curso_academico'])
    : '';
$familiaCicloId = (int) ($input['familia_ciclo_id'] ?? 0);
$diesRebuts = array_values(array_filter([
    $input['dia1'] ?? '', $input['dia2'] ?? '', $input['dia3'] ?? '',
    $input['dia4'] ?? '', $input['dia5'] ?? '',
], static fn($dia): bool => is_string($dia) && $dia !== ''));
$horesRebudes = [
    $input['hora_inici_mati'] ?? '', $input['hora_fi_mati'] ?? '',
    $input['hora_inici_tarda'] ?? '', $input['hora_fi_tarda'] ?? '',
];
$descansosRebuts = [
    $input['descans_inici_mati'] ?? '', $input['descans_fi_mati'] ?? '',
    $input['descans_inici_tarda'] ?? '', $input['descans_fi_tarda'] ?? '',
];
$duracioRebuda = (int) ($input['duracio_franja'] ?? 0);
$datesValides = array_filter($diesRebuts, static function ($dia): bool {
    if (!is_string($dia)) {
        return false;
    }
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);
    return $data !== false && $data->format('Y-m-d') === $dia;
});
$horesValides = array_filter(array_merge($horesRebudes, $descansosRebuts), static function ($hora): bool {
    if (!is_string($hora)) {
        return false;
    }
    $temps = DateTimeImmutable::createFromFormat('!H:i', $hora);
    return $temps !== false && $temps->format('H:i') === $hora;
});

if (
    $cursoAcademico !== cursoAcademicoDefensas()
    || $familiaCicloId <= 0
    || count($diesRebuts) < 1
    || count($diesRebuts) > 5
    || count(array_unique($diesRebuts)) !== count($diesRebuts)
    || count($datesValides) !== count($diesRebuts)
    || count($horesValides) !== 8
    || $horesRebudes[1] <= $horesRebudes[0]
    || $horesRebudes[3] <= $horesRebudes[2]
    || $descansosRebuts[1] <= $descansosRebuts[0]
    || $descansosRebuts[3] <= $descansosRebuts[2]
    || $descansosRebuts[0] < $horesRebudes[0]
    || $descansosRebuts[1] > $horesRebudes[1]
    || $descansosRebuts[2] < $horesRebudes[2]
    || $descansosRebuts[3] > $horesRebudes[3]
    || $duracioRebuda < 20
    || $duracioRebuda > 90
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'Revisa el curs, els dies i els horaris.']);
    exit;
}

$stmtFamilia = $pdo->prepare("SELECT 1 FROM app.familias_ciclos WHERE id_familia_ciclo = :id AND activo = true");
$stmtFamilia->execute([':id' => $familiaCicloId]);
if (!$stmtFamilia->fetchColumn()) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'missatge' => 'La família professional no és vàlida.']);
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
            $input['dia4'] ?? '',
            $input['dia5'] ?? '',
        ])),
        'duracio' => max(20, (int)($input['duracio_franja'] ?? 45)),
        'mati' => [
            'inici' => $input['hora_inici_mati']  ?? '08:30',
            'fi'    => $input['hora_fi_mati']      ?? '11:30',
            'descans_inici' => $input['descans_inici_mati'] ?? '11:10',
            'descans_fi'    => $input['descans_fi_mati'] ?? '11:40',
        ],
        'tarda' => [
            'inici' => $input['hora_inici_tarda'] ?? '15:00',
            'fi'    => $input['hora_fi_tarda']    ?? '18:00',
            'descans_inici' => $input['descans_inici_tarda'] ?? '18:00',
            'descans_fi'    => $input['descans_fi_tarda'] ?? '18:20',
        ],
    ];
}

// Genera l'array de franges horàries per a un torn: ['09:00', '09:45', ...]
function generarHores(array $cfg, string $torn): array
{
    $durSecs   = $cfg['duracio'] * 60;
    $ref_inici = strtotime('2000-01-01 ' . $cfg[$torn]['inici']);
    $ref_fi    = strtotime('2000-01-01 ' . $cfg[$torn]['fi']);
    $descansInici = strtotime('2000-01-01 ' . $cfg[$torn]['descans_inici']);
    $descansFi = strtotime('2000-01-01 ' . $cfg[$torn]['descans_fi']);

    $hores = [];
    $trams = [[$ref_inici, $descansInici], [$descansFi, $ref_fi]];
    foreach ($trams as [$iniciTram, $fiTram]) {
        for ($t = $iniciTram; $t + $durSecs <= $fiTram; $t += $durSecs) {
            $hores[] = date('H:i', $t);
        }
    }
    return $hores;
}

// El cursor recorre primero los días y después las horas para repartir cada
// grupo homogéneamente. La ocupación compartida aplica los límites globales.
function assignarEquilibrat(
    array $projectes,
    int $aulaId,
    string $torn,
    array $cfg,
    array &$cursor,
    array &$ocupacioGlobal,
    array &$ocupacioAules
): array
{
    $assignats = [];
    $senseSlot = [];

    $dies      = $cfg['dies'];
    $hores     = generarHores($cfg, $torn);
    $nDies     = count($dies);
    $totalSlots = $nDies * count($hores);

    foreach ($projectes as $p) {
        $trobat = false;
        $posicioInicial = $cursor['posicio'] ?? 0;

        for ($intent = 0; $intent < $totalSlots; $intent++) {
            $posicio = ($posicioInicial + $intent) % $totalSlots;
            $horaIdx = intdiv($posicio, $nDies);
            $diaIdx = $posicio % $nDies;
            $diaHora = $dies[$diaIdx] . ' ' . $hores[$horaIdx];
            $clauAula = $diaHora . '|' . $aulaId;

            if (($ocupacioGlobal[$diaHora] ?? 0) >= 3 || isset($ocupacioAules[$clauAula])) {
                continue;
            }

            $assignats[] = [
                'proj_id' => $p['id'],
                'dia' => $dies[$diaIdx],
                'hora_inici' => $hores[$horaIdx],
                'aula_id' => $aulaId,
            ];
            $ocupacioGlobal[$diaHora] = ($ocupacioGlobal[$diaHora] ?? 0) + 1;
            $ocupacioAules[$clauAula] = true;
            $cursor['posicio'] = ($posicio + 1) % $totalSlots;
            $trobat = true;
            break;
        }

        if (!$trobat) {
            $senseSlot[] = $p['id'];
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

// 1. Carregar configuració de grups des de BD
try {
    $stmtGrups = $pdo->prepare("
        SELECT c.abr AS cicle,
               g.grupo,
               g.id_aula,
               g.torn
        FROM app.grupos g
        JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE g.id_aula IS NOT NULL
          AND c.familia_ciclo_id = :familia_id
        ORDER BY c.abr, g.grupo
    ");
    $stmtGrups->execute([':familia_id' => $familiaCicloId]);
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
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, c.abr AS ciclo, g.grupo, p.defensa_fecha, p.defensa_aula_id
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE p.curso_academico = :curso_academico
          AND c.familia_ciclo_id = :familia_id
    ");
    $stmt->execute([
        ':curso_academico' => $cursoAcademico,
        ':familia_id' => $familiaCicloId,
    ]);
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

$ocupacioGlobal = [];
$ocupacioAules = [];
$condicionOcupacio = $sobreescriure
    ? 'AND c.familia_ciclo_id IS DISTINCT FROM :familia_id'
    : '';
$stmtOcupacio = $pdo->prepare("
    SELECT p.defensa_fecha, p.defensa_aula_id
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE p.curso_academico = :curso_academico
      AND p.defensa_fecha IS NOT NULL
      AND p.defensa_aula_id IS NOT NULL
      $condicionOcupacio
");
$parametrosOcupacio = [':curso_academico' => $cursoAcademico];
if ($sobreescriure) {
    $parametrosOcupacio[':familia_id'] = $familiaCicloId;
}
$stmtOcupacio->execute($parametrosOcupacio);
foreach ($stmtOcupacio->fetchAll(PDO::FETCH_ASSOC) as $projecteExistent) {
    $diaHora = substr((string) $projecteExistent['defensa_fecha'], 0, 16);
    $ocupacioGlobal[$diaHora] = ($ocupacioGlobal[$diaHora] ?? 0) + 1;
    $ocupacioAules[$diaHora . '|' . (int) $projecteExistent['defensa_aula_id']] = true;
}

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
    $cursorMati  = ['posicio' => $diaInici];
    $cursorTarda = ['posicio' => $diaInici];
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
            $res = assignarEquilibrat(
                $projectes,
                $gc['aula_id'],
                $torn,
                $cfg,
                $cursorMati,
                $ocupacioGlobal,
                $ocupacioAules
            );
        } else {
            $res = assignarEquilibrat(
                $projectes,
                $gc['aula_id'],
                $torn,
                $cfg,
                $cursorTarda,
                $ocupacioGlobal,
                $ocupacioAules
            );
        }

        $assignats = array_merge($assignats, $res['assignats']);
        $senseSlot = array_merge($senseSlot, $res['sense_slot']);
    }
}

// 5. Guardar a la BD
try {
    $pdo->beginTransaction();

    $ids = $sobreescriure
        ? array_column($tots, 'id')
        : array_column($assignats, 'proj_id');
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("
            UPDATE app.proyectos
            SET defensa_fecha = NULL, defensa_aula_id = NULL
            WHERE curso_academico = ?
              AND id_proyecto IN ($ph)
        ")->execute(array_merge([$cursoAcademico], $ids));
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE app.proyectos
        SET defensa_fecha   = :defensa_fecha,
            defensa_aula_id = :defensa_aula_id
        WHERE id_proyecto   = :id
          AND curso_academico = :curso_academico
    ");

    foreach ($assignats as $a) {
        $stmtUpdate->execute([
            'defensa_fecha'   => $a['dia'] . ' ' . $a['hora_inici'] . ':00',
            'defensa_aula_id' => $a['aula_id'],
            'id'              => $a['proj_id'],
            'curso_academico' => $cursoAcademico,
        ]);
    }

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Error generant planificació de defenses: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut guardar la planificació.']);
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
