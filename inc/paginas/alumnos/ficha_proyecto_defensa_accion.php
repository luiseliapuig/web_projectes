<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/pdf/funciones.php';

$idProyecto = (int) ($_POST['id_proyecto'] ?? 0);

// La acción vuelve a comprobar método, CSRF y propiedad antes de aceptar el PDF.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || $idProyecto <= 0
    || !esSuProyectoAlumno($idProyecto)
) {
    http_response_code(403);
    die('Sol·licitud no vàlida.');
}

/* =========================
   VALIDAR PROYECTO
========================= */

$stmt = $pdo->prepare("
    SELECT
        p.id_proyecto,
        p.curso_academico,
        c.abr AS ciclo,
        g.grupo AS grupo
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE p.id_proyecto = :id
");
$stmt->execute([
    ':id' => $idProyecto
]);

$proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proyecto) {
    die('Projecte no trobat.');
}

$nombreArchivo = 'presentacion-defensa-' . $idProyecto . '.pdf';
$archivo = $_FILES['presentacion_pdf'] ?? null;
if (!is_array($archivo)) {
    die('Error pujant el fitxer.');
}

$resultat = pdfGuardarDefinitiu(
    $archivo,
    (string) $proyecto['curso_academico'],
    (string) $proyecto['ciclo'],
    $idProyecto,
    $nombreArchivo
);
if (!$resultat['ok']) {
    die(htmlspecialchars($resultat['error'] ?? 'No s’ha pogut guardar el fitxer.', ENT_QUOTES, 'UTF-8'));
}

// Es conserva el format històric sense barra inicial perquè ficha.php ja la
// prefixa en construir l'enllaç públic.
$rutaBD = ltrim((string) $resultat['ruta_rel'], '/');

/* =========================
   GUARDAR EN BD
========================= */

$stmt = $pdo->prepare("
    UPDATE app.proyectos
    SET presentacion_pdf = :ruta
    WHERE id_proyecto = :id
");

$stmt->execute([
    ':ruta' => $rutaBD,
    ':id'   => $idProyecto
]);

/* =========================
   REDIRECCIÓN
========================= */

echo '<script>location.href=' . json_encode('/projecte/' . $idProyecto) . ';</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=/projecte/' . $idProyecto . '"></noscript>';
exit;
