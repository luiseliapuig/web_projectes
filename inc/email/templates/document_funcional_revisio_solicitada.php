<?php
declare(strict_types=1);

function emailDocumentFuncionalRevisioSolicitada(string $tutor, string $alumnat, string $projecte, string $url): array
{
    $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $text = "Hola {$tutor},\n\n{$alumnat} ha sol·licitat la revisió del document funcional del projecte {$projecte}.\n\nRevisa'l aquí: {$url}";
    $html = '<p>Hola ' . $h($tutor) . ',</p><p>' . $h($alumnat) . ' ha sol·licitat la revisió del document funcional del projecte <strong>' . $h($projecte) . '</strong>.</p><p><a href="' . $h($url) . '">Revisar el document funcional</a></p>';
    return ['html' => $html, 'text' => $text];
}
