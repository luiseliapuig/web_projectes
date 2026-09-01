<?php
declare(strict_types=1);

// Ruta 'api' (vegeu index.php): respon en JSON i no renderitza el layout.
// Intervenció del tutor sobre la Proposta de projecte (Fase 2): validar-la
// formalment o tancar la sol·licitud oberta sense validar. Tancar no és
// rebutjar ni demanar canvis i no crea cap estat funcional nou.
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/fases/funciones.php';

if (!esProfesor()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'Accés no permès.']);
    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'missatge' => 'La sol·licitud no és vàlida o ha caducat.']);
    exit;
}

$accio = isset($_POST['accio']) && is_string($_POST['accio']) ? trim($_POST['accio']) : '';
$proyectoId = isset($_POST['proyecto_id']) ? (int) $_POST['proyecto_id'] : 0;

// L'autoritat formal sobre la proposta és sempre el TUTOR actual del
// projecte, mai un cotutor (esTutorDelProyecto() els tracta per igual i és
// correcte per a Autoseguiment/Memòria, però aquí calia la distinció
// singular; vegeu esTutorFormalDelProyecto() a inc/seguridad.php). Formar
// part del grup no n'hi ha prou, i tampoc ser-ne cotutor.
if ($proyectoId <= 0 || !esTutorFormalDelProyecto($proyectoId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'No tens autorització per intervenir en aquest projecte.']);
    exit;
}

// Aquesta acció és específica de la Fase 2 d'informatica'.
$stmtCiclo = $pdo->prepare("
    SELECT c.fases_clave
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE p.id_proyecto = :id
");
$stmtCiclo->execute([':id' => $proyectoId]);
$fasesClaveProyecto = $stmtCiclo->fetchColumn();
if (!proyectoPerteneceArquitecturaFases(['fases_clave' => $fasesClaveProyecto !== false ? $fasesClaveProyecto : null], 'informatica')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'missatge' => 'Accés no permès.']);
    exit;
}

// -----------------------------------------------------------------------------

if ($accio === 'tancar_solicitud') {
    try {
        $stmt = $pdo->prepare("\n            UPDATE app.revisiones_solicitudes rs
            SET resuelto_en = NOW()
            WHERE rs.proyecto_id = :id
              AND rs.tipo = 'proposta'
              AND rs.resuelto_en IS NULL
              AND EXISTS (
                  SELECT 1 FROM app.proyectos p
                  WHERE p.id_proyecto = rs.proyecto_id
                    AND p.propuesta_validada_en IS NULL
              )
        ");
        $stmt->execute([':id' => $proyectoId]);
        if ($stmt->rowCount() !== 1) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'missatge' => 'La sol·licitud ja no està oberta.']);
            exit;
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('Error tancant la sol·licitud de revisió de la proposta: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut tancar la sol·licitud.']);
    }
    exit;
}

// -----------------------------------------------------------------------------
// Validació formal: estableix propuesta_validada_en i resol qualsevol
// sol·licitud oberta corresponent, de manera transaccional. No es guarda cap
// "validado_por": l'autoritat és sempre el tutor actual, sense excepcions per
// a tutors anteriors.
// -----------------------------------------------------------------------------

if ($accio === 'validar') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE app.proyectos
            SET propuesta_validada_en = NOW()
            WHERE id_proyecto = :id AND propuesta_url IS NOT NULL AND propuesta_validada_en IS NULL
        ");
        $stmt->execute([':id' => $proyectoId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('no_validable');
        }

        $pdo->prepare("
            UPDATE app.revisiones_solicitudes
            SET resuelto_en = NOW()
            WHERE proyecto_id = :proyecto_id AND tipo = 'proposta' AND resuelto_en IS NULL
        ")->execute([':proyecto_id' => $proyectoId]);

        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(409);
        echo json_encode(['ok' => false, 'missatge' => 'La proposta ja estava validada o encara no té document viu.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error validant la proposta: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'missatge' => 'No s’ha pogut validar la proposta.']);
    }
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'missatge' => 'Acció no reconeguda.']);
