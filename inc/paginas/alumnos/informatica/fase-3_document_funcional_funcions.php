<?php
declare(strict_types=1);

function fase3DocumentFuncionalObtenirEstat(PDO $pdo, int $idProjecte): array
{
    $fila = [];
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare('SELECT funcional_url, funcional_pdf, funcional_validado_en FROM app.proyectos WHERE id_proyecto = :id');
        $stmt->execute([':id' => $idProjecte]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $url = trim((string) ($fila['funcional_url'] ?? ''));
    $pdf = trim((string) ($fila['funcional_pdf'] ?? ''));
    $validat = ($fila['funcional_validado_en'] ?? null) !== null;
    $solicitudOberta = null;
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare("SELECT id_revision_solicitud, solicitado_en FROM app.revisiones_solicitudes WHERE proyecto_id = :id AND tipo = 'funcional' AND resuelto_en IS NULL LIMIT 1");
        $stmt->execute([':id' => $idProjecte]);
        $solicitudOberta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $completada = $validat && $pdf !== '';
    $pendentPdf = $validat && !$completada;
    // Groc només mentre la intervenció correspon al tutor. Un cop validat,
    // si falta el PDF definitiu, el llenguatge és actiu/granate perquè ha
    // d'actuar l'alumnat.
    $atencion = $solicitudOberta !== null;
    return [
        'url' => $url, 'pdf' => $pdf, 'validat' => $validat,
        'completada' => $completada, 'atencion' => $atencion,
        'solicitud_oberta' => $solicitudOberta,
        'text' => $completada ? 'Validat' : ($pendentPdf ? 'Pendent de PDF' : ($solicitudOberta ? 'Revisió sol·licitada' : ($url !== '' ? 'En curs' : 'Pendent'))),
        'classe_badge' => $completada ? 'text-bg-success' : ($atencion ? 'text-bg-warning' : 'badge-activitat'),
        'classe_bloc' => $completada ? 'bloc-completat' : ($atencion ? 'bloc-atencio' : 'bloc-activitat'),
        'classe_cta' => $completada ? 'btn-outline-success' : ($atencion ? 'btn-atencio-solid' : 'btn-puig-solid'),
        'classe_outline' => $completada ? 'btn-outline-success' : ($atencion ? 'btn-atencio' : 'btn-puig'),
    ];
}

function fase3DocumentFuncionalData(?string $data): string
{
    if ($data === null || $data === '') return '';
    $marca = strtotime($data);
    return $marca !== false ? date('d/m/Y', $marca) : $data;
}
