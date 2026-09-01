<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Capa única per guardar PDFs DEFINITIUS d'un projecte (document funcional,
// memòria, proposta, fitxa d'entrega, adjunts i qualsevol futur PDF de
// projecte). Cap tasca ha d'implementar per separat la validació o la
// compressió: totes reutilitzen pdfGuardarDefinitiu().
//
// Origen: substitueix les dues còpies quasi idèntiques de comprimirPdf() que
// hi havia a ficha_proyecto_accion.php i ficha_proyecto_adjunto_accion.php
// (mecanisme V1, basat en Ghostscript). fase-2_accion.php (Proposta de
// projecte, V2) pujava el PDF definitiu sense passar mai per aquest pas —
// aquest fitxer també ho corregeix.
//
// Estructura de destí: SEMPRE uploads/{curso_academico}/{abr_ciclo}/{id_proyecto}/
// — mai una altra jerarquia. pdfResoldreDirectoriProjecte() és l'únic lloc
// que la construeix.
//
// Política davant fallada de l'optimitzador (Ghostscript pot no estar
// instal·lat, petar, o trigar): l'optimització és "best effort" i s'aplica
// SEMPRE sobre un fitxer temporal, mai sobre el definitiu ja publicat. Si
// falla o produeix un resultat pitjor/invàlid, es conserva el PDF original
// vàlid tal qual. El fitxer final (rutaAbs) no existeix mai amb contingut a
// mig escriure: només es publica (rename) un cop es té un resultat final
// vàlid, optimitzat o no. Si pdfGuardarDefinitiu() retorna ok=false, el
// cridant NO ha de desar cap ruta a BD.
// -----------------------------------------------------------------------------

const PDF_MIDA_MAXIMA_BYTES = 20 * 1024 * 1024;

// Mateix criteri ja establert a fases/funciones.php i ficha_proyecto*.php per
// a trams de ruta (curs acadèmic, abreviació de cicle): només alfanumèric,
// guionet i guió baix. Mai es construeix una ruta amb el valor cru rebut.
function pdfSanejarTramRuta(string $valor): string
{
    $valor = trim($valor);
    $valor = preg_replace('/[^A-Za-z0-9\-_]/', '', $valor);
    return (string) $valor;
}

// Nom de fitxer segur: sense separadors de directori ni ".." (evita path
// traversal), i ha d'acabar en ".pdf". El cridant decideix el nom lògic
// (fix, com "proposta.pdf", o derivat d'un slug ja sanejat); aquesta funció
// només el neteja, no en decideix el contingut.
function pdfNomFitxerSegur(string $nom): string
{
    $nom = trim($nom);
    $nom = str_replace(['\\', '/'], '', $nom);
    $nom = preg_replace('/\.\.+/', '.', (string) $nom);
    $nom = preg_replace('/[^A-Za-z0-9\-_.]/', '', (string) $nom);
    $nom = (string) $nom;
    if ($nom === '' || strtolower(pathinfo($nom, PATHINFO_EXTENSION)) !== 'pdf') {
        return '';
    }
    return $nom;
}

// Únic lloc que construeix la ruta d'un projecte dins uploads/: sempre
// uploads/{curso_academico}/{abr_ciclo}/{id_proyecto}/. Retorna NULL si
// qualsevol component és buit o invàlid (mai una ruta a mitges).
function pdfResoldreDirectoriProjecte(string $cursoAcademico, string $ciclo, int $idProyecto): ?array
{
    $curs  = pdfSanejarTramRuta($cursoAcademico);
    $cicle = pdfSanejarTramRuta($ciclo);
    if ($curs === '' || $cicle === '' || $idProyecto <= 0) {
        return null;
    }
    $baseAbs = dirname(__DIR__, 2) . '/uploads';
    return [
        'abs' => $baseAbs . '/' . $curs . '/' . $cicle . '/' . $idProyecto,
        'rel' => '/uploads/' . $curs . '/' . $cicle . '/' . $idProyecto,
    ];
}

// Resol una ruta pública ja desada a BD contra uploads/ i només retorna el
// fitxer físic si queda realment dins d'aquesta arrel. Serveix per retirar
// una versió anterior després d'haver persistit correctament la nova.
function pdfResoldreRutaLocalSegura(string $ruta): ?string
{
    $camiUrl = (string) parse_url(trim($ruta), PHP_URL_PATH);
    if (!str_starts_with($camiUrl, '/uploads/')) {
        return null;
    }
    $arrelUploads = realpath(dirname(__DIR__, 2) . '/uploads');
    $fitxer = realpath(dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $camiUrl));
    if ($arrelUploads === false || $fitxer === false || !str_starts_with($fitxer, $arrelUploads . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $fitxer;
}

// Validació real de contingut: mai n'hi ha prou amb el nom ".pdf" ni amb el
// Content-Type que declara el navegador. Comprova el MIME real (fileinfo,
// que inspecciona el contingut) I la capçalera màgica "%PDF-" del fitxer ja
// al disc.
function pdfEsContingutPdfValid(string $rutaFitxer): bool
{
    if (!is_file($rutaFitxer)) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo !== false ? finfo_file($finfo, $rutaFitxer) : false;
    if ($finfo !== false) {
        finfo_close($finfo);
    }
    if ($mime !== 'application/pdf') {
        return false;
    }
    $handle = @fopen($rutaFitxer, 'rb');
    if ($handle === false) {
        return false;
    }
    $capcalera = fread($handle, 5);
    fclose($handle);
    return $capcalera === '%PDF-';
}

// Validacions sobre el $_FILES[...] rebut, ABANS de moure'l enlloc: mida,
// extensió, origen real de la pujada (is_uploaded_file) i contingut real.
// Retorna null si tot és correcte, o un missatge d'error (en català, apte
// per mostrar a l'usuari) si no ho és.
function pdfValidarUploadRebut(array $file, int $midaMaxima = PDF_MIDA_MAXIMA_BYTES): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'No s\'ha rebut cap fitxer.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Error en la pujada del fitxer.';
    }
    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return 'Fitxer no vàlid.';
    }
    $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        return 'Només s\'admeten fitxers PDF.';
    }
    $mida = (int) ($file['size'] ?? 0);
    if ($mida <= 0 || $mida > $midaMaxima) {
        return 'El PDF supera el límit permès o està buit.';
    }
    if (!pdfEsContingutPdfValid($file['tmp_name'])) {
        return 'El fitxer no és un PDF vàlid.';
    }
    return null;
}

// Optimització "best effort" amb Ghostscript, SEMPRE sobre un fitxer que
// encara no és el definitiu. Mai llança excepció ni interromp el flux: si
// falla (binari absent, error, resultat invàlid o més gran), simplement
// retorna false i el fitxer d'entrada queda intacte. El binari es pot
// configurar amb la variable d'entorn PDF_GS_BIN (per exemple en entorns on
// no es diu "gs"); per defecte, "gs", el mateix que ja feia servir el
// mecanisme V1.
function pdfOptimitzar(string $rutaFitxer): bool
{
    if (!is_file($rutaFitxer)) {
        return false;
    }

    $binari = trim((string) (getenv('PDF_GS_BIN') ?: '')) ?: 'gs';
    $rutaTmp = $rutaFitxer . '.opt.tmp';
    @unlink($rutaTmp);

    $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $cmd = sprintf(
        '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook ' .
        '-dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>%s',
        escapeshellarg($binari),
        escapeshellarg($rutaTmp),
        escapeshellarg($rutaFitxer),
        $nul
    );

    @shell_exec($cmd);

    // Només se substitueix si el resultat és un PDF realment vàlid I més
    // petit que l'original: mateix criteri de seguretat que el mecanisme V1
    // (mai un resultat "optimitzat" que sigui més gran o estigui corrupte),
    // reforçat aquí amb la validació real de contingut.
    $optimitzatOk = is_file($rutaTmp)
        && filesize($rutaTmp) > 0
        && filesize($rutaTmp) < filesize($rutaFitxer)
        && pdfEsContingutPdfValid($rutaTmp);

    if ($optimitzatOk && @rename($rutaTmp, $rutaFitxer)) {
        return true;
    }

    @unlink($rutaTmp);
    return false;
}

// -----------------------------------------------------------------------------
// Capa única: valida, guarda i optimitza un PDF definitiu de projecte.
//
// Seqüència pensada perquè el fitxer final (uploads/.../$nomFitxer) mai
// existeixi amb un contingut a mitges ni desaparegui un cop ja era vàlid:
//   1) es valida el fitxer rebut (mida, extensió, origen, contingut real);
//   2) es mou a un temporal DINS del mateix directori final (mateix
//      filesystem, perquè el pas 5 pugui ser un rename real);
//   3) es revalida el contingut ja al disc;
//   4) s'intenta optimitzar el temporal (best effort, mai el definitiu);
//   5) només ara es publica: si ja existia un PDF amb aquest nom, es mou a
//      una còpia de reserva controlada; es publica el temporal amb `rename()`
//      i només després s'elimina la reserva. Si la publicació falla, es
//      restaura el definitiu anterior.
//
// Si en qualsevol pas hi ha un error, es retorna ok=false i NO s'ha de
// persistir cap ruta a BD. Si ja hi havia un definitiu, es conserva o es
// restaura abans de retornar l'error.
//
// @return array{ok:bool, ruta_rel:?string, ruta_abs:?string, optimitzat:bool, error:?string}
function pdfPublicarTemporal(string $rutaTemporal, string $rutaDefinitiva): bool
{
    if (!is_file($rutaTemporal)) {
        return false;
    }

    $rutaReserva = $rutaDefinitiva . '.previous.tmp';

    // Recuperació d'una interrupció anterior: una reserva sense definitiu
    // es restaura abans d'intentar una publicació nova. Si tots dos existeixen,
    // el definitiu ja es va publicar i la reserva és només un residu pendent.
    if (is_file($rutaReserva)) {
        if (!is_file($rutaDefinitiva)) {
            if (!@rename($rutaReserva, $rutaDefinitiva)) {
                return false;
            }
        } elseif (!@unlink($rutaReserva)) {
            return false;
        }
    }

    $teniaDefinitiu = is_file($rutaDefinitiva);
    if ($teniaDefinitiu && !@rename($rutaDefinitiva, $rutaReserva)) {
        return false;
    }

    if (@rename($rutaTemporal, $rutaDefinitiva)) {
        if ($teniaDefinitiu) {
            @unlink($rutaReserva);
        }
        return true;
    }

    // La publicació nova ha fallat: el caller rebrà error, però el document
    // anterior torna a ocupar la ruta canònica abans de continuar.
    if ($teniaDefinitiu && !is_file($rutaDefinitiva)) {
        if (!@rename($rutaReserva, $rutaDefinitiva) && @copy($rutaReserva, $rutaDefinitiva)) {
            @unlink($rutaReserva);
        }
    }
    return false;
}

function pdfGuardarDefinitiu(
    array $file,
    string $cursoAcademico,
    string $ciclo,
    int $idProyecto,
    string $nombreArchivo,
    int $midaMaxima = PDF_MIDA_MAXIMA_BYTES
): array {
    $buit = static fn (string $error): array => [
        'ok' => false, 'ruta_rel' => null, 'ruta_abs' => null, 'optimitzat' => false, 'error' => $error,
    ];

    $errorValidacio = pdfValidarUploadRebut($file, $midaMaxima);
    if ($errorValidacio !== null) {
        return $buit($errorValidacio);
    }

    $directori = pdfResoldreDirectoriProjecte($cursoAcademico, $ciclo, $idProyecto);
    if ($directori === null) {
        return $buit('El projecte no té curs acadèmic, cicle o identificador vàlids.');
    }

    $nomSegur = pdfNomFitxerSegur($nombreArchivo);
    if ($nomSegur === '') {
        return $buit('Nom de fitxer no vàlid.');
    }

    if (!is_dir($directori['abs']) && !mkdir($directori['abs'], 0775, true) && !is_dir($directori['abs'])) {
        return $buit('No s\'ha pogut crear la carpeta del projecte.');
    }

    $rutaAbs = $directori['abs'] . '/' . $nomSegur;
    $rutaRel = $directori['rel'] . '/' . $nomSegur;
    $rutaPujadaTmp = $rutaAbs . '.upload.tmp';
    @unlink($rutaPujadaTmp);

    if (!move_uploaded_file($file['tmp_name'], $rutaPujadaTmp)) {
        return $buit('No s\'ha pogut guardar el fitxer.');
    }

    if (!pdfEsContingutPdfValid($rutaPujadaTmp)) {
        @unlink($rutaPujadaTmp);
        return $buit('El fitxer no és un PDF vàlid.');
    }

    // Best effort: si falla, $rutaPujadaTmp segueix sent el PDF original
    // vàlid (pdfOptimitzar() mai el deixa en un estat pitjor).
    $optimitzat = pdfOptimitzar($rutaPujadaTmp);

    if (!pdfPublicarTemporal($rutaPujadaTmp, $rutaAbs)) {
        @unlink($rutaPujadaTmp);
        return $buit('No s\'ha pogut publicar el fitxer definitiu.');
    }

    return [
        'ok' => true,
        'ruta_rel' => $rutaRel,
        'ruta_abs' => $rutaAbs,
        'optimitzat' => $optimitzat,
        'error' => null,
    ];
}
