<?php
declare(strict_types=1);

soloSuperadmin();
require_once dirname(__DIR__, 2) . '/seguimiento/funciones.php';
$estadoActual = seguimientoEstadoActual($pdo, 'actual');
$estadoSiguiente = seguimientoEstadoActual($pdo, 'siguiente');
$historial = $pdo->query("SELECT ejecutado_en, origen, fecha_inicio, fecha_fin, numero_ejecucion,
    candidatos, creados, ya_existentes, errores, detalle_error FROM app.seguimiento_ejecuciones
    ORDER BY ejecutado_en DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$resultado = $_SESSION['autoseguiment_control_resultat'] ?? null;
$error = $_SESSION['autoseguiment_control_error'] ?? null;
unset($_SESSION['autoseguiment_control_resultat'], $_SESSION['autoseguiment_control_error']);
$fecha = static fn(string $v): string => (new DateTimeImmutable($v))->format('d/m/Y');
$fechaHora = static fn(string $v): string => (new DateTimeImmutable($v))->setTimezone(new DateTimeZone('Europe/Madrid'))->format('d/m/Y H:i');
?>
<script>window.PAGE_TITLE = 'Control d’autoseguiment';</script>
<style>
.autoseguiment-history-table tbody tr:last-child > td {
    padding-bottom: 1.5rem;
}
</style>
<div class="container-fluid px-4 py-4">
 <div class="mb-4"><h1 class="h3 mb-1">Control d’autoseguiment</h1><p class="text-muted mb-0">Estat real del període i historial recent del procés automàtic.</p></div>
 <?php if (is_array($resultado)): ?><div class="alert <?= (int)$resultado['errores'] > 0 ? 'alert-warning' : 'alert-success' ?>">Comprovació completada: <?= (int)$resultado['candidatos'] ?> candidats, <?= (int)$resultado['creados'] ?> creats, <?= (int)$resultado['ya_existentes'] ?> ja existents i <?= (int)$resultado['errores'] ?> errors.</div>
 <?php elseif (is_string($error) && $error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
 <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
  <h2 class="h5 mb-4">Estat actual</h2>
  <section>
   <div class="d-flex justify-content-between gap-3 mb-3"><div><h3 class="h6 mb-1">Setmana actual</h3><p class="text-muted mb-0"><?= htmlspecialchars($fecha($estadoActual['fecha_inicio']).' – '.$fecha($estadoActual['fecha_fin']), ENT_QUOTES, 'UTF-8') ?></p></div>
   <?php if ($estadoActual['disponible']): ?><span class="badge <?= $estadoActual['pendientes'] === 0 ? 'text-bg-success' : 'text-bg-warning' ?> rounded-pill align-self-start px-3 py-2"><?= $estadoActual['pendientes'] === 0 ? 'Tot correcte' : 'Atenció' ?></span><?php endif; ?></div>
   <?php if (!$estadoActual['disponible']): ?><div class="alert alert-warning"><?= htmlspecialchars((string)$estadoActual['detalle_error'], ENT_QUOTES, 'UTF-8') ?></div>
   <?php else: ?><div class="row g-3 mb-4">
    <?php foreach (['Candidats'=>count($estadoActual['candidatos']),'Existents'=>$estadoActual['existentes'],'Pendents'=>$estadoActual['pendientes']] as $etiqueta=>$valor): ?>
    <div class="col-6 col-lg"><div class="border rounded-3 p-3 h-100"><div class="text-muted small"><?= $etiqueta ?></div><div class="h4 mb-0"><?= (int)$valor ?></div></div></div><?php endforeach; ?>
   </div><?php endif; ?>
   <form method="post" action="/index.php?main=autoseguiment-control_accion"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="periodo" value="actual"><button class="btn btn-puig-solid" <?= !$estadoActual['disponible'] ? 'disabled' : '' ?>>Comprovar i crear seguiments de la setmana actual</button></form>
  </section>
  <section class="border-top mt-4 pt-4">
   <div class="d-flex justify-content-between gap-3 mb-3"><div><h3 class="h6 mb-1">Període a preparar</h3><p class="text-muted mb-0"><?= htmlspecialchars($fecha($estadoSiguiente['fecha_inicio']).' – '.$fecha($estadoSiguiente['fecha_fin']), ENT_QUOTES, 'UTF-8') ?></p></div>
   <?php if ($estadoSiguiente['disponible']): ?><span class="badge <?= $estadoSiguiente['pendientes'] === 0 ? 'text-bg-success' : 'text-bg-warning' ?> rounded-pill align-self-start px-3 py-2"><?= $estadoSiguiente['pendientes'] === 0 ? 'Tot correcte' : 'Atenció' ?></span><?php endif; ?></div>
   <?php if (!$estadoSiguiente['disponible']): ?><div class="alert alert-warning"><?= htmlspecialchars((string)$estadoSiguiente['detalle_error'], ENT_QUOTES, 'UTF-8') ?></div>
   <?php else: ?><div class="row g-3 mb-4">
    <?php foreach (['Candidats'=>count($estadoSiguiente['candidatos']),'Existents'=>$estadoSiguiente['existentes'],'Pendents'=>$estadoSiguiente['pendientes'],'Execucions'=>$estadoSiguiente['ejecuciones']] as $etiqueta=>$valor): ?>
    <div class="col-6 col-lg"><div class="border rounded-3 p-3 h-100"><div class="text-muted small"><?= $etiqueta ?></div><div class="h4 mb-0"><?= (int)$valor ?></div></div></div><?php endforeach; ?>
   </div><?php endif; ?>
   <form method="post" action="/index.php?main=autoseguiment-control_accion"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="periodo" value="siguiente"><button class="btn btn-puig-solid" <?= !$estadoSiguiente['disponible'] ? 'disabled' : '' ?>>Comprovar i crear seguiments pendents</button></form>
  </section>
  <p class="text-muted small mt-3 mb-0">La integritat dels seguiments i el nombre d’execucions són indicadors independents.</p>
 </div></div>
 <div class="card border-0 shadow-sm rounded-4 overflow-hidden"><div class="card-body p-4 border-bottom"><h2 class="h5 mb-1">Historial d’execucions</h2><p class="text-muted mb-0">Últimes 50 execucions registrades.</p></div><div class="table-responsive"><table class="table table-hover align-middle text-center mb-0 autoseguiment-history-table">
 <thead class="table-light"><tr><th class="ps-4">Data i hora</th><th>Període</th><th>Execució</th><th>Origen</th><th>Candidats</th><th>Creats</th><th>Ja existents</th><th class="pe-4">Errors</th></tr></thead><tbody>
 <?php if ($historial === []): ?><tr><td colspan="8" class="text-center text-muted py-5">Encara no hi ha execucions registrades.</td></tr>
 <?php else: foreach ($historial as $e): ?><tr class="<?= (int)$e['errores'] > 0 ? 'table-warning' : '' ?>"><td class="ps-4"><?= htmlspecialchars($fechaHora((string)$e['ejecutado_en']), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fecha((string)$e['fecha_inicio']).' – '.$fecha((string)$e['fecha_fin']), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$e['numero_ejecucion'] ?></td><td><span class="badge text-bg-light border"><?= $e['origen']==='manual'?'Manual':'Cron' ?></span></td><td><?= (int)$e['candidatos'] ?></td><td><?= (int)$e['creados'] ?></td><td><?= (int)$e['ya_existentes'] ?></td><td class="pe-4"><?= (int)$e['errores'] ?><?php if (is_string($e['detalle_error']) && $e['detalle_error']!==''): ?><details class="small mt-1"><summary>Veure detall</summary><div class="mt-1"><?= htmlspecialchars($e['detalle_error'], ENT_QUOTES, 'UTF-8') ?></div></details><?php endif; ?></td></tr><?php endforeach; endif; ?>
 </tbody></table></div></div>
</div>
