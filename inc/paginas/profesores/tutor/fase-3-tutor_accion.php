<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__,3).'/fases/funciones.php';
function fase3TutorResposta(int $c,string $m):never{http_response_code($c);echo json_encode(['ok'=>false,'missatge'=>$m]);exit;}
if(!esProfesor())fase3TutorResposta(403,'Accés no permès.');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!validarTokenCsrf($_POST['csrf_token']??null))fase3TutorResposta(400,'La sol·licitud no és vàlida o ha caducat.');
$id=(int)($_POST['proyecto_id']??0);$accio=is_string($_POST['accio']??null)?trim($_POST['accio']):'';
if($id<=0||!esTutorFormalDelProyecto($id))fase3TutorResposta(403,'No tens autorització per intervenir en aquest projecte.');
$stmt=$pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');$stmt->execute([':id'=>$id]);if(!proyectoPerteneceArquitecturaFases(['fases_clave'=>$stmt->fetchColumn()?:null],'informatica'))fase3TutorResposta(403,'Accés no permès.');
if ($accio === 'tancar_solicitud') {
    try {
        $stmt = $pdo->prepare("\n            UPDATE app.revisiones_solicitudes rs
            SET resuelto_en = NOW()
            WHERE rs.proyecto_id = :id
              AND rs.tipo = 'funcional'
              AND rs.resuelto_en IS NULL
              AND EXISTS (
                  SELECT 1 FROM app.proyectos p
                  WHERE p.id_proyecto = rs.proyecto_id
                    AND p.funcional_validado_en IS NULL
              )
        ");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            fase3TutorResposta(409, 'La sol·licitud ja no està oberta.');
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        error_log('Error tancant la sol·licitud de revisió del document funcional: ' . $e->getMessage());
        fase3TutorResposta(500, 'No s’ha pogut tancar la sol·licitud.');
    }
    exit;
}
if($accio!=='validar')fase3TutorResposta(422,'Acció no reconeguda.');
try{$pdo->beginTransaction();$stmt=$pdo->prepare("UPDATE app.proyectos SET funcional_validado_en=NOW() WHERE id_proyecto=:id AND funcional_url IS NOT NULL AND BTRIM(funcional_url)<>'' AND funcional_validado_en IS NULL");$stmt->execute([':id'=>$id]);if($stmt->rowCount()!==1){$pdo->rollBack();fase3TutorResposta(409,'El document no es pot validar en l’estat actual.');}$pdo->prepare("UPDATE app.revisiones_solicitudes SET resuelto_en=NOW() WHERE proyecto_id=:id AND tipo='funcional' AND resuelto_en IS NULL")->execute([':id'=>$id]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());fase3TutorResposta(500,'No s’ha pogut validar el document.');}
echo json_encode(['ok'=>true]);
