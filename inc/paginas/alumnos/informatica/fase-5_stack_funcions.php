<?php
declare(strict_types=1);

function fase5StackObtenirEstat(PDO $pdo, int $projecteId): array
{
    if ($projecteId <= 0) {
        return ['tecnologies' => [], 'eines' => [], 'completada' => false];
    }

    $consultes = [
        'tecnologies' => 'SELECT t.id, t.nombre, t.descripcion, t.url, t.activo, t.propuesto_en
            FROM app.rel_proyectos_tecnologias r
            INNER JOIN app.tecnologias t ON t.id = r.tecnologia_id
            WHERE r.proyecto_id = :projecte
            ORDER BY t.nombre',
        'eines' => 'SELECT h.id, h.nombre, h.descripcion, h.url, h.activo, h.propuesto_en
            FROM app.rel_proyectos_herramientas r
            INNER JOIN app.herramientas h ON h.id = r.herramienta_id
            WHERE r.proyecto_id = :projecte
            ORDER BY h.nombre',
    ];

    $estat = [];
    foreach ($consultes as $clau => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':projecte' => $projecteId]);
        $estat[$clau] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $estat['completada'] = $estat['tecnologies'] !== [];
    return $estat;
}
