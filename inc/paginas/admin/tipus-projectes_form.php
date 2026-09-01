<?php
declare(strict_types=1);

soloSuperadmin();

// Formulario único para alta, edición y borrado de tipos.
$modo = isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : 'new';
$modo = in_array($modo, ['new', 'edit', 'delete'], true) ? $modo : 'new';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tipo = ['id_tipo_proyecto' => 0, 'categoria_proyecto_id' => 0, 'nombre' => '', 'activo' => true, 'orden' => 1];

if ($modo !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM app.proyecto_tipos WHERE id_tipo_proyecto = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['tipus_projectes_error'] = 'Tipus no trobat.';
        echo '<script>location.href="/index.php?main=tipus-projectes";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=/index.php?main=tipus-projectes"></noscript>';
        exit;
    }
    $tipo = $row;
}

// El tipo se elige entre las categorías activas; en edición se conserva la
// categoría actual aunque esté desactivada. Cada opción muestra "Família >
// Categoria" para no generar ambigüedad entre categories del mateix nom.
$stmt = $pdo->prepare("
    SELECT cp.id_categoria_proyecto, cp.nombre AS categoria, f.nombre AS familia
    FROM app.proyecto_categorias cp
    INNER JOIN app.familias_ciclos f ON f.id_familia_ciclo = cp.familia_ciclo_id
    WHERE cp.activo = true OR cp.id_categoria_proyecto = :categoria_actual
    ORDER BY f.orden, f.nombre, cp.orden, cp.nombre
");
$stmt->execute([':categoria_actual' => (int) $tipo['categoria_proyecto_id']]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($modo === 'new' && $categorias !== []) {
    $tipo['categoria_proyecto_id'] = (int) $categorias[0]['id_categoria_proyecto'];
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM app.proyecto_tipos WHERE categoria_proyecto_id = :categoria_id");
    $stmt->execute([':categoria_id' => $tipo['categoria_proyecto_id']]);
    $tipo['orden'] = (int) $stmt->fetchColumn();
}
$titulo = match ($modo) {'edit' => 'Editar tipus', 'delete' => 'Borrar tipus', default => 'Nou tipus'};
?>

<script>window.PAGE_TITLE = '<?= $titulo ?>';</script>
<div class="container py-4"><h1 class="h3 mb-3"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1><div class="card shadow-sm"><div class="card-body">
    <form method="post" action="/index.php?main=tipus-projectes_accion">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="modo" value="<?= htmlspecialchars($modo, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_tipo_proyecto" value="<?= (int) $tipo['id_tipo_proyecto'] ?>">
        <?php if ($modo === 'delete'): ?><div class="alert alert-danger">Segur que vols borrar aquest tipus?</div><?php endif; ?>
        <div class="row">
            <div class="col-md-4 mb-3"><label for="categoria_proyecto_id" class="form-label">Categoria</label><select id="categoria_proyecto_id" name="categoria_proyecto_id" class="form-select" required <?= $modo === 'delete' ? 'disabled' : '' ?>><?php foreach ($categorias as $categoria): ?><option value="<?= (int) $categoria['id_categoria_proyecto'] ?>" <?= (int) $tipo['categoria_proyecto_id'] === (int) $categoria['id_categoria_proyecto'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $categoria['familia'] . ' > ' . (string) $categoria['categoria'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6 mb-3"><label for="nombre" class="form-label">Nom</label><input id="nombre" name="nombre" class="form-control" maxlength="120" required value="<?= htmlspecialchars((string) $tipo['nombre'], ENT_QUOTES, 'UTF-8') ?>" <?= $modo === 'delete' ? 'disabled' : '' ?>></div>
            <div class="col-md-2 mb-3"><label for="orden" class="form-label">Ordre</label><input id="orden" name="orden" type="number" min="1" max="32767" class="form-control" required value="<?= (int) $tipo['orden'] ?>" <?= $modo === 'delete' ? 'disabled' : '' ?>></div>
        </div>
        <?php if ($modo !== 'delete'): ?><div class="form-check mb-3"><input id="activo" name="activo" type="checkbox" class="form-check-input" value="1" <?= (bool) $tipo['activo'] ? 'checked' : '' ?>><label for="activo" class="form-check-label">Actiu</label></div><?php endif; ?>
        <div class="d-flex gap-2"><button class="btn <?= $modo === 'delete' ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= $modo === 'delete' ? 'Sí, borrar' : 'Guardar' ?></button><a href="/index.php?main=tipus-projectes" class="btn btn-outline-secondary">Cancel·lar</a></div>
    </form>
</div></div></div>
