<?php
declare(strict_types=1);

soloSuperadmin();


function formatearNombres(string $nombres): string
{
    $array = array_filter(array_map('trim', explode(',', $nombres)));

    $total = count($array);

    if ($total === 0) {
        return 'Bon dia,';
    }

    if ($total === 1) {
        return 'Bon dia ' . $array[0] . ',';
    }

    if ($total === 2) {
        return 'Bon dia ' . $array[0] . ' i ' . $array[1] . ',';
    }

    $ultimo = array_pop($array);

    return 'Bon dia ' . implode(', ', $array) . ' i ' . $ultimo . ',';
}


// --------------------------------------------------
// PROMOCIÓN ACTUAL Y FILTROS
// La pantalla es operativa: solo incluye proyectos del curso vigente.
// --------------------------------------------------

$cursoAcademico = cursoAcademicoActual();
$ciclo = isset($_GET['ciclo']) && is_string($_GET['ciclo'])
    ? trim($_GET['ciclo'])
    : '';
$grupo = isset($_GET['grupo']) && is_string($_GET['grupo'])
    ? trim($_GET['grupo'])
    : '';

// Valores posibles de filtro de grupo:
// ''       -> todos
// 'A'      -> solo grupo A
// 'RESTO'  -> todos menos A

$where = ['p.curso_academico = :curso_academico'];
$params = [':curso_academico' => $cursoAcademico];

if ($ciclo !== '') {
    $where[] = 'c.abr = :ciclo';
    $params[':ciclo'] = $ciclo;
}

if ($grupo !== '') {
    $where[] = 'g.grupo = :grupo';
    $params[':grupo'] = $grupo;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// --------------------------------------------------
// LISTADO DE CICLOS PARA EL FILTRO
// --------------------------------------------------

$sqlCiclos = "
    SELECT DISTINCT c.abr
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    ORDER BY c.abr
";
$stmtCiclos = $pdo->query($sqlCiclos);
$ciclos = $stmtCiclos->fetchAll(PDO::FETCH_COLUMN);

// --------------------------------------------------
// CONSULTA PRINCIPAL
// --------------------------------------------------
// Sacamos proyectos y concatenamos emails de alumnos
// para poder construir el Gmail compose por proyecto.

$sql = "
    SELECT
        p.id_proyecto,
        p.nombre,
        c.abr AS ciclo,
        g.grupo,
        p.curso_academico,
        STRING_AGG(a.email, ',' ORDER BY a.apellidos, a.nombre) AS emails_alumnos,
        STRING_AGG(
            TRIM(COALESCE(a.nombre, '') || ' ' || COALESCE(a.apellidos, '')),
            ', ' ORDER BY a.apellidos, a.nombre
        ) AS nombres_alumnos,
        COUNT(a.id_alumno) AS num_alumnos
    FROM app.proyectos p
    INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
    INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
    LEFT JOIN app.rel_proyectos_alumnos rpa
        ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos a
        ON a.id_alumno = rpa.alumno_id
    $whereSql
    GROUP BY
        p.id_proyecto,
        p.nombre,
        c.abr,
        g.grupo,
        p.curso_academico
    ORDER BY
        c.orden,
        c.abr,
        g.grupo NULLS LAST,
        p.nombre
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// FUNCIÓN PARA GENERAR ENLACE DE EMAIL POR PROYECTO
// --------------------------------------------------

function generarMailProyecto(array $proyecto): string
{
    $destinatarios = [];

    if (!empty($proyecto['emails_alumnos'])) {
        $destinatarios = array_filter(array_map('trim', explode(',', $proyecto['emails_alumnos'])));
    }

    //$asunto = 'Accés i gestió del vostre projecte';
    $asunto = 'Últim pas del projecte · Pujada de la presentació';

    // Cada proyecto tiene su UUID único
    $enlace_acceso = 'https://projectes.elpuig.xeill.net/acces';

    $saludo = formatearNombres((string) $proyecto['nombres_alumnos']);


// EMAIL DE ENTRADA PARA EDITAR LA FICHA
$cuerpo = $saludo . "

Us fem arribar l'enllaç d'accés al vostre projecte a la web de Projectes Puig Castellar:

" . $enlace_acceso . "

Aquesta web serà l'ÚNIC punt d'entrega del projecte. No s'haurà de lliurar res per Moodle.

Dins la fitxa del projecte trobareu tota la informació necessària per completar-la correctament, així com els espais per pujar la documentació corresponent.

IMPORTANT:
- Heu d'omplir també l'autoavaluació del projecte.
- Heu d'anar completant la fitxa amb tota la informació del vostre projecte.

DATA LÍMIT:
- Diumenge 17 de maig a les 24:00.
A partir d’aquest moment, l’edició quedarà desactivada.

Encara que no tingueu la memòria o el projecte finalitzat, ja hi ha moltes parts de la fitxa que podeu començar a omplir des d'ara.

No ho deixeu per a l'últim moment.

Una salutació.";


// EMAIL PARA QUE SUBAN LA DEFENSA
$cuerpo = $saludo . "

Us fem arribar novament l'enllaç d'accés al vostre projecte a la web de Projectes Puig Castellar:

" . $enlace_acceso . "

La fase de defensa ja ha finalitzat i ara només queda una última acció per deixar la fitxa del projecte completament tancada.


IMPORTANT:

* Heu de pujar el PDF de la presentació utilitzada durant la defensa.



Un cop pujada la presentació, la fitxa del vostre projecte quedarà completada i passarà a formar part de l’arxiu de Projectes Puig Castellar.

Gràcies per la vostra implicació i bon estiu!.

";




    $gmail = 'https://mail.google.com/mail/?view=cm'
        . '&to='   . rawurlencode(implode(',', $destinatarios))
        . '&su='   . rawurlencode($asunto)
        . '&body=' . rawurlencode($cuerpo);

    return $gmail;
}
?>

<script>
window.PAGE_TITLE = 'Emails alumnat';
</script>

<style>
.email-alumnat-action {
    padding: .25rem .6rem;
    font-size: .875rem;
    line-height: 1.5;
}
</style>

<div class="container-fluid py-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Enviament d’emails a alumnat</h1>
            <p class="text-muted mb-0">Llistat per projecte amb enviament conjunt als alumnes vinculats.</p>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="main" value="enviar-emails-alumnado">

                <div class="col-md-4">
                    <label for="ciclo" class="form-label fw-semibold">Cicle</label>
                    <select name="ciclo" id="ciclo" class="form-select">
                        <option value="">Tots els cicles</option>
                        <?php foreach ($ciclos as $c): ?>
                            <option value="<?= htmlspecialchars((string) $c, ENT_QUOTES, 'UTF-8') ?>" <?= ($ciclo === $c ? 'selected' : '') ?>>
                                <?= htmlspecialchars((string) $c, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="grupo" class="form-label fw-semibold">Grup</label>
                    <select name="grupo" id="grupo" class="form-select">
                        <option value="" <?= ($grupo === '' ? 'selected' : '') ?>>Tots</option>
                        <option value="A" <?= ($grupo === 'A' ? 'selected' : '') ?>>A</option>
                        <option value="B" <?= ($grupo === 'B' ? 'selected' : '') ?>>B</option>
                        <option value="C" <?= ($grupo === 'C' ? 'selected' : '') ?>>C</option>
                        <option value="D" <?= ($grupo === 'D' ? 'selected' : '') ?>>D</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-puig">
                        Filtrar
                    </button>
                    <a href="?main=enviar-emails-alumnado" class="btn btn-outline-secondary ms-2">
                        Netejar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">

            <?php if (empty($proyectos)): ?>
                <div class="p-4 text-muted">
                    No s’han trobat projectes amb aquests filtres.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Projecte</th>
                                <th class="py-3">Cicle</th>
                                <th class="py-3">Grup</th>
                                <th class="py-3">Alumnes</th>
                                <th class="py-3">Emails</th>
                                <th class="py-3 text-end pe-4">Acció</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proyectos as $proyecto): ?>
                                <?php
                                    $hayEmails = !empty($proyecto['emails_alumnos']);
                                    $gmailLink = $hayEmails ? generarMailProyecto($proyecto) : '';
                                ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars((string) $proyecto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>

                                    <td class="py-3">
                                        <?= htmlspecialchars((string) $proyecto['ciclo'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td class="py-3">
                                        <?= htmlspecialchars((string) ($proyecto['grupo'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td class="py-3">
                                        <div class="small">
                                            <?= htmlspecialchars((string) ($proyecto['nombres_alumnos'] ?: 'Sense alumnes vinculats'), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= (int)$proyecto['num_alumnos'] ?> alumne/s
                                        </div>
                                    </td>

                                    <td class="py-3">
                                        <div class="small text-muted" style="max-width: 280px;">
                                            <?php if ($hayEmails): ?>
                                                <?= htmlspecialchars((string) $proyecto['emails_alumnos'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php else: ?>
                                                Sense emails
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="py-3 text-end pe-4">
                                        <?php if ($hayEmails): ?>
                                            <a href="<?= htmlspecialchars($gmailLink, ENT_QUOTES, 'UTF-8') ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="btn btn-puig email-alumnat-action">
                                                Enviar email
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-dark border">
                                                Sense email
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
