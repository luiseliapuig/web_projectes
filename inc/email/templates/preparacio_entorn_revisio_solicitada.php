<?php
declare(strict_types=1);

function emailPreparacioEntornRevisioSolicitada(string $tutor, string $alumnat, string $projecte, string $url): array
{
    $h = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    $text = "Hola {$tutor},\n\n{$alumnat} ha sol·licitat la revisió de la preparació de l’entorn de desenvolupament del projecte {$projecte}.\n\nRevisa-la aquí: {$url}";
    $html = '<p>Hola ' . $h($tutor) . ',</p><p>' . $h($alumnat) . ' ha sol·licitat la revisió de la preparació de l’entorn de desenvolupament del projecte <strong>' . $h($projecte) . '</strong>.</p><p><a href="' . $h($url) . '">Revisar la preparació de l’entorn</a></p>';
    return ['html' => $html, 'text' => $text];
}
