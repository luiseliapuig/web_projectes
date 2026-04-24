<?php
solosuperadmin();

$modo = $_POST['modo'] ?? 'new';
$idGrupo = (int)($_POST['id_grupo'] ?? 0);

try {

    if ($modo === 'delete') {

        if ($idGrupo <= 0) {
            throw new Exception('ID de grupo no válido para borrar.');
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

        $idCiclo = (int)($_POST['id_ciclo'] ?? 0);
        $grupo = trim($_POST['grupo'] ?? '');
        $torn = trim($_POST['torn'] ?? 'Matí');
        $idAula = ($_POST['id_aula'] ?? '') !== '' ? (int)$_POST['id_aula'] : null;

        if ($idCiclo <= 0) {
            throw new Exception('Falta el ciclo.');
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
    $msg = 'Error: ' . $e->getMessage();
}

$to = '/index.php?main=grupos&msg=' . urlencode($msg);
echo '<script>location.href=' . json_encode($to) . ';</script>';
exit;