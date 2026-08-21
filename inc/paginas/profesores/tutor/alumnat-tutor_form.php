<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}
$profesorId = (int) $_SESSION['professor_id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$curso = isset($_GET['curso']) && is_string($_GET['curso']) ? trim($_GET['curso']) : cursoAcademicoActual();
if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $curso)) {
    $curso = cursoAcademicoActual();
}

$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr, c.nombre, c.orden
    FROM app.rel_profesores_grupos rpg
    INNER JOIN app.grupos g ON g.id_grupo=rpg.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo
    WHERE rpg.profesor_id=:profesor_id AND rpg.curso_academico=:curso
    ORDER BY c.orden, c.abr, g.grupo
");
$stmt->execute([':profesor_id' => $profesorId, ':curso' => $curso]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ciclos = [];
foreach ($grupos as $grupo) {
    $ciclos[(int) $grupo['id_ciclo']] = ['id_ciclo' => (int) $grupo['id_ciclo'], 'abr' => (string) $grupo['abr'], 'nombre' => (string) $grupo['nombre']];
}
$grupoRecordado = (int) ($_SESSION['tutor_filtres']['por_curso'][$curso]['grupo_id'] ?? 0);
$grupoIdsPermitidos = array_map('intval', array_column($grupos, 'id_grupo'));
if (!in_array($grupoRecordado, $grupoIdsPermitidos, true)) {
    $grupoRecordado = 0;
}
$data = ['id_alumno' => 0, 'nombre' => '', 'apellidos' => '', 'email' => '', 'activo' => 1, 'grupo_id' => $grupoRecordado];
$formError = $_SESSION['alumnat_tutor_form_error'] ?? '';
$formOld = $_SESSION['alumnat_tutor_form_old'] ?? null;
unset($_SESSION['alumnat_tutor_form_error'], $_SESSION['alumnat_tutor_form_old']);
if ($id <= 0 && is_array($formOld)) {
    $grupoAnterior = (int) ($formOld['grupo_id'] ?? 0);
    $data = [
        'id_alumno' => 0,
        'nombre' => is_string($formOld['nombre'] ?? null) ? $formOld['nombre'] : '',
        'apellidos' => is_string($formOld['apellidos'] ?? null) ? $formOld['apellidos'] : '',
        'email' => is_string($formOld['email'] ?? null) ? $formOld['email'] : '',
        'activo' => (int) ($formOld['activo'] ?? 0),
        'grupo_id' => in_array($grupoAnterior, $grupoIdsPermitidos, true) ? $grupoAnterior : $grupoRecordado,
    ];
}

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, a.nombre, a.apellidos, a.email, a.activo, rag.grupo_id
        FROM app.alumnos a
        INNER JOIN app.rel_alumnos_grupos rag ON rag.alumno_id=a.id_alumno AND rag.curso_academico=:curso
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id=rag.grupo_id AND rpg.curso_academico=rag.curso_academico AND rpg.profesor_id=:profesor_id
        WHERE a.id_alumno=:id
        LIMIT 1
    ");
    $stmt->execute([':curso' => $curso, ':profesor_id' => $profesorId, ':id' => $id]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$alumno) {
        http_response_code(404);
        echo '<div class="container-fluid py-4"><div class="alert alert-danger">Alumne no trobat entre els teus grups.</div></div>';
        return;
    }
    $data = $alumno;
}

$grupoSeleccionado = (int) $data['grupo_id'];
$cicloSeleccionado = 0;
foreach ($grupos as $grupo) {
    if ((int) $grupo['id_grupo'] === $grupoSeleccionado) {
        $cicloSeleccionado = (int) $grupo['id_ciclo'];
        break;
    }
}
$esEdicion = $id > 0;
$mailDomain = dominioCorreoInstitucional();
$emailVisible = parteLocalCorreoInstitucional((string) $data['email']);
?>
<script>window.PAGE_TITLE = '<?= $esEdicion ? 'Editar alumne' : 'Nou alumne' ?>';</script>
<div class="container-fluid py-4">
    <div class="mb-3"><h1 class="h3 mb-1"><?= $esEdicion ? 'Editar alumne' : 'Nou alumne' ?></h1><p class="text-muted mb-0">Dades personals i matrícula en un dels teus grups del curs <?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>.</p></div>
    <?php if (is_string($formError) && $formError !== ''): ?><div class="alert alert-warning" role="alert"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="row"><div class="col-xl-8"><div class="card shadow-sm border-0 rounded-4 p-4">
        <?php if ($grupos === []): ?><div class="alert alert-warning mb-0">No tens grups assignats durant aquest curs.</div>
        <?php else: ?><form method="post" action="/index.php?main=alumnat-tutor_accion">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accio" value="guardar"><input type="hidden" name="id_alumno" value="<?= (int) $data['id_alumno'] ?>"><input type="hidden" name="curso_academico" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="curso_original" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
            <div class="row g-3">
                <div class="col-md-5"><label for="nombre" class="form-label">Nom</label><input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required value="<?= htmlspecialchars((string) $data['nombre'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-7"><label for="apellidos" class="form-label">Cognoms</label><input type="text" name="apellidos" id="apellidos" class="form-control" maxlength="150" required value="<?= htmlspecialchars((string) $data['apellidos'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-8"><label for="email" class="form-label">Email</label><?php if ($mailDomain !== ''): ?><div class="input-group"><input type="text" name="email" id="email" class="form-control" maxlength="64" autocomplete="email" required value="<?= htmlspecialchars($emailVisible, ENT_QUOTES, 'UTF-8') ?>" aria-describedby="email-domain"><span class="input-group-text" id="email-domain">@<?= htmlspecialchars($mailDomain, ENT_QUOTES, 'UTF-8') ?></span></div><?php else: ?><input type="email" name="email" id="email" class="form-control" maxlength="255" required value="<?= htmlspecialchars((string) $data['email'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?></div>
                <div class="col-md-4"><span class="form-label d-block">Curs acadèmic</span><span class="form-control bg-light"><?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="col-md-4"><label for="ciclo_id" class="form-label">Cicle</label><select id="ciclo_id" class="form-select" required><option value="" <?= $cicloSeleccionado === 0 ? 'selected' : '' ?> disabled>Selecciona un cicle</option><?php foreach ($ciclos as $ciclo): ?><option value="<?= $ciclo['id_ciclo'] ?>" <?= $cicloSeleccionado === $ciclo['id_ciclo'] ? 'selected' : '' ?>><?= htmlspecialchars($ciclo['abr'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label for="grupo_id" class="form-label">Grup</label><select name="grupo_id" id="grupo_id" class="form-select" required><option value="" <?= $grupoSeleccionado === 0 ? 'selected' : '' ?>>Selecciona un grup</option><?php foreach ($grupos as $grupo): ?><option value="<?= (int) $grupo['id_grupo'] ?>" data-ciclo="<?= (int) $grupo['id_ciclo'] ?>" <?= $grupoSeleccionado === (int) $grupo['id_grupo'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($grupo['abr'] . ' ' . $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" <?= (int) $data['activo'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="activo">Actiu</label></div></div>
                <?php if (!$esEdicion): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="enviar_invitacion" id="enviar_invitacion" value="1"><label class="form-check-label" for="enviar_invitacion">Enviar invitació</label><div class="form-text">L’enllaç per crear la primera contrasenya serà vàlid durant cinc hores.</div></div></div><?php endif; ?>
            </div>
            <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-puig-solid px-4">Guardar</button><a href="/index.php?main=alumnat-tutor&amp;curso=<?= rawurlencode($curso) ?>" class="btn btn-puig px-4">Tornar</a></div>
        </form><?php endif; ?>
    </div></div></div>
</div>
<script>
(() => {
    const ciclo=document.getElementById('ciclo_id'), grupo=document.getElementById('grupo_id');
    if (!ciclo || !grupo) return;
    const actualizar=()=>{ Array.from(grupo.options).forEach(opcion=>{ const visible=opcion.value==='' || opcion.dataset.ciclo===ciclo.value; opcion.hidden=!visible; opcion.disabled=!visible; }); if(grupo.selectedOptions[0]?.disabled)grupo.value=''; };
    ciclo.addEventListener('change',actualizar); actualizar();
})();
</script>
