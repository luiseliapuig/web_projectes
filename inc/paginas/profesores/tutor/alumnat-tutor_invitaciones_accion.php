<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}
$profesorId=(int)$_SESSION['professor_id'];
$curso=isset($_POST['curso']) && is_string($_POST['curso']) ? trim($_POST['curso']) : '';
$cicloId=isset($_POST['ciclo_id']) ? (int)$_POST['ciclo_id'] : 0;
$grupoId=isset($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : 0;
$alumnoId=isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
$returnCicloId=isset($_POST['return_ciclo_id']) ? max(0,(int)$_POST['return_ciclo_id']) : $cicloId;
$returnGrupoId=isset($_POST['return_grupo_id']) ? max(0,(int)$_POST['return_grupo_id']) : $grupoId;
$redirigir=static function(string $curso,int $cicloId,int $grupoId):never {
    if(!preg_match('/^[0-9]{4}-[0-9]{2}$/',$curso))$curso=cursoAcademicoActual();
    $url='/index.php?main=alumnat-tutor&curso='.rawurlencode($curso).'&ciclo_id='.$cicloId.'&grupo_id='.$grupoId;
    echo '<script>location.href='.json_encode($url).';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"></noscript>';
    exit;
};
if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || !validarTokenCsrf($_POST['csrf_token']??null) || !preg_match('/^[0-9]{4}-[0-9]{2}$/',$curso) || $grupoId<=0){
    $_SESSION['alumnat_tutor_error']='La sol·licitud no és vàlida o ha caducat.';
    $redirigir($curso,$returnCicloId,$returnGrupoId);
}
$stmt=$pdo->prepare("
    SELECT g.id_ciclo FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo=rpg.grupo_id
    WHERE rpg.profesor_id=:profesor_id AND rpg.grupo_id=:grupo_id AND rpg.curso_academico=:curso
    LIMIT 1
");
$stmt->execute([':profesor_id'=>$profesorId,':grupo_id'=>$grupoId,':curso'=>$curso]);
$cicloAutorizado=(int)($stmt->fetchColumn() ?: 0);
if($cicloAutorizado<=0){
    $_SESSION['alumnat_tutor_error']='No tens permís per enviar invitacions a aquest grup.';
    $redirigir($curso,$returnCicloId,$returnGrupoId);
}
$cicloId=$cicloAutorizado;
$sql="
    SELECT a.id_alumno, a.activo, a.password_hash
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.alumnos a ON a.id_alumno=rag.alumno_id
    WHERE rag.grupo_id=:grupo_id AND rag.curso_academico=:curso
";
$params=[':grupo_id'=>$grupoId,':curso'=>$curso];
if($alumnoId>0){
    $sql.=' AND a.id_alumno=:alumno_id';
    $params[':alumno_id']=$alumnoId;
}
$sql.=' ORDER BY a.id_alumno';
$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$alumnosInvitables=$stmt->fetchAll(PDO::FETCH_ASSOC);
if($alumnoId>0 && $alumnosInvitables===[]){
    $_SESSION['alumnat_tutor_error']='No tens permís per convidar aquest alumne.';
    $redirigir($curso,$returnCicloId,$returnGrupoId);
}
$alumnoIds=[];
foreach($alumnosInvitables as $alumnoInvitable){
    $activoAlumno=in_array($alumnoInvitable['activo'],[true,1,'1','t'],true);
    if($activoAlumno && (!is_string($alumnoInvitable['password_hash']) || $alumnoInvitable['password_hash']==='')){
        $alumnoIds[]=(int)$alumnoInvitable['id_alumno'];
    }
}
if($alumnoIds===[]){
    $_SESSION['alumnat_tutor_notice']=$alumnoId>0
        ? 'Aquest alumne ja té contrasenya o no està actiu.'
        : 'No hi ha alumnat actiu pendent de crear la contrasenya en aquest grup.';
    $redirigir($curso,$returnCicloId,$returnGrupoId);
}
$enviadas=0;
$fallidas=0;
try {
    require_once dirname(__DIR__,3).'/email/bootstrap.php';
    $invitacion=new StudentInvitation($pdo,new EmailService(EmailConfig::fromEnvironment()));
    foreach($alumnoIds as $alumnoId){
        try {
            $invitacion->send($alumnoId);
            $enviadas++;
        } catch(Throwable $e){
            $fallidas++;
            error_log('No se pudo enviar la invitación colectiva al alumno #'.$alumnoId.': '.$e->getMessage());
        }
    }
} catch(Throwable $e){
    $fallidas=count($alumnoIds);
    error_log('No se pudo iniciar el envío colectivo de invitaciones: '.$e->getMessage());
}
if($fallidas>0){
    $_SESSION['alumnat_tutor_warning']=$enviadas>0
        ? 'S’han enviat '.$enviadas.' invitacions, però '.$fallidas.' han fallat.'
        : 'No s’han pogut enviar les invitacions del grup.';
} else {
    $_SESSION['alumnat_tutor_notice']=$enviadas===1 ? 'S’ha enviat una invitació.' : 'S’han enviat '.$enviadas.' invitacions.';
}
$redirigir($curso,$returnCicloId,$returnGrupoId);
