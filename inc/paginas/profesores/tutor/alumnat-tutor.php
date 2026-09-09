<?php
declare(strict_types=1);

if (!esTutor()) {
    http_response_code(403);
    die('Accés no permès');
}

$profesorId = (int) $_SESSION['professor_id'];
$cursoActual = cursoAcademicoActual();
$preferenciasTutor = isset($_SESSION['tutor_filtres']) && is_array($_SESSION['tutor_filtres'])
    ? $_SESSION['tutor_filtres']
    : [];
$curso = $cursoActual;

$stmt = $pdo->prepare("
    SELECT g.id_grupo, g.id_ciclo, g.grupo, c.abr, c.nombre, c.color, c.orden
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
    $ciclos[(int) $grupo['id_ciclo']] = ['id_ciclo' => (int) $grupo['id_ciclo'], 'abr' => (string) $grupo['abr']];
}

$filtrosCurso = isset($preferenciasTutor['por_curso'][$curso]) && is_array($preferenciasTutor['por_curso'][$curso])
    ? $preferenciasTutor['por_curso'][$curso]
    : [];
$filtrosEnPeticion = array_key_exists('ciclo_id', $_GET) || array_key_exists('grupo_id', $_GET);
$cicloId = $filtrosEnPeticion
    ? (isset($_GET['ciclo_id']) ? (int) $_GET['ciclo_id'] : 0)
    : (int) ($filtrosCurso['ciclo_id'] ?? 0);
$grupoId = $filtrosEnPeticion
    ? (isset($_GET['grupo_id']) ? (int) $_GET['grupo_id'] : 0)
    : (int) ($filtrosCurso['grupo_id'] ?? 0);
if ($cicloId > 0 && !isset($ciclos[$cicloId])) {
    $cicloId = 0;
}
$grupoIds = array_map('intval', array_column($grupos, 'id_grupo'));
if ($grupoId > 0 && !in_array($grupoId, $grupoIds, true)) {
    $grupoId = 0;
}
if ($grupoId > 0) {
    foreach ($grupos as $grupo) {
        if ((int) $grupo['id_grupo'] === $grupoId && $cicloId > 0 && (int) $grupo['id_ciclo'] !== $cicloId) {
            $grupoId = 0;
            break;
        }
    }
}
$_SESSION['tutor_filtres'] = [
    'curso' => $curso,
    'por_curso' => array_replace(
        isset($preferenciasTutor['por_curso']) && is_array($preferenciasTutor['por_curso'])
            ? $preferenciasTutor['por_curso']
            : [],
        [$curso => ['ciclo_id' => $cicloId, 'grupo_id' => $grupoId]]
    ),
];

$sql = "
    SELECT a.id_alumno, a.nombre, a.apellidos, a.email, a.activo,
           rag.curso_academico, g.id_grupo, g.grupo, c.id_ciclo,
           c.abr AS ciclo, c.color, c.orden,
           CASE WHEN a.activo=true AND (a.password_hash IS NULL OR a.password_hash='') THEN 1 ELSE 0 END AS pendiente_invitacion,
           (SELECT COUNT(*) FROM app.rel_proyectos_alumnos rpa WHERE rpa.alumno_id=a.id_alumno) AS proyectos,
           (SELECT NULLIF(BTRIM(p.nombre), '')
              FROM app.rel_proyectos_alumnos rpa
              INNER JOIN app.proyectos p ON p.id_proyecto=rpa.proyecto_id
             WHERE rpa.alumno_id=a.id_alumno
             ORDER BY p.curso_academico DESC, p.id_proyecto DESC
             LIMIT 1) AS proyecto_nombre,
           (SELECT COUNT(*) FROM app.rel_alumnos_grupos rag_total WHERE rag_total.alumno_id=a.id_alumno) AS matriculas,
           (SELECT STRING_AGG(rag_otro.curso_academico, ', ' ORDER BY rag_otro.curso_academico)
              FROM app.rel_alumnos_grupos rag_otro
             WHERE rag_otro.alumno_id=a.id_alumno AND rag_otro.curso_academico<>:curso_otras) AS otros_cursos,
           (SELECT COUNT(*) FROM app.seguimiento_alumnos sa WHERE sa.alumno_id=a.id_alumno) AS seguimientos,
           (SELECT COUNT(*) FROM app.seguimiento_alumnos sa WHERE sa.alumno_id=a.id_alumno AND sa.valoracion_tutor IS NOT NULL) AS valoraciones,
           (SELECT COUNT(*) FROM app.seguimiento_alumnos sa WHERE sa.alumno_id=a.id_alumno AND NULLIF(BTRIM(sa.comentario_tutor), '') IS NOT NULL) AS comentarios,
           (SELECT COUNT(*) FROM app.ajustes_nota_individual ani WHERE ani.alumno_id=a.id_alumno) AS ajustes
    FROM app.rel_alumnos_grupos rag
    INNER JOIN app.alumnos a ON a.id_alumno=rag.alumno_id
    INNER JOIN app.grupos g ON g.id_grupo=rag.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo=g.id_ciclo
    INNER JOIN app.rel_profesores_grupos rpg
        ON rpg.grupo_id=rag.grupo_id
       AND rpg.curso_academico=rag.curso_academico
       AND rpg.profesor_id=:profesor_id
    WHERE rag.curso_academico=:curso
";
$params = [':profesor_id' => $profesorId, ':curso' => $curso, ':curso_otras' => $curso];
if ($cicloId > 0) {
    $sql .= ' AND c.id_ciclo=:ciclo_id';
    $params[':ciclo_id'] = $cicloId;
}
if ($grupoId > 0) {
    $sql .= ' AND g.id_grupo=:grupo_id';
    $params[':grupo_id'] = $grupoId;
}
$sql .= ' ORDER BY c.orden, c.abr, g.grupo, a.nombre, a.apellidos';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = $_SESSION['alumnat_tutor_error'] ?? '';
unset($_SESSION['alumnat_tutor_error']);
$warning = $_SESSION['alumnat_tutor_warning'] ?? '';
unset($_SESSION['alumnat_tutor_warning']);
$notice = $_SESSION['alumnat_tutor_notice'] ?? '';
unset($_SESSION['alumnat_tutor_notice']);
$mensaje = isset($_GET['msg']) && is_string($_GET['msg']) ? $_GET['msg'] : '';
?>
<script>window.PAGE_TITLE = 'Administrar alumnat';</script>
<style>
.alumnat-tutor-table { min-width: 920px; }
.alumnat-tutor-table tbody tr:last-child > td { padding-bottom: 1rem; }
.alumnat-tutor-email { margin-top:.2rem; color:var(--bs-secondary-color); font-size:.875rem; font-weight:400; overflow-wrap:anywhere; }
.alumnat-tutor-invitacio {
    padding: .25rem .5rem;
    border: 1px solid #adb5bd;
    border-radius: .25rem;
    background: #fff;
    color: #6c757d;
    font-size: .875rem;
    line-height: 1.5;
}
.alumnat-tutor-invitacio:hover:not(:disabled) { background: #f8f9fa; border-color: #6c757d; color: #495057; }
.alumnat-tutor-invitacio:disabled { opacity: .5; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div><h1 class="h3 mb-1">Administrar alumnat</h1><p class="text-muted mb-0">Gestiona l’alumnat dels grups que tens assignats.</p></div>
        <a href="/index.php?main=alumnat-tutor_form&amp;curso=<?= rawurlencode($curso) ?>" class="btn btn-puig-solid rounded-pill px-4">Nou alumne</a>
    </div>

    <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (is_string($warning) && $warning !== ''): ?><div class="alert alert-warning" role="alert"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (is_string($notice) && $notice !== ''): ?><div class="alert alert-success" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($mensaje === 'guardat'): ?><div class="alert alert-success" role="alert">Alumne guardat correctament.</div>
    <?php elseif ($mensaje === 'desactivat'): ?><div class="alert alert-success" role="alert">Alumne desactivat correctament.</div>
    <?php elseif ($mensaje === 'eliminat'): ?><div class="alert alert-success" role="alert">Alumne eliminat correctament.</div><?php endif; ?>

    <form method="get" class="row g-2 align-items-end mb-3" id="alumnat-tutor-filtres">
        <input type="hidden" name="main" value="alumnat-tutor">
        <div class="col-sm-4 col-lg-3"><label for="ciclo_id" class="form-label">Cicle</label><select name="ciclo_id" id="ciclo_id" class="form-select"><option value="0">Tots</option>
            <?php foreach ($ciclos as $ciclo): ?><option value="<?= $ciclo['id_ciclo'] ?>" <?= $cicloId === $ciclo['id_ciclo'] ? 'selected' : '' ?>><?= htmlspecialchars($ciclo['abr'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-sm-4 col-lg-3"><label for="grupo_id" class="form-label">Grup</label><select name="grupo_id" id="grupo_id" class="form-select"><option value="0">Tots</option>
            <?php foreach ($grupos as $grupo): ?><option value="<?= (int) $grupo['id_grupo'] ?>" data-ciclo="<?= (int) $grupo['id_ciclo'] ?>" <?= $grupoId === (int) $grupo['id_grupo'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($grupo['abr'] . ' ' . $grupo['grupo']), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-lg-6 text-lg-end text-muted pb-2">Total: <strong><?= count($alumnos) ?></strong></div>
    </form>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0 alumnat-tutor-table">
            <colgroup><col style="width:30%"><col style="width:16%"><col style="width:18%"><col style="width:9%"><col style="width:9%"><col style="width:18%"></colgroup>
            <thead class="table-light"><tr><th class="ps-4">Nom</th><th>Invitació</th><th>Grup</th><th class="text-center">Projectes</th><th class="text-center">Actiu</th><th class="text-end pe-4">Accions</th></tr></thead>
            <tbody>
            <?php if ($alumnos === []): ?><tr><td colspan="6" class="text-center text-muted py-5">No hi ha alumnes amb aquests filtres.</td></tr>
            <?php else: foreach ($alumnos as $alumno): ?>
                <tr>
                    <td class="ps-4"><div class="fw-semibold"><?= htmlspecialchars(trim($alumno['nombre'] . ' ' . $alumno['apellidos']), ENT_QUOTES, 'UTF-8') ?></div><div class="alumnat-tutor-email"><?= htmlspecialchars((string) $alumno['email'], ENT_QUOTES, 'UTF-8') ?></div></td>
                    <td><form method="post" action="/index.php?main=alumnat-tutor_invitaciones_accion">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="curso" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="ciclo_id" value="<?= (int) $alumno['id_ciclo'] ?>">
                        <input type="hidden" name="grupo_id" value="<?= (int) $alumno['id_grupo'] ?>">
                        <input type="hidden" name="return_ciclo_id" value="<?= $cicloId ?>">
                        <input type="hidden" name="return_grupo_id" value="<?= $grupoId ?>">
                        <input type="hidden" name="alumno_id" value="<?= (int) $alumno['id_alumno'] ?>">
                        <button type="submit" class="alumnat-tutor-invitacio text-nowrap" <?= (int) $alumno['pendiente_invitacion'] === 1 ? '' : 'disabled' ?>>Enviar invitació</button>
                    </form></td>
                    <td><span class="badge rounded-pill border px-3 py-2 fw-semibold <?= clasesColorCiclo((string) $alumno['color']) ?>"><?= htmlspecialchars(trim($alumno['ciclo'] . ' ' . $alumno['grupo']), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="text-center"><?= (int) $alumno['proyectos'] ?></td>
                    <td class="text-center"><?php if ((int) $alumno['activo'] === 1): ?><i class="bi bi-check-circle-fill text-success" title="Actiu"></i><?php else: ?><i class="bi bi-x-circle-fill text-danger" title="Inactiu"></i><?php endif; ?></td>
                    <td class="text-end pe-4 text-nowrap"><form method="post" action="/index.php?main=alumnat-tutor_accion" class="btn-group btn-group-sm">
                        <a href="/index.php?main=alumnat-tutor_form&amp;id=<?= (int) $alumno['id_alumno'] ?>&amp;curso=<?= rawurlencode($curso) ?>" class="btn btn-outline-primary">Editar</a>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_alumno" value="<?= (int) $alumno['id_alumno'] ?>"><input type="hidden" name="return_curso" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
                        <button
                            type="button"
                            class="btn btn-outline-danger obrir-eliminar-alumne"
                            data-bs-toggle="modal"
                            data-bs-target="#eliminar-alumne-modal"
                            data-alumne-id="<?= (int) $alumno['id_alumno'] ?>"
                            data-alumne-nom="<?= htmlspecialchars(trim($alumno['nombre'] . ' ' . $alumno['apellidos']), ENT_QUOTES, 'UTF-8') ?>"
                            data-projectes="<?= (int) $alumno['proyectos'] ?>"
                            data-projecte-nom="<?= htmlspecialchars((string) ($alumno['proyecto_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-altres-cursos="<?= htmlspecialchars((string) ($alumno['otros_cursos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-seguiments="<?= (int) $alumno['seguimientos'] ?>"
                            data-valoracions="<?= (int) $alumno['valoraciones'] ?>"
                            data-comentaris="<?= (int) $alumno['comentarios'] ?>"
                            data-ajustos="<?= (int) $alumno['ajustes'] ?>"
                        >Borrar</button>
                    </form></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div></div>

    <?php if ($grupoId > 0): ?>
        <div class="d-flex justify-content-end mt-3">
            <form method="post" action="/index.php?main=alumnat-tutor_invitaciones_accion" id="invitacions-grup-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="curso" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="ciclo_id" value="<?= $cicloId ?>">
                <input type="hidden" name="grupo_id" value="<?= $grupoId ?>">
                <input type="hidden" name="return_ciclo_id" value="<?= $cicloId ?>">
                <input type="hidden" name="return_grupo_id" value="<?= $grupoId ?>">
                <button type="button" class="btn btn-puig btn-sm px-3" data-bs-toggle="modal" data-bs-target="#confirmar-invitacions-grup">Enviar invitacions al grup</button>
            </form>
        </div>

        <div class="modal fade" id="confirmar-invitacions-grup" tabindex="-1" aria-labelledby="confirmar-invitacions-grup-titol" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-puig">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="confirmar-invitacions-grup-titol">Enviar invitacions al grup</h2>
                            <p class="text-muted mb-0 mt-1 small">Confirma l’enviament abans de continuar.</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tancar"></button>
                    </div>
                    <div class="modal-body px-4 py-3">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px;color:#970a2c">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                            </div>
                            <p class="mb-0">S’enviarà una invitació a tot l’alumnat actiu del grup que encara no tingui contrasenya.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel·lar</button>
                        <button type="submit" form="invitacions-grup-form" class="btn btn-puig-solid px-4">Enviar invitacions</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="eliminar-alumne-modal" tabindex="-1" aria-labelledby="eliminar-alumne-titol" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-puig">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="eliminar-alumne-titol">Eliminar alumne</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tancar"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p id="eliminar-alumne-intro" class="mb-3"></p>
                    <div id="eliminar-alumne-contingut"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" id="eliminar-alumne-cancelar">Cancel·lar</button>
                    <form method="post" action="/index.php?main=alumnat-tutor_accion" id="desactivar-alumne-form" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return_curso" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id_alumno" value="">
                        <button type="submit" name="accio" value="desactivar" class="btn btn-puig px-4">Desactivar alumne</button>
                    </form>
                    <form method="post" action="/index.php?main=alumnat-tutor_accion" id="eliminar-alumne-form" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return_curso" value="<?= htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id_alumno" value="">
                        <button type="submit" name="accio" value="eliminar" class="btn btn-puig-solid px-4">Eliminar definitivament</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const form=document.getElementById('alumnat-tutor-filtres'), ciclo=document.getElementById('ciclo_id'), grupo=document.getElementById('grupo_id');
    if (!form || !ciclo || !grupo) return;
    const actualizarGrupos=()=>Array.from(grupo.options).forEach(opcion=>{ const visible=opcion.value==='0' || ciclo.value==='0' || opcion.dataset.ciclo===ciclo.value; opcion.hidden=!visible; opcion.disabled=!visible; });
    actualizarGrupos();
    ciclo.addEventListener('change',()=>{ grupo.value='0'; actualizarGrupos(); form.submit(); });
    grupo.addEventListener('change',()=>form.submit());
})();

(() => {
    const modal=document.getElementById('eliminar-alumne-modal');
    if (!modal) return;
    const titol=document.getElementById('eliminar-alumne-titol');
    const intro=document.getElementById('eliminar-alumne-intro');
    const contingut=document.getElementById('eliminar-alumne-contingut');
    const cancelar=document.getElementById('eliminar-alumne-cancelar');
    const formDesactivar=document.getElementById('desactivar-alumne-form');
    const formEliminar=document.getElementById('eliminar-alumne-form');
    const nombreElementos=(valor, singular, plural)=>`${valor} ${valor===1?singular:plural}`;

    modal.addEventListener('show.bs.modal', event => {
        const boto=event.relatedTarget;
        if (!(boto instanceof HTMLElement)) return;
        const dades=boto.dataset;
        const id=dades.alumneId || '';
        const nom=dades.alumneNom || '';
        const projectes=Number(dades.projectes || 0);
        const altresCursos=dades.altresCursos || '';
        const seguiments=Number(dades.seguiments || 0);
        const valoracions=Number(dades.valoracions || 0);
        const comentaris=Number(dades.comentaris || 0);
        const ajustos=Number(dades.ajustos || 0);
        const teHistorial=seguiments>0 || valoracions>0 || comentaris>0 || ajustos>0;

        formDesactivar.querySelector('[name="id_alumno"]').value=id;
        formEliminar.querySelector('[name="id_alumno"]').value=id;
        titol.textContent='Eliminar alumne';
        intro.textContent=`Estàs a punt d’eliminar ${nom} definitivament.`;
        cancelar.textContent='Cancel·lar';
        formDesactivar.classList.add('d-none');
        formEliminar.classList.add('d-none');

        if (projectes>0) {
            titol.textContent='Aquest alumne no es pot eliminar';
            intro.textContent=projectes===1 && dades.projecteNom
                ? `${nom} està vinculat al projecte “${dades.projecteNom}”.`
                : `${nom} està vinculat a ${nombreElementos(projectes,'projecte','projectes')}.`;
            contingut.innerHTML='<p class="mb-2">El projecte i el seu historial s’han de conservar.</p><p class="mb-0">Si l’alumne ja no participa en Projecte, pots desactivar-lo.</p>';
            cancelar.textContent='Tancar';
            formDesactivar.classList.remove('d-none');
            return;
        }

        if (altresCursos!=='') {
            titol.textContent='Aquest alumne no es pot eliminar';
            intro.textContent=`${nom} té historial de matrícula en altres cursos.`;
            contingut.innerHTML=`<p class="mb-2">Cursos relacionats: <strong>${altresCursos.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')}</strong>.</p><p class="mb-0">Per conservar aquest historial, l’alumne no es pot eliminar des d’aquest gestor.</p>`;
            cancelar.textContent='Tancar';
            formDesactivar.classList.remove('d-none');
            return;
        }

        formEliminar.classList.remove('d-none');
        if (!teHistorial) {
            contingut.innerHTML='<p class="mb-2">Aquest alumne no té activitat ni historial associat.</p><p class="text-danger mb-0">Aquesta acció no es pot desfer.</p>';
            return;
        }

        const elements=[];
        if (seguiments>0) elements.push(nombreElementos(seguiments,'seguiment setmanal','seguiments setmanals'));
        if (valoracions>0) elements.push(nombreElementos(valoracions,'valoració del tutor','valoracions del tutor'));
        if (comentaris>0) elements.push(nombreElementos(comentaris,'comentari del tutor','comentaris del tutor'));
        if (ajustos>0) elements.push(nombreElementos(ajustos,'ajust individual de nota','ajustos individuals de nota'));
        contingut.replaceChildren();
        const avis=document.createElement('p');
        avis.className='mb-2';
        avis.textContent='S’eliminaran també:';
        const llista=document.createElement('ul');
        llista.className='mb-3';
        elements.forEach(text => { const item=document.createElement('li'); item.textContent=text; llista.append(item); });
        const alternativa=document.createElement('p');
        alternativa.className='mb-0';
        alternativa.textContent='Si l’alumne ja no participa en Projecte però vols conservar el seu historial, pots desactivar-lo en lloc d’eliminar-lo.';
        contingut.append(avis,llista,alternativa);
        formDesactivar.classList.remove('d-none');
    });
})();
</script>
