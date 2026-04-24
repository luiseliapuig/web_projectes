<?php
// planificacio_simular.php
// Rep JSON via POST. Retorna JSON amb resum de capacitat per grup. No modifica la BD.

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

// Nombre de franges disponibles per a un torn en un sol dia
function frangesDia(array $cfg, string $torn): int
{
    $inici   = strtotime('2000-01-01 ' . $cfg[$torn]['inici']);
    $fi      = strtotime('2000-01-01 ' . $cfg[$torn]['fi']);
    $duracio = $cfg['duracio'] * 60;
    if ($fi <= $inici || $duracio <= 0) return 0;
    return (int)(($fi - $inici) / $duracio);
}

$cfg           = parseConfig($input);
$sobreescriure = ($input['sobreescriure'] ?? '0') === '1';

$nDies         = count($cfg['dies']);
$frangesMati   = $nDies > 0 ? frangesDia($cfg, 'mati')  * $nDies : 0;
$frangesTarda  = $nDies > 0 ? frangesDia($cfg, 'tarda') * $nDies : 0;

// 1. Carregar configuració de grups des de BD
try {
    $stmtGrups = $pdo->query("
        SELECT c.abr AS cicle,
               g.grupo,
               g.id_aula,
               a.codigo AS aula_codi,
               g.torn
        FROM app.grupos g
        JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        JOIN app.aulas  a ON a.id_aula  = g.id_aula
    ");
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
    $stmt = $pdo->query("
        SELECT id_proyecto, ciclo, grupo, defensa_fecha, defensa_aula_id
        FROM app.proyectos
    ");
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
