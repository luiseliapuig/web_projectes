<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';

function fase5StackResposta(int $codi, string $missatge, array $extra = []): never
{
    http_response_code($codi);
    echo json_encode(array_merge(['ok' => $codi < 400, 'missatge' => $missatge], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!esAlumno()) fase5StackResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    fase5StackResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
}

$projecteId = (int) ($_POST['proyecto_id'] ?? 0);
if ($projecteId <= 0 || !esSuProyectoAlumno($projecteId)) fase5StackResposta(403, 'No tens autorització sobre aquest projecte.');
$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
$stmt->execute([':id' => $projecteId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $projecteId)['completada']) {
    fase5StackResposta(403, 'Accés no permès.');
}

$tipus = is_string($_POST['tipus'] ?? null) ? $_POST['tipus'] : '';
$configuracions = [
    'tecnologia' => ['cataleg' => 'app.tecnologias', 'relacio' => 'app.rel_proyectos_tecnologias', 'fk' => 'tecnologia_id'],
    'eina' => ['cataleg' => 'app.herramientas', 'relacio' => 'app.rel_proyectos_herramientas', 'fk' => 'herramienta_id'],
];
if (!isset($configuracions[$tipus])) fase5StackResposta(422, 'Tipus no reconegut.');
$config = $configuracions[$tipus];
$accio = is_string($_POST['accio'] ?? null) ? trim($_POST['accio']) : '';

if ($accio === 'cercar') {
    $cerca = is_string($_POST['cerca'] ?? null) ? trim($_POST['cerca']) : '';
    if ($cerca === '' || mb_strlen($cerca) > 150) fase5StackResposta(422, 'Escriu una cerca vàlida.');
    $sql = "SELECT c.id, c.nombre, c.descripcion, c.url
        FROM {$config['cataleg']} c
        WHERE c.activo = TRUE AND c.propuesto_en IS NULL
          AND c.nombre ILIKE :cerca
          AND NOT EXISTS (SELECT 1 FROM {$config['relacio']} r WHERE r.proyecto_id = :projecte AND r.{$config['fk']} = c.id)
        ORDER BY c.nombre LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cerca' => '%' . $cerca . '%', ':projecte' => $projecteId]);
    fase5StackResposta(200, '', ['resultats' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($accio === 'afegir') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) fase5StackResposta(422, 'Element no vàlid.');
    $sql = "INSERT INTO {$config['relacio']} (proyecto_id, {$config['fk']})
        SELECT :projecte, c.id FROM {$config['cataleg']} c
        WHERE c.id = :id AND c.activo = TRUE AND c.propuesto_en IS NULL
        ON CONFLICT DO NOTHING";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':projecte' => $projecteId, ':id' => $id]);
    if ($stmt->rowCount() === 0) fase5StackResposta(409, 'Aquest element no es pot afegir o ja està seleccionat.');
    fase5StackResposta(200, 'Element afegit.');
}

if ($accio === 'treure') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) fase5StackResposta(422, 'Element no vàlid.');
    $stmt = $pdo->prepare("DELETE FROM {$config['relacio']} WHERE proyecto_id = :projecte AND {$config['fk']} = :id");
    $stmt->execute([':projecte' => $projecteId, ':id' => $id]);
    if ($stmt->rowCount() === 0) fase5StackResposta(404, 'Element no trobat.');
    fase5StackResposta(200, 'Element eliminat.');
}

if ($accio === 'proposar') {
    $nom = is_string($_POST['nom'] ?? null) ? preg_replace('/\s+/u', ' ', trim($_POST['nom'])) : '';
    if (!is_string($nom) || mb_strlen($nom) < 2 || mb_strlen($nom) > 150) fase5StackResposta(422, 'Escriu un nom entre 2 i 150 caràcters.');
    try {
        $pdo->beginTransaction();
        $clau = $tipus . ':' . mb_strtolower($nom, 'UTF-8');
        $stmt = $pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:clau))');
        $stmt->execute([':clau' => $clau]);
        $sql = "SELECT id, nombre, activo, propuesto_en FROM {$config['cataleg']}
            WHERE lower(regexp_replace(trim(nombre), '\\s+', ' ', 'g')) = lower(:nom)
            ORDER BY id LIMIT 1 FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nom' => $nom]);
        $existent = $stmt->fetch(PDO::FETCH_ASSOC);
        $existentActiu = $existent && in_array($existent['activo'], [true, 1, '1', 't'], true);
        if ($existent && !$existentActiu && $existent['propuesto_en'] === null) {
            $pdo->rollBack();
            fase5StackResposta(409, 'Aquest element existeix, però no està disponible.');
        }
        if ($existent) {
            $id = (int) $existent['id'];
            $nomFinal = (string) $existent['nombre'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$config['cataleg']} (nombre, descripcion, url, activo, propuesto_en) VALUES (:nom, NULL, NULL, FALSE, NOW()) RETURNING id");
            $stmt->execute([':nom' => $nom]);
            $id = (int) $stmt->fetchColumn();
            $nomFinal = $nom;
        }
        $stmt = $pdo->prepare("INSERT INTO {$config['relacio']} (proyecto_id, {$config['fk']}) VALUES (:projecte, :id) ON CONFLICT DO NOTHING");
        $stmt->execute([':projecte' => $projecteId, ':id' => $id]);
        $relacioCreada = $stmt->rowCount() === 1;
        $pdo->commit();
        if (!$relacioCreada) fase5StackResposta(409, 'Aquest element ja està seleccionat.');
        fase5StackResposta(200, $existentActiu ? 'Element afegit.' : 'Proposta afegida i pendent de revisió.', ['id' => $id, 'nom' => $nomFinal, 'pendent' => !$existentActiu]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fase5StackResposta(500, 'No s’ha pogut desar la proposta.');
    }
}

fase5StackResposta(422, 'Acció no reconeguda.');
