<?php
declare(strict_types=1);
require_once __DIR__ . '/fase-5_funcions.php';
$rolVisualitzacio = $rolVisualitzacio ?? 'alumne';
$idProjecte = (int) ($proyectoAlumno['id_proyecto'] ?? 0);
$faseBloquejada = $rolVisualitzacio === 'alumne' && !empty($aparencaFaseActiva['bloquejada']);
$estatFaseCinc = fase5ObtenirEstat($pdo, $idProjecte);
$estatRepositoris = $estatFaseCinc['repositoris'];
$estatStack = $estatFaseCinc['stack'];
$estatAutoavaluacio = $estatFaseCinc['autoavaluacio'];
$estatProduccio = $estatFaseCinc['produccio'];
if (!$faseBloquejada) {
    $hrefGit = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $idProjecte . '/fases/fase-5/repositoris-git'
        : '/fases-del-projecte/fase-5/repositoris-git';
    $hrefStack = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $idProjecte . '/fases/fase-5/tecnologies-eines'
        : '/fases-del-projecte/fase-5/tecnologies-eines';
    $hrefProduccio = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $idProjecte . '/fases/fase-5/projecte-en-produccio'
        : '/fases-del-projecte/fase-5/projecte-en-produccio';
    $hrefAutoavaluacio = $rolVisualitzacio === 'professor'
        ? '/projecte/' . $idProjecte . '/fases/fase-5/autoavaluacio-final'
        : '/fases-del-projecte/fase-5/autoavaluacio-final';
}
?>
<div class="d-grid gap-4">
    <p class="fase-introduccio mb-0"><?= htmlspecialchars($faseIntroduccion, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($faseBloquejada): ?>
        <?php
        $tasquesBloquejades = [
            ['titol' => 'Repositoris Git', 'descripcio' => 'Afegiu els repositoris Git associats al projecte i identifiqueu-los amb etiquetes breus quan calgui.'],
            ['titol' => 'Tecnologies i eines', 'descripcio' => 'Identifiqueu les tecnologies i les eines que utilitzareu durant el desenvolupament del projecte.'],
            ['titol' => 'Autoavaluació final', 'descripcio' => 'Reflexioneu sobre l’aprenentatge, el resultat i les millores del projecte.'],
            ['titol' => 'Entrega del projecte', 'descripcio' => 'És el moment de publicar el resultat final del vostre projecte i deixar-lo accessible des de fora.'],
        ];
        ?>
        <?php foreach ($tasquesBloquejades as $tasca): ?>
            <section class="bloc bloc-bloquejat">
                <div class="bloc-contingut">
                    <div class="bloc-tipus">Bloquejada</div>
                    <h2><?= htmlspecialchars($tasca['titol'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mb-3"><?= htmlspecialchars($tasca['descripcio'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i> Primer has de completar la Fase 4.</p>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
    <section class="bloc <?= $estatRepositoris['repositoris_informats'] ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estatRepositoris['repositoris_informats'] ? 'Completada' : 'Activitat' ?></div>
            <h2>Repositoris Git</h2>
            <p class="mb-3">Afegiu els repositoris Git associats al projecte i identifiqueu-los amb etiquetes breus quan calgui.</p>
            <?php if ($estatRepositoris['repositoris'] !== []): ?>
                <div class="d-grid gap-2 mb-3">
                    <?php foreach ($estatRepositoris['repositoris'] as $repositori): ?>
                        <div><a href="<?= htmlspecialchars($repositori['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="tasca-recurs-link <?= $estatRepositoris['repositoris_informats'] ? 'tasca-recurs-resultat--completat' : 'tasca-recurs-resultat--activitat' ?>"><i class="bi bi-git" aria-hidden="true"></i> <?= htmlspecialchars($repositori['literal'], ENT_QUOTES, 'UTF-8') ?></a></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($hrefGit, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatRepositoris['repositoris_informats'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
        </div>
    </section>
    <section class="bloc <?= $estatStack['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estatStack['completada'] ? 'Completada' : 'Activitat' ?></div>
            <h2>Tecnologies i eines</h2>
            <p class="mb-3">Identifiqueu les tecnologies i les eines que utilitzareu durant el desenvolupament del projecte.</p>
            <?php if ($estatStack['tecnologies'] !== [] || $estatStack['eines'] !== []): ?>
                <?php if ($estatStack['tecnologies'] !== []): ?>
                    <div class="stack-resum-titol">Tecnologies</div>
                    <div class="d-flex flex-wrap gap-2 <?= $estatStack['eines'] !== [] ? 'mb-2' : 'mb-3' ?> stack-tecnologies-resum">
                        <?php foreach ($estatStack['tecnologies'] as $tecnologia): ?><span class="stack-tecnologia-pill"><?= htmlspecialchars((string) $tecnologia['nombre'], ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($estatStack['eines'] !== []): ?>
                    <div class="stack-resum-titol">Eines</div>
                    <p class="stack-eines-resum mb-3"><?= htmlspecialchars(implode(' · ', array_column($estatStack['eines'], 'nombre')), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($hrefStack, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatStack['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
        </div>
    </section>
    <section class="bloc <?= $estatAutoavaluacio['completada'] ? 'bloc-completat' : 'bloc-activitat' ?>">
        <div class="bloc-contingut">
            <div class="bloc-tipus"><?= $estatAutoavaluacio['completada'] ? 'Completada' : ($estatAutoavaluacio['iniciada'] ? 'En curs' : 'Activitat') ?></div>
            <h2>Autoavaluació final</h2>
            <p class="mb-3">Reflexioneu sobre l’aprenentatge, el resultat i les millores del projecte.</p>
            <div class="d-grid gap-2 mb-4">
                <?php foreach (fase5AutoavaluacioPreguntes() as $camp => $pregunta): ?>
                    <?php $respostaInformada = ($estatAutoavaluacio['respostes'][$camp] ?? '') !== ''; ?>
                    <p class="mb-0 <?= $respostaInformada ? 'fase-resultat-completat' : 'fase-resultat-pendent' ?>">
                        <i class="bi <?= $respostaInformada ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($pregunta, ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                <?php endforeach; ?>
            </div>
            <a href="<?= htmlspecialchars($hrefAutoavaluacio, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatAutoavaluacio['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
        </div>
    </section>
    <section class="lliurament-final lliurament-final--compacte<?= $estatProduccio['completada'] ? ' lliurament-final--completat' : '' ?>">
        <header class="lliurament-final-cap">Publicació final</header>
        <div class="lliurament-final-cos">
            <h2>Entrega del projecte</h2>
            <p>És el moment de publicar el resultat final del vostre projecte i deixar-lo accessible des de fora.</p>
            <div class="lliurament-final-opcions">
                <div class="lliurament-final-opcio">
                    <div class="lliurament-final-subtitol">Posada en producció</div>
                    <p>El producte deixa l’entorn local.</p>
                </div>
                <div class="lliurament-final-opcio">
                    <div class="lliurament-final-subtitol">Accés públic</div>
                    <p>S’ha de poder consultar en obert.</p>
                </div>
            </div>
            <div class="lliurament-final-accions d-flex flex-column align-items-start gap-3">
                <?php if ($estatProduccio['completada']): ?>
                    <?php $urlProduccioCta = $estatProduccio['url']; ?>
                    <?php include __DIR__ . '/fase-5_produccio_cta.php'; ?>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($hrefProduccio, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-fase <?= $estatProduccio['completada'] ? 'btn-outline-success' : 'btn-puig-solid' ?>">Entrar</a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
