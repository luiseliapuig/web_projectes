<?php
declare(strict_types=1);

solosuperadmin();

// La acción solo admite formularios POST autenticados y modos conocidos.
$peticionValida = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && validarTokenCsrf($_POST['csrf_token'] ?? null);
$modo = isset($_POST['modo']) && is_string($_POST['modo']) ? $_POST['modo'] : '';
$modoValido = in_array($modo, ['new', 'edit', 'delete'], true);
$idGrupo = (int)($_POST['id_grupo'] ?? 0);

try {

    if (!$peticionValida || !$modoValido) {
        throw new RuntimeException('invalid_request');
    }

    if ($modo === 'delete') {

        if ($idGrupo <= 0) {
            throw new Exception('ID de grupo no válido para borrar.');
        }

        // Una asignación docente convierte el grupo en parte del histórico.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM app.rel_profesores_grupos
            WHERE grupo_id = :id_grupo
        ");
        $stmt->execute([':id_grupo' => $idGrupo]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception('El grupo tiene asignaciones históricas.');
        }

        $stmt = $pdo->prepare("
            DELETE FROM app.grupos
            WHERE id_grupo = :id_grupo
        ");

        $stmt->execute([
            ':id_grupo' => $idGrupo
        ]);

        $msg = 'Grupo borrado correctamente.';

    } else {

        $familiaCicloId = (int)($_POST['familia_ciclo_id'] ?? 0);
        $idCiclo = (int)($_POST['id_ciclo'] ?? 0);
        $grupo = trim($_POST['grupo'] ?? '');
        $torn = trim($_POST['torn'] ?? 'Matí');
        $idAula = ($_POST['id_aula'] ?? '') !== '' ? (int)$_POST['id_aula'] : null;

        if ($familiaCicloId <= 0 || $idCiclo <= 0) {
            throw new Exception('Falten la família o el cicle.');
        }

        // La família es valida contra la FK del ciclo; no se confía en el selector del formulario.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM app.ciclos c
            INNER JOIN app.familias_ciclos f
                ON f.id_familia_ciclo = c.familia_ciclo_id
            WHERE c.id_ciclo = :id_ciclo
              AND f.id_familia_ciclo = :familia_ciclo_id
              AND (
                    (c.activo = true AND f.activo = true)
                    OR c.id_ciclo = (
                        SELECT g.id_ciclo
                        FROM app.grupos g
                        WHERE g.id_grupo = :id_grupo
                    )
              )
        ");
        $stmt->execute([
            ':id_ciclo' => $idCiclo,
            ':familia_ciclo_id' => $familiaCicloId,
            ':id_grupo' => $idGrupo,
        ]);

        if ((int) $stmt->fetchColumn() !== 1) {
            throw new Exception('La família i el cicle seleccionats no són vàlids.');
        }

        if ($grupo === '') {
            throw new Exception('Falta el grupo.');
        }

        if (!in_array($torn, ['Matí', 'Tarda'], true)) {
            throw new Exception('Torn no válido.');
        }

        if ($modo === 'edit') {

            if ($idGrupo <= 0) {
                throw new Exception('ID de grupo no válido para editar.');
            }

            $stmt = $pdo->prepare("
                UPDATE app.grupos
                SET
                    id_ciclo = :id_ciclo,
                    grupo = :grupo,
                    torn = :torn,
                    id_aula = :id_aula
                WHERE id_grupo = :id_grupo
            ");

            $stmt->execute([
                ':id_ciclo' => $idCiclo,
                ':grupo' => $grupo,
                ':torn' => $torn,
                ':id_aula' => $idAula,
                ':id_grupo' => $idGrupo
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('No se ha actualizado ningún registro. ID recibido: ' . $idGrupo);
            }

            $msg = 'Grupo actualizado correctamente.';

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO app.grupos (id_ciclo, grupo, torn, id_aula)
                VALUES (:id_ciclo, :grupo, :torn, :id_aula)
            ");

            $stmt->execute([
                ':id_ciclo' => $idCiclo,
                ':grupo' => $grupo,
                ':torn' => $torn,
                ':id_aula' => $idAula
            ]);

            $msg = 'Grupo creado correctamente.';
        }
    }

} catch (Throwable $e) {
    $msg = 'No s\'ha pogut completar l\'operació.';
}

$to = '/index.php?main=grups-cicle&msg=' . urlencode($msg);
echo '<script>location.href=' . json_encode($to) . ';</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url='
    . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '"></noscript>';
exit;
