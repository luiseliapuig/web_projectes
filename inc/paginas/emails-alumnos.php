<?php

// --------------------------------------------------
// FILTROS
// --------------------------------------------------

$ciclo = $_GET['ciclo'] ?? '';
$grupo = $_GET['grupo'] ?? '';

// Valores posibles de filtro de grupo:
// ''       -> todos
// 'A'      -> solo grupo A
// 'RESTO'  -> todos menos A

$where = [];
$params = [];

if ($ciclo !== '') {
    $where[] = 'p.ciclo = :ciclo';
    $params[':ciclo'] = $ciclo;
}

if ($grupo === 'A') {
    $where[] = 'p.grupo = :grupo_a';
    $params[':grupo_a'] = 'A';
} elseif ($grupo === 'RESTO') {
    $where[] = "COALESCE(p.grupo, '') <> 'A'";
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// --------------------------------------------------
// LISTADO DE CICLOS PARA EL FILTRO
// --------------------------------------------------

$sqlCiclos = "
    SELECT DISTINCT ciclo
    FROM app.proyectos
    ORDER BY ciclo
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
        p.uuid,
        p.nombre,
        p.ciclo,
        p.grupo,
        p.curso_academico,
        STRING_AGG(a.email, ',' ORDER BY a.apellidos, a.nombre) AS emails_alumnos,
        STRING_AGG(
            TRIM(COALESCE(a.nombre, '') || ' ' || COALESCE(a.apellidos, '')),
            ', ' ORDER BY a.apellidos, a.nombre
        ) AS nombres_alumnos,
        COUNT(a.id_alumno) AS num_alumnos
    FROM app.proyectos p
    LEFT JOIN app.rel_proyectos_alumnos rpa
        ON rpa.proyecto_id = p.id_proyecto
    LEFT JOIN app.alumnos a
        ON a.id_alumno = rpa.alumno_id
    $whereSql
    GROUP BY
        p.id_proyecto,
        p.uuid,
        p.nombre,
        p.ciclo,
        p.grupo,
        p.curso_academico
    ORDER BY
        p.ciclo,
        p.grupo NULLS LAST,
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

    $asunto = 'Accés i gestió del vostre projecte';

    // Cada proyecto tiene su UUID único
    $enlace_acceso = 'https://projectes.elpuig.xeill.net/login/alumnes/' . $proyecto['uuid'];

    $cuerpo = "Bon dia,

Us fem arribar l'enllaç d'accés al vostre projecte a la web de Projectes Puig Castellar.

Accés directe:
" . $enlace_acceso . "

Des d'aquest enllaç podreu completar i actualitzar la fitxa del vostre projecte, afegir informació i pujar la documentació corresponent.

Una salutació.";

    $gmail = 'https://mail.google.com/mail/?view=cm'
        . '&to='   . rawurlencode(implode(',', $destinatarios))
        . '&su='   . rawurlencode($asunto)
        . '&body=' . rawurlencode($cuerpo);

    return $gmail;
}
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Enviament d’emails a alumnat</h1>
            <p class="text-muted mb-0">Llistat per projecte amb enviament conjunt als alumnes vinculats.</p>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="main" value="emails-alumnos">

                <div class="col-md-4">
                    <label for="ciclo" class="form-label fw-semibold">Cicle</label>
                    <select name="ciclo" id="ciclo" class="form-select">
                        <option value="">Tots els cicles</option>
                        <?php foreach ($ciclos as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($ciclo === $c ? 'selected' : '') ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="grupo" class="form-label fw-semibold">Grup</label>
                    <select name="grupo" id="grupo" class="form-select">
                        <option value="" <?= ($grupo === '' ? 'selected' : '') ?>>Tots</option>
                        <option value="A" <?= ($grupo === 'A' ? 'selected' : '') ?>>Només A</option>
                        <option value="RESTO" <?= ($grupo === 'RESTO' ? 'selected' : '') ?>>Resta de grups</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        Filtrar
                    </button>
                    <a href="?main=emails-alumnos" class="btn btn-outline-secondary ms-2">
                        Netejar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">

            <?php if (empty($proyectos)): ?>
                <div class="p-4 text-muted">
                    No s’han trobat projectes amb aquests filtres.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
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
                                            <?= htmlspecialchars($proyecto['nombre']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            UUID: <?= htmlspecialchars($proyecto['uuid']) ?>
                                        </div>
                                    </td>

                                    <td class="py-3">
                                        <?= htmlspecialchars($proyecto['ciclo']) ?>
                                    </td>

                                    <td class="py-3">
                                        <?= htmlspecialchars($proyecto['grupo'] ?? '-') ?>
                                    </td>

                                    <td class="py-3">
                                        <div class="small">
                                            <?= htmlspecialchars($proyecto['nombres_alumnos'] ?: 'Sense alumnes vinculats') ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= (int)$proyecto['num_alumnos'] ?> alumne/s
                                        </div>
                                    </td>

                                    <td class="py-3">
                                        <div class="small text-muted" style="max-width: 280px;">
                                            <?php if ($hayEmails): ?>
                                                <?= htmlspecialchars($proyecto['emails_alumnos']) ?>
                                            <?php else: ?>
                                                Sense emails
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td class="py-3 text-end pe-4">
                                        <?php if ($hayEmails): ?>
                                            <a href="<?= htmlspecialchars($gmailLink) ?>"
                                               target="_blank"
                                               class="btn btn-sm btn-primary">
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