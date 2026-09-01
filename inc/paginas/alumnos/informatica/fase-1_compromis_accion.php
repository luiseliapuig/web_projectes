<?php
declare(strict_types=1);

// Endpoint específic de l'arquitectura 'informatica': no carrega
// projecte_context.php (no li correspon, és una acció d'escriptura pura,
// sense render), però sí necessita el gate d'arquitectura.
require_once dirname(__DIR__, 3) . '/fases/funciones.php';

$redirigir = static function (string $destino): never {
    echo '<script>location.href=' . json_encode($destino) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

$alumnoId = (int) ($_SESSION['alumno_id'] ?? 0);
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || $alumnoId <= 0
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || ($_POST['accepto_compromis'] ?? '') !== '1'
) {
    $_SESSION['fase_1_compromis_error'] = 'Has de llegir i acceptar el compromís abans de confirmar.';
    $redirigir('/fases-del-projecte/fase-1/compromis');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        SELECT rpa.proyecto_id, rpa.grupo_trabajo_confirmado_en, c.fases_clave
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE rpa.alumno_id = :alumno_id
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
        ORDER BY p.id_proyecto DESC
        LIMIT 1
        FOR UPDATE OF rpa, p
    ");
    $stmt->execute([
        ':alumno_id' => $alumnoId,
        ':curso_academico' => cursoAcademicoActual(),
    ]);
    $participacion = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$participacion || $participacion['grupo_trabajo_confirmado_en'] === null) {
        throw new DomainException('dependencia');
    }

    // Gate d'arquitectura ABANS de qualsevol escriptura: aquesta acció és
    // específica de la Fase 1 d''informatica'; si el cicle real del projecte
    // no hi pertany (NULL, una altra arquitectura o una clau desconeguda),
    // es talla aquí. No n'hi ha prou amb amagar l'enllaç a la navegació.
    if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $participacion['fases_clave']], 'informatica')) {
        $pdo->rollBack();
        http_response_code(403);
        die('Accés no permès');
    }

    $stmt = $pdo->prepare("
        UPDATE app.rel_proyectos_alumnos
        SET compromiso_trabajo_aceptado = true
        WHERE proyecto_id = :proyecto_id AND alumno_id = :alumno_id
    ");
    $stmt->execute([
        ':proyecto_id' => (int) $participacion['proyecto_id'],
        ':alumno_id' => $alumnoId,
    ]);
    $pdo->commit();

    $_SESSION['fase_1_compromis_mensaje'] = 'El compromís de treball s’ha acceptat correctament.';
    $redirigir('/fases-del-projecte/fase-1');
} catch (DomainException) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['fase_1_compromis_error'] = 'Primer has de completar «Defineix el grup de treball».';
    $redirigir('/fases-del-projecte/fase-1');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error acceptant el compromís de treball: ' . $e->getMessage());
    $_SESSION['fase_1_compromis_error'] = 'No s’ha pogut guardar el compromís. Torna-ho a provar.';
    $redirigir('/fases-del-projecte/fase-1/compromis');
}
