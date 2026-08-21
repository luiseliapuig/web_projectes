<?php
declare(strict_types=1);

function emailPasswordReset(string $nombre, string $url, int $minutos): array
{
    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="ca"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)">'
        . '<tr><td style="height:8px;background:#a50034"></td></tr>'
        . '<tr><td style="padding:34px 40px 12px"><div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#a50034">Web Projectes</div>'
        . '<h1 style="margin:12px 0 0;font-size:27px;line-height:1.25;color:#172033">Restableix la contrasenya</h1></td></tr>'
        . '<tr><td style="padding:12px 40px 36px;font-size:16px;line-height:1.65;color:#465166">'
        . '<p style="margin:0 0 18px">Hola ' . $nombreSeguro . ',</p>'
        . '<p style="margin:0 0 24px">Hem rebut una sol·licitud per crear o restablir la teva contrasenya d’accés.</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:8px;background:#a50034">'
        . '<a href="' . $urlSegura . '" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700">Restablir contrasenya</a>'
        . '</td></tr></table>'
        . '<p style="margin:24px 0 0;font-size:14px">L’enllaç caduca en ' . $minutos . ' minuts i només es pot utilitzar una vegada.</p>'
        . '<p style="margin:18px 0 0;font-size:14px;color:#687386">Si no has fet aquesta sol·licitud, pots ignorar el missatge. La teva contrasenya no canviarà.</p>'
        . '</td></tr><tr><td style="padding:20px 40px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#7b8494">'
        . 'Aquest és un missatge automàtic de Web Projectes. Si respons, el missatge arribarà al centre.'
        . '</td></tr></table></td></tr></table></body></html>';

    $text = "Hola $nombre,\n\n"
        . "Hem rebut una sol·licitud per crear o restablir la teva contrasenya d’accés.\n\n"
        . "Obre aquest enllaç: $url\n\n"
        . "L’enllaç caduca en $minutos minuts i només es pot utilitzar una vegada.\n\n"
        . "Si no has fet aquesta sol·licitud, pots ignorar el missatge.";

    return ['html' => $html, 'text' => $text];
}
