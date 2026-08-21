<?php
// planificacio_simular.php
// Rep JSON via POST. Retorna JSON amb resum de capacitat per grup. No modifica la BD.

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

// Nombre de franges disponibles per a un torn en un sol dia
function frangesDia(array $cfg, string $torn): int
{
    $inici = strtotime('2000-01-01 ' . $cfg[$torn]['inici']);
    $fi = strtotime('2000-01-01 ' . $cfg[$torn]['fi']);
    $descansInici = strtotime('2000-01-01 ' . $cfg[$torn]['descans_inici']);
    $descansFi = strtotime('2000-01-01 ' . $cfg[$torn]['descans_fi']);
    $duracio = $cfg['duracio'] * 60;
    if ($fi <= $inici || $duracio <= 0) return 0;
    return max(0, intdiv($descansInici - $inici, $duracio))
        + max(0, intdiv($fi - $descansFi, $duracio));
}

$cfg           = parseConfig($input);
$sobreescriure = ($input['sobreescriure'] ?? '0') === '1';

$nDies         = count($cfg['dies']);
$frangesMati   = $nDies > 0 ? frangesDia($cfg, 'mati')  * $nDies : 0;
$frangesTarda  = $nDies > 0 ? frangesDia($cfg, 'tarda') * $nDies : 0;

// 1. Carregar configuració de grups des de BD
try {
    $stmtGrups = $pdo->prepare("
        SELECT c.abr AS cicle,
               g.grupo,
               g.id_aula,
               a.codigo AS aula_codi,
               g.torn
        FROM app.grupos g
        JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        JOIN app.aulas  a ON a.id_aula  = g.id_aula
        WHERE c.familia_ciclo_id = :familia_id
    ");
    $stmtGrups->execute([':familia_id' => $familiaCicloId]);
    $configGrups = $stmtGrups->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir la configuració de grups.']);
    exit;
}

// Índex: $grupConfig['DAM']['A'] = ['aula_id'=>X, 'aula_codi'=>'INF01', 'torn'=>'Tarda']
$grupConfig = [];
foreach ($configGrups as $g) {
    $cicle = strtoupper($g['cicle']);
    $grup  = ($g['grupo'] !== null && $g['grupo'] !== '') ? strtoupper($g['grupo']) : '';
    $grupConfig[$cicle][$grup] = [
        'aula_id'   => (int)$g['id_aula'],
        'aula_codi' => $g['aula_codi'],
        'torn'      => $g['torn'],
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
    echo json_encode(['ok' => false, 'missatge' => 'Error en llegir projectes.']);
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

// 3. Agrupar per cicle+grup
$perGrup = [];
foreach ($tots as $p) {
    $perGrup[$p['cicle']][$p['grup']][] = $p;
}

// 4. Analitzar cada grup
$problemes   = [];
$resumGrups  = []; // per al log del client
$totalMati   = 0;
$totalTarda  = 0;
$senseConfig = 0;
$projectesPerAula = ['mati' => [], 'tarda' => []];

foreach ($perGrup as $cicle => $grups) {
    foreach ($grups as $grup => $projectes) {
        $etiqueta = $grup !== '' ? "{$cicle} {$grup}" : $cicle;
        $nProj    = count($projectes);

        if (!isset($grupConfig[$cicle][$grup])) {
            $problemes[]  = "{$etiqueta}: sense configuració d'aula a la BD (grup no trobat a 'grupos').";
            $senseConfig += $nProj;
            continue;
        }

        $gc        = $grupConfig[$cicle][$grup];
        $esMati    = (stripos($gc['torn'], 'mat') !== false);
        $franges   = $esMati ? $frangesMati : $frangesTarda;
        $tornLabel = $esMati ? 'Matí' : 'Tarda';

        if ($esMati) $totalMati  += $nProj;
        else         $totalTarda += $nProj;
        $clauTorn = $esMati ? 'mati' : 'tarda';
        $projectesPerAula[$clauTorn][$gc['aula_id']] =
            ($projectesPerAula[$clauTorn][$gc['aula_id']] ?? 0) + $nProj;

        $resumGrups[] = [
            'grup'      => $etiqueta,
            'torn'      => $tornLabel,
            'aula'      => $gc['aula_codi'],
            'projectes' => $nProj,
            'slots'     => $franges,
            'ok'        => $nProj <= $franges,
        ];

        if ($nProj > $franges) {
            $problemes[] = "{$etiqueta} ({$tornLabel} · {$gc['aula_codi']}): {$nProj} projectes però només {$franges} franges disponibles.";
        }
    }
}

// Validacions globals
if ($nDies === 0) {
    $problemes[] = "No s'han indicat dies de defensa.";
}
if (frangesDia($cfg, 'mati') === 0 && $totalMati > 0) {
    $problemes[] = 'El torn de matí no genera franges amb els horaris indicats.';
}
if (frangesDia($cfg, 'tarda') === 0 && $totalTarda > 0) {
    $problemes[] = 'El torn de tarda no genera franges amb els horaris indicats.';
}
if ($totalMati > $frangesMati * 3) {
    $problemes[] = "El torn de matí supera el límit global de tres tribunals simultanis ({$totalMati} projectes per " . ($frangesMati * 3) . ' places).';
}
if ($totalTarda > $frangesTarda * 3) {
    $problemes[] = "El torn de tarda supera el límit global de tres tribunals simultanis ({$totalTarda} projectes per " . ($frangesTarda * 3) . ' places).';
}
foreach ($projectesPerAula['mati'] as $aulaId => $totalAula) {
    if ($totalAula > $frangesMati) {
        $problemes[] = "L'aula #{$aulaId} del torn de matí acumula {$totalAula} projectes per {$frangesMati} franges.";
    }
}
foreach ($projectesPerAula['tarda'] as $aulaId => $totalAula) {
    if ($totalAula > $frangesTarda) {
        $problemes[] = "L'aula #{$aulaId} del torn de tarda acumula {$totalAula} projectes per {$frangesTarda} franges.";
    }
}

// Slots totals (informatiu)
// No és un límit real perquè cada grup té la seva aula pròpia,
// però el client vol veure projectes vs franges totals per torn.
$slotsMati  = $frangesMati;   // per grup, no acumulat — el client mostrarà el detall
$slotsTarda = $frangesTarda;

echo json_encode([
    'ok'           => count($problemes) === 0,
    'proj_mati'    => $totalMati,
    'slots_mati'   => $slotsMati,
    'proj_tarda'   => $totalTarda,
    'slots_tarda'  => $slotsTarda,
    'resum_grups'  => $resumGrups,   // detall per grup per al log
    'sense_config' => $senseConfig,
    'problemes'    => $problemes,
]);
exit;
