<?php
declare(strict_types=1);

function fase7PresentacioDefensaObtenirEstat(PDO $pdo, int $projecteId): array
{
    $pdf = '';
    if ($projecteId > 0) {
        $stmt = $pdo->prepare('SELECT presentacion_pdf FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $projecteId]);
        $pdf = trim((string) $stmt->fetchColumn());
    }

    return [
        'pdf' => $pdf,
        'pdf_url' => $pdf === '' ? '' : '/' . ltrim($pdf, '/'),
        'completada' => $pdf !== '',
    ];
}
