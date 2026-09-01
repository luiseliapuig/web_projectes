<?php
declare(strict_types=1);

// Recordatori periòdic de calendari (no una alerta d'incompliment): s'envia a
// tot l'alumnat dins del període actiu, hagi o no completat l'autoseguiment.
function emailAutoseguimentRecordatori(string $nombre, string $dataLimitText, string $url): array
{
    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $dataLimitSegura = htmlspecialchars($dataLimitText, ENT_QUOTES, 'UTF-8');

    $botoHtml = $urlSegura !== ''
        ? '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:8px;background:#970A2C">'
            . '<a href="' . $urlSegura . '" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700">Anar a l’Autoseguiment</a>'
            . '</td></tr></table>'
        : '';
    $dataLimitHtml = $dataLimitSegura !== ''
        ? '<p style="margin:0 0 24px;font-size:14px;color:#687386"><strong>Data límit:</strong> ' . $dataLimitSegura . '</p>'
        : '';

    $html = '<!doctype html><html lang="ca"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)">'
        . '<tr><td style="height:8px;background:#970A2C"></td></tr>'
        . '<tr><td style="padding:34px 40px 12px"><div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#970A2C">Web Projectes</div>'
        . '<h1 style="margin:12px 0 0;font-size:27px;line-height:1.25;color:#172033">Autoseguiment setmanal</h1></td></tr>'
        . '<tr><td style="padding:12px 40px 36px;font-size:16px;line-height:1.65;color:#465166">'
        . '<p style="margin:0 0 18px">Hola ' . $nombreSeguro . ',</p>'
        . '<p style="margin:0 0 18px">Recorda que tens fins diumenge a les 23:59 per completar l’autoseguiment d’aquesta setmana.</p>'
        . '<p style="margin:0 0 18px">Revisa que hi hagis indicat la feina realitzada, les possibles incidències i l’objectiu que et marques per a la setmana següent.</p>'
        . '<p style="margin:0 0 24px">Pots consultar-lo i modificar-lo tantes vegades com vulguis fins al tancament de la setmana des de l’apartat Autoseguiment de la web de Projectes.</p>'
        . $dataLimitHtml
        . $botoHtml
        . '<p style="margin:24px 0 0;font-size:14px">Gràcies!</p>'
        . '</td></tr><tr><td style="padding:20px 40px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#7b8494">Projectes · Puig Castellar</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Hola $nombre,\n\n"
        . "Recorda que tens fins diumenge a les 23:59 per completar l’autoseguiment d’aquesta setmana.\n\n"
        . "Revisa que hi hagis indicat la feina realitzada, les possibles incidències i l’objectiu que et marques per a la setmana següent.\n\n"
        . "Pots consultar-lo i modificar-lo tantes vegades com vulguis fins al tancament de la setmana des de l’apartat Autoseguiment de la web de Projectes."
        . ($dataLimitText !== '' ? "\n\nData límit: $dataLimitText" : '')
        . ($url !== '' ? "\n\nAccedeix-hi aquí: $url" : '')
        . "\n\nGràcies!";

    return ['html' => $html, 'text' => $text];
}
