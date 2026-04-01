<?php soloSuperadmin();


$ciclos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];

function gruposPorCiclo(string $ciclo): array
{
    if ($ciclo === 'SMX') {
        return ['A', 'B', 'C', 'D'];
    }

    if (in_array($ciclo, ['DAM', 'DAW', 'ASIX'], true)) {
        return ['A', 'B'];
    }

    return [];
}

$returnCiclo = $_GET['return_ciclo'] ?? ($_GET['ciclo'] ?? '');
$returnGrupo = $_GET['return_grupo'] ?? ($_GET['grupo'] ?? '');

$returnCiclo = in_array($returnCiclo, $ciclos, true) ? $returnCiclo : '';
$returnGrupo = in_array($returnGrupo, ['A', 'B', 'C', 'D'], true) ? $returnGrupo : '';

function cursoAcademicoActual(): string
{
    $year = (int)date('Y');
    $month = (int)date('n');

    if ($month < 8) {
        $inicio = $year - 1;
        $fin = $year;
    } else {
        $inicio = $year;
        $fin = $year + 1;
    }

    return sprintf('%d-%02d', $inicio, $fin % 100);
}

function toDateTimeLocal(?string $value): string
{
    if (!$value) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return str_replace(' ', 'T', substr($value, 0, 16));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$data = [
    'id_proyecto' => 0,
    'nombre' => '',
    'curso_academico' => cursoAcademicoActual(),
    'ciclo' => 'DAM',
    'grupo' => 'A',
    'estado' => 'activo',
    'tutor_id' => '',
    'defensa_aula_id' => '',
    'defensa_fecha' => '',
    'publicado' => false,
];

$alumnosSeleccionados = [];

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT
            id_proyecto,
            nombre,
            curso_academico,
            ciclo,
            grupo,
            estado,
            tutor_id,
            defensa_aula_id,
            defensa_fecha,
            publicado
        FROM app.proyectos
        WHERE id_proyecto = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        die('Projecte no trobat');
    }

    $data = $row;

    $stmtRel = $pdo->prepare("
        SELECT alumno_id
        FROM app.rel_proyectos_alumnos
        WHERE proyecto_id = :id
    ");
    $stmtRel->execute(['id' => $id]);
    $alumnosSeleccionados = array_map('intval', $stmtRel->fetchAll(PDO::FETCH_COLUMN));
}

/*
|--------------------------------------------------------------------------
| Si llegan ciclo/grupo por GET, actualizan el contexto del formulario
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['ciclo']) && $_GET['ciclo'] !== '') {
        $cicloGet = trim((string)$_GET['ciclo']);
        if (in_array($cicloGet, $ciclos, true)) {
            $data['ciclo'] = $cicloGet;
        }
    }

    if (isset($_GET['grupo'])) {
        $grupoGet = trim((string)$_GET['grupo']);
        $data['grupo'] = $grupoGet;
    }
}

$grupos = gruposPorCiclo((string)$data['ciclo']);
$usaGrupo = count($grupos) > 0;

if ($usaGrupo) {
    if (!in_array((string)$data['grupo'], $grupos, true)) {
        $data['grupo'] = $grupos[0];
    }
} else {
    $data['grupo'] = '';
}

$estados = ['activo', 'finalizado'];

$stmtProfes = $pdo->query("
    SELECT id_profesor, nombre, apellidos
    FROM app.profesores
    WHERE activo = true
    ORDER BY apellidos ASC, nombre ASC
");
$profesores = $stmtProfes->fetchAll(PDO::FETCH_ASSOC);

$stmtAulas = $pdo->query("
    SELECT id_aula, codigo, nombre
    FROM app.aulas
    ORDER BY codigo ASC, nombre ASC
");
$aulas = $stmtAulas->fetchAll(PDO::FETCH_ASSOC);

$sqlAlumnos = "
    SELECT
        a.id_alumno,
        a.nombre,
        a.apellidos,
        a.email,
        a.grupo
    FROM app.alumnos a
    WHERE a.activo = true
      AND a.ciclo = :ciclo
";

$paramsAlumnos = [
    'ciclo' => $data['ciclo'],
    'proyecto_id' => $id,
];

if ($usaGrupo) {
    $sqlAlumnos .= " AND a.grupo = :grupo ";
    $paramsAlumnos['grupo'] = $data['grupo'];
}

$sqlAlumnos .= "
      AND (
            a.id_alumno IN (
                SELECT rpa.alumno_id
                FROM app.rel_proyectos_alumnos rpa
                WHERE rpa.proyecto_id = :proyecto_id
            )
            OR a.id_alumno NOT IN (
                SELECT rpa2.alumno_id
                FROM app.rel_proyectos_alumnos rpa2
                WHERE rpa2.proyecto_id <> :proyecto_id
            )
      )
    ORDER BY a.nombre ASC, a.apellidos ASC
";

$stmtAlumnos = $pdo->prepare($sqlAlumnos);
$stmtAlumnos->execute($paramsAlumnos);
$alumnosDisponibles = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

$isEdit = (int)$data['id_proyecto'] > 0;
?>

<script>
window.PAGE_TITLE = '<?= $isEdit ? 'Editar projecte' : 'Nou projecte' ?>';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-9">
            <div class="card-style mb-30">
                <h6 class="mb-3"><?= $isEdit ? 'Editar projecte' : 'Nou projecte' ?></h6>

                <form method="post" action="index.php?main=proyecto_accion&raw=1">
                    <input type="hidden" name="return_ciclo" value="<?= htmlspecialchars($returnCiclo) ?>">
                    <input type="hidden" name="return_grupo" value="<?= htmlspecialchars($returnGrupo) ?>">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_proyecto" value="<?= (int)$data['id_proyecto'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Nom del projecte</label>
                        <div class="form-control bg-light">
                            <?= htmlspecialchars((string)$data['nombre']) ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estat</label>
                            <select name="estado" class="form-select" required>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?= $estado ?>" <?= $data['estado'] === $estado ? 'selected' : '' ?>>
                                        <?= ucfirst($estado) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cicle</label>
                            <select
                                name="ciclo"
                                class="form-select"
                                onchange="window.location='index.php?main=proyecto_form&id=<?= (int)$data['id_proyecto'] ?>&ciclo=' + encodeURIComponent(this.value) + '&grupo=&return_ciclo=<?= urlencode($returnCiclo) ?>&return_grupo=<?= urlencode($returnGrupo) ?>';"
                                required
                            >
                                <?php foreach ($ciclos as $ciclo): ?>
                                    <option value="<?= $ciclo ?>" <?= $data['ciclo'] === $ciclo ? 'selected' : '' ?>>
                                        <?= $ciclo ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Grup</label>

                            <?php if ($usaGrupo): ?>
                                <select
                                    name="grupo"
                                    class="form-select"
                                    onchange="window.location='index.php?main=proyecto_form&id=<?= (int)$data['id_proyecto'] ?>&ciclo=<?= urlencode((string)$data['ciclo']) ?>&grupo=' + encodeURIComponent(this.value) + '&return_ciclo=<?= urlencode($returnCiclo) ?>&return_grupo=<?= urlencode($returnGrupo) ?>';"
                                    required
                                >
                                    <?php foreach ($grupos as $grupo): ?>
                                        <option value="<?= $grupo ?>" <?= $data['grupo'] === $grupo ? 'selected' : '' ?>>
                                            <?= $grupo ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control" value="Sense grup" disabled>
                                <input type="hidden" name="grupo" value="">
                            <?php endif; ?>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">Publicat</label>
                            <div class="form-check mt-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="publicado"
                                    id="publicado"
                                    value="1"
                                    <?= !empty($data['publicado']) ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="publicado">
                                    Sí
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Curs acadèmic</label>
                        <input
                            type="text"
                            name="curso_academico"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['curso_academico']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label d-block">Alumnat del projecte</label>

                        <?php if (!$alumnosDisponibles): ?>
                            <div class="alert alert-light border">
                                No hi ha alumnes disponibles per al cicle <?= htmlspecialchars((string)$data['ciclo']) ?>
                                <?= $usaGrupo ? ' grup ' . htmlspecialchars((string)$data['grupo']) : '' ?>.
                            </div>
                        <?php else: ?>
                            <div class="border rounded p-3" style="max-height: 280px; overflow:auto;">
                                <?php foreach ($alumnosDisponibles as $al): ?>
                                    <?php $idAlumno = (int)$al['id_alumno']; ?>
                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="alumnos[]"
                                            id="alumno_<?= $idAlumno ?>"
                                            value="<?= $idAlumno ?>"
                                            <?= in_array($idAlumno, $alumnosSeleccionados, true) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="alumno_<?= $idAlumno ?>">
                                            <?= htmlspecialchars($al['nombre'] . ' ' . $al['apellidos']) ?>
                                            <small class="text-muted">
                                                — <?= htmlspecialchars((string)$al['email']) ?>
                                                <?php if (!empty($al['grupo'])): ?>
                                                    — Grup <?= htmlspecialchars((string)$al['grupo']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tutor</label>
                            <select name="tutor_id" class="form-select">
                                <option value="">-- Sense tutor --</option>
                                <?php foreach ($profesores as $prof): ?>
                                    <option
                                        value="<?= (int)$prof['id_profesor'] ?>"
                                        <?= (string)$data['tutor_id'] === (string)$prof['id_profesor'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($prof['apellidos'] . ', ' . $prof['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Aula defensa</label>
                            <select name="defensa_aula_id" class="form-select">
                                <option value="">-- Sense aula --</option>
                                <?php foreach ($aulas as $aula): ?>
                                    <option
                                        value="<?= (int)$aula['id_aula'] ?>"
                                        <?= (string)$data['defensa_aula_id'] === (string)$aula['id_aula'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars(trim($aula['codigo'] . ' - ' . $aula['nombre'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data i hora de defensa</label>
                            <input
                                type="datetime-local"
                                name="defensa_fecha"
                                class="form-control"
                                value="<?= htmlspecialchars(toDateTimeLocal($data['defensa_fecha'])) ?>"
                            >
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="main-btn primary-btn btn-hover">
                            Guardar
                        </button>
                        <a href="index.php?main=proyecto&ciclo=<?= urlencode($returnCiclo) ?>&grupo=<?= urlencode($returnGrupo) ?>" class="main-btn light-btn btn-hover">
                            Tornar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>