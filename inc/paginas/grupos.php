<?php
solosuperadmin();

$msg = $_GET['msg'] ?? '';

$sql = "
   SELECT
    g.id_grupo,
    c.abr AS ciclo,
    c.nombre AS ciclo_nombre,
    g.grupo,
    g.torn,
    a.codigo AS aula_codigo,
    a.nombre AS aula_nombre
FROM app.grupos g
JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
LEFT JOIN app.aulas a ON a.id_aula = g.id_aula
ORDER BY c.abr, g.torn, g.grupo
";

$grupos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$coloresCiclo = [
    'SMX' => 'bg-info-subtle text-dark border-info-subtle',
    'ASIX' => 'bg-warning-subtle text-dark border-warning-subtle',
    'DAM' => 'bg-primary-subtle text-primary border-primary-subtle',
    'DAW' => 'bg-success-subtle text-success border-success-subtle',
    'DEV' => 'bg-danger-subtle text-danger border-danger-subtle',
];

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Grupos</h1>
            <p class="text-muted mb-0">Gestión de grupos por ciclo y aula asignada.</p>
        </div>

        <a href="/index.php?main=grupos_form&modo=new"
           class="btn btn-primary rounded-pill px-4">
            Nuevo grupo
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

   <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1">Listado de grupos</h2>
                <div class="text-muted small">
                    Ciclos, grupos y aulas asignadas.
                </div>
            </div>
            <span class="badge text-bg-light border">
                <?= count($grupos) ?> grupos
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Ciclo</th>
                    
                    <th class="text-center">Grupo</th>
                    <th>Aula</th>
                    <th class="text-center">Torn</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($grupos as $g): ?>
                <tr>
                    <td class="ps-4">
<?php
$clase = $coloresCiclo[$g['ciclo']] ?? 'bg-light text-dark border';
?>

<span class="badge rounded-pill border px-3 py-2 fw-semibold <?= $clase ?>">
    <?= htmlspecialchars($g['ciclo']) ?>
    <?= htmlspecialchars($g['grupo']) ?>
</span>
                    </td>

                    

                    <td class="text-center">
                        <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                            <?= htmlspecialchars($g['grupo']) ?>
                        </span>
                    </td>

                    <td>
                        <?php if ($g['aula_codigo']): ?>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($g['aula_codigo']) ?>
                            </div>
                            <?php if (!empty($g['aula_nombre'])): ?>
                                <div class="small text-muted">
                                    <?= htmlspecialchars($g['aula_nombre']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted fst-italic">Sin aula asignada</span>
                        <?php endif; ?>
                    </td>

                    <td class="text-center">
        <span class="badge rounded-pill <?= $g['torn'] === 'Matí' ? 'text-bg-warning' : 'text-bg-info' ?>">
            <?= htmlspecialchars($g['torn']) ?>
        </span>
    </td>

                    <td class="text-end pe-4">
                        <div class="btn-group btn-group-sm">
                            <a href="/index.php?main=grupos_form&modo=edit&id=<?= (int)$g['id_grupo'] ?>"
                               class="btn btn-outline-primary">
                                Editar
                            </a>

                            <a href="/index.php?main=grupos_form&modo=delete&id=<?= (int)$g['id_grupo'] ?>"
                               class="btn btn-outline-danger">
                                Borrar
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$grupos): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        Todavía no hay grupos creados.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>