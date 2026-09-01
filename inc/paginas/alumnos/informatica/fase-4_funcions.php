<?php
declare(strict_types=1);

function fase4PlanificacioGestioObtenirEstat(PDO $pdo, int $idProyecto): array
{
    $fila = [];
    if ($idProyecto > 0) {
        $stmt = $pdo->prepare('SELECT planificacion_url, gestion_url FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $idProyecto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $planificacioUrl = trim((string) ($fila['planificacion_url'] ?? ''));
    $gestioUrl = trim((string) ($fila['gestion_url'] ?? ''));

    return [
        'planificacio_url' => $planificacioUrl,
        'gestio_url' => $gestioUrl,
        'planificacio_completada' => $planificacioUrl !== '',
        'gestio_completada' => $gestioUrl !== '',
        'completada' => $planificacioUrl !== '' && $gestioUrl !== '',
    ];
}
