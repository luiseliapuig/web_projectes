<?php
declare(strict_types=1);

// recordatorio_worker.php — Genera els recordatoris setmanals d'Autoseguiment.
// Significat: "genera els recordatoris de la setmana actual si estem dins del
// període d'Autoseguiment". No sap ni li importa quin dia de la setmana és
// avui: la periodicitat de divendres la decideix el cron que l'invoqui, no
// aquest script (es pot executar manualment qualsevol dia per provar-lo).
//
// És un recordatori de calendari, no una alerta d'incompliment: s'encola per
// a tot l'alumnat dins del període vigent, independentment de si ja ha
// omplert total o parcialment el seguiment de la setmana. No s'inspecciona
// cap camp de contingut (trabajo_realizado, incidencias, objetivo_siguiente,
// cumplimiento_objetivo_anterior) per decidir l'enviament.
//
// Només encola a app.email_outbox; l'enviament SMTP real el fa, com sempre,
// inc/email/worker.php. No s'ha d'exposar mai per web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = require dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__) . '/funciones.php';
require_once __DIR__ . '/../email/bootstrap.php';
require_once __DIR__ . '/../email/templates/autoseguiment_recordatori.php';

// -----------------------------------------------------------------------------
// Petita utilitat local: nom de dia i mes en català a partir d'una data.
// No s'introdueix cap dependència nova (ni intl ni cap altra) únicament per
// formatar aquesta data; n'hi ha prou amb una taula fixa de 7 + 12 noms.
// -----------------------------------------------------------------------------

function autoseguimentRecordatoriDataCatalana(DateTimeImmutable $data): string
{
    $diesSetmana = ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];
    $mesos = [
        1 => ['gener', 'de'], 2 => ['febrer', 'de'], 3 => ['març', 'de'], 4 => ['abril', '’'],
        5 => ['maig', 'de'], 6 => ['juny', 'de'], 7 => ['juliol', 'de'], 8 => ['agost', '’'],
        9 => ['setembre', 'de'], 10 => ['octubre', '’'], 11 => ['novembre', 'de'], 12 => ['desembre', 'de'],
    ];
    $diaSetmana = $diesSetmana[(int) $data->format('w')];
    $dia = (int) $data->format('j');
    [$mes, $prep] = $mesos[(int) $data->format('n')];
    $prefix = $prep === '’' ? 'd’' : 'de ';

    return "{$diaSetmana} {$dia} {$prefix}{$mes}";
}

$zonaHoraria = new DateTimeZone('Europe/Madrid');

// -----------------------------------------------------------------------------
// 1. Període actiu de l'Autoseguiment (app.seguimiento_config, únic registre).
// Mateix criteri que la resta de l'aplicació: fecha_inicio <= avui <= fecha_fin.
// Fora d'aquest període: no s'encola res, s'acaba sense error.
// -----------------------------------------------------------------------------

$stmt = $pdo->query("SELECT fecha_inicio, fecha_fin FROM app.seguimiento_config WHERE id = 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    echo 'No hi ha cap configuració a app.seguimiento_config. No es genera cap recordatori.' . PHP_EOL;
    exit(0);
}

$avui = new DateTimeImmutable('now', $zonaHoraria);
$avuiStr = $avui->format('Y-m-d');

if ($avuiStr < (string) $config['fecha_inicio'] || $avuiStr > (string) $config['fecha_fin']) {
    echo "Avui ({$avuiStr}) queda fora del període configurat d’Autoseguiment. No es genera cap recordatori." . PHP_EOL;
    exit(0);
}

// Només informatiu per a la sortida CLI (setmana natural que conté avui).
$dillunsSetmanaActual = $avui->modify('monday this week');
$diumengeSetmanaActual = $dillunsSetmanaActual->modify('+6 days');

echo 'Autoseguiment recordatoris' . PHP_EOL;
echo 'Setmana actual: ' . $dillunsSetmanaActual->format('d/m/Y') . ' - ' . $diumengeSetmanaActual->format('d/m/Y') . PHP_EOL;

// -----------------------------------------------------------------------------
// 2. Destinataris: seguiments de la setmana actual (fecha_inicio <= avui <=
// fecha_fin, el mateix criteri de "setmana actual" que ja fan servir la vista
// de l'alumne i el tutor), restringits al curs acadèmic actual, a projectes
// actius i alumnat actiu, i confirmant que la relació alumne/projecte encara
// és real (rel_proyectos_alumnos) — els mateixos criteris que ja aplica
// inc/seguimiento/worker.php a l'hora de generar les files.
// -----------------------------------------------------------------------------

$cursoActual = cursoAcademicoActual();

$stmt = $pdo->prepare("
    SELECT sa.id_seguimiento, sa.proyecto_id, sa.fecha_fin, a.nombre, a.apellidos, a.email
    FROM app.seguimiento_alumnos sa
    INNER JOIN app.proyectos p ON p.id_proyecto = sa.proyecto_id
    INNER JOIN app.alumnos a ON a.id_alumno = sa.alumno_id
    INNER JOIN app.rel_proyectos_alumnos rpa ON rpa.proyecto_id = sa.proyecto_id AND rpa.alumno_id = sa.alumno_id
    WHERE sa.fecha_inicio <= CURRENT_DATE AND sa.fecha_fin >= CURRENT_DATE
      AND p.estado = 'activo'
      AND p.curso_academico = :curso_academico
      AND a.activo = true
      AND a.curso_academico = :curso_academico
    ORDER BY a.apellidos, a.nombre
");
$stmt->execute([':curso_academico' => $cursoActual]);
$destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Destinataris trobats: ' . count($destinatarios) . PHP_EOL;

if ($destinatarios === []) {
    echo 'Encolats: 0' . PHP_EOL;
    echo 'Ja existien: 0' . PHP_EOL;
    echo 'Omesos sense email: 0' . PHP_EOL;
    exit(0);
}

// -----------------------------------------------------------------------------
// 3. URL base: mateix patró que la resta de correus (APP_URL, HTTPS
// obligatori). L'enllaç apunta a la pantalla estable d'Autoseguiment de
// l'alumnat, ja existent a inc/router.php i a .htaccess.
// -----------------------------------------------------------------------------

$baseUrl = rtrim(trim((string) (getenv('APP_URL') ?: '')), '/');
if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !str_starts_with($baseUrl, 'https://')) {
    fwrite(STDERR, 'APP_URL debe ser una URL HTTPS válida.' . PHP_EOL);
    exit(1);
}
$urlAutoseguiment = $baseUrl . '/autoseguiment';

// -----------------------------------------------------------------------------
// 4. Encolat idempotent: una clau per fila de seguiment garanteix que
// reexecutar el procés (accidentalment o no) no duplica cap recordatori per
// al mateix alumne i la mateixa setmana.
// -----------------------------------------------------------------------------

$queue = new EmailQueue($pdo);
$encolados = 0;
$yaExistian = 0;
$omitidosSinEmail = 0;

foreach ($destinatarios as $fila) {
    $email = strtolower(trim((string) $fila['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $omitidosSinEmail++;
        continue;
    }

    $nombre = trim((string) $fila['nombre'] . ' ' . (string) $fila['apellidos']);
    $fechaFin = new DateTimeImmutable((string) $fila['fecha_fin'], $zonaHoraria);
    $dataLimitText = autoseguimentRecordatoriDataCatalana($fechaFin) . ' a les 23:59';
    $body = emailAutoseguimentRecordatori($nombre, $dataLimitText, $urlAutoseguiment);

    try {
        $id = $queue->enqueue([
            'destinatario' => $email,
            'nombre_destinatario' => $nombre,
            'asunto' => 'Autoseguiment setmanal',
            'cuerpo_html' => $body['html'],
            'cuerpo_texto' => $body['text'],
            'tipo' => 'autoseguiment_recordatori',
            'proyecto_id' => (int) $fila['proyecto_id'],
            'clave_idempotencia' => 'autoseguiment_recordatori:' . (int) $fila['id_seguimiento'],
        ]);
        if ($id > 0) {
            $encolados++;
        } else {
            $yaExistian++;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Error encolant el recordatori del seguiment #' . (int) $fila['id_seguimiento'] . ': ' . $e->getMessage() . PHP_EOL);
    }
}

echo 'Encolats: ' . $encolados . PHP_EOL;
echo 'Ja existien: ' . $yaExistian . PHP_EOL;
echo 'Omesos sense email: ' . $omitidosSinEmail . PHP_EOL;
exit(0);
