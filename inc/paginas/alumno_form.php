<?php soloSuperadmin();

function cursoAcademicoActual(): string
{
    $year = (int)date('Y');
    $month = (int)date('n');

    // Si estamos de enero a julio, seguimos en el curso iniciado el año anterior.
    if ($month < 8) {
        $inicio = $year - 1;
        $fin = $year;
    } else {
        $inicio = $year;
        $fin = $year + 1;
    }

    return sprintf('%d-%02d', $inicio, $fin % 100);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$data = [
    'id_alumno' => 0,
    'nombre' => '',
    'apellidos' => '',
    'email' => '',
    'ciclo' => 'DAM',
    'grupo' => 'A',
    'curso_academico' => cursoAcademicoActual(),
    'activo' => 1,
];

if ($id > 0) {
    $stmt = $pdo->prepare("
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
        WHERE id_alumno = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        die('Alumne no trobat');
    }

    $data = $row;
}

$isEdit = (int)$data['id_alumno'] > 0;

$ciclos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
$grupos = ['A', 'B', 'C', 'D'];
?>

<script>
window.PAGE_TITLE = '<?= $isEdit ? 'Editar alumne' : 'Nou alumne' ?>';
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card-style mb-30">
                <h6 class="mb-3"><?= $isEdit ? 'Editar alumne' : 'Nou alumne' ?></h6>

                <form method="post" action="index.php?main=alumno_accion&raw=1">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_alumno" value="<?= (int)$data['id_alumno'] ?>">

                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['nombre']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cognoms</label>
                        <input
                            type="text"
                            name="apellidos"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['apellidos']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['email']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cicle</label>
                        <select name="ciclo" class="form-select" required>
                            <?php foreach ($ciclos as $ciclo): ?>
                                <option value="<?= $ciclo ?>" <?= $data['ciclo'] === $ciclo ? 'selected' : '' ?>>
                                    <?= $ciclo ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Grup</label>
                        <select name="grupo" class="form-select" required>
                            <?php foreach ($grupos as $grupo): ?>
                                <option value="<?= $grupo ?>" <?= $data['grupo'] === $grupo ? 'selected' : '' ?>>
                                    <?= $grupo ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Curs acadèmic</label>
                        <input
                            type="text"
                            name="curso_academico"
                            class="form-control"
                            value="<?= htmlspecialchars((string)$data['curso_academico']) ?>"
                            required
                        >
                    </div>

                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="activo"
                            id="activo"
                            value="1"
                            <?= (int)$data['activo'] === 1 ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="activo">
                            Actiu
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="main-btn primary-btn btn-hover">
                            Guardar
                        </button>
                        <a href="index.php?main=alumno" class="main-btn light-btn btn-hover">
                            Tornar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>