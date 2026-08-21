<?php
declare(strict_types=1);

solosuperadmin();

$modo = isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : 'new';
$modo = in_array($modo, ['new', 'edit', 'delete'], true) ? $modo : 'new';
$id = (int)($_GET['id'] ?? 0);


$grupo = [
    'id_grupo' => 0,
    'id_ciclo' => '',
    'familia_ciclo_id' => '',
    'grupo' => '',
    'id_aula' => ''
];

if ($modo !== 'new') {
    $stmt = $pdo->prepare("
        SELECT g.*, c.familia_ciclo_id
        FROM app.grupos g
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        WHERE g.id_grupo = :id
    ");
    $stmt->execute([':id' => $id]);
    $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        echo '<div class="alert alert-danger">Grupo no encontrado.</div>';
        return;
    }
}

$stmtFamilias = $pdo->prepare("
    SELECT id_familia_ciclo, nombre, orden
    FROM app.familias_ciclos
    WHERE activo = true OR id_familia_ciclo = :familia_actual
    ORDER BY orden, nombre
");
$stmtFamilias->execute([':familia_actual' => (int) ($grupo['familia_ciclo_id'] ?? 0)]);
$familias = $stmtFamilias->fetchAll(PDO::FETCH_ASSOC);

if ($modo === 'new' && $familias !== []) {
    $grupo['familia_ciclo_id'] = (int) $familias[0]['id_familia_ciclo'];
}

$stmtCiclos = $pdo->prepare("
    SELECT id_ciclo, abr, nombre, orden, familia_ciclo_id
    FROM app.ciclos
    WHERE activo = true OR id_ciclo = :id_ciclo_actual
    ORDER BY orden, abr
");
$stmtCiclos->execute([':id_ciclo_actual' => (int) ($grupo['id_ciclo'] ?? 0)]);
$ciclos = $stmtCiclos->fetchAll(PDO::FETCH_ASSOC);

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

            <form method="post" action="/index.php?main=grups-cicle_accion">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
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
                    <label for="familia_ciclo_id" class="form-label">Família professional</label>
                    <select id="familia_ciclo_id" name="familia_ciclo_id" class="form-select" required
                            <?= $modo === 'delete' ? 'disabled' : '' ?>>
                        <?php foreach ($familias as $familia): ?>
                            <option value="<?= (int) $familia['id_familia_ciclo'] ?>"
                                <?= (int) $grupo['familia_ciclo_id'] === (int) $familia['id_familia_ciclo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $familia['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 col-4">
                    <label for="id_ciclo" class="form-label">Cicle</label>
                    <select id="id_ciclo" name="id_ciclo" class="form-select" required <?= $modo === 'delete' ? 'disabled' : '' ?>>
                        <option value="">Selecciona un cicle</option>

                        <?php foreach ($ciclos as $c): ?>
                            <option value="<?= (int)$c['id_ciclo'] ?>"
                                data-familia="<?= (int) $c['familia_ciclo_id'] ?>"
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
                           required
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

                    <a href="/index.php?main=grups-cicle" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>

<?php if ($modo !== 'delete'): ?>
<script>
(() => {
    const familia = document.getElementById('familia_ciclo_id');
    const ciclo = document.getElementById('id_ciclo');

    const filtrarCiclos = () => {
        const familiaId = familia.value;
        let seleccionVisible = false;

        Array.from(ciclo.options).forEach((option) => {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            const visible = option.dataset.familia === familiaId;
            option.hidden = !visible;
            option.disabled = !visible;
            seleccionVisible ||= visible && option.selected;
        });

        if (!seleccionVisible) {
            ciclo.value = '';
        }
    };

    familia.addEventListener('change', filtrarCiclos);
    filtrarCiclos();
})();
</script>
<?php endif; ?>
