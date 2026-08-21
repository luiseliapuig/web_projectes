<?php
declare(strict_types=1);

$contextoCursoActual = true;
if (!(require __DIR__ . '/projecte_context.php')) {
    return;
}

$alumnoId = (int) $_SESSION['alumno_id'];
$proyectoId = (int) $proyectoAlumno['id_proyecto'];
$stmt = $pdo->prepare("
    SELECT rpa.grupo_trabajo_confirmado_en,
           rpa.compromiso_trabajo_aceptado,
           a.nombre, a.apellidos,
           c.nombre AS ciclo_nombre
    FROM app.rel_proyectos_alumnos rpa
    INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
    INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE rpa.proyecto_id = :proyecto_id AND rpa.alumno_id = :alumno_id
    LIMIT 1
");
$stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoId]);
$participacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participacion || $participacion['grupo_trabajo_confirmado_en'] === null) {
    $_SESSION['fase_1_compromis_error'] = 'Primer has de completar «Defineix el grup de treball».';
    $destino = '/el-meu-projecte/fase-1';
    echo '<script>location.href=' . json_encode($destino) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

$compromisoAceptado = (bool) $participacion['compromiso_trabajo_aceptado'];
$nombreAlumno = trim((string) $participacion['nombre'] . ' ' . (string) $participacion['apellidos']);
$nombreCiclo = trim((string) $participacion['ciclo_nombre']);
$stmt = $pdo->prepare("
    SELECT a.nombre, a.apellidos
    FROM app.rel_proyectos_alumnos rpa
    INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
    WHERE rpa.proyecto_id = :proyecto_id
      AND rpa.alumno_id <> :alumno_id
    ORDER BY a.nombre, a.apellidos
");
$stmt->execute([':proyecto_id' => $proyectoId, ':alumno_id' => $alumnoId]);
$companeros = array_map(
    static fn (array $fila): string => trim((string) $fila['nombre'] . ' ' . (string) $fila['apellidos']),
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);
$esProyectoEnPareja = $companeros !== [];
$nombreCompaneros = implode(' i ', $companeros);
$error = isset($_SESSION['fase_1_compromis_error']) && is_string($_SESSION['fase_1_compromis_error'])
    ? $_SESSION['fase_1_compromis_error']
    : '';
unset($_SESSION['fase_1_compromis_error']);
$faseActiva = 1;
$fasesNavegacionBloqueada = true;
?>
<script>window.PAGE_TITLE = 'Compromís de treball';</script>
<div class="container-fluid py-4">
    <div class="row g-4 align-items-start">
        <aside class="col-lg-3"><?php include __DIR__ . '/fases_navegacion.php'; ?></aside>
        <section class="col-lg-9">
    <div class="mb-3">
        <span class="small fase-etiqueta">Fase 1</span>
        <h1 class="h3 mb-1 mt-1">Compromís de treball</h1>
        <p class="text-muted mb-0"><?= $compromisoAceptado ? 'Consulta el compromís que vas acceptar.' : 'Llegeix detingudament el compromís abans d’acceptar-lo.' ?></p>
    </div>

    <div class="card shadow-sm border-0 rounded-4 p-4 p-lg-5">
        <?php if ($error !== ''): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="bloc bloc-informacio mb-4">
                <div class="bloc-contingut">
                    <div class="bloc-tipus">Compromís</div>
                    <h2><?= $esProyectoEnPareja ? 'Compromís de treball en parella' : 'Compromís de treball individual' ?></h2>

                    <?php if ($esProyectoEnPareja): ?>
                        <p class="mb-3">
                            Jo, <strong class="text-dark"><?= htmlspecialchars($nombreAlumno, ENT_QUOTES, 'UTF-8') ?></strong>,
                            alumne/a del cicle formatiu <strong class="text-dark"><?= htmlspecialchars($nombreCiclo, ENT_QUOTES, 'UTF-8') ?></strong>
                            i membre del grup de projecte amb <strong class="text-dark"><?= htmlspecialchars($nombreCompaneros, ENT_QUOTES, 'UTF-8') ?></strong>,
                            em comprometo personalment a:
                        </p>
                        <ol class="compromis-llista mb-0">
                            <li class="mb-2">Desenvolupar el projecte de manera responsable i constant durant tot el curs.</li>
                            <li class="mb-2">Assistir a totes les classes de Projecte i ser puntual, tal com faig amb la resta de mòduls, acceptant que les faltes d’assistència o puntualitat reiterades podran afectar negativament la meva nota final.</li>
                            <li class="mb-2">No utilitzar les hores de Projecte per realitzar tasques d’altres mòduls, respectant que aquest temps està dedicat exclusivament al desenvolupament del projecte.</li>
                            <li class="mb-2">Acceptar que cada integrant del grup pot obtenir una nota diferent en funció del seu treball, la seva actitud a classe i el seu compromís amb el projecte, i que fins i tot es pot donar el cas que un dels integrants aprovi i l’altre no.</li>
                            <li class="mb-2">Complir els terminis i entregues establerts pel professorat.</li>
                            <li class="mb-2">Tenir en compte i seguir les indicacions del professorat per a la bona evolució del projecte.</li>
                            <li class="mb-2">Participar de manera equilibrada en totes les fases del projecte i repartir-nos les tasques de forma justa.</li>
                            <li class="mb-2">Fer servir el repositori Git de manera regular i equilibrada, amb còmits freqüents i clars per part de tots dos.</li>
                            <li class="mb-2">Mantenir actualitzada la documentació al Drive compartit.</li>
                            <li>Demanar ajuda al professorat en cas de dificultats i informar de qualsevol incidència o desequilibri que pugui afectar el projecte.</li>
                        </ol>
                    <?php else: ?>
                        <p class="mb-3">
                            Jo, <strong class="text-dark"><?= htmlspecialchars($nombreAlumno, ENT_QUOTES, 'UTF-8') ?></strong>,
                            alumne/a del cicle formatiu <strong class="text-dark"><?= htmlspecialchars($nombreCiclo, ENT_QUOTES, 'UTF-8') ?></strong>,
                            em comprometo a:
                        </p>
                        <ol class="compromis-llista mb-0">
                            <li class="mb-2">Desenvolupar el projecte de manera responsable i constant durant tot el curs.</li>
                            <li class="mb-2">Assistir a totes les classes de Projecte i ser puntual, tal com faig amb la resta de mòduls, acceptant que les faltes d’assistència o puntualitat reiterades podran afectar negativament la meva nota final.</li>
                            <li class="mb-2">Em comprometo a no utilitzar les hores de Projecte per realitzar tasques d’altres mòduls, respectant que aquest temps està dedicat exclusivament al desenvolupament del projecte.</li>
                            <li class="mb-2">Complir els terminis i entregues establerts pel professorat.</li>
                            <li class="mb-2">Tenir en compte i seguir les indicacions del professorat per a la bona evolució del projecte.</li>
                            <li class="mb-2">Mantenir actualitzada la documentació al Drive compartit.</li>
                            <li class="mb-2">Utilitzar el control de versions (Git) de forma regular i amb missatges clars.</li>
                            <li>Demanar ajuda al professorat en cas de dificultats i informar de qualsevol incidència que pugui afectar el projecte.</li>
                        </ol>
                    <?php endif; ?>
                </div>
        </section>

        <?php if ($compromisoAceptado): ?>
            <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Compromís acceptat.</div>
        <?php endif; ?>

        <?php if (!$compromisoAceptado): ?>
            <form method="post" action="/index.php?main=alumne-fase-1-compromis-accion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="accepto_compromis" id="accepto_compromis" value="1" required>
                    <label class="form-check-label fw-semibold" for="accepto_compromis">
                        He llegit detingudament el compromís de treball i l’accepto personalment.
                    </label>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-puig-solid px-4">Confirmar el compromís</button>
                    <a href="/el-meu-projecte/fase-1" class="btn btn-puig px-4">Cancel·lar</a>
                </div>
            </form>
        <?php else: ?>
            <div>
                <a href="/el-meu-projecte/fase-1" class="btn btn-fase-informacio d-inline-flex">Tornar</a>
            </div>
        <?php endif; ?>
    </div>
        </section>
    </div>
</div>
