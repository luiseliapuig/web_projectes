<?php
declare(strict_types=1);

function detectarExtensionImagen(array $file): ?string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) return null;
    return match ($imageInfo[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
}

function guardarImagenWeb(array $file, string $rutaDestinoAbs, int $maxAncho = 1600, int $maxAlto = 1200, int $calidadJpg = 85): bool
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return false;
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) return false;
    $extension = detectarExtensionImagen($file);
    if ($extension === null) return false;
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) return false;
    [$anchoOriginal, $altoOriginal, $tipo] = $imageInfo;
    if ($anchoOriginal <= 0 || $altoOriginal <= 0) return false;
    // La pipeline legacy depèn de GD. Cal detectar-ho abans de cridar les
    // funcions perquè un servidor sense l'extensió respongui de manera
    // controlada en lloc d'interrompre l'endpoint amb un fatal sense JSON.
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) return false;
    if ($tipo === IMAGETYPE_JPEG && !function_exists('imagecreatefromjpeg')) return false;
    if ($tipo === IMAGETYPE_PNG && !function_exists('imagecreatefrompng')) return false;
    if ($tipo === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) return false;
    $ratio = min($maxAncho / $anchoOriginal, $maxAlto / $altoOriginal, 1);
    $nuevoAncho = (int) round($anchoOriginal * $ratio);
    $nuevoAlto = (int) round($altoOriginal * $ratio);
    $origen = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        default => false,
    };
    if ($origen === false) return false;
    $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    if ($destino === false) {
        imagedestroy($origen);
        return false;
    }
    $blanco = imagecolorallocate($destino, 255, 255, 255);
    imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, $blanco);
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOriginal, $altoOriginal);
    $ok = imagejpeg($destino, $rutaDestinoAbs, $calidadJpg);
    imagedestroy($origen);
    imagedestroy($destino);
    return $ok;
}
