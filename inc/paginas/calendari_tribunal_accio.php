<?php
// calendari_tribunal_accio.php
// Endpoint raw — gestiona apuntar i desapuntar professors al tribunal.
// Rep JSON via php://input (com el fitxer anterior). Retorna JSON.
// Crida: POST /index.php?main=calendari_tribunal_accio&raw=1
//
// Accions:
//   accio=apuntar        → apunta el professor actual
//   accio=desapuntar     → desapunta (target_profesor_id opcional per superadmin)
//   accio=apuntar_admin  → superadmin apunta un professor concret (requereix target_profesor_id)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['professor_id'])) {
    echo json_encode(['ok' => false, 'missatge' => 'Sessió no vàlida']);
    exit;
}

$profId      = (int)$_SESSION['professor_id'];
$esSuperadm  = esSuperadmin();

// Rep JSON (manté compatibilitat amb el patró anterior)
$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$accio     = trim($input['accio']      ?? '');
$projecteId = (int)($input['proj_id'] ?? $input['proyecto_id'] ?? 0);

if (!$projecteId) {
    echo json_encode(['ok' => false, 'missatge' => 'Projecte no especificat']);
    exit;
}

// Verificar que el projecte existeix i té defensa planificada
$stmtProj = $pdo->prepare("
    SELECT id_proyecto FROM app.proyectos
    WHERE id_proyecto = ? AND defensa_fecha IS NOT NULL AND defensa_aula_id IS NOT NULL
");
$stmtProj->execute([$projecteId]);
if (!$stmtProj->fetch()) {
    echo json_encode(['ok' => false, 'missatge' => 'Projecte no trobat o sense defensa planificada']);
    exit;
}

try {
    switch ($accio) {

        // ── Apuntar professor actual ───────────────────────────────────
        case 'apuntar': {
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE id_proyecto = ?");
            $stmtC->execute([$projecteId]);
            if ((int)$stmtC->fetchColumn() >= 3) {
                echo json_encode(['ok' => false, 'tribunal_ple' => true, 'missatge' => 'El tribunal ja té 3 membres']);
                exit;
            }

            $stmtEx = $pdo->prepare("SELECT 1 FROM app.rel_profesores_tribunal WHERE id_proyecto = ? AND profesor_id = ?");
            $stmtEx->execute([$projecteId, $profId]);
            if ($stmtEx->fetch()) {
                echo json_encode(['ok' => false, 'missatge' => 'Ja estàs apuntat a aquest tribunal']);
                exit;
            }

            // Comprovar solapament horari: el professor no pot estar a dos tribunals
            // el mateix dia a la mateixa hora
            $stmtSolap = $pdo->prepare("
                SELECT p2.nombre
                FROM app.rel_profesores_tribunal rpt
                JOIN app.proyectos p2 ON p2.id_proyecto = rpt.id_proyecto
                JOIN app.proyectos p1 ON p1.id_proyecto = ?
                WHERE rpt.profesor_id = ?
                  AND p2.defensa_fecha = p1.defensa_fecha
            ");
            $stmtSolap->execute([$projecteId, $profId]);
            $solapament = $stmtSolap->fetch(PDO::FETCH_ASSOC);
            if ($solapament) {
                $nomProj = $solapament['nombre'] ?? 'un altre projecte';
                echo json_encode(['ok' => false, 'missatge' => "Ja estàs apuntat a «{$nomProj}» a la mateixa hora"]);
                exit;
            }

            $pdo->prepare("INSERT INTO app.rel_profesores_tribunal (id_proyecto, profesor_id) VALUES (?, ?)")
                ->execute([$projecteId, $profId]);

            echo json_encode(['ok' => true, 'accio' => 'apuntat']);
            break;
        }

        // ── Desapuntar ────────────────────────────────────────────────
        case 'desapuntar': {
            // target_profesor_id: el prof actual per defecte; superadmin pot indicar un altre
            $targetId = isset($input['target_profesor_id']) ? (int)$input['target_profesor_id'] : $profId;
            // compatibilitat amb clau antiga
            if (isset($input['profesor_id']) && $esSuperadm) {
                $targetId = (int)$input['profesor_id'];
            }

            if ($targetId !== $profId && !$esSuperadm) {
                echo json_encode(['ok' => false, 'missatge' => 'Sense permís']);
                exit;
            }

            $pdo->prepare("DELETE FROM app.rel_profesores_tribunal WHERE id_proyecto = ? AND profesor_id = ?")
                ->execute([$projecteId, $targetId]);

            echo json_encode(['ok' => true, 'accio' => 'desapuntat']);
            break;
        }

        // ── Apuntar qualsevol professor (superadmin) ──────────────────
        case 'apuntar_admin': {
            if (!$esSuperadm) {
                echo json_encode(['ok' => false, 'missatge' => 'Sense permís de superadmin']);
                exit;
            }

            $targetId = (int)($input['target_profesor_id'] ?? 0);
            if (!$targetId) {
                echo json_encode(['ok' => false, 'missatge' => 'Professor no especificat']);
                exit;
            }

            $stmtP = $pdo->prepare("SELECT 1 FROM app.profesores WHERE id_profesor = ? AND activo = true");
            $stmtP->execute([$targetId]);
            if (!$stmtP->fetch()) {
                echo json_encode(['ok' => false, 'missatge' => 'Professor no trobat']);
                exit;
            }

            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM app.rel_profesores_tribunal WHERE id_proyecto = ?");
            $stmtC->execute([$projecteId]);
            if ((int)$stmtC->fetchColumn() >= 3) {
                echo json_encode(['ok' => false, 'tribunal_ple' => true, 'missatge' => 'El tribunal ja té 3 membres']);
                exit;
            }

            $stmtEx = $pdo->prepare("SELECT 1 FROM app.rel_profesores_tribunal WHERE id_proyecto = ? AND profesor_id = ?");
            $stmtEx->execute([$projecteId, $targetId]);
            if ($stmtEx->fetch()) {
                echo json_encode(['ok' => false, 'missatge' => 'El professor ja és al tribunal']);
                exit;
            }

            // Comprovar solapament horari
            $stmtSolap = $pdo->prepare("
                SELECT p2.nombre
                FROM app.rel_profesores_tribunal rpt
                JOIN app.proyectos p2 ON p2.id_proyecto = rpt.id_proyecto
                JOIN app.proyectos p1 ON p1.id_proyecto = ?
                WHERE rpt.profesor_id = ?
                  AND p2.defensa_fecha = p1.defensa_fecha
            ");
            $stmtSolap->execute([$projecteId, $targetId]);
            $solapament = $stmtSolap->fetch(PDO::FETCH_ASSOC);
            if ($solapament) {
                $nomProj = $solapament['nombre'] ?? 'un altre projecte';
                echo json_encode(['ok' => false, 'missatge' => "El professor ja està apuntat a «{$nomProj}» a la mateixa hora"]);
                exit;
            }

            $pdo->prepare("INSERT INTO app.rel_profesores_tribunal (id_proyecto, profesor_id) VALUES (?, ?)")
                ->execute([$projecteId, $targetId]);

            echo json_encode(['ok' => true, 'accio' => 'apuntat_admin']);
            break;
        }

        default:
            echo json_encode(['ok' => false, 'missatge' => 'Acció desconeguda']);
    }

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error intern: ' . $e->getMessage()]);
}
exit;
