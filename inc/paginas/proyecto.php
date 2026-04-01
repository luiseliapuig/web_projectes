<?php soloSuperadmin();

$ciclos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
$grupos = ['A', 'B', 'C', 'D'];

$filtroCiclo = $_GET['ciclo'] ?? '';
$filtroCiclo = in_array($filtroCiclo, $ciclos, true) ? $filtroCiclo : '';

$filtroGrupo = $_GET['grupo'] ?? '';
$filtroGrupo = in_array($filtroGrupo, $grupos, true) ? $filtroGrupo : '';

$sql = "
    SELECT
        p.id_proyecto,
        p.uuid,
        p.nombre,
        p.ciclo,
        p.grupo,
        p.curso_academico,
        p.estado,
        p.publicado,
        p.defensa_fecha,
        a.nombre AS aula_nombre,
        a.codigo AS aula_codigo,
        pr.nombre AS tutor_nombre,
        pr.apellidos AS tutor_apellidos,
        COALESCE(
            string_agg(
                al.nombre || ' ' || al.apellidos,
                '|||' ORDER BY al.apellidos, al.nombre
            ),
            ''
        ) AS alumnos_nombres
    FROM app.proyectos p
    LEFT JOIN app.aulas a
        ON a.id_aula = p.defensa_aula_id
    LEFT JOIN app.profesores pr
        ON pr.id_profesor = p.tutor_id
    LEFT JOIN app.rel_proyectos_alumnos rpa
        ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos al
        ON al.id_alumno = rpa.alumno_id
";

$where = [];
$params = [];

if ($filtroCiclo !== '') {
    $where[] = "p.ciclo = :ciclo";
    $params['ciclo'] = $filtroCiclo;
}

if ($filtroGrupo !== '') {
    $where[] = "p.grupo = :grupo";
    $params['grupo'] = $filtroGrupo;
}

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= "
    GROUP BY
        p.id_proyecto,
        p.uuid,
        p.nombre,
        p.ciclo,
        p.grupo,
        p.curso_academico,
        p.estado,
        p.publicado,
        p.defensa_fecha,
        a.nombre,
        a.codigo,
        pr.nombre,
        pr.apellidos
    ORDER BY p.curso_academico DESC, p.ciclo ASC, p.grupo ASC, p.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProjectes = count($rows);
?>

<script>
window.PAGE_TITLE = 'Projectes';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-style mb-30">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <form method="get" class="d-flex align-items-center gap-2 flex-wrap mb-0">
                            <input type="hidden" name="main" value="proyecto">

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
                            Total: <strong><?= $totalProjectes ?></strong> projectes
                        </div>
                    </div>

                    <a href="index.php?main=proyecto_form&ciclo=<?= urlencode($filtroCiclo) ?>&grupo=<?= urlencode($filtroGrupo) ?>" class="main-btn primary-btn btn-hover btn-sm">
                        Nou projecte
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Cicle</th>
                                <th>Grup</th>
                                <th>Curs</th>
                                <th>Tutor</th>
                                <th>Alumnes</th>
                                <th>Defensa</th>
                                <th>Estat</th>
                                <th>Publicat</th>
                                <th style="width: 140px;">Accions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="10">No hi ha projectes.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars((string)$r['nombre']) ?></strong>

                                            <br>

                                            <a
                                                href="index.php?main=ficha_form&uuid=<?= urlencode((string)$r['uuid']) ?>"
                                                style="font-size: .85rem;"
                                            >
                                                Editar ficha
                                            </a>

<br>

                                            <a
                                                href="/login/alumnes/<?= urlencode((string)$r['uuid']) ?>"
                                                target="_blank"
                                                style="font-size: .85rem;"
                                            >
                                                Enlace de entrada
                                            </a>

                                        </td>
                                        <td><?= htmlspecialchars((string)$r['ciclo']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['grupo']) ?></td>
                                        <td><?= htmlspecialchars((string)$r['curso_academico']) ?></td>
                                        <td>
                                            <?= htmlspecialchars(trim(((string)$r['tutor_apellidos']) . ', ' . ((string)$r['tutor_nombre']))) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $alumnosHtml = '';
                                            if (!empty($r['alumnos_nombres'])) {
                                                $partes = explode('|||', $r['alumnos_nombres']);
                                                $partes = array_map(fn($v) => htmlspecialchars(trim($v)), $partes);
                                                $alumnosHtml = implode('<br>', $partes);
                                            }
                                            ?>
                                            <?= $alumnosHtml !== '' ? $alumnosHtml : '-' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $defensa = (string)($r['defensa_fecha'] ?? '');
                                            $aula = trim(((string)($r['aula_codigo'] ?? '')) . ' ' . ((string)($r['aula_nombre'] ?? '')));
                                            ?>
                                            <?= $defensa !== '' ? htmlspecialchars($defensa) : '-' ?>
                                            <?= $aula !== '' ? '<br><small>' . htmlspecialchars($aula) . '</small>' : '' ?>
                                        </td>
                                        <td><?= htmlspecialchars((string)$r['estado']) ?></td>
                                        <td><?= !empty($r['publicado']) ? 'Sí' : 'No' ?></td>
                                        <td>
                                            <a href="index.php?main=proyecto_form&id=<?= (int)$r['id_proyecto'] ?>&ciclo=<?= urlencode($filtroCiclo) ?>&grupo=<?= urlencode($filtroGrupo) ?>">
                                                Editar
                                            </a>

                                            <form
                                                method="post"
                                                action="index.php?main=proyecto_accion&raw=1"
                                                style="display:inline-block; margin-left:10px;"
                                                onsubmit="return confirm('Vols eliminar aquest projecte?');"
                                            >
                                                <input type="hidden" name="accion" value="borrar">
                                                <input type="hidden" name="id_proyecto" value="<?= (int)$r['id_proyecto'] ?>">
                                                <input type="hidden" name="return_ciclo" value="<?= htmlspecialchars($filtroCiclo) ?>">
                                                <input type="hidden" name="return_grupo" value="<?= htmlspecialchars($filtroGrupo) ?>">
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