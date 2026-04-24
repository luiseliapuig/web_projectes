<?php
solosuperadmin();

$modo = $_GET['modo'] ?? 'new';
$id = (int)($_GET['id'] ?? 0);


$grupo = [
    'id_grupo' => 0,
    'id_ciclo' => '',
    'grupo' => '',
    'id_aula' => ''
];

if ($modo !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM app.grupos WHERE id_grupo = :id");
    $stmt->execute([':id' => $id]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        echo '<div class="alert alert-danger">Grupo no encontrado.</div>';
        return;
    }
}

$ciclos = $pdo->query("
    SELECT id_ciclo, abr, nombre
    FROM app.ciclos
    ORDER BY abr
")->fetchAll(PDO::FETCH_ASSOC);

$aulas = $pdo->query("
    SELECT id_aula, codigo, nombre
    FROM app.aulas
    ORDER BY codigo
")->fetchAll(PDO::FETCH_ASSOC);

$titulo = match ($modo) {
    'edit' => 'Editar grupo',
    'delete' => 'Borrar grupo',
    default => 'Nuevo grupo'
};
?>

<div class="container py-4">

    <div class="mb-3">
        <h1 class="h3 mb-1"><?= htmlspecialchars($titulo) ?></h1>
        <p class="text-muted mb-0">Ciclo, letra de grupo y aula de referencia.</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <form method="post" action="/index.php?main=grupos_accion&raw=1">

                <input type="hidden" name="modo" value="<?= htmlspecialchars($modo) ?>">
                <input type="hidden" name="id_grupo" value="<?= (int)$grupo['id_grupo'] ?>">

                <?php if ($modo === 'delete'): ?>

                    <div class="alert alert-danger">
                        ¿Seguro que quieres borrar este grupo?
                        <br>
                        <strong>
                            <?= htmlspecialchars($grupo['grupo']) ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div class="mb-3 col-4">
                    <label class="form-label">Ciclo</label>
                    <select name="id_ciclo" class="form-select" required <?= $modo === 'delete' ? 'disabled' : '' ?>>
                        <option value="">Selecciona ciclo</option>

                        <?php foreach ($ciclos as $c): ?>
                            <option value="<?= (int)$c['id_ciclo'] ?>"
                                <?= (int)$grupo['id_ciclo'] === (int)$c['id_ciclo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['abr']) ?> — <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 col-4">
                    <label class="form-label">Grupo</label>
                    <input type="text"
                           name="grupo"
                           class="form-control"
                           maxlength="10"
                           value="<?= htmlspecialchars($grupo['grupo'] ?? '') ?>"
                           placeholder="A, B, C, D..."
                           
                           <?= $modo === 'delete' ? 'disabled' : '' ?>>
                </div>

                <div class="mb-3 col-4">
                    <label class="form-label">Torn</label>
                    <select name="torn" class="form-select" required <?= $modo === 'delete' ? 'disabled' : '' ?>>
                      <option value="Matí" <?= ($grupo['torn'] ?? '') === 'Matí' ? 'selected' : '' ?>>Matí</option>
                          <option value="Tarda" <?= ($grupo['torn'] ?? '') === 'Tarda' ? 'selected' : '' ?>>Tarda</option>
                      </select>
                 </div>

                <div class="mb-3 col-4">
                    <label class="form-label">Aula</label>
                    <select name="id_aula" class="form-select" <?= $modo === 'delete' ? 'disabled' : '' ?>>
                        <option value="">Sin aula asignada</option>

                        <?php foreach ($aulas as $a): ?>
                            <option value="<?= (int)$a['id_aula'] ?>"
                                <?= (int)($grupo['id_aula'] ?? 0) === (int)$a['id_aula'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['codigo']) ?> — <?= htmlspecialchars($a['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit"
                            class="btn <?= $modo === 'delete' ? 'btn-danger' : 'btn-primary' ?>">
                        <?= $modo === 'delete' ? 'Sí, borrar' : 'Guardar' ?>
                    </button>

                    <a href="/index.php?main=grupos" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>