<?php
declare(strict_types=1);

$permitirSinProyecto = true;
$contextoCursoActual = true;
// projecte_context.php és transversal de tota l'àrea d'alumnat: es
// referencia des del directori pare, no es mou a informatica/.
if (!(require dirname(__DIR__) . '/projecte_context.php')) {
    return;
}

// L'accés directe a aquesta URL no és suficient: el projecte/matrícula ha de
// pertànyer realment a l'arquitectura 'informatica' (proyecto → ciclo →
// fases_clave). Amagar l'enllaç a la navegació no protegeix per si sol.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
if (!proyectoPerteneceArquitecturaFases($proyectoAlumno, 'informatica')) {
    http_response_code(403);
    die('Accés no permès');
}

$alumnoId = (int) $_SESSION['alumno_id'];
$cursoAcademico = cursoAcademicoActual();
$grupoId = (int) ($proyectoAlumno['grupo_id'] ?? 0);
if ($grupoId <= 0) {
    $stmt = $pdo->prepare("
        SELECT grupo_id
        FROM app.rel_alumnos_grupos
        WHERE alumno_id = :alumno_id AND curso_academico = :curso_academico
        LIMIT 1
    ");
    $stmt->execute([':alumno_id' => $alumnoId, ':curso_academico' => $cursoAcademico]);
    $grupoId = (int) ($stmt->fetchColumn() ?: 0);
}

$proyectoId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$miembrosActuales = [];
$tareaConfirmada = false;
if ($proyectoId > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, a.nombre, a.apellidos,
               propio.grupo_trabajo_confirmado_en
        FROM app.rel_proyectos_alumnos propio
        INNER JOIN app.rel_proyectos_alumnos miembro
            ON miembro.proyecto_id = propio.proyecto_id
        INNER JOIN app.alumnos a ON a.id_alumno = miembro.alumno_id
        WHERE propio.proyecto_id = :proyecto_id
          AND propio.alumno_id = :alumno_id
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoId]);
    $miembrosActuales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tareaConfirmada = $miembrosActuales !== []
        && $miembrosActuales[0]['grupo_trabajo_confirmado_en'] !== null;
}

$companeroActualId = 0;
foreach ($miembrosActuales as $miembroActual) {
    if ((int) $miembroActual['id_alumno'] !== $alumnoId) {
        $companeroActualId = (int) $miembroActual['id_alumno'];
        break;
    }
}
$agrupacionRepresentable = count($miembrosActuales) <= 2;
$agrupacionPredefinida = $proyectoId > 0 && $miembrosActuales !== [];

// Solo se muestran compañeros del mismo grupo que estén libres o que ya
// compartan exactamente este proyecto de dos miembros con el alumno actual.
$stmt = $pdo->prepare("
    SELECT a.id_alumno, a.nombre, a.apellidos
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.alumnos a ON a.id_alumno = rag.alumno_id
    WHERE rag.grupo_id = :grupo_id
      AND rag.curso_academico = :curso_academico
      AND rag.alumno_id <> :alumno_id
      AND a.activo = true
      AND NOT EXISTS (
          SELECT 1
          FROM app.rel_proyectos_alumnos rpa_candidat
          INNER JOIN app.proyectos p_candidat
              ON p_candidat.id_proyecto = rpa_candidat.proyecto_id
          WHERE rpa_candidat.alumno_id = a.id_alumno
            AND p_candidat.curso_academico = :curso_academico
            AND p_candidat.estado = 'activo'
            AND (
                NOT EXISTS (
                    SELECT 1 FROM app.rel_proyectos_alumnos rpa_propi
                    WHERE rpa_propi.proyecto_id = p_candidat.id_proyecto
                      AND rpa_propi.alumno_id = :alumno_id
                )
                OR (SELECT COUNT(*) FROM app.rel_proyectos_alumnos rpa_membre
                    WHERE rpa_membre.proyecto_id = p_candidat.id_proyecto) <> 2
            )
      )
    ORDER BY a.nombre, a.apellidos
");
$stmt->execute([
    ':grupo_id' => $grupoId,
    ':curso_academico' => $cursoAcademico,
    ':alumno_id' => $alumnoId,
]);
$companerosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = isset($_SESSION['fase_1_grup_error']) && is_string($_SESSION['fase_1_grup_error'])
    ? $_SESSION['fase_1_grup_error']
    : '';
unset($_SESSION['fase_1_grup_error']);

// Consolidat sobre la mateixa infraestructura/navegació que ja fa servir
// Fase 2 (fase_base.php: sidebar amb estats reals + breadcrumb comú), en
// lloc del "mode formulari" antic que bloquejava el sidebar
// ($fasesNavegacionBloqueada). ESTAT i SELECCIÓ són conceptes independents:
// entrar en aquesta tasca mai canvia el color de cap fase al sidebar.
$faseNumero = 1;
$faseTitulo = 'Defineix el grup de treball';
$breadcrumbTasca = 'Defineix el grup de treball';
$faseContenidoArchivo = __DIR__ . '/fase-1_grup_contingut.php';
require __DIR__ . '/fase_base.php';
