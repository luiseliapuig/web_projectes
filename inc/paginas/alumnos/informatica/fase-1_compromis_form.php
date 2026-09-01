<?php
declare(strict_types=1);

// El professorat consulta aquesta mateixa pàgina com a "visitant amb drets"
// (vegeu docs/codex/arquitectura.md): és una acció de CONSULTA — mai pot
// acceptar ni modificar el compromís de ningú —, disponible per a qualsevol
// professorat autoritzat sobre el projecte, no només el tutor formal.
if (!isset($proyectoAlumno)) {
    $contextoCursoActual = true;
    // projecte_context.php és transversal de tota l'àrea d'alumnat: es
    // referencia des del directori pare, no es mou a informatica/.
    if (!(require dirname(__DIR__) . '/projecte_context.php')) {
        return;
    }
}
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';

// L'accés directe a aquesta URL no és suficient: el projecte ha de pertànyer
// realment a l'arquitectura 'informatica' (proyecto → ciclo → fases_clau).
// Amagar l'enllaç a la navegació no protegeix per si sol.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
if (!proyectoPerteneceArquitecturaFases($proyectoAlumno, 'informatica')) {
    http_response_code(403);
    die('Accés no permès');
}

$proyectoId = (int) $proyectoAlumno['id_proyecto'];

if ($esAlumnat) {
    $alumnoIdReferencia = (int) $_SESSION['alumno_id'];
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, rpa.grupo_trabajo_confirmado_en,
               rpa.compromiso_trabajo_aceptado,
               a.nombre, a.apellidos,
               c.nombre AS ciclo_nombre
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE rpa.proyecto_id = :proyecto_id AND rpa.alumno_id = :alumno_id
        LIMIT 1
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoIdReferencia]);
} else {
    // Professorat: no hi ha "l'alumne actual" al qual referir-se. Aquesta
    // pàgina només és accessible al professorat quan «fase-1_contingut.php»
    // ja mostra el compromís acceptat per TOT el projecte, així que prendre
    // com a referència el primer membre (ordre estable nombre, apellidos)
    // és suficient per mostrar-ne el contingut i l'estat acceptat.
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, rpa.grupo_trabajo_confirmado_en,
               rpa.compromiso_trabajo_aceptado,
               a.nombre, a.apellidos,
               c.nombre AS ciclo_nombre
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE rpa.proyecto_id = :proyecto_id
        ORDER BY a.nombre, a.apellidos
        LIMIT 1
    ");
    $stmt->execute([':proyecto_id' => $proyectoId]);
}
$participacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participacion || $participacion['grupo_trabajo_confirmado_en'] === null) {
    $destino = $esAlumnat
        ? '/fases-del-projecte/fase-1'
        : '/projecte/' . $proyectoId . '/fases/fase-1';
    if ($esAlumnat) {
        $_SESSION['fase_1_compromis_error'] = 'Primer has de completar «Defineix el grup de treball».';
    }
    echo '<script>location.href=' . json_encode($destino) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

$compromisoAceptado = (bool) $participacion['compromiso_trabajo_aceptado'];
$nombreAlumno = trim((string) $participacion['nombre'] . ' ' . (string) $participacion['apellidos']);
$nombreCiclo = trim((string) $participacion['ciclo_nombre']);
$alumnoIdReferencia = (int) $participacion['id_alumno'];
$stmt = $pdo->prepare("
    SELECT a.nombre, a.apellidos
    FROM app.rel_proyectos_alumnos rpa
    INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
    WHERE rpa.proyecto_id = :proyecto_id
      AND rpa.alumno_id <> :alumno_id
    ORDER BY a.nombre, a.apellidos
");
$stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoIdReferencia]);
$companeros = array_map(
    static fn (array $fila): string => trim((string) $fila['nombre'] . ' ' . (string) $fila['apellidos']),
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);
$esProyectoEnPareja = $companeros !== [];
$nombreCompaneros = implode(' i ', $companeros);
$error = isset($_SESSION['fase_1_compromis_error']) && is_string($_SESSION['fase_1_compromis_error'])
    ? $_SESSION['fase_1_compromis_error']
    : '';
unset($_SESSION['fase_1_compromis_error']);

// Consolidat sobre la mateixa infraestructura/navegació que ja fa servir
// Fase 2 (fase_base.php: sidebar amb estats reals + breadcrumb comú), en
// lloc del "mode formulari" antic que bloquejava el sidebar
// ($fasesNavegacionBloqueada). ESTAT i SELECCIÓ són conceptes independents:
// entrar en aquesta tasca mai canvia el color de cap fase al sidebar.
$faseNumero = 1;
$faseTitulo = 'Compromís de treball';
$breadcrumbTasca = 'Compromís de treball';
$faseContenidoArchivo = __DIR__ . '/fase-1_compromis_contingut.php';
require __DIR__ . '/fase_base.php';
