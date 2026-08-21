<?php
declare(strict_types=1);

$faseActiva = isset($faseActiva) ? (int) $faseActiva : 0;
$navegacionBloqueada = !empty($fasesNavegacionBloqueada);
$faseUnoCompletada = false;
if (!$navegacionBloqueada) {
    $stmtEstadoFaseUno = $pdo->prepare("
        SELECT rpa.grupo_trabajo_confirmado_en IS NOT NULL
               AND rpa.compromiso_trabajo_aceptado = true
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.proyectos p ON p.id_proyecto = rpa.proyecto_id
        WHERE rpa.alumno_id = :alumno_id
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
        ORDER BY p.id_proyecto DESC
        LIMIT 1
    ");
    $stmtEstadoFaseUno->execute([
        ':alumno_id' => (int) ($_SESSION['alumno_id'] ?? 0),
        ':curso_academico' => cursoAcademicoActual(),
    ]);
    $faseUnoCompletada = (bool) $stmtEstadoFaseUno->fetchColumn();
}
$fasesProyecto = [
    1 => "Pluja d’idees\ni formació de grups",
    2 => 'Proposta de projecte',
    3 => 'Document funcional',
    4 => 'Planificació i gestió',
    5 => "Desenvolupament\ndel projecte",
    6 => 'Memòria',
    7 => 'Defensa',
];
?>
<nav class="projecte-fases-nav" aria-label="Fases del projecte">
    <?php foreach ($fasesProyecto as $numeroFase => $titolFase): ?>
        <?php $elementoNavegacion = $navegacionBloqueada ? 'span' : 'a'; ?>
        <?php $faseMaximaDisponible = $faseUnoCompletada ? 2 : 1; ?>
        <?php $faseBloqueadaApariencia = !$navegacionBloqueada && $numeroFase > $faseMaximaDisponible; ?>
        <?php $faseCompletadaApariencia = !$navegacionBloqueada && $faseUnoCompletada && $numeroFase === 1; ?>
        <<?= $elementoNavegacion ?>
            class="projecte-fase-enllac <?= $faseActiva === $numeroFase ? 'active' : '' ?> <?= $navegacionBloqueada ? 'projecte-fase-enllac-disabled' : '' ?> <?= $faseBloqueadaApariencia ? 'projecte-fase-enllac-pendent' : '' ?> <?= $faseCompletadaApariencia ? 'projecte-fase-enllac-completada' : '' ?>"
            <?= $navegacionBloqueada ? 'aria-disabled="true"' : 'href="/el-meu-projecte/fase-' . $numeroFase . '"' ?>
            <?= $faseActiva === $numeroFase ? 'aria-current="page"' : '' ?>
        >
            <span class="projecte-fase-fletxa"><i class="bi <?= $faseBloqueadaApariencia ? 'bi-lock-fill' : ($faseCompletadaApariencia ? 'bi-check-lg' : 'bi-chevron-right') ?>" aria-hidden="true"></i></span>
            <span>
                <small>Fase <?= $numeroFase ?></small>
                <strong><?= nl2br(htmlspecialchars($titolFase, ENT_QUOTES, 'UTF-8'), false) ?></strong>
            </span>
        </<?= $elementoNavegacion ?>>
    <?php endforeach; ?>
</nav>
