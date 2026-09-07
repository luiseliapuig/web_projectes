<?php
declare(strict_types=1);

// Dades compactes de la darrera promoció finalitzada per a la capçalera.
require_once __DIR__ . '/projectes-publics_funcions.php';

$orden_ciclos = ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
$cursoActual = cursoAcademicoActual();

if (!preg_match('/^([0-9]{4})-[0-9]{2}$/', $cursoActual, $coincidenciaCurso)) {
    throw new RuntimeException('El curs acadèmic actual no té el format canònic.');
}

$inicioPromocion = (int) $coincidenciaCurso[1] - 1;
$finPromocion = $inicioPromocion + 1;
$promocionCurso = sprintf('%d-%02d', $inicioPromocion, $finPromocion % 100);
$promocionTitulo = sprintf('%d–%d', $inicioPromocion, $finPromocion);

$sql = "
    SELECT
        c.abr AS ciclo,
        COUNT(*) AS total
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    WHERE p.curso_academico = :promocion_curso
      AND " . projectesPublicsCondicioSql('p') . "
    GROUP BY c.abr
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge(
    [':promocion_curso' => $promocionCurso],
    projectesPublicsParametres()
));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cicles_map = [];
foreach ($rows as $row) {
    $ciclo = trim((string) $row['ciclo']);
    $cicles_map[$ciclo] = (int) $row['total'];
}

$cicles = [];
foreach ($orden_ciclos as $ciclo) {
    if (isset($cicles_map[$ciclo])) {
        $cicles[] = ['ciclo' => $ciclo, 'total' => $cicles_map[$ciclo]];
    }
}

foreach ($cicles_map as $ciclo => $total) {
    if (!in_array($ciclo, $orden_ciclos, true)) {
        $cicles[] = ['ciclo' => $ciclo, 'total' => $total];
    }
}

$total_projectes = array_sum(array_column($cicles, 'total'));
