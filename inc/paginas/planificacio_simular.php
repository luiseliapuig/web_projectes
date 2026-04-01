<?php
// planificacio_simular.php
// Rep JSON via POST. Retorna JSON amb resum de capacitat. No modifica la BD.

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
            'aules' => $input['aules_mati']        ?? [],
        ],
        'tarda' => [
            'inici' => $input['hora_inici_tarda'] ?? '15:00',
            'fi'    => $input['hora_fi_tarda']    ?? '18:00',
            'aules' => $input['aules_tarda']      ?? [],
        ],
    ];
}

function esManyana(array $p): bool
{
    return strtoupper($p['cicle']) === 'SMX'
        && in_array(strtoupper($p['grup'] ?? ''), ['A', 'B'], true);
}

// Slots disponibles per aula en un torn (franges * dies)
function slotsPorAula(array $cfg, string $torn): int
{
    $inici   = strtotime('2000-01-01 ' . $cfg[$torn]['inici']);
    $fi      = strtotime('2000-01-01 ' . $cfg[$torn]['fi']);
    $duracio = $cfg['duracio'] * 60;
    if ($fi <= $inici || $duracio <= 0) return 0;
    $frangesDia = (int)(($fi - $inici) / $duracio);
    return $frangesDia * count($cfg['dies']);
}

$cfg           = parseConfig($input);
$sobreescriure = ($input['sobreescriure'] ?? '0') === '1';

// Carregar projectes
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
    'cicle'           => $p['ciclo']           ?? '',
    'grup'            => $p['grupo']           ?? '',
    'defensa_fecha'   => $p['defensa_fecha']   ?? null,
    'defensa_aula_id' => $p['defensa_aula_id'] ?? null,
], $tots);

if (!$sobreescriure) {
    $tots = array_values(array_filter($tots, fn($p) =>
        empty($p['defensa_fecha']) || empty($p['defensa_aula_id'])
    ));
}

// Agrupar per cicle
$ciclesMati  = [];
$ciclesTarda = [];
foreach ($tots as $p) {
    $clau = strtoupper($p['cicle']) . (trim($p['grup']) !== '' ? '_' . strtoupper($p['grup']) : '');
    if (esManyana($p)) {
        $ciclesMati[$clau][]  = $p;
    } else {
        $ciclesTarda[$clau][] = $p;
    }
}

$nCiclesMati  = count($ciclesMati);
$nCiclesTarda = count($ciclesTarda);
$nAulesMati   = count($cfg['mati']['aules']);
$nAulesTarda  = count($cfg['tarda']['aules']);
$slotsPerAula = slotsPorAula($cfg, 'mati');   // matí i tarda usen la mateixa duració
$slotsPerAulaTarda = slotsPorAula($cfg, 'tarda');

$projMati  = count(array_filter($tots, fn($p) =>  esManyana($p)));
$projTarda = count(array_filter($tots, fn($p) => !esManyana($p)));

// Slots totals disponibles (aules × franges × dies)
$slotsMati  = $nAulesMati  * $slotsPerAula;
$slotsTarda = $nAulesTarda * $slotsPerAulaTarda;

// Detectar problemes
$problemes = [];

if (count($cfg['dies']) === 0) {
    $problemes[] = "No s'han indicat dies de defensa.";
}
if ($nAulesMati === 0) {
    $problemes[] = 'No hi ha aules seleccionades per al torn de matí.';
}
if ($nAulesTarda === 0) {
    $problemes[] = 'No hi ha aules seleccionades per al torn de tarda.';
}

// Validació clau: prou aules per al nombre de cicles
if ($nCiclesMati > $nAulesMati) {
    $problemes[] = "Matí: {$nCiclesMati} cicles però només {$nAulesMati} aules — cal almenys {$nCiclesMati} aules.";
}
if ($nCiclesTarda > $nAulesTarda) {
    $problemes[] = "Tarda: {$nCiclesTarda} cicles però només {$nAulesTarda} aules — cal almenys {$nCiclesTarda} aules.";
}

// Validació secundària: prou franges per cicle
foreach ($ciclesMati as $clau => $projs) {
    if (count($projs) > $slotsPerAula) {
        $problemes[] = "Matí · {$clau}: {" . count($projs) . "} projectes però l'aula només té {$slotsPerAula} franges disponibles.";
    }
}
foreach ($ciclesTarda as $clau => $projs) {
    if (count($projs) > $slotsPerAulaTarda) {
        $problemes[] = "Tarda · {$clau}: " . count($projs) . " projectes però l'aula només té {$slotsPerAulaTarda} franges disponibles.";
    }
}

// Resum per cicle per mostrar al log
$resumCiclesMati  = array_map(fn($ps) => count($ps), $ciclesMati);
$resumCiclesTarda = array_map(fn($ps) => count($ps), $ciclesTarda);

echo json_encode([
    'ok'                 => count($problemes) === 0,
    'proj_mati'          => $projMati,
    'slots_mati'         => $slotsMati,
    'proj_tarda'         => $projTarda,
    'slots_tarda'        => $slotsTarda,
    'cicles_mati'        => $nCiclesMati,
    'cicles_tarda'       => $nCiclesTarda,
    'aules_mati'         => $nAulesMati,
    'aules_tarda'        => $nAulesTarda,
    'slots_per_aula_mati'  => $slotsPerAula,
    'slots_per_aula_tarda' => $slotsPerAulaTarda,
    'resum_cicles_mati'  => $resumCiclesMati,
    'resum_cicles_tarda' => $resumCiclesTarda,
    'problemes'          => $problemes,
]);
exit;
