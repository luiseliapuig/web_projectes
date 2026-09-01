<?php
declare(strict_types=1);
soloSuperadmin();
if (!isset($stackCatalogo) || !is_array($stackCatalogo)) { http_response_code(500); exit; }
$id=(int)($_GET['id']??0); $tabla=$stackCatalogo['tabla']; $main=$stackCatalogo['main'];
$stmt=$pdo->prepare("SELECT id,nombre,descripcion,url FROM app.$tabla WHERE id=:id");$stmt->execute([':id'=>$id]);$fila=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$fila){$_SESSION[$main.'_error']='Entrada no trobada.';$url='/index.php?main='.$main;echo '<script>location.href='.json_encode($url).';</script><noscript><meta http-equiv="refresh" content="0;url='.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"></noscript>';exit;}
$titol='Editar '.$stackCatalogo['singular'];
?>
<script>window.PAGE_TITLE=<?= json_encode($titol,JSON_UNESCAPED_UNICODE) ?>;</script>
<div class="container py-4"><h1 class="h3 mb-3"><?= htmlspecialchars($titol,ENT_QUOTES,'UTF-8') ?></h1><div class="card shadow-sm"><div class="card-body">
<form method="post" action="/index.php?main=<?= $main ?>_accion"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(),ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="accio" value="editar"><input type="hidden" name="id" value="<?= (int)$fila['id'] ?>">
 <div class="mb-3"><label class="form-label" for="nombre">Nom</label><input class="form-control" id="nombre" name="nombre" maxlength="150" required value="<?= htmlspecialchars((string)$fila['nombre'],ENT_QUOTES,'UTF-8') ?>"></div>
 <div class="mb-3"><label class="form-label" for="descripcion">Descripció</label><textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars((string)($fila['descripcion']??''),ENT_QUOTES,'UTF-8') ?></textarea></div>
 <div class="mb-3"><label class="form-label" for="url">URL oficial <span class="text-muted">(opcional)</span></label><input class="form-control" type="url" id="url" name="url" maxlength="2048" value="<?= htmlspecialchars((string)($fila['url']??''),ENT_QUOTES,'UTF-8') ?>"></div>
 <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Guardar</button><a class="btn btn-outline-secondary" href="/index.php?main=<?= $main ?>">Cancel·lar</a></div>
</form></div></div></div>
