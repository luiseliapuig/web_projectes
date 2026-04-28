<?php
// calendari_tribunal_dades.php
// Endpoint raw.
// FIX DEFINITIU: PDO+PostgreSQL no accepta array d'ints via execute() per a IN().
// Els $ids s'interpolen directament a la query (segur: tots forçats a intval).
// Crida: /index.php?main=calendari_tribunal_dades&raw=1

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['professor_id'])) {
    echo json_encode(['ok' => false, 'missatge' => 'Sessió no vàlida']);
    exit;
}

$profId       = (int)$_SESSION['professor_id'];
$esSuperadmin = esSuperadmin();

try {
    // ── 1. Projectes amb defensa planificada ──────────────────────────
    $projectes = $pdo->query("
        SELECT
            p.id_proyecto, p.uuid, p.nombre, p.ciclo, p.grupo,
            p.defensa_aula_id,
            a.codigo AS aula_codigo, a.nombre AS aula_nombre,
            TO_CHAR(p.defensa_fecha, 'YYYY-MM-DD') AS dia,
            TO_CHAR(p.defensa_fecha, 'HH24:MI')    AS hora,
            CASE WHEN EXTRACT(HOUR FROM p.defensa_fecha) < 14 THEN 'mati' ELSE 'tarda' END AS torn
        FROM app.proyectos p
        JOIN app.aulas a ON a.id_aula = p.defensa_aula_id
        WHERE p.defensa_fecha IS NOT NULL AND p.defensa_aula_id IS NOT NULL
        ORDER BY p.defensa_fecha, a.codigo
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projectes as &$p) {
        $p['id_proyecto']     = (int)$p['id_proyecto'];
        $p['defensa_aula_id'] = (int)$p['defensa_aula_id'];
    }
    unset($p);

    // IDs com a ints validats — segur per interpolar
    $ids = array_map('intval', array_column($projectes, 'id_proyecto'));

    if (!$ids) {
        echo json_encode([
            'ok' => true, 'blocs' => ['mati' => null, 'tarda' => null],
            'prof_id_actual' => $profId, 'es_superadmin' => $esSuperadmin,
            'professors_disponibles' => [], 'duracio_min' => 45, 'stats' => null,
        ]);
        exit;
    }

    // String d'ids per interpolar directament (tots intval, cap risc d'injecció)
    $idsStr = implode(',', $ids);

    // ── 2. Duració real ───────────────────────────────────────────────
    $horesUniques = [];
    foreach ($projectes as $p) { $horesUniques[$p['hora']] = true; }
    ksort($horesUniques);
    $horesArr   = array_keys($horesUniques);
    $duracioMin = 45;

    if (count($horesArr) >= 2) {
        $minDif = PHP_INT_MAX;
        for ($i = 1; $i < count($horesArr); $i++) {
            [$hh1, $mm1] = explode(':', $horesArr[$i - 1]);
            [$hh2, $mm2] = explode(':', $horesArr[$i]);
            $dif = ((int)$hh2 * 60 + (int)$mm2) - ((int)$hh1 * 60 + (int)$mm1);
            if ($dif > 0 && $dif < $minDif) $minDif = $dif;
        }
        if ($minDif !== PHP_INT_MAX) $duracioMin = $minDif;
    } else {
        $cfgRow = $pdo->query("SELECT valor FROM app.config WHERE clave = 'duracio_defensa_min' LIMIT 1")->fetchColumn();
        if ($cfgRow) $duracioMin = (int)$cfgRow;
    }

    // ── 3. Alumnes per projecte (query directa, sense placeholders) ───
    $alumnesMap = [];
    $rowsAl = $pdo->query("
        SELECT rpa.proyecto_id, al.nombre || ' ' || al.apellidos AS nom_complet
        FROM app.rel_proyectos_alumnos rpa
        JOIN app.alumnos al ON al.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id IN ($idsStr)
        ORDER BY al.apellidos, al.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsAl as $r) {
        $alumnesMap[(int)$r['proyecto_id']][] = $r['nom_complet'];
    }

    // ── 4. Tribunal per projecte (query directa, sense placeholders) ──
    $tribunalMap = [];
    $rowsTrib = $pdo->query("
        SELECT rpt.id_proyecto, rpt.profesor_id,
               pr.nombre || ' ' || pr.apellidos AS nom_complet
        FROM app.rel_profesores_tribunal rpt
        JOIN app.profesores pr ON pr.id_profesor = rpt.profesor_id
        WHERE rpt.id_proyecto IN ($idsStr)
        ORDER BY pr.apellidos, pr.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsTrib as $r) {
        $pIdProj = (int)$r['id_proyecto'];
        $pIdProf = (int)$r['profesor_id'];
        $tribunalMap[$pIdProj][] = [
            'profesor_id' => $pIdProf,
            'nom_complet' => $r['nom_complet'],
            'es_jo'       => ($pIdProf === $profId),
        ];
    }

    // ── 5. Stats ──────────────────────────────────────────────────────
    $totalProjectes = count($ids);
    $totalOcupades  = 0;
    $apuntatsJo     = 0;
    foreach ($tribunalMap as $membres) {
        $totalOcupades += count($membres);
        foreach ($membres as $m) {
            if ($m['es_jo']) { $apuntatsJo++; break; }
        }
    }
    $profsActius   = (int)$pdo->query("SELECT COUNT(*) FROM app.profesores WHERE activo = true")->fetchColumn();
    $placesLliures = max(0, $totalProjectes * 3 - $totalOcupades);
    $mitjanaToca   = $profsActius > 0 ? $placesLliures / $profsActius : 0;
    // Rang personal: quantes en li queden a ell específicament
    $rangMin       = max(0, (int)floor($mitjanaToca) - $apuntatsJo);
    $rangMax       = max(0, (int)ceil($mitjanaToca)  - $apuntatsJo);
    if ($rangMin === $rangMax && $rangMin > 0) $rangMax++;

    // ── 6. Professors per modal admin ─────────────────────────────────
    $profsList = $pdo->query("
        SELECT p.id_profesor, p.nombre || ' ' || p.apellidos AS nom_complet,
               COUNT(rpt.id_proyecto) AS total_tribunals
        FROM app.profesores p
        LEFT JOIN app.rel_profesores_tribunal rpt ON rpt.profesor_id = p.id_profesor
        WHERE p.activo = true
        GROUP BY p.id_profesor, p.nombre, p.apellidos
        ORDER BY total_tribunals ASC, p.apellidos, p.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($profsList as &$pr) { $pr['id_profesor'] = (int)$pr['id_profesor']; }
    unset($pr);

    // ── 7. Blocs ──────────────────────────────────────────────────────
    $blocs       = ['tarda' => null, 'mati' => null];
    $projPerTorn = ['mati' => [], 'tarda' => []];
    foreach ($projectes as $p) { $projPerTorn[$p['torn']][] = $p; }

    foreach (['tarda', 'mati'] as $torn) {
        $pt = $projPerTorn[$torn];
        if (!$pt) continue;

        $diesSet = []; $horesSet = [];
        foreach ($pt as $p) { $diesSet[$p['dia']] = true; $horesSet[$p['hora']] = true; }
        ksort($diesSet); ksort($horesSet);
        $dies  = array_keys($diesSet);
        $hores = array_keys($horesSet);

        $celdas  = [];
        $maxCols = 0;
        foreach ($dies as $dia) {
            foreach ($hores as $hora) { $celdas[$dia][$hora] = []; }
        }

        foreach ($pt as $p) {
            $projId  = (int)$p['id_proyecto'];
            $membres = $tribunalMap[$projId] ?? [];

            $jaApuntat = false;
            foreach ($membres as $m) {
                if ($m['es_jo']) { $jaApuntat = true; break; }
            }

            $celdas[$p['dia']][$p['hora']][] = [
                'id'            => $projId,
                'uuid'          => $p['uuid'],
                'nom'           => $p['nombre'] ?? '—',
                'cicle'         => $p['ciclo'],
                'grup'          => $p['grupo'],
                'aula_id'       => $p['defensa_aula_id'],
                'aula_codigo'   => $p['aula_codigo'],
                'aula_nombre'   => $p['aula_nombre'],
                'hora'          => $p['hora'],
                'alumnes'       => $alumnesMap[$projId] ?? [],
                'tribunal'      => $membres,
                'slots_lliures' => max(0, 3 - count($membres)),
                'ja_apuntat'    => $jaApuntat,
            ];
        }

        foreach ($dies as $dia) {
            foreach ($hores as $hora) {
                $n = count($celdas[$dia][$hora]);
                if ($n > $maxCols) $maxCols = $n;
            }
        }

        $blocs[$torn] = [
            'dies'        => $dies,
            'hores'       => $hores,
            'max_cols'    => $maxCols,
            'duracio_min' => $duracioMin,
            'celdas'      => $celdas,
        ];
    }

    echo json_encode([
        'ok'                     => true,
        'blocs'                  => $blocs,
        'prof_id_actual'         => $profId,
        'es_superadmin'          => $esSuperadmin,
        'professors_disponibles' => $profsList,
        'duracio_min'            => $duracioMin,
        'stats'                  => [
            'total_projectes' => $totalProjectes,
            'places_lliures'  => $placesLliures,
            'profs_actius'    => $profsActius,
            'apuntats_jo'     => $apuntatsJo,
            'rang_min'        => $rangMin,
            'rang_max'        => $rangMax,
        ],
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'missatge' => 'Error intern: ' . $e->getMessage()]);
}
