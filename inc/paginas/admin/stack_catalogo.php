<?php
declare(strict_types=1);
soloSuperadmin();
if (!isset($stackCatalogo) || !is_array($stackCatalogo)) { http_response_code(500); exit; }
$tabla = $stackCatalogo['tabla']; $main = $stackCatalogo['main']; $titulo = $stackCatalogo['titulo'];
$stmt = $pdo->query("SELECT id,nombre,descripcion,url,activo,propuesto_en FROM app.$tabla WHERE propuesto_en IS NOT NULL ORDER BY propuesto_en,id");
$propostes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->query("SELECT id,nombre,descripcion,url,activo,propuesto_en FROM app.$tabla WHERE propuesto_en IS NULL ORDER BY nombre");
$catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
$error = $_SESSION[$main . '_error'] ?? ''; unset($_SESSION[$main . '_error']);
$renderTaula = static function(array $files, bool $propostes, string $main): void { ?>
<div class="card shadow-sm border-0 rounded-4 overflow-hidden">
 <div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th class="ps-4">Nom</th><th>Descripció</th><th>URL</th><th class="text-center"><?= $propostes ? 'Proposada' : 'Estat' ?></th><th class="text-end pe-4">Accions</th></tr></thead>
  <tbody><?php foreach($files as $fila): ?><tr>
   <td class="ps-4 fw-semibold"><?= htmlspecialchars((string)$fila['nombre'],ENT_QUOTES,'UTF-8') ?></td>
   <td><?= htmlspecialchars((string)($fila['descripcion']??''),ENT_QUOTES,'UTF-8') ?></td>
   <td><?php if(trim((string)($fila['url']??''))!==''): ?><a href="<?= htmlspecialchars((string)$fila['url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="text-break">Web oficial</a><?php endif; ?></td>
   <td class="text-center"><?php if($propostes): ?><span class="text-muted small"><?= htmlspecialchars(date('d/m/Y',strtotime((string)$fila['propuesto_en'])),ENT_QUOTES,'UTF-8') ?></span><?php else: ?><i class="bi <?= $fila['activo']?'bi-check-circle-fill text-success':'bi-x-circle-fill text-danger' ?>" aria-hidden="true"></i><span class="visually-hidden"><?= $fila['activo']?'Activa':'Inactiva' ?></span><?php endif; ?></td>
   <td class="text-end pe-4"><div class="btn-group btn-group-sm">
    <a class="btn btn-outline-primary" href="/index.php?main=<?= $main ?>_form&amp;id=<?= (int)$fila['id'] ?>">Editar</a>
    <form method="post" action="/index.php?main=<?= $main ?>_accion" class="d-inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(),ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$fila['id'] ?>"><input type="hidden" name="accio" value="<?= $propostes?'acceptar':'alternar_actiu' ?>"><button class="btn <?= $propostes?'btn-outline-success':'btn-outline-secondary' ?>" type="submit"><?= $propostes?'Acceptar':($fila['activo']?'Desactivar':'Activar') ?></button></form>
   </div></td>
  </tr><?php endforeach; ?><?php if(!$propostes && $files===[]): ?><tr><td colspan="5" class="text-center text-muted py-5">El catàleg és buit.</td></tr><?php endif; ?></tbody>
 </table></div>
</div><?php };
?>
<script>window.PAGE_TITLE=<?= json_encode($titulo,JSON_UNESCAPED_UNICODE) ?>;</script>
<div class="container-fluid py-4">
 <div class="mb-3"><h1 class="h3 mb-1"><?= htmlspecialchars($titulo,ENT_QUOTES,'UTF-8') ?></h1><p class="text-muted mb-0">Gestiona les entrades disponibles i les propostes pendents de moderació.</p></div>
 <?php if(is_string($error)&&$error!==''): ?><div class="alert alert-danger"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
 <?php if(isset($_GET['msg'])&&is_string($_GET['msg'])&&$_GET['msg']!==''): ?><div class="alert alert-success"><?= htmlspecialchars($_GET['msg'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
 <?php if($propostes!==[]): ?><section class="mb-4"><h2 class="h5 mb-3">Propostes</h2><?php $renderTaula($propostes,true,$main); ?></section><?php endif; ?>
 <section><h2 class="h5 mb-3"><?= htmlspecialchars($titulo,ENT_QUOTES,'UTF-8') ?></h2><?php $renderTaula($catalog,false,$main); ?></section>
</div>
