<?php
declare(strict_types=1);

function emailAutoseguimentFeedback(
    string $nombre,
    int $semana,
    string $periodo,
    int $valoracion,
    string $comentario,
    string $url
): array {
    $etiquetaValoracion = match ($valoracion) {
        0 => 'Sense avanç',
        1 => 'Poc avanç',
        2 => 'Avanç adequat',
        3 => 'Avanç destacat',
        default => throw new InvalidArgumentException('Valoració d’autoseguiment no vàlida.'),
    };

    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $periodoSeguro = htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8');
    $valoracionSegura = htmlspecialchars($etiquetaValoracion, ENT_QUOTES, 'UTF-8');
    $comentarioSeguro = nl2br(htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8'), false);
    $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    $html = '<!doctype html><html lang="ca"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)">'
        . '<tr><td style="height:8px;background:#970A2C"></td></tr>'
        . '<tr><td style="padding:34px 40px 12px"><div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#970A2C">Web Projectes</div>'
        . '<h1 style="margin:12px 0 0;font-size:27px;line-height:1.25;color:#172033">Feedback de l’Autoseguiment</h1></td></tr>'
        . '<tr><td style="padding:12px 40px 36px;font-size:16px;line-height:1.65;color:#465166">'
        . '<p style="margin:0 0 18px">Hola ' . $nombreSeguro . ',</p>'
        . '<p style="margin:0 0 18px">El tutor o tutora ha valorat el teu seguiment de la <strong style="color:#172033">setmana ' . $semana . '</strong>, corresponent al període <strong style="color:#172033">' . $periodoSeguro . '</strong>.</p>'
        . '<div style="margin:0 0 20px;padding:14px 16px;background:#f8fafc;border-left:4px solid #970A2C;border-radius:6px">'
        . '<div style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#687386">Valoració del tutor</div>'
        . '<div style="margin-top:4px;font-size:17px;font-weight:700;color:#172033">' . $valoracionSegura . '</div></div>'
        . ($comentario !== ''
            ? '<div style="margin:0 0 22px;padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px"><div style="margin-bottom:6px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#687386">Comentari del tutor</div><div style="color:#465166">' . $comentarioSeguro . '</div></div>'
            : '')
        . '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:8px;background:#970A2C">'
        . '<a href="' . $urlSegura . '" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700">Veure l’Autoseguiment</a>'
        . '</td></tr></table>'
        . '<p style="margin:24px 0 0;font-size:14px;color:#687386">La informació actualitzada i l’historial complet continuen disponibles a Web Projectes.</p>'
        . '</td></tr><tr><td style="padding:20px 40px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#7b8494">Projectes · Puig Castellar</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Hola {$nombre},\n\n"
        . "El tutor o tutora ha valorat el teu seguiment de la setmana {$semana}, corresponent al període {$periodo}.\n\n"
        . "Valoració del tutor: {$etiquetaValoracion}\n\n"
        . ($comentario !== '' ? "Comentari del tutor:\n{$comentario}\n\n" : '')
        . "Veure l’Autoseguiment: {$url}\n\n"
        . "La informació actualitzada i l’historial complet continuen disponibles a Web Projectes.";

    return ['html' => $html, 'text' => $text];
}
