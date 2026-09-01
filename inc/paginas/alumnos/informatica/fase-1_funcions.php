<?php
declare(strict_types=1);

// Criteri real de "Fase 1 completada" per a un alumne concret sobre un
// projecte concret: grup de treball confirmat i compromís de treball
// acceptat (rel_proyectos_alumnos). Única font de veritat d'aquest criteri
// — tant la navegació de fases (fases_navegacion.php) com el gate d'accés a
// Fase 2 hi criden, en lloc de repetir la condició.
//
// No canvia el criteri de completat de Fase 1: és exactament la mateixa
// condició que ja feia servir fases_navegacion.php.
function fase1CompletadaAlumnoProyecto(PDO $pdo, int $alumnoId, int $idProyecto): bool
{
    if ($alumnoId <= 0 || $idProyecto <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT grupo_trabajo_confirmado_en IS NOT NULL
               AND compromiso_trabajo_aceptado = true
        FROM app.rel_proyectos_alumnos
        WHERE alumno_id = :alumno_id AND proyecto_id = :proyecto_id
    ");
    $stmt->execute([
        ':alumno_id' => $alumnoId,
        ':proyecto_id' => $idProyecto,
    ]);
    return (bool) $stmt->fetchColumn();
}

// Mateix criteri, a nivell de PROJECTE sencer: útil quan no hi ha un
// alumne_id de sessió al qual referir-se (navegació contextual del
// professorat, que consulta el recorregut d'un projecte, no la sessió d'un
// alumne concret). Completat només quan el projecte té membres i CAP d'ells
// li falta el criteri. No és un criteri nou: és el mateix, aplicat a totes
// les files del projecte en lloc d'una de sola.
function fase1CompletadaProyecte(PDO $pdo, int $idProyecto): bool
{
    if ($idProyecto <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1 FROM app.rel_proyectos_alumnos WHERE proyecto_id = :proyecto_id
        ) AND NOT EXISTS (
            SELECT 1 FROM app.rel_proyectos_alumnos
            WHERE proyecto_id = :proyecto_id
              AND (grupo_trabajo_confirmado_en IS NULL OR compromiso_trabajo_aceptado = false)
        )
    ");
    $stmt->execute([':proyecto_id' => $idProyecto]);
    return (bool) $stmt->fetchColumn();
}

// Resultat detallat (no només el booleà de completat) de les dues tasques de
// Fase 1: si el grup ja està confirmat, si el compromís ja està acceptat, i
// el text objectiu de la composició del grup ("Projecte individual: X" /
// "Projecte en parella: X i Y"). Única font d'aquest resultat: abans vivia
// només inline a fase-1_contingut.php; ara també el reutilitza el resum de
// "Fases del projecte" (fases_projecte.php) perquè cap dels dos hagi de
// tornar a calcular-lo pel seu compte. Mateix criteri exacte per rol
// (alumnat: el seu propi estat personal; professorat: TOTS els membres),
// sense cap canvi de comportament.
function fase1ResultadoGrupoTrabajo(PDO $pdo, int $idProyecto, string $rolVisualitzacio, int $alumnoSessioId): array
{
    $buit = ['confirmado' => false, 'aceptado' => false, 'resultado' => ''];
    if ($idProyecto <= 0) {
        return $buit;
    }

    $stmt = $pdo->prepare("
        SELECT a.id_alumno, a.nombre, a.apellidos,
               rpa.grupo_trabajo_confirmado_en, rpa.compromiso_trabajo_aceptado
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id = :proyecto_id
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute([':proyecto_id' => $idProyecto]);
    $miembrosGrupo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $esAlumnat = $rolVisualitzacio === 'alumne';
    if ($esAlumnat) {
        // Alumnat: l'estat és sempre el seu propi, personal.
        $filaPropia = null;
        foreach ($miembrosGrupo as $miembro) {
            if ((int) $miembro['id_alumno'] === $alumnoSessioId) {
                $filaPropia = $miembro;
                break;
            }
        }
        $grupoTrabajoConfirmado = $filaPropia !== null && $filaPropia['grupo_trabajo_confirmado_en'] !== null;
        $compromisoTrabajoAceptado = $filaPropia !== null && (bool) $filaPropia['compromiso_trabajo_aceptado'];
    } else {
        // Professorat: no hi ha "l'alumne actual"; es dona per completat
        // només quan TOTS els membres del projecte compleixen el criteri.
        $grupoTrabajoConfirmado = $miembrosGrupo !== []
            && array_reduce($miembrosGrupo, static fn (bool $c, array $m): bool => $c && $m['grupo_trabajo_confirmado_en'] !== null, true);
        $compromisoTrabajoAceptado = $miembrosGrupo !== []
            && array_reduce($miembrosGrupo, static fn (bool $c, array $m): bool => $c && (bool) $m['compromiso_trabajo_aceptado'], true);
    }

    $resultadoGrupoTrabajo = '';
    if ($grupoTrabajoConfirmado) {
        // Text objectiu del projecte: descriu el projecte, no parla des del
        // punt de vista de qui el consulta. Única representació, vàlida per
        // a qualsevol usuari autoritzat (alumnat o professorat) — mateix
        // ordre estable (nombre, apellidos) que ja fa servir la consulta SQL.
        $nomsMembres = array_map(
            static fn (array $m): string => trim((string) $m['nombre'] . ' ' . (string) $m['apellidos']),
            $miembrosGrupo
        );
        $resultadoGrupoTrabajo = count($nomsMembres) <= 1
            ? 'Projecte individual: ' . ($nomsMembres[0] ?? '')
            : 'Projecte en parella: ' . implode(' i ', $nomsMembres);
    }

    return [
        'confirmado' => $grupoTrabajoConfirmado,
        'aceptado' => $compromisoTrabajoAceptado,
        'resultado' => $resultadoGrupoTrabajo,
    ];
}

// -----------------------------------------------------------------------------
// Aparença (bloquejada/completada/atenció/activa) d'una fase concreta al
// recorregut de 7 fases, a partir dels mateixos senyals ja calculats
// (fase1CompletadaAlumnoProyecto/Proyecte + fase2PropostaObtenirEstat): única
// font d'aquesta derivació, perquè el sidebar (fases_navegacion.php) i el
// resum gran (fases_projecte.php) SEMPRE hi arribin al mateix resultat — mai
// dues implementacions que puguin divergir. Després de completar Fase 4,
// Fase 5 i Fase 6 queden disponibles en paral·lel; Fase 7 només queda
// disponible quan totes dues estan completades.
//
// Prioritat quan coincideixen senyals: bloquejada > completada > atenció >
// activa (una fase bloquejada o ja completada no "torna" a atenció).
function fasesEstatAparenca(int $numeroFase, bool $faseUnoCompletada, bool $faseDosCompletada, bool $faseDosAtencio, bool $faseTresCompletada = false, bool $faseTresAtencio = false, bool $faseQuatreCompletada = false, bool $faseCincCompletada = false, bool $faseSisCompletada = false, bool $faseSetCompletada = false): array
{
    $faseMaximaDisponible = $faseCincCompletada && $faseSisCompletada ? 7 : ($faseQuatreCompletada ? 6 : ($faseTresCompletada ? 4 : ($faseDosCompletada ? 3 : ($faseUnoCompletada ? 2 : 1))));
    $bloquejada = $numeroFase > $faseMaximaDisponible;
    $completada = !$bloquejada && (($faseUnoCompletada && $numeroFase === 1) || ($faseDosCompletada && $numeroFase === 2) || ($faseTresCompletada && $numeroFase === 3) || ($faseQuatreCompletada && $numeroFase === 4) || ($faseCincCompletada && $numeroFase === 5) || ($faseSisCompletada && $numeroFase === 6) || ($faseSetCompletada && $numeroFase === 7));
    $atencio = !$bloquejada && !$completada && (($numeroFase === 2 && $faseDosAtencio) || ($numeroFase === 3 && $faseTresAtencio));
    $activa = !$bloquejada && !$completada && !$atencio;

    return [
        'bloquejada' => $bloquejada,
        'completada' => $completada,
        'atencio' => $atencio,
        'activa' => $activa,
    ];
}
