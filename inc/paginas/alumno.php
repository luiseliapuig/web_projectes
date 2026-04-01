<?php soloSuperadmin();

$ciclos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
$grupos = ['A', 'B', 'C', 'D'];

$filtroCiclo = $_GET['ciclo'] ?? '';
$filtroCiclo = in_array($filtroCiclo, $ciclos, true) ? $filtroCiclo : '';

$filtroGrupo = $_GET['grupo'] ?? '';
$filtroGrupo = in_array($filtroGrupo, $grupos, true) ? $filtroGrupo : '';

$sql = "
    SELECT
        id_alumno,
        nombre,
        apellidos,
        email,
        ciclo,
        grupo,
        curso_academico,
        activo
    FROM app.alumnos
";

$where = [];
$params = [];

if ($filtroCiclo !== '') {
    $where[] = "ciclo = :ciclo";
    $params['ciclo'] = $filtroCiclo;
}

if ($filtroGrupo !== '') {
    $where[] = "grupo = :grupo";
    $params['grupo'] = $filtroGrupo;
}

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= "
    ORDER BY curso_academico DESC, ciclo ASC, grupo ASC, apellidos ASC, nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAlumnes = count($rows);
?>

<script>
window.PAGE_TITLE = 'Alumnat';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-style mb-30">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <form method="get" class="d-flex align-items-center gap-2 flex-wrap mb-0">
                            <input type="hidden" name="main" value="alumno">

                            <label class="mb-0">Cicle:</label>
                            <select
                                name="ciclo"
                                class="form-select form-select-sm"
                                onchange="this.form.submit()"
                                style="width:auto;"
                            >
                                <option value="">Tots</option>
                                <?php foreach ($ciclos as $c): ?>
                                    <option value="<?= $c ?>" <?= $filtroCiclo === $c ? 'selected' : '' ?>>
                                        <?= $c ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label class="mb-0 ms-2">Grup:</label>
                            <select
                                name="grupo"
                                class="form-select form-select-sm"
                                onchange="this.form.submit()"
                                style="width:auto;"
                            >
                                <option value="">Tots</option>
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= $g ?>" <?= $filtroGrupo === $g ? 'selected' : '' ?>>
                                        <?= $g ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <div style="font-size: .95rem; color: #6b7280;">
                            Total: <strong><?= $totalAlumnes ?></strong> alumnes
                        </div>
                    </div>

                    <a href="index.php?main=alumno_form" class="main-btn primary-btn btn-hover btn-sm">
                        Nou alumne
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Cicle</th>
                                <th>Grup</th>
                                <th>Curs acadèmic</th>
                                <th>Actiu</th>
                                <th style="width: 140px;">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="7">No hi ha alumnes.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['apellidos'] . ', ' . $r['nombre']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['email']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['ciclo']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['grupo']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['curso_academico']) ?></td>
                                        <td><?= (int)$r['activo'] === 1 ? 'Sí' : 'No' ?></td>
                                        <td>
                                            <a href="index.php?main=alumno_form&id=<?= (int)$r['id_alumno'] ?>">Editar</a>

                                            <form
                                                method="post"
                                                action="index.php?main=alumno_accion&raw=1"
                                                style="display:inline-block; margin-left:10px;"
                                                onsubmit="return confirm('Vols eliminar aquest alumne?');"
                                            >
                                                <input type="hidden" name="accion" value="borrar">
                                                <input type="hidden" name="id_alumno" value="<?= (int)$r['id_alumno'] ?>">
                                                <button type="submit" class="btn btn-link p-0 m-0 align-baseline">
                                                    Borrar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>