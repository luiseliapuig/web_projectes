<?php
declare(strict_types=1);

$cicloProyecto = trim((string) ($proyectoAlumno['ciclo'] ?? ''));
$enlaceProyectosCiclo = $cicloProyecto !== ''
    ? '/projectes/' . rawurlencode($cicloProyecto)
    : '/projectes';

$grupoTrabajoConfirmado = false;
$compromisoTrabajoAceptado = false;
$resultadoGrupoTrabajo = '';
$proyectoGrupoId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
if ($proyectoGrupoId > 0) {
    $stmt = $pdo->prepare("
        SELECT rpa.grupo_trabajo_confirmado_en,
               rpa.compromiso_trabajo_aceptado,
               a.id_alumno, a.nombre, a.apellidos
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.rel_proyectos_alumnos miembros
            ON miembros.proyecto_id = rpa.proyecto_id
        INNER JOIN app.alumnos a ON a.id_alumno = miembros.alumno_id
        WHERE rpa.proyecto_id = :proyecto_id
          AND rpa.alumno_id = :alumno_id
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute([
        ':proyecto_id' => $proyectoGrupoId,
        ':alumno_id' => (int) $_SESSION['alumno_id'],
    ]);
    $miembrosGrupo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $grupoTrabajoConfirmado = $miembrosGrupo !== []
        && $miembrosGrupo[0]['grupo_trabajo_confirmado_en'] !== null;
    $compromisoTrabajoAceptado = $miembrosGrupo !== []
        && (bool) $miembrosGrupo[0]['compromiso_trabajo_aceptado'];
    if ($grupoTrabajoConfirmado) {
        $companeros = array_values(array_filter(
            $miembrosGrupo,
            static fn (array $miembro): bool => (int) $miembro['id_alumno'] !== (int) $_SESSION['alumno_id']
        ));
        $resultadoGrupoTrabajo = $companeros === []
            ? 'Projecte individual'
            : 'Projecte en parella amb ' . trim((string) $companeros[0]['nombre'] . ' ' . (string) $companeros[0]['apellidos']);
    }
}

$mensajeGrupoTrabajo = isset($_SESSION['fase_1_grup_mensaje']) && is_string($_SESSION['fase_1_grup_mensaje'])
    ? $_SESSION['fase_1_grup_mensaje']
    : '';
$errorGrupoTrabajo = isset($_SESSION['fase_1_grup_error']) && is_string($_SESSION['fase_1_grup_error'])
    ? $_SESSION['fase_1_grup_error']
    : '';
unset($_SESSION['fase_1_grup_mensaje'], $_SESSION['fase_1_grup_error']);
$mensajeCompromiso = isset($_SESSION['fase_1_compromis_mensaje']) && is_string($_SESSION['fase_1_compromis_mensaje'])
    ? $_SESSION['fase_1_compromis_mensaje']
    : '';
$errorCompromiso = isset($_SESSION['fase_1_compromis_error']) && is_string($_SESSION['fase_1_compromis_error'])
    ? $_SESSION['fase_1_compromis_error']
    : '';
unset($_SESSION['fase_1_compromis_mensaje'], $_SESSION['fase_1_compromis_error']);
?>
<div class="d-grid gap-4">
    <?php if ($mensajeGrupoTrabajo !== ''): ?>
        <div class="alert alert-success mb-0"><?= htmlspecialchars($mensajeGrupoTrabajo, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorGrupoTrabajo !== ''): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($errorGrupoTrabajo, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($mensajeCompromiso !== ''): ?>
        <div class="alert alert-success mb-0"><?= htmlspecialchars($mensajeCompromiso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorCompromiso !== ''): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($errorCompromiso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

    <section class="bloc bloc-informacio">
        <div class="bloc-contingut">
            <div class="bloc-tipus">Exemples</div>
            <h2>Projectes d’altres cursos</h2>
            <p class="mb-3">Abans de començar el vostre projecte és útil conèixer exemples reals.</p>
            <p class="mb-3">En aquest enllaç trobareu una selecció de projectes realitzats per alumnes d’altres cursos que us poden servir d’inspiració i orientació.</p>
            <a href="<?= htmlspecialchars($enlaceProyectosCiclo, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase-informacio">Veure projectes</a>
        </div>
    </section>

    <section class="bloc <?= $grupoTrabajoConfirmado ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $grupoTrabajoConfirmado ? 'Completada' : 'Activitat' ?></div>
            <h2>Defineix el grup de treball</h2>
            <?php if ($grupoTrabajoConfirmado): ?>
                <p class="fase-resultat-completat"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($resultadoGrupoTrabajo, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="mb-3">Abans de començar el projecte, decidiu si el fareu individualment o en parella. Si treballeu en parella, la decisió ha d’estar acordada prèviament entre tots dos.</p>
                <a href="/el-meu-projecte/fase-1/definir-grup" class="btn btn-puig-solid btn-sm px-3">Definir el grup</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="bloc <?= !$grupoTrabajoConfirmado ? 'bloc-bloquejat' : ($compromisoTrabajoAceptado ? 'bloc-completat' : 'bloc-activitat') ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus">
                <?= !$grupoTrabajoConfirmado ? 'Bloquejada' : ($compromisoTrabajoAceptado ? 'Completada' : 'Activitat individual') ?>
            </div>
            <h2>Compromís de treball</h2>
            <?php if (!$grupoTrabajoConfirmado): ?>
                <p><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar «Defineix el grup de treball».</p>
            <?php elseif ($compromisoTrabajoAceptado): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <p class="fase-resultat-completat"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Compromís acceptat</p>
                    <a href="/el-meu-projecte/fase-1/compromis" class="btn btn-outline-success btn-sm px-3">Revisar compromís</a>
                </div>
            <?php else: ?>
                <p class="mb-3">Llegeix i accepta personalment el compromís de treball del projecte.</p>
                <a href="/el-meu-projecte/fase-1/compromis" class="btn btn-puig-solid btn-sm px-3">Llegir el compromís</a>
            <?php endif; ?>
        </div>
    </section>
</div>
