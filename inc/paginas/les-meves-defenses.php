<?php
// les-meves-defenses.php — vista

$professor_id = isset($_SESSION['professor_id']) ? (int)$_SESSION['professor_id'] : null;

if (!$professor_id) {
    echo '<div class="alert alert-warning rounded-4">Has d\'estar identificat com a professor per veure les teves defenses.</div>';
    return;
}

// Dades del professor
$stmtProf = $pdo->prepare("SELECT nombre, apellidos FROM app.profesores WHERE id_profesor = ?");
$stmtProf->execute([$professor_id]);
$professor = $stmtProf->fetch(PDO::FETCH_ASSOC);

// Consulta principal — només projectes on és membre del tribunal i tenen data de defensa
$sql = "
SELECT
    p.id_proyecto,
    p.uuid,
    p.nombre,
    p.resumen,
    p.descripcion,
    p.stack,
    p.ruta_imagen,
    p.ruta_memoria,
    p.ruta_funcional,
    p.url_github,
    p.url_proyecto,
    p.ciclo,
    p.grupo,
    p.curso_academico,
    p.defensa_fecha,
    p.defensa_aula_id,
    p.autoev1,
    p.autoev2,
    p.autoev3,
    p.autoev4,
    p.nota_tutor_planificacion,
    p.nota_tutor_gestion,
    p.nota_tutor_memoria,
    p.nota_tutor_proyecto,
    p.nota_tutor_compromiso,

    TO_CHAR(p.defensa_fecha, 'YYYY-MM-DD') AS defensa_data,

    pt.nombre  || ' ' || pt.apellidos  AS tutor_nom,
    pct.nombre || ' ' || pct.apellidos AS cotutor_nom,
    TRIM(au.codigo || CASE WHEN au.nombre IS NOT NULL AND au.nombre <> '' THEN ' ' || au.nombre ELSE '' END) AS aula_nom,

    COALESCE(
        (
            SELECT json_agg(json_build_object('id', a2.id_alumno, 'nom', a2.nombre || ' ' || a2.apellidos)
                   ORDER BY a2.apellidos, a2.nombre)
            FROM app.rel_proyectos_alumnos rpa2
            JOIN app.alumnos a2 ON a2.id_alumno = rpa2.alumno_id
            WHERE rpa2.proyecto_id = p.id_proyecto
        ), '[]'
    ) AS alumnes,

    COALESCE(
        (
            SELECT json_agg(json_build_object('id', t.id_profesor, 'nom', t.nombre || ' ' || t.apellidos))
            FROM (
                SELECT DISTINCT rpt2.profesor_id AS id_profesor, tpr2.nombre, tpr2.apellidos
                FROM app.rel_profesores_tribunal rpt2
                JOIN app.profesores tpr2 ON tpr2.id_profesor = rpt2.profesor_id
                WHERE rpt2.id_proyecto = p.id_proyecto
            ) t
        ), '[]'
    ) AS tribunal,

    COUNT(adj.id) FILTER (WHERE adj.tipo = 'arxiu')        AS num_arxius,
    COUNT(adj.id) FILTER (WHERE adj.tipo = 'enllac')       AS num_enllacos,
    COUNT(adj.id) FILTER (WHERE adj.tipo = 'gestio')       AS num_gestio,
    COUNT(adj.id) FILTER (WHERE adj.tipo = 'planificacio') AS num_planificacio,

    AVG(et.nota_memoria)  AS avg_memoria,
    AVG(et.nota_proyecto) AS avg_proyecto,
    AVG(et.nota_defensa)  AS avg_defensa,

    COALESCE(
        json_object_agg(ani.alumno_id::text, ani.ajuste)
        FILTER (WHERE ani.alumno_id IS NOT NULL),
        '{}'
    ) AS ajustos

FROM app.rel_profesores_tribunal rpt
JOIN app.proyectos p ON p.id_proyecto = rpt.id_proyecto
LEFT JOIN app.profesores              pt  ON pt.id_profesor  = p.tutor_id
LEFT JOIN app.profesores              pct ON pct.id_profesor = p.cotutor_id
LEFT JOIN app.aulas                   au  ON au.id_aula      = p.defensa_aula_id
LEFT JOIN app.proyecto_adjuntos       adj ON adj.proyecto_id = p.id_proyecto
LEFT JOIN app.evaluacion_tribunal     et  ON et.proyecto_id  = p.id_proyecto
LEFT JOIN app.ajustes_nota_individual ani ON ani.proyecto_id = p.id_proyecto

WHERE rpt.profesor_id = :professor_id
  AND p.defensa_fecha IS NOT NULL

GROUP BY
    p.id_proyecto, p.uuid, p.nombre, p.resumen, p.descripcion, p.stack,
    p.ruta_imagen, p.ruta_memoria, p.ruta_funcional, p.url_github, p.url_proyecto,
    p.ciclo, p.grupo, p.curso_academico, p.defensa_fecha, p.defensa_aula_id,
    p.autoev1, p.autoev2, p.autoev3, p.autoev4,
    p.nota_tutor_planificacion, p.nota_tutor_gestion, p.nota_tutor_memoria,
    p.nota_tutor_proyecto, p.nota_tutor_compromiso,
    pt.nombre, pt.apellidos,
    pct.nombre, pct.apellidos,
    au.nombre, au.codigo

ORDER BY p.defensa_fecha ASC, p.nombre
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':professor_id' => $professor_id]);
$projectes = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['alumnes']  = json_decode($row['alumnes'], true);
    $row['tribunal'] = json_decode($row['tribunal'], true);
    $row['ajustos']  = json_decode($row['ajustos'], true);

   $obligatoris = !empty($row['nombre'])
            && !empty($row['ruta_memoria']);

    $presentacioCompleta = !empty($row['ruta_imagen'])
                        && !empty($row['resumen'])
                        && !empty($row['descripcion']);

    $autoavCompleta = !empty($row['autoev1'])
                   && !empty($row['autoev2'])
                   && !empty($row['autoev3'])
                   && !empty($row['autoev4']);

    $opcionalsComplets = $presentacioCompleta && $autoavCompleta;

    $row['estat_entrega'] = !$obligatoris ? 'incomplet' : ($opcionalsComplets ? 'complet' : 'valid');

    $projectes[] = $row;
}

// Agrupar per data
$per_data = [];
foreach ($projectes as $p) {
    $per_data[$p['defensa_data']][] = $p;
}

// Helper nom llarg de la data
if (!function_exists('nomDataLlarga')) {
    function nomDataLlarga(string $data): string {
        $dies  = ['diumenge','dilluns','dimarts','dimecres','dijous','divendres','dissabte'];
        $mesos = ['','gener','febrer','març','abril','maig','juny',
                   'juliol','agost','setembre','octubre','novembre','desembre'];
        $ts  = strtotime($data);
        $dia = (int)date('j', $ts);
        $mes = (int)date('n', $ts);
        $any = date('Y', $ts);
        $dow = (int)date('w', $ts);
        return ucfirst($dies[$dow]) . ', ' . $dia . ' de ' . $mesos[$mes] . ' de ' . $any;
    }
}

// Helpers card (guards)
if (!function_exists('renderPill')) {
    function renderPill(bool $ok, string $label, bool $obligatori = false): string {
        if ($ok)         return '<span class="pill-check">✓ ' . h($label) . '</span>';
        if ($obligatori) return '<span class="pill-x">✕ '    . h($label) . '</span>';
        return '<span class="pill-neutral">— ' . h($label) . '</span>';
    }
}

if (!function_exists('calcularNotes')) {
    function calcularNotes(array $p, array $alumnes): array {
        $notesT = array_filter([
            $p['nota_tutor_planificacion'],
            $p['nota_tutor_gestion'],
            $p['nota_tutor_memoria'],
            $p['nota_tutor_proyecto'],
            $p['nota_tutor_compromiso'],
        ], fn($v) => $v !== null && $v !== '');

        $notaTutor    = count($notesT)             ? (array_sum($notesT) / count($notesT)) * 2 : null;
        $notaMemoria  = $p['avg_memoria']  !== null ? (float)$p['avg_memoria']  * 2 : null;
        $notaProjecte = $p['avg_proyecto'] !== null ? (float)$p['avg_proyecto'] * 2 : null;
        $notaDefensa  = $p['avg_defensa']  !== null ? (float)$p['avg_defensa']  * 2 : null;

        $parcials = [];
        if ($notaTutor    !== null) $parcials[] = $notaTutor    * 0.20;
        if ($notaMemoria  !== null) $parcials[] = $notaMemoria  * 0.30;
        if ($notaProjecte !== null) $parcials[] = $notaProjecte * 0.30;
        if ($notaDefensa  !== null) $parcials[] = $notaDefensa  * 0.20;

        $notaBase = count($parcials) === 4 ? array_sum($parcials) : null;

        $notesAlumnes = [];
        foreach ($alumnes as $al) {
            $ajust = (float)($p['ajustos'][(string)$al['id']] ?? 0);
            $notesAlumnes[] = [
                'id'    => $al['id'],
                'nom'   => $al['nom'],
                'nota'  => $notaBase !== null ? round($notaBase + $ajust, 1) : null,
                'ajust' => $ajust,
            ];
        }

        return [
            'tutor'    => $notaTutor,
            'memoria'  => $notaMemoria,
            'projecte' => $notaProjecte,
            'defensa'  => $notaDefensa,
            'base'     => $notaBase,
            'alumnes'  => $notesAlumnes,
        ];
    }
}

if (!function_exists('formatNota')) {
    function formatNota(?float $n): string {
        if ($n === null) return '—';
        return number_format($n, 1, ',', '');
    }
}

if (!function_exists('renderCardEntrega')) {
    function renderCardEntrega(array $p): void {
        $estat    = $p['estat_entrega'];
        $alumnes  = $p['alumnes'];
        $tribunal = $p['tribunal'];

        $ckTitol  = !empty($p['nombre']);
        $ckImatge = !empty($p['ruta_imagen']);
        $ckResum  = !empty($p['resumen']);
        $ckDesc   = !empty($p['descripcion']);

        $ckMemoria   = !empty($p['ruta_memoria']);
        $ckFuncional = !empty($p['ruta_funcional']);
        $ckStack     = !empty($p['stack']);
        $ckGestio    = $p['num_gestio'] > 0;
        $numArxius   = (int)$p['num_arxius'];

        $ckGithub    = !empty($p['url_github']);
        $ckDemo      = !empty($p['url_proyecto']);
        $numEnllacos = (int)$p['num_enllacos'];

        $ckAev1 = !empty($p['autoev1']);
        $ckAev2 = !empty($p['autoev2']);
        $ckAev3 = !empty($p['autoev3']);
        $ckAev4 = !empty($p['autoev4']);

        $estatClass = match($estat) {
            'complet' => 'estat-complet',
            'valid'   => 'estat-valid',
            default   => 'estat-invalid',
        };
        $estatText = match($estat) {
            'complet' => 'Lliurament complet',
            'valid'   => 'Lliurament vàlid',
            default   => 'Lliurament incomplet',
        };
        $estatSub = match($estat) {
            'complet' => 'Tots els elements estan informats.',
            'valid'   => 'Falten només elements opcionals.',
            default   => 'Falten elements obligatoris.',
        };

        $defensaStr = '—';
        if (!empty($p['defensa_fecha'])) {
            $dt = new DateTime($p['defensa_fecha']);
            $defensaStr = $dt->format('d/m/Y') . ' · ' . $dt->format('H:i');
            if (!empty($p['aula_nom'])) $defensaStr .= ' · Aula ' . h($p['aula_nom']);
        }

        $tribunalStr = $tribunal
            ? implode(', ', array_map(fn($t) => h($t['nom']), $tribunal))
            : '—';

        $notes = calcularNotes($p, $alumnes);
        ?>

        <article class="card-entrega">

            <header class="card-header-projecte">
                <div class="card-header-main">
                    <div class="card-titol"><?= h($p['nombre'] ?: '(sense títol)') ?></div>
                    <div class="card-meta"><?= h($p['ciclo']) ?> · <?= h($p['grupo'] ?? '—') ?> · <?= h($p['curso_academico']) ?></div>
                </div>
                <div class="card-alumnes">
                    <span class="card-alumnes-label">Alumnes</span>
                    <?php foreach ($alumnes as $i => $al): ?>
                        <?= ($i > 0 ? '<br>' : '') . h($al['nom']) ?>
                    <?php endforeach ?>
                    <?php if (empty($alumnes)): ?>—<?php endif ?>
                </div>
            </header>

            <div class="card-cos">

                <section class="bloc-control">
                    <div class="bloc-titol">Presentació</div>
                    <div class="bloc-pills">
                        <?= renderPill($ckTitol,  'Títol',      true) ?>
                        <?= renderPill($ckImatge, 'Imatge',     false) ?>
                        <?= renderPill($ckResum,  'Resum',      false) ?>
                        <?= renderPill($ckDesc,   'Descripció', false) ?>
                    </div>
                </section>

                <section class="bloc-control">
                    <div class="bloc-titol">Documentació</div>
                    <div class="bloc-pills">
                        <?= renderPill($ckMemoria,   'Memòria',     true) ?>
                        <?= renderPill($ckFuncional, 'Funcional',   false) ?>
                        <?= renderPill($ckStack,     'Tecnologies', false) ?>
                        <?= renderPill($ckGestio,    'Gestió',      false) ?>
                        <?php if ($numArxius > 0): ?>
                            <span class="pill-check">✓ <?= $numArxius ?> doc<?= $numArxius > 1 ? 's' : '' ?> extra</span>
                        <?php endif ?>
                    </div>
                </section>

                <section class="bloc-control">
                    <div class="bloc-titol">Enllaços</div>
                    <div class="bloc-pills">
                        <?= renderPill($ckGithub, 'GitHub', false) ?>
                        <?= renderPill($ckDemo,   'Demo',   false) ?>
                        <?php if ($numEnllacos > 0): ?>
                            <span class="pill-check">✓ <?= $numEnllacos ?> enllaç<?= $numEnllacos > 1 ? 'os' : '' ?> extra</span>
                        <?php endif ?>
                    </div>
                </section>

                <section class="bloc-control">
                    <div class="bloc-titol">Autoavaluació</div>
                    <div class="bloc-pills">
                        <?= renderPill($ckAev1, 'Aprenentatges',      false) ?>
                        <?= renderPill($ckAev2, 'Parts fortes',       false) ?>
                        <?= renderPill($ckAev3, 'Parts incompletes',  false) ?>
                        <?= renderPill($ckAev4, 'Possibles millores', false) ?>
                    </div>
                </section>

                <section class="bloc-control">
                    <div class="bloc-titol">Estat</div>
                    <div class="estat-box <?= $estatClass ?>">
                        <?= $estatText ?>
                        <small><?= $estatSub ?></small>
                    </div>
                </section>

                <section class="bloc-control notas-box">
                    <div class="bloc-titol">Notes</div>

                    <?php if ($notes['base'] !== null): ?>
                        <div class="nota-global"><?= formatNota($notes['base']) ?></div>
                        <div class="nota-label">Nota global</div>
                    <?php else: ?>
                        <div class="nota-global nota-pendent">—</div>
                        <div class="nota-label">Nota pendent</div>
                    <?php endif ?>

                    <?php foreach ($notes['alumnes'] as $an): ?>
                        <div class="nota-alumne">
                            <span><?= h($an['nom']) ?></span>
                            <span class="nota-separador"></span>
                            <strong><?= formatNota($an['nota']) ?></strong>
                        </div>
                    <?php endforeach ?>

                    <?php if (empty($notes['alumnes'])): ?>
                        <div class="nota-alumne">
                            <span class="text-muted">Sense alumnes assignats</span>
                        </div>
                    <?php endif ?>
                </section>

            </div>

            <footer class="card-footer-projecte">
                <div class="footer-responsables">
                    <span><strong>Tutor:</strong> <?= h($p['tutor_nom'] ?? '—') ?></span>
                    <span><strong>Cotutor:</strong> <?= h($p['cotutor_nom'] ?? '—') ?></span>
                    <span><strong>Tribunal:</strong> <?= $tribunalStr ?></span>
                    <span><strong>Defensa:</strong> <?= $defensaStr ?></span>
                </div>
                <a href="/index.php?main=ficha&id=<?= $p['id_proyecto'] ?>" class="boto-fitxa">Veure fitxa</a>
            </footer>

        </article>

        <?php
    }
}
?>

<script>
window.PAGE_TITLE = 'Les meves defenses';
</script>

<div class="panel-entregues">

    <div class="projectes-header mb-4">
        <h2>Les meves defenses</h2>
        <?php if ($professor): ?>
            <div class="subtitol">
                Projectes assignats a <strong><?= h($professor['nombre'] . ' ' . $professor['apellidos']) ?></strong> com a membre del tribunal.
            </div>
        <?php endif ?>
    </div>

    <?php if (empty($projectes)): ?>
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <h3 class="h5 mb-2">Encara no tens cap defensa assignada</h3>
                <?php if (configuracion('seleccionar_defensas')): ?>
                    <p class="mb-0 text-muted">
                        Apunta't als tribunals des del <a href="/calendari-defenses">calendari de defenses</a>.
                    </p>
                <?php endif ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($per_data as $data => $projectes_dia): ?>
            <div class="projectes-grup-header mb-3 mt-4">
                <h3 class="projectes-grup-title mb-0"><?= h(nomDataLlarga($data)) ?></h3>
            </div>
            <?php foreach ($projectes_dia as $p): ?>
                <?php renderCardEntrega($p) ?>
            <?php endforeach ?>
        <?php endforeach ?>
    <?php endif ?>

</div>
