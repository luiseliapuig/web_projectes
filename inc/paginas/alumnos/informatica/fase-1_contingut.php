<?php
declare(strict_types=1);

// El professorat consulta aquesta mateixa vista com a "visitant amb drets"
// (vegeu docs/codex/arquitectura.md): veu el mateix contingut i el mateix
// estat, però sense sessió d'alumne pròpia i sense poder executar les
// accions personals de l'alumnat (definir grup, acceptar el compromís).
require_once __DIR__ . '/fase-1_funcions.php';

$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$esAlumnat = $rolVisualitzacio === 'alumne';

$cicloProyecto = trim((string) ($proyectoAlumno['ciclo'] ?? ''));
$enlaceProyectosCiclo = $cicloProyecto !== ''
    ? '/projectes/' . rawurlencode($cicloProyecto)
    : '/projectes';

$proyectoGrupoId = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$alumnoSessioId = (int) ($_SESSION['alumno_id'] ?? 0);
// Mateix resultat que reutilitza el resum de "Fases del projecte" (vegeu
// fases_projecte.php): una sola implementació, mai dues.
$resultatGrupTreball = fase1ResultadoGrupoTrabajo($pdo, $proyectoGrupoId, $rolVisualitzacio, $alumnoSessioId);
$grupoTrabajoConfirmado = $resultatGrupTreball['confirmado'];
$compromisoTrabajoAceptado = $resultatGrupTreball['aceptado'];
$resultadoGrupoTrabajo = $resultatGrupTreball['resultado'];

$mensajeGrupoTrabajo = isset($_SESSION['fase_1_grup_mensaje']) && is_string($_SESSION['fase_1_grup_mensaje'])
    ? $_SESSION['fase_1_grup_mensaje']
    : '';
$errorGrupoTrabajo = isset($_SESSION['fase_1_grup_error']) && is_string($_SESSION['fase_1_grup_error'])
    ? $_SESSION['fase_1_grup_error']
    : '';
unset($_SESSION['fase_1_grup_mensaje'], $_SESSION['fase_1_grup_error']);
$mensajeCompromiso = isset($_SESSION['fase_1_compromis_mensaje']) && is_string($_SESSION['fase_1_compromis_mensaje'])
    ? $_SESSION['fase_1_compromis_mensaje']
    : '';
$errorCompromiso = isset($_SESSION['fase_1_compromis_error']) && is_string($_SESSION['fase_1_compromis_error'])
    ? $_SESSION['fase_1_compromis_error']
    : '';
unset($_SESSION['fase_1_compromis_mensaje'], $_SESSION['fase_1_compromis_error']);
?>
<div class="d-grid gap-4">
    <?php if ($mensajeGrupoTrabajo !== ''): ?>
        <div class="alert alert-success mb-0"><?= htmlspecialchars($mensajeGrupoTrabajo, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorGrupoTrabajo !== ''): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($errorGrupoTrabajo, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($mensajeCompromiso !== ''): ?>
        <div class="alert alert-success mb-0"><?= htmlspecialchars($mensajeCompromiso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorCompromiso !== ''): ?>
        <div class="alert alert-warning mb-0"><?= htmlspecialchars($errorCompromiso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>

    <section class="bloc bloc-informacio">
        <div class="bloc-contingut">
            <div class="bloc-tipus">Exemples</div>
            <h2>Projectes d’altres cursos</h2>
            <p class="mb-3">Abans de començar el vostre projecte és útil conèixer exemples reals.</p>
            <p class="mb-3">En aquest enllaç trobareu una selecció de projectes realitzats per alumnes d’altres cursos que us poden servir d’inspiració i orientació.</p>
            <a href="<?= htmlspecialchars($enlaceProyectosCiclo, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase btn-fase-informacio">Veure projectes</a>
        </div>
    </section>

    <section class="bloc <?= $grupoTrabajoConfirmado ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $grupoTrabajoConfirmado ? 'Completada' : 'Activitat' ?></div>
            <h2>Defineix el grup de treball</h2>
            <?php if ($grupoTrabajoConfirmado): ?>
                <p class="fase-resultat-completat"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($resultadoGrupoTrabajo, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="mb-3">Abans de començar el projecte, decidiu si el fareu individualment o en parella. Si treballeu en parella, la decisió ha d’estar acordada prèviament entre tots dos.</p>
                <?php if ($esAlumnat): ?>
                    <a href="/fases-del-projecte/fase-1/definir-grup" class="btn btn-fase btn-puig-solid">Definir el grup</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="bloc <?= !$grupoTrabajoConfirmado ? 'bloc-bloquejat' : ($compromisoTrabajoAceptado ? 'bloc-completat' : 'bloc-activitat') ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus">
                <?= !$grupoTrabajoConfirmado ? 'Bloquejada' : ($compromisoTrabajoAceptado ? 'Completada' : 'Activitat individual') ?>
            </div>
            <h2>Compromís de treball</h2>
            <?php if (!$grupoTrabajoConfirmado): ?>
                <p><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar «Defineix el grup de treball».</p>
            <?php elseif ($compromisoTrabajoAceptado): ?>
                <p class="fase-resultat-completat mb-3"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Compromís acceptat</p>
                <?php
                    // "Veure compromís" és una acció de consulta: disponible per a
                    // qualsevol usuari autoritzat sobre el projecte (alumnat i
                    // professorat), no només qui el pot acceptar personalment.
                    $hrefVeureCompromis = $esAlumnat
                        ? '/fases-del-projecte/fase-1/compromis'
                        : '/projecte/' . $proyectoGrupoId . '/fases/fase-1/compromis';
                ?>
                <a href="<?= htmlspecialchars($hrefVeureCompromis, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase btn-outline-success">Veure compromís</a>
            <?php else: ?>
                <p class="mb-3">Llegeix i accepta personalment el compromís de treball del projecte.</p>
                <?php if ($esAlumnat): ?>
                    <a href="/fases-del-projecte/fase-1/compromis" class="btn btn-fase btn-puig-solid">Llegir el compromís</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
