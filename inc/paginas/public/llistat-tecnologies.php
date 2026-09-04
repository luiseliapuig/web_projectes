<?php
// Catàleg públic de projectes filtrat pel model normalitzat de tecnologies.
require_once __DIR__ . '/projectes-publics_funcions.php';

$condicioProjectePublic = projectesPublicsCondicioSql('p');
$parametresProjectesPublics = projectesPublicsParametres();

$stmt = $pdo->prepare(
    'SELECT
         t.id,
         t.nombre,
         t.descripcion,
         COUNT(DISTINCT CASE
             WHEN g.id_grupo IS NOT NULL
              AND c.id_ciclo IS NOT NULL
              AND ' . $condicioProjectePublic . '
             THEN p.id_proyecto
         END) AS projectes_publics
     FROM app.tecnologias t
     LEFT JOIN app.rel_proyectos_tecnologias rpt
         ON rpt.tecnologia_id = t.id
     LEFT JOIN app.proyectos p
         ON p.id_proyecto = rpt.proyecto_id
     LEFT JOIN app.grupos g
         ON g.id_grupo = p.grupo_id
     LEFT JOIN app.ciclos c
         ON c.id_ciclo = g.id_ciclo
     WHERE t.activo = true
       AND t.propuesto_en IS NULL
     GROUP BY t.id, t.nombre, t.descripcion
     ORDER BY t.nombre, t.id'
);
$stmt->execute($parametresProjectesPublics);
$tecnologies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tecnologiesDestacades = $tecnologies;
usort(
    $tecnologiesDestacades,
    static function (array $a, array $b): int {
        $perProjectes = (int) $b['projectes_publics'] <=> (int) $a['projectes_publics'];
        if ($perProjectes !== 0) {
            return $perProjectes;
        }

        $perNom = strnatcasecmp((string) $a['nombre'], (string) $b['nombre']);
        return $perNom !== 0 ? $perNom : (int) $a['id'] <=> (int) $b['id'];
    }
);
$tecnologiesDestacades = array_slice($tecnologiesDestacades, 0, 10);

$tecnologiaIdDemanada = filter_input(INPUT_GET, 'tecnologia_id', FILTER_VALIDATE_INT);
$tecnologiaActiva = null;

foreach ($tecnologies as $tecnologia) {
    if ((int) $tecnologia['id'] === (int) $tecnologiaIdDemanada) {
        $tecnologiaActiva = $tecnologia;
        break;
    }
}

if ($tecnologiaActiva === null && $tecnologiesDestacades !== []) {
    $tecnologiaActiva = $tecnologiesDestacades[0];
}

$idsTecnologiesDestacades = array_map(
    static fn(array $tecnologia): int => (int) $tecnologia['id'],
    $tecnologiesDestacades
);
$activaForaDestacades = $tecnologiaActiva !== null
    && !in_array((int) $tecnologiaActiva['id'], $idsTecnologiesDestacades, true);

$projectes = [];
if ($tecnologiaActiva !== null) {
    $sql = '
        SELECT
            p.id_proyecto,
            p.uuid,
            p.nombre,
            p.resumen,
            p.ruta_imagen,
            p.curso_academico,
            c.abr AS ciclo,
            g.grupo,
            string_agg(
                a.nombre || \' \' || a.apellidos,
                \'||\' ORDER BY a.apellidos, a.nombre
            ) AS alumnos
        FROM app.proyectos p
        INNER JOIN app.rel_proyectos_tecnologias rpt
            ON rpt.proyecto_id = p.id_proyecto
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        LEFT JOIN app.rel_proyectos_alumnos rpa
            ON rpa.proyecto_id = p.id_proyecto
        LEFT JOIN app.alumnos a
            ON a.id_alumno = rpa.alumno_id
        WHERE rpt.tecnologia_id = :tecnologia_id
          AND ' . $condicioProjectePublic . '
        GROUP BY
            p.id_proyecto,
            p.uuid,
            p.nombre,
            p.resumen,
            p.ruta_imagen,
            p.curso_academico,
            c.abr,
            g.grupo
        ORDER BY p.nombre ASC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(
        [':tecnologia_id' => (int) $tecnologiaActiva['id']],
        $parametresProjectesPublics
    ));
    $projectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($projectes as &$projecte) {
    $projecte['alumnos_array'] = !empty($projecte['alumnos'])
        ? explode('||', (string) $projecte['alumnos'])
        : [];

    $rutaImatge = trim((string) ($projecte['ruta_imagen'] ?? ''));
    if ($rutaImatge === '') {
        $projecte['ruta_imagen_absoluta'] = '';
    } elseif (
        str_starts_with($rutaImatge, '/')
        || str_starts_with($rutaImatge, 'http://')
        || str_starts_with($rutaImatge, 'https://')
    ) {
        $projecte['ruta_imagen_absoluta'] = $rutaImatge;
    } else {
        $projecte['ruta_imagen_absoluta'] = '/' . ltrim($rutaImatge, '/');
    }
}
unset($projecte);

$nomTecnologia = (string) ($tecnologiaActiva['nombre'] ?? 'Tecnologies');
?>

<script>
window.PAGE_TITLE = <?= json_encode($nomTecnologia, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<div class="container-fluid">
    <div class="projectes-header mb-4 mt-30">
        <h1 class="projectes-title mb-2">Tecnologies</h1>
        <p class="projectes-subtitle mb-0">Explora els projectes segons les tecnologies que utilitzen.</p>
    </div>

    <?php if ($tecnologies !== []): ?>
        <nav class="projectes-filter mb-3" aria-label="Tecnologies destacades">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($tecnologiesDestacades as $tecnologia): ?>
                    <a
                        href="/tecnologies/<?= (int) $tecnologia['id'] ?>"
                        class="projectes-filter-pill <?= (int) $tecnologia['id'] === (int) $tecnologiaActiva['id'] ? 'active' : '' ?>"
                    ><?= htmlspecialchars((string) $tecnologia['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
                <?php if ($activaForaDestacades): ?>
                    <a
                        href="/tecnologies/<?= (int) $tecnologiaActiva['id'] ?>"
                        class="projectes-filter-pill active"
                    ><?= htmlspecialchars((string) $tecnologiaActiva['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="tecnologies-cerca mb-4">
            <label class="visually-hidden" for="tecnologies-cerca-input">Cerca una tecnologia</label>
            <input
                type="search"
                class="form-control"
                id="tecnologies-cerca-input"
                placeholder="Cerca una tecnologia…"
                autocomplete="off"
                aria-controls="tecnologies-cerca-resultats"
                aria-expanded="false"
            >
            <div
                class="list-group position-absolute shadow-sm d-none tecnologies-cerca-resultats"
                id="tecnologies-cerca-resultats"
                aria-live="polite"
            ></div>
        </div>

        <section class="projectes-grup-section mb-5">
            <div class="projectes-grup-header mb-3">
                <div>
                    <h2 class="projectes-grup-title mb-0"><?= htmlspecialchars($nomTecnologia, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if (trim((string) ($tecnologiaActiva['descripcion'] ?? '')) !== ''): ?>
                        <p class="projectes-subtitle mt-3 mb-0"><?= htmlspecialchars((string) $tecnologiaActiva['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($projectes !== []): ?>
                <div class="row g-5">
                    <?php foreach ($projectes as $projecte): ?>
                        <?php require __DIR__ . '/_projecte-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="projectes-empty-state mt-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h5 mb-2">No hi ha projectes disponibles</h3>
                            <p class="mb-0 text-muted">Encara no hi ha projectes publicats amb aquesta tecnologia.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="projectes-empty-state">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-2">No hi ha tecnologies disponibles</h2>
                    <p class="mb-0 text-muted">El catàleg de tecnologies encara és buit.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($tecnologies !== []): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tecnologies = <?= json_encode(
        array_map(
            static fn(array $tecnologia): array => [
                'id' => (int) $tecnologia['id'],
                'nom' => (string) $tecnologia['nombre'],
            ],
            $tecnologies
        ),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const input = document.getElementById('tecnologies-cerca-input');
    const resultats = document.getElementById('tecnologies-cerca-resultats');

    if (!input || !resultats) {
        return;
    }

    const tancarResultats = function () {
        resultats.classList.add('d-none');
        resultats.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
    };

    input.addEventListener('input', function () {
        const cerca = input.value.trim().toLocaleLowerCase('ca');
        resultats.replaceChildren();

        if (cerca === '') {
            tancarResultats();
            return;
        }

        const coincidencies = tecnologies
            .filter(function (tecnologia) {
                return tecnologia.nom.toLocaleLowerCase('ca').includes(cerca);
            })
            .slice(0, 10);

        if (coincidencies.length === 0) {
            const buit = document.createElement('div');
            buit.className = 'list-group-item text-muted small';
            buit.textContent = "No s'han trobat tecnologies.";
            resultats.append(buit);
        } else {
            coincidencies.forEach(function (tecnologia) {
                const enllac = document.createElement('a');
                enllac.className = 'list-group-item list-group-item-action';
                enllac.href = '/tecnologies/' + tecnologia.id;
                enllac.textContent = tecnologia.nom;
                resultats.append(enllac);
            });
        }

        resultats.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            tancarResultats();
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.tecnologies-cerca')) {
            tancarResultats();
        }
    });
});
</script>
<?php endif; ?>
