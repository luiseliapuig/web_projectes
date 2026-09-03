<?php
declare(strict_types=1);

function emailMemoriaApartatRevisat(
    string $alumne,
    string $projecte,
    string $apartat,
    string $estat,
    string $comentari,
    string $url
): array {
    $configEstat = match ($estat) {
        'corregir' => [
            'etiqueta' => 'Cal corregir',
            'missatge' => 'Aquest apartat necessita canvis abans de poder-se validar.',
        ],
        'completo' => [
            'etiqueta' => 'Apartat validat',
            'missatge' => 'Aquest apartat ha estat validat pel tutor o tutora.',
        ],
        default => throw new InvalidArgumentException('Estat de revisió de memòria no vàlid.'),
    };

    $alumneSegur = htmlspecialchars($alumne, ENT_QUOTES, 'UTF-8');
    $projecteSegur = htmlspecialchars($projecte, ENT_QUOTES, 'UTF-8');
    $apartatSegur = htmlspecialchars($apartat, ENT_QUOTES, 'UTF-8');
    $etiquetaSegura = htmlspecialchars($configEstat['etiqueta'], ENT_QUOTES, 'UTF-8');
    $missatgeSegur = htmlspecialchars($configEstat['missatge'], ENT_QUOTES, 'UTF-8');
    $comentariSegur = nl2br(htmlspecialchars($comentari, ENT_QUOTES, 'UTF-8'), false);
    $urlSegura = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    $html = '<!doctype html><html lang="ca"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)">'
        . '<tr><td style="height:8px;background:#970A2C"></td></tr>'
        . '<tr><td style="padding:34px 40px 12px"><div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#970A2C">Web Projectes</div>'
        . '<h1 style="margin:12px 0 0;font-size:27px;line-height:1.25;color:#172033">Revisió d’un apartat de Memòria</h1></td></tr>'
        . '<tr><td style="padding:12px 40px 36px;font-size:16px;line-height:1.65;color:#465166">'
        . '<p style="margin:0 0 18px">Hola ' . $alumneSegur . ',</p>'
        . '<p style="margin:0 0 18px">El tutor o tutora ha revisat l’apartat <strong style="color:#172033">' . $apartatSegur . '</strong> de la Memòria del projecte <strong style="color:#172033">' . $projecteSegur . '</strong>.</p>'
        . '<div style="margin:0 0 20px;padding:14px 16px;background:#f8fafc;border-left:4px solid #970A2C;border-radius:6px">'
        . '<div style="font-weight:700;color:#172033">' . $etiquetaSegura . '</div>'
        . '<div style="margin-top:4px;font-size:14px;color:#687386">' . $missatgeSegur . '</div></div>'
        . ($comentari !== ''
            ? '<div style="margin:0 0 22px;padding:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px"><div style="margin-bottom:6px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#9a3412">Comentari del tutor</div><div style="color:#465166">' . $comentariSegur . '</div></div>'
            : '')
        . '<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:8px;background:#970A2C">'
        . '<a href="' . $urlSegura . '" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-weight:700">Veure la revisió</a>'
        . '</td></tr></table>'
        . '<p style="margin:24px 0 0;font-size:14px;color:#687386">La revisió i l’historial complet de comentaris continuen disponibles a Web Projectes.</p>'
        . '</td></tr><tr><td style="padding:20px 40px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.5;color:#7b8494">Projectes · Puig Castellar</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Hola {$alumne},\n\n"
        . "El tutor o tutora ha revisat l’apartat {$apartat} de la Memòria del projecte {$projecte}.\n\n"
        . $configEstat['etiqueta'] . ". " . $configEstat['missatge'] . "\n\n"
        . ($comentari !== '' ? "Comentari del tutor:\n{$comentari}\n\n" : '')
        . "Veure la revisió: {$url}\n\n"
        . "La revisió i l’historial complet de comentaris continuen disponibles a Web Projectes.";

    return ['html' => $html, 'text' => $text];
}
