<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once __DIR__ . '/fase-4_funcions.php';
require_once __DIR__ . '/fase-5_repositoris_funcions.php';

function fase5RepositorisResposta(int $codi, string $missatge, array $extra = []): never
{
    http_response_code($codi);
    echo json_encode(array_merge(['ok' => $codi < 400, 'missatge' => $missatge], $extra));
    exit;
}

function fase5RepositorisEtiqueta(mixed $valor, bool $obligatoria): string
{
    $etiqueta = is_string($valor) ? trim($valor) : '';
    if (($obligatoria && $etiqueta === '') || mb_strlen($etiqueta) > 80) {
        fase5RepositorisResposta(422, $obligatoria ? 'Introdueix una etiqueta breu.' : 'L’etiqueta és massa llarga.');
    }
    if ($etiqueta !== '' && preg_match('/^(?:repositori\s+git|github|gitlab)\b/iu', $etiqueta)) {
        fase5RepositorisResposta(422, 'Escriu només l’etiqueta, per exemple «Frontend» o «Backend».');
    }
    return $etiqueta;
}

if (!esAlumno()) fase5RepositorisResposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) {
    fase5RepositorisResposta(400, 'La sol·licitud no és vàlida o ha caducat.');
}

$proyectoId = (int) ($_POST['proyecto_id'] ?? 0);
if ($proyectoId <= 0 || !esSuProyectoAlumno($proyectoId)) fase5RepositorisResposta(403, 'No tens autorització sobre aquest projecte.');

$stmt = $pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo WHERE p.id_proyecto = :id');
$stmt->execute([':id' => $proyectoId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $stmt->fetchColumn() ?: null], 'informatica') || !fase4PlanificacioGestioObtenirEstat($pdo, $proyectoId)['completada']) {
    fase5RepositorisResposta(403, 'Accés no permès.');
}

$accio = is_string($_POST['accio'] ?? null) ? trim($_POST['accio']) : '';

if ($accio === 'guardar_principal') {
    $url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
    $etiqueta = fase5RepositorisEtiqueta($_POST['etiqueta'] ?? null, false);
    if ($url === '') {
        if ($etiqueta !== '') fase5RepositorisResposta(422, 'No pots desar una etiqueta sense enllaç.');
        $stmt = $pdo->prepare('UPDATE app.proyectos SET git_url = NULL, git_etiqueta = NULL WHERE id_proyecto = :id');
        $stmt->execute([':id' => $proyectoId]);
        fase5RepositorisResposta(200, 'Repositori principal eliminat.');
    }
    if (!fase5RepositoriUrlValida($url)) fase5RepositorisResposta(422, 'Introdueix una URL http o https vàlida.');
    $stmt = $pdo->prepare('UPDATE app.proyectos SET git_url = :url, git_etiqueta = :etiqueta WHERE id_proyecto = :id');
    $stmt->execute([':url' => $url, ':etiqueta' => $etiqueta !== '' ? $etiqueta : null, ':id' => $proyectoId]);
    fase5RepositorisResposta(200, 'Repositori principal desat.');
}

if ($accio === 'afegir') {
    $url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
    $etiqueta = fase5RepositorisEtiqueta($_POST['etiqueta'] ?? null, true);
    if (!fase5RepositoriUrlValida($url)) fase5RepositorisResposta(422, 'Introdueix una URL http o https vàlida.');
    $stmt = $pdo->prepare("INSERT INTO app.proyecto_adjuntos (proyecto_id, tipo, nom, ruta) VALUES (:id, 'git', :nom, :ruta) RETURNING id");
    $stmt->execute([':id' => $proyectoId, ':nom' => $etiqueta, ':ruta' => $url]);
    fase5RepositorisResposta(200, 'Repositori afegit.', ['id' => (int) $stmt->fetchColumn()]);
}

if ($accio === 'editar') {
    $id = (int) ($_POST['id'] ?? 0);
    $url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
    $etiqueta = fase5RepositorisEtiqueta($_POST['etiqueta'] ?? null, true);
    if ($id <= 0 || !fase5RepositoriUrlValida($url)) fase5RepositorisResposta(422, 'Dades incorrectes.');
    $stmt = $pdo->prepare("UPDATE app.proyecto_adjuntos SET nom = :nom, ruta = :ruta WHERE id = :adjunt AND proyecto_id = :projecte AND tipo = 'git'");
    $stmt->execute([':nom' => $etiqueta, ':ruta' => $url, ':adjunt' => $id, ':projecte' => $proyectoId]);
    if ($stmt->rowCount() !== 1) fase5RepositorisResposta(404, 'Repositori no trobat.');
    fase5RepositorisResposta(200, 'Repositori actualitzat.');
}

if ($accio === 'eliminar') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) fase5RepositorisResposta(422, 'Repositori no vàlid.');
    $stmt = $pdo->prepare("DELETE FROM app.proyecto_adjuntos WHERE id = :adjunt AND proyecto_id = :projecte AND tipo = 'git'");
    $stmt->execute([':adjunt' => $id, ':projecte' => $proyectoId]);
    if ($stmt->rowCount() !== 1) fase5RepositorisResposta(404, 'Repositori no trobat.');
    fase5RepositorisResposta(200, 'Repositori eliminat.');
}

fase5RepositorisResposta(422, 'Acció no reconeguda.');
