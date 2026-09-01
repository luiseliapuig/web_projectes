<?php
declare(strict_types=1);

soloSuperadmin();

// Formulari únic per a alta, edició i borrat d'apartats de memòria.
$modo = isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : 'new';
$modo = in_array($modo, ['new', 'edit', 'delete'], true) ? $modo : 'new';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$apartat = [
    'id_memoria_estructura' => 0,
    'categoria_proyecto_id' => 0,
    'titulo' => '',
    'descripcion' => '',
    'enlace_guia' => '',
    'activo' => true,
];

if ($modo !== 'new') {
    $stmt = $pdo->prepare("
        SELECT id_memoria_estructura, categoria_proyecto_id, titulo, descripcion, enlace_guia, activo
        FROM app.memoria_estructura
        WHERE id_memoria_estructura = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['memoria_estructura_error'] = 'Apartat no trobat.';
        echo '<script>location.href="/index.php?main=memoria-estructura";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=/index.php?main=memoria-estructura"></noscript>';
        exit;
    }
    $apartat = $row;
}

// Categories vàlides per al selector únic Família > Categoria: actives, més
// la categoria ja assignada encara que estigui desactivada (mateix criteri
// que ja fa servir tipus-projectes_form.php). La família és només context
// visual dins de l'etiqueta de l'opció; el value és sempre id_categoria_proyecto.
$stmt = $pdo->prepare("
    SELECT cp.id_categoria_proyecto, cp.nombre AS categoria, f.nombre AS familia
    FROM app.proyecto_categorias cp
    INNER JOIN app.familias_ciclos f ON f.id_familia_ciclo = cp.familia_ciclo_id
    WHERE cp.activo = true OR cp.id_categoria_proyecto = :categoria_actual
    ORDER BY f.orden, cp.orden, cp.nombre
");
$stmt->execute([':categoria_actual' => (int) $apartat['categoria_proyecto_id']]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($modo === 'new' && $categories !== []) {
    $apartat['categoria_proyecto_id'] = (int) $categories[0]['id_categoria_proyecto'];
}

$titulo = match ($modo) {
    'edit' => 'Editar apartat de memòria',
    'delete' => 'Borrar apartat de memòria',
    default => 'Nou apartat de memòria',
};
?>

<script>window.PAGE_TITLE = '<?= $titulo ?>';</script>

<div class="container py-4">
    <h1 class="h3 mb-3"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="/index.php?main=memoria-estructura_accion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="modo" value="<?= htmlspecialchars($modo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_memoria_estructura" value="<?= (int) $apartat['id_memoria_estructura'] ?>">

                <?php if ($modo === 'delete'): ?>
                    <div class="alert alert-danger">
                        Segur que vols borrar l’apartat <strong><?= htmlspecialchars((string) $apartat['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>?
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="categoria_proyecto_id" class="form-label">Categoria de projecte</label>
                        <select id="categoria_proyecto_id" name="categoria_proyecto_id" class="form-select" required
                                <?= $modo === 'delete' ? 'disabled' : '' ?>>
                            <?php foreach ($categories as $categoria): ?>
                                <option value="<?= (int) $categoria['id_categoria_proyecto'] ?>"
                                    <?= (int) $apartat['categoria_proyecto_id'] === (int) $categoria['id_categoria_proyecto'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $categoria['familia'] . ' > ' . (string) $categoria['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Si canvies la categoria d’un apartat existent, es col·locarà al final de la nova categoria.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="titulo" class="form-label">Títol</label>
                        <input id="titulo" name="titulo" class="form-control" maxlength="150" required
                               value="<?= htmlspecialchars((string) $apartat['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-12 mb-3">
                        <label for="descripcion" class="form-label">Descripció</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3" maxlength="4000"
                                  <?= $modo === 'delete' ? 'disabled' : '' ?>><?= htmlspecialchars((string) ($apartat['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label for="enlace_guia" class="form-label">Enllaç a la guia (opcional)</label>
                        <input id="enlace_guia" name="enlace_guia" type="url" class="form-control" maxlength="2048"
                               placeholder="https://..."
                               value="<?= htmlspecialchars((string) ($apartat['enlace_guia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               <?= $modo === 'delete' ? 'disabled' : '' ?>>
                    </div>
                </div>

                <?php if ($modo !== 'delete'): ?>
                    <div class="form-check mb-3">
                        <input id="activo" name="activo" type="checkbox" class="form-check-input" value="1"
                               <?= (bool) $apartat['activo'] ? 'checked' : '' ?>>
                        <label for="activo" class="form-check-label">Actiu</label>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn <?= $modo === 'delete' ? 'btn-danger' : 'btn-primary' ?>">
                        <?= $modo === 'delete' ? 'Sí, borrar' : 'Guardar' ?>
                    </button>
                    <a href="/index.php?main=memoria-estructura" class="btn btn-outline-secondary">Cancel·lar</a>
                </div>
            </form>
        </div>
    </div>
</div>
