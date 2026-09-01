<?php
declare(strict_types=1);

// Contingut de la tasca "Compromís de treball" (Fase 1). Wrapper:
// fase-1_compromis_form.php (gate + dades). El subtítol és dinàmic (depèn
// de si ja s'ha acceptat i de qui el consulta), per això viu aquí i no a
// fase_base.php: el shell només renderitza subtítols estàtics.
?>
<p class="text-muted mb-4"><?= $compromisoAceptado ? ($esAlumnat ? 'Consulta el compromís que vas acceptar.' : 'Consulta el compromís acceptat en aquest projecte.') : 'Llegeix detingudament el compromís abans d’acceptar-lo.' ?></p>

<?php if ($error !== ''): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="bloc bloc-informacio mb-4">
    <div class="bloc-contingut">
        <div class="bloc-tipus">Compromís</div>
        <h2><?= $esProyectoEnPareja ? 'Compromís de treball en parella' : 'Compromís de treball individual' ?></h2>

        <?php if ($esProyectoEnPareja): ?>
            <p class="mb-3">
                Jo, <strong class="text-dark"><?= htmlspecialchars($nombreAlumno, ENT_QUOTES, 'UTF-8') ?></strong>,
                alumne/a del cicle formatiu <strong class="text-dark"><?= htmlspecialchars($nombreCiclo, ENT_QUOTES, 'UTF-8') ?></strong>
                i membre del grup de projecte amb <strong class="text-dark"><?= htmlspecialchars($nombreCompaneros, ENT_QUOTES, 'UTF-8') ?></strong>,
                em comprometo personalment a:
            </p>
            <ol class="compromis-llista mb-0">
                <li class="mb-2">Desenvolupar el projecte de manera responsable i constant durant tot el curs.</li>
                <li class="mb-2">Assistir a totes les classes de Projecte i ser puntual, tal com faig amb la resta de mòduls, acceptant que les faltes d’assistència o puntualitat reiterades podran afectar negativament la meva nota final.</li>
                <li class="mb-2">No utilitzar les hores de Projecte per realitzar tasques d’altres mòduls, respectant que aquest temps està dedicat exclusivament al desenvolupament del projecte.</li>
                <li class="mb-2">Acceptar que cada integrant del grup pot obtenir una nota diferent en funció del seu treball, la seva actitud a classe i el seu compromís amb el projecte, i que fins i tot es pot donar el cas que un dels integrants aprovi i l’altre no.</li>
                <li class="mb-2">Complir els terminis i entregues establerts pel professorat.</li>
                <li class="mb-2">Tenir en compte i seguir les indicacions del professorat per a la bona evolució del projecte.</li>
                <li class="mb-2">Participar de manera equilibrada en totes les fases del projecte i repartir-nos les tasques de forma justa.</li>
                <li class="mb-2">Fer servir el repositori Git de manera regular i equilibrada, amb còmits freqüents i clars per part de tots dos.</li>
                <li class="mb-2">Mantenir actualitzada la documentació al Drive compartit.</li>
                <li>Demanar ajuda al professorat en cas de dificultats i informar de qualsevol incidència o desequilibri que pugui afectar el projecte.</li>
            </ol>
        <?php else: ?>
            <p class="mb-3">
                Jo, <strong class="text-dark"><?= htmlspecialchars($nombreAlumno, ENT_QUOTES, 'UTF-8') ?></strong>,
                alumne/a del cicle formatiu <strong class="text-dark"><?= htmlspecialchars($nombreCiclo, ENT_QUOTES, 'UTF-8') ?></strong>,
                em comprometo a:
            </p>
            <ol class="compromis-llista mb-0">
                <li class="mb-2">Desenvolupar el projecte de manera responsable i constant durant tot el curs.</li>
                <li class="mb-2">Assistir a totes les classes de Projecte i ser puntual, tal com faig amb la resta de mòduls, acceptant que les faltes d’assistència o puntualitat reiterades podran afectar negativament la meva nota final.</li>
                <li class="mb-2">Em comprometo a no utilitzar les hores de Projecte per realitzar tasques d’altres mòduls, respectant que aquest temps està dedicat exclusivament al desenvolupament del projecte.</li>
                <li class="mb-2">Complir els terminis i entregues establerts pel professorat.</li>
                <li class="mb-2">Tenir en compte i seguir les indicacions del professorat per a la bona evolució del projecte.</li>
                <li class="mb-2">Mantenir actualitzada la documentació al Drive compartit.</li>
                <li class="mb-2">Utilitzar el control de versions (Git) de forma regular i amb missatges clars.</li>
                <li>Demanar ajuda al professorat en cas de dificultats i informar de qualsevol incidència que pugui afectar el projecte.</li>
            </ol>
        <?php endif; ?>
    </div>
</section>

<?php if ($compromisoAceptado): ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Compromís acceptat.</div>
<?php endif; ?>

<?php if ($esAlumnat && !$compromisoAceptado): ?>
    <form method="post" action="/index.php?main=alumne-fase-1-compromis-accion">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="accepto_compromis" id="accepto_compromis" value="1" required>
            <label class="form-check-label fw-semibold" for="accepto_compromis">
                He llegit detingudament el compromís de treball i l’accepto personalment.
            </label>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-fase btn-puig-solid">Confirmar el compromís</button>
            <a href="/fases-del-projecte/fase-1" class="btn btn-fase btn-puig">Cancel·lar</a>
        </div>
    </form>
<?php endif; ?>
