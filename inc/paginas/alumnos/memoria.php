<?php
declare(strict_types=1);

// La memòria exigeix un projecte actiu: sense projecte no hi ha res a
// mostrar. Es reutilitza el context habitual de l'àrea d'alumnat.
if (!(require __DIR__ . '/projecte_context.php')) {
    return;
}

$alumnoId = (int) $_SESSION['alumno_id'];
$proyectoId = (int) $proyectoAlumno['id_proyecto'];

// ── Etiquetes i utilitats de presentació ────────────────────────────────
// Estats reals de app.memoria_seguimiento (CHECK constraint): pendiente,
// revision_solicitada, corregir, completo. pendiente=gris/neutre,
// revision_solicitada=groc, corregir=vermell, completo=verd.
function memoriaEtiquetaEstat(string $estado): string
{
    return match ($estado) {
        'pendiente' => 'Pendent',
        'revision_solicitada' => 'Revisió sol·licitada',
        'corregir' => 'Cal corregir',
        'completo' => 'Apartat validat',
        default => ucfirst($estado),
    };
}

function memoriaEstatComplet(string $estado): bool
{
    return $estado === 'completo';
}

// Classe visual del badge d'estat de memòria.
function memoriaEstatClasseBadge(string $estado): string
{
    return match ($estado) {
        'completo' => 'memoria-estat-complet',
        'revision_solicitada' => 'memoria-estat-revisio',
        'corregir' => 'memoria-estat-corregir',
        default => 'memoria-estat-pendent',
    };
}

// Classe visual de la barra superior de la targeta (.bloc).
function memoriaEstatClasseBloc(string $estado): string
{
    return match ($estado) {
        'completo' => 'bloc-memoria-complet',
        'revision_solicitada' => 'bloc-memoria-revisio',
        'corregir' => 'bloc-memoria-corregir',
        default => 'bloc-informacio',
    };
}

function memoriaPotSolicitarRevisio(string $estado): bool
{
    return in_array($estado, ['pendiente', 'corregir'], true);
}

function memoriaData(?string $data): string
{
    return dataCatalanaNatural($data);
}

// ── Categoria del projecte: determina quins apartats de memoria_estructura
// li corresponen. Sense categoria assignada, no hi ha estructura possible. ──
$stmt = $pdo->prepare("SELECT categoria_proyecto_id FROM app.proyectos WHERE id_proyecto = :id");
$stmt->execute([':id' => $proyectoId]);
$categoriaId = $stmt->fetchColumn();
$categoriaId = $categoriaId !== false && $categoriaId !== null ? (int) $categoriaId : 0;

$apartats = [];
if ($categoriaId > 0) {
    // Cada apartat actiu de la categoria ha de tenir sempre una fila de
    // seguiment per a aquest projecte. El seguiment és per projecte, no per
    // alumne, i encara no existeix cap altre procés (worker/acció) que la
    // creï per endavant, així que es garanteix aquí de manera idempotent
    // (mateix idioma que ja fa servir seguiment/worker.php): una alta
    // ON CONFLICT DO NOTHING per cada apartat que encara no en tingui.
    try {
        $pdo->prepare("
            INSERT INTO app.memoria_seguimiento (proyecto_id, memoria_estructura_id)
            SELECT :proyecto_id, me.id_memoria_estructura
            FROM app.memoria_estructura me
            WHERE me.categoria_proyecto_id = :categoria_id AND me.activo = true
            ON CONFLICT (proyecto_id, memoria_estructura_id) DO NOTHING
        ")->execute([':proyecto_id' => $proyectoId, ':categoria_id' => $categoriaId]);
    } catch (Throwable $e) {
        error_log('Error creant el seguiment de memòria: ' . $e->getMessage());
    }

    // LEFT JOIN de reserva: si per qualsevol motiu l'alta anterior no s'ha
    // pogut fer, l'apartat encara es mostra (com a "Pendent", sense poder
    // sol·licitar revisió) en lloc de desaparèixer o trencar la pàgina.
    $stmt = $pdo->prepare("
        SELECT me.id_memoria_estructura, me.titulo, me.descripcion, me.enlace_guia, me.orden,
               ms.id_memoria_seguimiento, ms.estado, ms.fecha_solicitud_revision, ms.fecha_ultima_revision,
               mc.comentario AS ultim_comentari, mc.creado_en AS ultim_comentari_data
        FROM app.memoria_estructura me
        LEFT JOIN app.memoria_seguimiento ms
            ON ms.memoria_estructura_id = me.id_memoria_estructura AND ms.proyecto_id = :proyecto_id
        LEFT JOIN LATERAL (
            SELECT comentario, creado_en
            FROM app.memoria_comentarios
            WHERE memoria_seguimiento_id = ms.id_memoria_seguimiento
            ORDER BY creado_en DESC, id_memoria_comentario DESC
            LIMIT 1
        ) mc ON true
        WHERE me.categoria_proyecto_id = :categoria_id AND me.activo = true
        ORDER BY me.orden, me.id_memoria_estructura
    ");
    $stmt->execute([':proyecto_id' => $proyectoId, ':categoria_id' => $categoriaId]);
    $apartats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Historial complet de comentaris per apartat (no només l'últim), agrupat
// per id_memoria_seguimiento i ordenat del més recent al més antic. Es
// carrega sempre juntament amb la pàgina; el desplegat "Veure comentaris
// anteriors" és només visual en client, no fa cap petició nova.
$comentarisPerApartat = [];
if ($apartats !== []) {
    $idsSeguiment = array_values(array_filter(array_map(
        static fn (array $a): int => $a['id_memoria_seguimiento'] !== null ? (int) $a['id_memoria_seguimiento'] : 0,
        $apartats
    )));
    if ($idsSeguiment !== []) {
        $marcadores = implode(',', array_fill(0, count($idsSeguiment), '?'));
        $stmt = $pdo->prepare("
            SELECT memoria_seguimiento_id, comentario, creado_en
            FROM app.memoria_comentarios
            WHERE memoria_seguimiento_id IN ($marcadores)
            ORDER BY memoria_seguimiento_id, creado_en DESC, id_memoria_comentario DESC
        ");
        $stmt->execute($idsSeguiment);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $comentarisPerApartat[(int) $fila['memoria_seguimiento_id']][] = $fila;
        }
    }
}
?>

<script>window.PAGE_TITLE = 'Memòria';</script>
<style>
.memoria-panell {
    background: #f7f8fa;
}
/* Granate corporatiu (mateix valor que .tutor-comentari-afegir-btn a
   autoseguiment-tutor.php): accions que produeixen canvis. */
.memoria-solicitar-btn {
    color: #970A2C !important;
}
.memoria-solicitar-btn:hover {
    color: #7e0825 !important;
}
/* Enllaços secundaris/de consulta d'aquesta peça (guia, historial): mai el
   blau de Bootstrap. */
.memoria-link-secundari,
.memoria-historial-toggle {
    color: #496B88 !important;
}
.memoria-link-secundari:hover,
.memoria-historial-toggle:hover {
    color: #35506b !important;
}
/* Data del comentari: metadada clarament secundària, mai competint amb el text. */
.memoria-comentari-data-secundaria {
    font-size: .75rem;
    color: #8a94a4;
}
</style>

<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Memòria</h1>
        <p class="text-muted mb-0">Apartats de la memòria del projecte i seguiment del tutor.</p>
    </div>

    <div class="card memoria-panell shadow-sm border-0 rounded-4 p-4 p-lg-5">
    <?php if ($categoriaId <= 0): ?>
        <div class="alert alert-warning mb-0">
            Aquest projecte encara no té una categoria assignada, així que encara no hi ha cap apartat de memòria definit.
        </div>
    <?php elseif ($apartats === []): ?>
        <div class="alert alert-info mb-0">
            Encara no hi ha cap apartat de memòria definit per a la categoria d’aquest projecte.
        </div>
    <?php else: ?>
        <div class="d-grid gap-3">
            <?php foreach ($apartats as $apartat): ?>
                <?php
                $estado = $apartat['estado'] !== null ? (string) $apartat['estado'] : 'pendiente';
                $idSeguiment = $apartat['id_memoria_seguimiento'] !== null ? (int) $apartat['id_memoria_seguimiento'] : 0;
                $comentari = trim((string) ($apartat['ultim_comentari'] ?? ''));
                $fechaMetadatoRevision = $estado === 'revision_solicitada'
                    ? (string) ($apartat['fecha_solicitud_revision'] ?? '')
                    : (string) ($apartat['fecha_ultima_revision'] ?? '');
                $etiquetaMetadatoRevision = $estado === 'revision_solicitada' ? 'Revisió sol·licitada' : 'Última revisió';
                $historial = $comentarisPerApartat[$idSeguiment] ?? [];
                ?>
                <section class="bloc <?= memoriaEstatClasseBloc($estado) ?> mb-0">
                    <div class="bloc-contingut">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                            <div class="bloc-tipus mb-0">Apartat <?= (int) $apartat['orden'] ?></div>
                            <?php if (trim((string) ($apartat['enlace_guia'] ?? '')) !== ''): ?>
                                <a href="<?= htmlspecialchars((string) $apartat['enlace_guia'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="memoria-link-secundari">
                                    Guia de l’apartat
                                </a>
                            <?php endif; ?>
                        </div>
                        <h2 class="h5 mb-1"><?= htmlspecialchars((string) $apartat['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if (trim((string) ($apartat['descripcion'] ?? '')) !== ''): ?>
                            <p class="mb-3"><?= nl2br(htmlspecialchars((string) $apartat['descripcion'], ENT_QUOTES, 'UTF-8'), false) ?></p>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-3 d-flex flex-column memoria-bloc-estat" data-id-seguiment="<?= $idSeguiment ?>">
                                <span class="badge rounded-pill px-3 py-2 align-self-start memoria-estat-badge <?= memoriaEstatClasseBadge($estado) ?>">
                                    <?= htmlspecialchars(memoriaEtiquetaEstat($estado), ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <?php if (memoriaPotSolicitarRevisio($estado) && $idSeguiment > 0): ?>
                                    <div class="memoria-accio-revisio mt-2">
                                        <button type="button" class="btn btn-link btn-sm p-0 memoria-solicitar-btn">Sol·licitar revisió</button>
                                    </div>
                                <?php endif; ?>

                                <?php if (trim($fechaMetadatoRevision) !== ''): ?>
                                    <div class="mt-auto pt-3 memoria-ultima-revisio">
                                        <p class="memoria-ultima-revisio-etiqueta mb-1"><?= htmlspecialchars($etiquetaMetadatoRevision, ENT_QUOTES, 'UTF-8') ?></p>
                                        <p class="small text-muted mb-0 memoria-fecha-revisio"><?= htmlspecialchars(memoriaData($fechaMetadatoRevision), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9 memoria-comentaris-columna">
                                <p class="fw-semibold small text-uppercase text-muted mb-1 memoria-comentaris-cap">Comentaris del tutor</p>
                                <?php if ($comentari !== ''): ?>
                                    <div class="memoria-comentari-item">
                                        <div class="memoria-comentari-cos">
                                            <?= nl2br(htmlspecialchars($comentari, ENT_QUOTES, 'UTF-8'), false) ?>
                                            <?php if (trim((string) ($apartat['ultim_comentari_data'] ?? '')) !== ''): ?>
                                                <p class="mb-0 mt-2 memoria-comentari-data-secundaria"><?= htmlspecialchars(memoriaData((string) $apartat['ultim_comentari_data']), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="memoria-comentari-item">
                                        <div class="memoria-comentari-cos">
                                            <span class="text-muted">Encara no hi ha cap comentari.</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php
                                // El primer element de $historial és el mateix comentari ja
                                // mostrat a la caixa d'amunt (és el més recent): el desplegat
                                // "anteriors" mostra només la resta.
                                $comentarisAnteriors = array_slice($historial, 1);
                                ?>
                                <?php if ($comentarisAnteriors !== []): ?>
                                    <div class="mt-2 memoria-historial-bloc">
                                        <button type="button" class="btn btn-link btn-sm ps-0 memoria-historial-toggle">Comentaris previs</button>
                                        <div class="d-none mt-2 memoria-historial-comentaris">
                                            <?php foreach ($comentarisAnteriors as $c): ?>
                                                <div class="memoria-comentari-item">
                                                    <div class="memoria-comentari-cos">
                                                        <p class="mb-0"><?= nl2br(htmlspecialchars((string) $c['comentario'], ENT_QUOTES, 'UTF-8'), false) ?></p>
                                                        <p class="mb-0 mt-1 memoria-comentari-data-secundaria"><?= htmlspecialchars(memoriaData((string) $c['creado_en']), ENT_QUOTES, 'UTF-8') ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode(tokenCsrf(), JSON_THROW_ON_ERROR) ?>;
    const urlAccio = '/index.php?main=alumne-memoria-accion';

    document.querySelectorAll('.memoria-solicitar-btn').forEach((boto) => {
        boto.addEventListener('click', async () => {
            const bloc = boto.closest('.memoria-bloc-estat');
            const idSeguiment = bloc?.dataset.idSeguiment;
            if (!idSeguiment) {
                return;
            }
            boto.disabled = true;

            const dades = new FormData();
            dades.append('accio', 'solicitar_revisio');
            dades.append('id_seguimiento', idSeguiment);
            dades.append('csrf_token', csrfToken);

            try {
                const resposta = await fetch(urlAccio, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: dades,
                });
                const resultat = await resposta.json();
                if (!resultat.ok) {
                    alert(resultat.missatge || 'No s’ha pogut sol·licitar la revisió.');
                    boto.disabled = false;
                    return;
                }

                const badge = bloc.querySelector('.memoria-estat-badge');
                if (badge) {
                    badge.textContent = resultat.etiqueta;
                    // "Sol·licitar revisió" sempre porta a revision_solicitada (groc):
                    // mateixa semàntica que memoriaEstatClasseBadge() al servidor.
                    badge.classList.remove('memoria-estat-pendent', 'memoria-estat-revisio', 'memoria-estat-corregir', 'memoria-estat-complet');
                    badge.classList.add('memoria-estat-revisio');
                }
                const seccio = bloc.closest('section.bloc');
                if (seccio) {
                    seccio.classList.remove('bloc-informacio', 'bloc-memoria-revisio', 'bloc-memoria-corregir', 'bloc-memoria-complet');
                    seccio.classList.add('bloc-memoria-revisio');
                }

                const accio = bloc.querySelector('.memoria-accio-revisio');
                if (resultat.data_solicitud) {
                    let meta = bloc.querySelector('.memoria-ultima-revisio');
                    if (!meta) {
                        meta = document.createElement('div');
                        meta.className = 'mt-auto pt-3 memoria-ultima-revisio';
                        bloc.appendChild(meta);
                    }
                    const titol = document.createElement('p');
                    titol.className = 'memoria-ultima-revisio-etiqueta mb-1';
                    titol.textContent = 'Revisió sol·licitada';
                    const data = document.createElement('p');
                    data.className = 'small text-muted mb-0 memoria-fecha-revisio';
                    data.textContent = resultat.data_solicitud;
                    meta.replaceChildren(titol, data);
                }
                accio?.remove();
            } catch (error) {
                alert('Error de connexió en sol·licitar la revisió.');
                boto.disabled = false;
            }
        });
    });

    // ── Historial de comentaris: desplegat purament visual, sense cap
    // petició nova (els comentaris ja han arribat amb la pàgina). ──────────
    document.querySelectorAll('.memoria-historial-toggle').forEach((boto) => {
        const bloc = boto.closest('.memoria-historial-bloc');
        const panell = bloc ? bloc.querySelector('.memoria-historial-comentaris') : null;
        if (!panell) {
            return;
        }
        boto.addEventListener('click', () => {
            panell.classList.remove('d-none');
            boto.remove();
        });
    });
})();
</script>
