<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
require_once dirname(__DIR__, 3) . '/pdf/funciones.php';
require_once __DIR__ . '/fase-2_proposta_funcions.php';
function fase3Resposta(int $codi, string $missatge): never { http_response_code($codi); echo json_encode(['ok'=>false,'missatge'=>$missatge]); exit; }
if (!esAlumno()) fase3Resposta(403, 'Accés no permès.');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf($_POST['csrf_token'] ?? null)) fase3Resposta(400, 'La sol·licitud no és vàlida o ha caducat.');
$accio = is_string($_POST['accio'] ?? null) ? trim($_POST['accio']) : '';
$proyectoId = (int) ($_POST['proyecto_id'] ?? 0);
if ($proyectoId <= 0 || !esSuProyectoAlumno($proyectoId)) fase3Resposta(403, 'No tens autorització sobre aquest projecte.');
$stmt=$pdo->prepare('SELECT c.fases_clave FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');$stmt->execute([':id'=>$proyectoId]);
if (!proyectoPerteneceArquitecturaFases(['fases_clave'=>$stmt->fetchColumn() ?: null], 'informatica') || !fase2PropostaObtenirEstat($pdo,$proyectoId)['completada']) fase3Resposta(403, 'Accés no permès.');

if ($accio === 'guardar_url') {
    $url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
    if ($url==='' || mb_strlen($url)>2048 || filter_var($url,FILTER_VALIDATE_URL)===false) fase3Resposta(422,'Introdueix una URL vàlida.');
    $stmt=$pdo->prepare('UPDATE app.proyectos SET funcional_url=:url WHERE id_proyecto=:id AND funcional_pdf IS NULL');$stmt->execute([':url'=>$url,':id'=>$proyectoId]);
    if($stmt->rowCount()!==1) fase3Resposta(409,'El document funcional ja és definitiu.');
    echo json_encode(['ok'=>true,'url'=>$url]);exit;
}
if ($accio === 'solicitar_revisio') {
    $stmt=$pdo->prepare('SELECT funcional_url,funcional_pdf,nombre FROM app.proyectos WHERE id_proyecto=:id');$stmt->execute([':id'=>$proyectoId]);$p=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    if(trim((string)($p['funcional_url']??''))==='') fase3Resposta(422,'Desa primer l’enllaç del document.');
    if(trim((string)($p['funcional_pdf']??''))!=='') fase3Resposta(409,'El document funcional ja és definitiu.');
    try {
        $stmt=$pdo->prepare("INSERT INTO app.revisiones_solicitudes(proyecto_id,tipo,referencia_id,titulo) VALUES(:id,'funcional',NULL,'Document funcional') ON CONFLICT (proyecto_id,tipo,COALESCE(referencia_id,0)) WHERE (resuelto_en IS NULL) DO NOTHING RETURNING id_revision_solicitud");$stmt->execute([':id'=>$proyectoId]);$rev=$stmt->fetchColumn();
        if($rev!==false){$stmt=$pdo->prepare("SELECT pr.nombre,pr.apellidos,pr.email FROM app.rel_proyectos_profesores r INNER JOIN app.profesores pr ON pr.id_profesor=r.profesor_id WHERE r.proyecto_id=:id AND r.rol='tutor' AND pr.activo=true LIMIT 1");$stmt->execute([':id'=>$proyectoId]);$t=$stmt->fetch(PDO::FETCH_ASSOC);
            if($t && filter_var($t['email'],FILTER_VALIDATE_EMAIL)){$stmt=$pdo->prepare('SELECT a.nombre,a.apellidos FROM app.rel_proyectos_alumnos r INNER JOIN app.alumnos a ON a.id_alumno=r.alumno_id WHERE r.proyecto_id=:id ORDER BY a.nombre,a.apellidos');$stmt->execute([':id'=>$proyectoId]);$al=implode(' / ',array_map(static fn($a)=>trim($a['nombre'].' '.$a['apellidos']),$stmt->fetchAll(PDO::FETCH_ASSOC)));try{require_once dirname(__DIR__,3).'/email/bootstrap.php';require_once dirname(__DIR__,3).'/email/templates/document_funcional_revisio_solicitada.php';$base=rtrim((string)(getenv('APP_URL')?:''),'/');if(filter_var($base,FILTER_VALIDATE_URL)&&str_starts_with($base,'https://')){$nom=trim($t['nombre'].' '.$t['apellidos']);$body=emailDocumentFuncionalRevisioSolicitada($nom,$al,(string)$p['nombre'],$base.'/projecte/'.$proyectoId.'/fases/fase-3/document-funcional');(new EmailQueue($pdo))->enqueue(['destinatario'=>$t['email'],'nombre_destinatario'=>$nom,'asunto'=>'Revisió del Document funcional','cuerpo_html'=>$body['html'],'cuerpo_texto'=>$body['text'],'tipo'=>'funcional_revisio_solicitada','proyecto_id'=>$proyectoId,'clave_idempotencia'=>'funcional_revisio:'.(int)$rev]);}}catch(Throwable $e){error_log($e->getMessage());}}
        }
    } catch(Throwable $e){error_log($e->getMessage());fase3Resposta(500,'No s’ha pogut sol·licitar la revisió.');}
    echo json_encode(['ok'=>true]);exit;
}
if ($accio === 'pujar_pdf') {
    $stmt=$pdo->prepare('SELECT p.funcional_validado_en,p.curso_academico,c.abr ciclo FROM app.proyectos p INNER JOIN app.grupos g ON g.id_grupo=p.grupo_id INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo WHERE p.id_proyecto=:id');$stmt->execute([':id'=>$proyectoId]);$p=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$p || $p['funcional_validado_en']===null) fase3Resposta(409,'Encara no s’ha validat el document funcional.');
    $file=$_FILES['pdf']??null;if(!is_array($file)) fase3Resposta(422,'Error en la pujada del fitxer.');
    $res=pdfGuardarDefinitiu($file,(string)$p['curso_academico'],(string)$p['ciclo'],$proyectoId,'document-funcional.pdf');if(!$res['ok']) fase3Resposta(422,$res['error']??'No s’ha pogut guardar el fitxer.');
    $ruta=$res['ruta_rel'].'?v='.time();$stmt=$pdo->prepare('UPDATE app.proyectos SET funcional_pdf=:ruta WHERE id_proyecto=:id AND funcional_validado_en IS NOT NULL');$stmt->execute([':ruta'=>$ruta,':id'=>$proyectoId]);if($stmt->rowCount()!==1) fase3Resposta(409,'No s’ha pogut desar el document.');echo json_encode(['ok'=>true,'ruta'=>$ruta]);exit;
}
fase3Resposta(422,'Acció no reconeguda.');
