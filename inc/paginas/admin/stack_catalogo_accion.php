<?php
declare(strict_types=1);
soloSuperadmin();
if (!isset($stackCatalogo) || !is_array($stackCatalogo)) { http_response_code(500); exit; }
$tabla=$stackCatalogo['tabla'];$main=$stackCatalogo['main'];
$tornar=static function(string $msg='',bool $error=false)use($main):never{if($error)$_SESSION[$main.'_error']=$msg;$url='/index.php?main='.$main.(!$error&&$msg!==''?'&msg='.urlencode($msg):'');echo '<script>location.href='.json_encode($url).';</script><noscript><meta http-equiv="refresh" content="0;url='.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"></noscript>';exit;};
$accio=is_string($_POST['accio']??null)?$_POST['accio']:'';$id=(int)($_POST['id']??0);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!validarTokenCsrf($_POST['csrf_token']??null)||$id<=0||!in_array($accio,['editar','acceptar','alternar_actiu'],true))$tornar('La sol·licitud no és vàlida o ha caducat.',true);
if($accio==='editar'){
 $nom=trim(is_string($_POST['nombre']??null)?$_POST['nombre']:'');$descripcio=trim(is_string($_POST['descripcion']??null)?$_POST['descripcion']:'');$url=trim(is_string($_POST['url']??null)?$_POST['url']:'');
 $esUrlValida=$url===''||(filter_var($url,FILTER_VALIDATE_URL)!==false&&in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true));
 if($nom===''||mb_strlen($nom)>150||mb_strlen($url)>2048||!$esUrlValida)$tornar('Revisa el nom i la URL de l’entrada.',true);
 try{$stmt=$pdo->prepare("UPDATE app.$tabla SET nombre=:nom,descripcion=:descripcio,url=:url WHERE id=:id");$stmt->execute([':nom'=>$nom,':descripcio'=>$descripcio===''?null:$descripcio,':url'=>$url===''?null:$url,':id'=>$id]);if($stmt->rowCount()!==1)$tornar('Entrada no trobada.',true);}catch(PDOException $e){if($e->getCode()==='23505')$tornar('Ja existeix una entrada amb aquest nom.',true);error_log($e->getMessage());$tornar('No s’han pogut guardar les dades.',true);}
 $tornar('Entrada actualitzada correctament.');
}
if($accio==='acceptar'){$stmt=$pdo->prepare("UPDATE app.$tabla SET activo=true,propuesto_en=NULL WHERE id=:id AND propuesto_en IS NOT NULL");$stmt->execute([':id'=>$id]);if($stmt->rowCount()!==1)$tornar('La proposta ja no està pendent.',true);$tornar('Proposta acceptada correctament.');}
$stmt=$pdo->prepare("UPDATE app.$tabla SET activo=NOT activo WHERE id=:id AND propuesto_en IS NULL");$stmt->execute([':id'=>$id]);if($stmt->rowCount()!==1)$tornar('Entrada no trobada o pendent de moderació.',true);$tornar('Estat actualitzat correctament.');
