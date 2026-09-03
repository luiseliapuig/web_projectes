<?php
declare(strict_types=1);

function emailPreparacioEntornRevisioSolicitada(string $tutor, string $alumnat, string $projecte, string $url): array
{
    $tutorSegur = htmlspecialchars($tutor, ENT_QUOTES, 'UTF-8');
    $alumnatSegur = htmlspecialchars($alumnat, ENT_QUOTES, 'UTF-8');
    $projecteSegur = htmlspecialchars($projecte, ENT_QUOTES, 'UTF-8');
    $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    $html = '<!doctype html><html lang="ca"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)">'
        . '<tr><td style="height:8px;background:#970A2C"></td></tr>'
        . '<tr><td style="padding:34px 40px 12px"><div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#970A2C">Web Projectes</div>'
        . '<h1 style="margin:12px 0 0;font-size:27px;line-height:1.25;color:#172033">Revisió de la Preparació de l’entorn</h1></td></tr>'
        . '<tr><td style="padding:12px 40px 36px;font-size:16px;line-height:1.65;color:#465166">'
        . '<p style="margin:0 0 18px">Hola ' . $tutorSegur . ',</p>'
        . '<p style="margin:0 0 24px"><strong style="color:#172033">' . $alumnatSegur . '</strong> ha sol·licitat la revisió de la Preparació de l’entorn de desenvolupament del projecte <strong style="color:#172033">' . $projecteSegur . '</strong>.</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:8px;background:#970A2C">'
        . '<a href="' . $urlSegura . '" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700">Revisar la Preparació de l’entorn</a>'
        . '</td></tr></table>'
        . '<p style="margin:24px 0 0;font-size:14px;color:#687386">Pots consultar el document viu i, quan correspongui, validar-lo des d’aquesta pantalla.</p>'
        . '</td></tr><tr><td style="padding:20px 40px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#7b8494">Projectes · Puig Castellar</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Hola {$tutor},\n\n"
        . "{$alumnat} ha sol·licitat la revisió de la Preparació de l’entorn de desenvolupament del projecte {$projecte}.\n\n"
        . "Revisa-la aquí: {$url}\n\n"
        . "Pots consultar el document viu i, quan correspongui, validar-la des d’aquesta pantalla.";

    return ['html' => $html, 'text' => $text];
}
