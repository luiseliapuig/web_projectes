<?php

$professor_id = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;

if (!$professor_id) {
    echo '<div class="alert alert-warning rounded-4">Has d\'estar identificat com a professor per veure les teves defenses.</div>';
    return;
}

// ── Dades del professor ───────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT nombre, apellidos FROM app.profesores WHERE id_profesor = ?
    ");
    $stmt->execute([$professor_id]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $professor = null;
}

// ── Projectes assignats al professor com a tribunal ───────────────
try {
    $stmt = $pdo->prepare("
        SELECT
            p.id_proyecto,
            p.nombre,
            p.resumen,
            p.ruta_imagen,
            p.ciclo,
            p.grupo,
            p.curso_academico,
            p.defensa_fecha,
            TO_CHAR(p.defensa_fecha, 'YYYY-MM-DD')  AS defensa_data,
            TO_CHAR(p.defensa_fecha, 'HH24:MI')      AS defensa_hora,
            a2.codigo                                 AS aula_codigo,
            a2.nombre                                 AS aula_nombre,
            string_agg(
                al.nombre || ' ' || al.apellidos,
                '||' ORDER BY al.apellidos, al.nombre
            ) AS alumnos
        FROM app.rel_profesores_tribunal r
        JOIN app.proyectos p
            ON p.id_proyecto = r.id_proyecto
        LEFT JOIN app.aulas a2
            ON a2.id_aula = p.defensa_aula_id
        LEFT JOIN app.rel_proyectos_alumnos rpa
            ON rpa.proyecto_id = p.id_proyecto
        LEFT JOIN app.alumnos al
            ON al.id_alumno = rpa.alumno_id
        WHERE r.profesor_id = ?
          AND p.defensa_fecha IS NOT NULL
        GROUP BY
            p.id_proyecto, p.nombre, p.resumen, p.ruta_imagen,
            p.ciclo, p.grupo, p.curso_academico, p.defensa_fecha,
            a2.codigo, a2.nombre
        ORDER BY p.defensa_fecha ASC
    ");
    $stmt->execute([$professor_id]);
    $projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projectes = [];
}

// ── Membres del tribunal per projecte ────────────────────────────
$tribunal_per_proj = [];
if (!empty($projectes)) {
    $ids = array_column($projectes, 'id_proyecto');
    $ids_int = implode(',', array_map('intval', $ids));
    try {
        $stmt = $pdo->query("
            SELECT r.id_proyecto,
                   TRIM(p.nombre || ' ' || p.apellidos) AS nom
            FROM app.rel_profesores_tribunal r
            JOIN app.profesores p ON p.id_profesor = r.profesor_id
            WHERE r.id_proyecto IN ($ids_int)
            ORDER BY r.id_proyecto, p.apellidos, p.nombre
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tribunal_per_proj[$row['id_proyecto']][] = $row['nom'];
        }
    } catch (PDOException $e) {
        $tribunal_per_proj = [];
    }
}

// ── Post-processar ────────────────────────────────────────────────
foreach ($projectes as &$p) {
    $p['alumnos_array'] = !empty($p['alumnos']) ? explode('||', $p['alumnos']) : [];

    $ruta = trim((string)($p['ruta_imagen'] ?? ''));
    if ($ruta !== '') {
        $p['ruta_imagen_absoluta'] = (str_starts_with($ruta, '/') || str_starts_with($ruta, 'http'))
            ? $ruta
            : '/' . ltrim($ruta, '/');
    } else {
        $p['ruta_imagen_absoluta'] = '';
    }
}
unset($p);

// ── Agrupar per data ──────────────────────────────────────────────
$per_data = [];
foreach ($projectes as $p) {
    $per_data[$p['defensa_data']][] = $p;
}

// ── Helper: nom llarg de la data ──────────────────────────────────
function nomDataLlarga(string $data): string {
    $dies = ['diumenge','dilluns','dimarts','dimecres','dijous','divendres','dissabte'];
    $mesos = ['','gener','febrer','març','abril','maig','juny',
               'juliol','agost','setembre','octubre','novembre','desembre'];
    $ts  = strtotime($data);
    $dia = (int)date('j', $ts);
    $mes = (int)date('n', $ts);
    $any = date('Y', $ts);
    $dow = (int)date('w', $ts);
    return ucfirst($dies[$dow]) . ', ' . $dia . ' de ' . $mesos[$mes] . ' de ' . $any;
}

?>

<script>
window.PAGE_TITLE = 'Les meves defenses';
</script>

<div class="container-fluid">

    <div class="projectes-header mb-4 mt-30">
        <h1 class="projectes-title mb-2">Les meves defenses</h1>
        <?php if ($professor): ?>
        <p class="projectes-subtitle mb-0">
            Projectes assignats a <strong><?= h($professor['nombre'] . ' ' . $professor['apellidos']) ?></strong> com a membre del tribunal.
        </p>
        <?php endif; ?>
    </div>

    <?php if (empty($projectes)): ?>
        <div class="projectes-empty-state">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h3 class="h5 mb-2">Encara no tens cap defensa assignada</h3>
                   <?php if ( configuracion('seleccionar_defensas')) { ?>
                    <p class="mb-0 text-muted">
                        Apunta't als tribunals des del <a href="/calendari-defenses">calendari de defenses</a>.
                    </p>
                    <?php   } ?>
                </div>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($per_data as $data => $projectes_dia): ?>
            <section class="projectes-grup-section mb-5">

                <div class="projectes-grup-header mb-3">
                    <h3 class="projectes-grup-title mb-0"><?= h(nomDataLlarga($data)) ?></h3>
                </div>

                <div class="row g-5">
                    <?php foreach ($projectes_dia as $projecte): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <a href="/projecte/<?= (int)$projecte['id_proyecto'] ?>" class="project-card-link">
                                <article class="project-card">

                                    <?php if (!empty($projecte['ruta_imagen_absoluta'])): ?>
                                        <img
                                            src="<?= h($projecte['ruta_imagen_absoluta']) ?>"
                                            alt="<?= h($projecte['nombre']) ?>"
                                            class="project-card-image">
                                    <?php else: ?>
                                        <div class="project-card-image project-card-image-placeholder">
                                            Sense imatge
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2 px-3 pt-3">
                                        <span class="badge rounded-pill pill-granate px-3 py-2 fs-6 fw-bold">
                                            🕐 <?= h($projecte['defensa_hora']) ?>
                                        </span>
                                        <?php if (!empty($projecte['aula_codigo'])): ?>
                                        <span class="badge rounded-pill pill-orange px-3 py-2 fs-6">
                                            📍 <?= h($projecte['aula_codigo']) ?>
                                            <?php if (!empty($projecte['aula_nombre'])): ?>
                                                · <?= h($projecte['aula_nombre']) ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php
                                        $membres = $tribunal_per_proj[$projecte['id_proyecto']] ?? [];
                                    ?>
                                    <?php if (!empty($membres)): ?>
                                    <div class="px-3 pt-2 pb-1 small text-muted">
                                        <span class="birrete">🎓</span> <?= h(implode(', ', $membres)) ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="project-card-body">

                                        <div class="project-card-meta mb-2">
                                            <span><?= h($projecte['ciclo']) ?></span>
                                            <span class="project-meta-separator">·</span>
                                            <span><?= h($projecte['grupo']) ?></span>
                                        </div>

                                        <h2 class="project-card-title">
                                            <?= h($projecte['nombre']) ?>
                                        </h2>

                                        <?php if (!empty($projecte['resumen'])): ?>
                                            <p class="project-card-summary">
                                                <?= h($projecte['resumen']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="project-card-alumnes">
                                            <?php if (!empty($projecte['alumnos_array'])): ?>
                                                <div class="project-card-students">
                                                    <?php foreach ($projecte['alumnos_array'] as $alumne): ?>
                                                        <span class="project-student-badge">
                                                            <?= h(trim($alumne)) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small">Sense alumnat assignat</div>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </article>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

            </section>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
