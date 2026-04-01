<?php
/**
 * ============================================================
 * CONTEXTO DE PERFIL PARA EL HEADER
 * ============================================================
 *
 * Este bloque prepara los datos que necesita el desplegable
 * superior derecho del header.
 *
 * Estados contemplados:
 * - alumno autenticado
 * - profesor autenticado
 * - sin sesión
 *
 * Requisitos asumidos:
 * - $pdo existe
 * - la sesión ya está iniciada en el flujo general
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * ------------------------------------------------------------
 * Valores por defecto: usuario no autenticado
 * ------------------------------------------------------------
 */
$headerAuthTipo = $_SESSION['auth_tipo'] ?? '';
$headerIsLogged = false;

$headerProfileName = 'Invitat';
$headerProfileRole = 'Sense sessió';
$headerProfileEmail = '';
$headerProfileImage = '';
$headerHasImage = false;

/**
 * Enlace principal del desplegable.
 * En alumnos/profesores se puede dejar como "#".
 * En no autenticado llevará a la futura pantalla de login.
 */
$headerMainLink = '#';

/**
 * Si no hay imagen real, mostramos un icono/placeholder textual.
 */
$headerFallbackIcon = '👤';

/**
 * ============================================================
 * CASO 1: ALUMNO AUTENTICADO
 * ============================================================
 *
 * La sesión del alumno guarda el proyecto.
 * A partir de ese id_proyecto sacamos el nombre del alumno
 * o alumnos asociados para mostrarlo en el header.
 * ============================================================
 */
if ($headerAuthTipo === 'alumne' && isset($_SESSION['projecte_id'])) {
    $idProyectoHeader = (int) $_SESSION['projecte_id'];

    if ($idProyectoHeader > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    a.nombre,
                    a.apellidos
                FROM rel_proyectos_alumnos r
                INNER JOIN alumnos a
                    ON a.id_alumno = r.alumno_id
                WHERE r.proyecto_id = :id_proyecto
                ORDER BY a.apellidos, a.nombre
            ");
            $stmt->execute([
                'id_proyecto' => $idProyectoHeader
            ]);
            $alumnosHeader = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $alumnosHeader = [];
        }

        $nombres = [];

        foreach ($alumnosHeader as $alumnoHeader) {
            $nombreCompleto = trim(
                ((string) ($alumnoHeader['nombre'] ?? '')) . ' ' .
                ((string) ($alumnoHeader['apellidos'] ?? ''))
            );

            if ($nombreCompleto !== '') {
                $nombres[] = $nombreCompleto;
            }
        }

        $headerIsLogged = true;
        $headerProfileName = !empty($nombres) ? implode(', ', $nombres) : 'Alumnat';
        $headerProfileRole = count($nombres) > 1 ? 'Alumnes' : 'Alumne';
        $headerProfileEmail = '';
        $headerProfileImage = '';
        $headerHasImage = false;
        $headerMainLink = '/projecte/' . $idProyectoHeader;
        $headerFallbackIcon = '👥';
    }
}

/**
 * ============================================================
 * CASO 2: PROFESOR AUTENTICADO
 * ============================================================
 *
 * Para el profesor usamos la sesión.
 * Si existe id_profesor, consultamos la BD para mostrar
 * la información más actualizada posible.
 * ============================================================
 */
if (
    in_array($headerAuthTipo, ['professor', 'professor_pending'], true) &&
    isset($_SESSION['professor_id'])
) {
    $idProfesorHeader = (int) $_SESSION['professor_id'];

    if ($idProfesorHeader > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    id_profesor,
                    nombre,
                    apellidos,
                    email,
                    rol,
                    imagen
                FROM profesores
                WHERE id_profesor = :id_profesor
                LIMIT 1
            ");
            $stmt->execute([
                'id_profesor' => $idProfesorHeader
            ]);
            $profesorHeader = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $profesorHeader = false;
        }

        if ($profesorHeader) {
            $headerIsLogged = true;
            $headerProfileName = trim(
                ((string) ($profesorHeader['nombre'] ?? '')) . ' ' .
                ((string) ($profesorHeader['apellidos'] ?? ''))
            );
            $headerProfileRole = 'Professor';
            $headerProfileEmail = (string) ($profesorHeader['email'] ?? '');
            $headerMainLink = '#';

            $imagenProfesor = trim((string) ($profesorHeader['imagen'] ?? ''));

            if ($imagenProfesor !== '') {
                if (
                    str_starts_with($imagenProfesor, '/')
                    || str_starts_with($imagenProfesor, 'http://')
                    || str_starts_with($imagenProfesor, 'https://')
                ) {
                    $headerProfileImage = $imagenProfesor;
                } else {
                    /**
                     * Ajusta esta carpeta si finalmente usas otra
                     * para las fotos del profesorado.
                     */
                    $headerProfileImage = 'https://elpuig.xeill.net/custom/img/profes/' . ltrim($imagenProfesor, '/');
                }

                $headerHasImage = true;
            } else {
                $headerHasImage = false;
                $headerProfileImage = '';
            }
        }
    }
}

/**
 * ============================================================
 * CASO 3: SIN SESIÓN
 * ============================================================
 *
 * Si no hay sesión válida, no mostramos desplegable de perfil.
 * Mostraremos simplemente un botón/enlace de login.
 * ============================================================
 */
if (!$headerIsLogged) {
    $headerProfileName = 'Accedir';
    $headerProfileRole = 'Professorat';
    $headerMainLink = '/accedir';
}
?>

<!-- profile start -->
<div class="profile-box ms-3">

<?php if ($headerIsLogged): ?>
    <button
        class="dropdown-toggle bg-transparent border-0 p-0"
        type="button"
        id="profile"
        data-bs-toggle="dropdown"
        aria-expanded="false"
    >
        <div class="profile-info d-flex align-items-center">
            <div class="info d-flex align-items-center">

                <div class="image me-2">
                    <?php if ($headerHasImage): ?>
                        <img
                            src="<?= h($headerProfileImage) ?>"
                            alt="Perfil"
                            class="profile-avatar"
                        >
                    <?php else: ?>
                        <div
                            class="profile-avatar d-flex align-items-center justify-content-center"
                            style="background:#f3f4f6; color:#6b7280; font-size:1rem;"
                        >
                            <?= h($headerFallbackIcon) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-start">
                    <h6 class="mb-0 fw-semibold profile-name"><?= h($headerProfileName) ?></h6>

                    <div class="d-flex align-items-center gap-1">
                        <small class="text-muted profile-role"><?= h($headerProfileRole) ?></small>
                        <span class="profile-caret"></span>
                    </div>
                </div>

            </div>
        </div>
    </button>

    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="profile">
        <li>
            <div class="author-info d-flex align-items-center p-3">
                <div class="image me-2">
                    <?php if ($headerHasImage): ?>
                        <img
                            src="<?= h($headerProfileImage) ?>"
                            alt="image"
                            class="profile-avatar profile-avatar-lg"
                        >
                    <?php else: ?>
                        <div
                            class="profile-avatar profile-avatar-lg d-flex align-items-center justify-content-center"
                            style="background:#f3f4f6; color:#6b7280; font-size:1.2rem;"
                        >
                            <?= h($headerFallbackIcon) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content">
                    <h6 class="mb-1"><?= h($headerProfileName) ?></h6>

                    <?php if ($headerProfileEmail !== ''): ?>
                        <a
                            class="small text-muted text-decoration-none"
                            href="mailto:<?= h($headerProfileEmail) ?>"
                        >
                            <?= h($headerProfileEmail) ?>
                        </a>
                    <?php else: ?>
                        <span class="small text-muted"><?= h($headerProfileRole) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </li>

        <li><hr class="dropdown-divider"></li>

        <?php if ($headerAuthTipo === 'alumne' && isset($_SESSION['projecte_id'])): ?>
            <li>
                <a class="dropdown-item" href="/projecte/<?= (int) $_SESSION['projecte_id'] ?>">
                    <i class="lni lni-files me-2"></i>La meva fitxa
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/projecte/<?= (int) $_SESSION['projecte_id'] ?>/editar">
                    <i class="lni lni-pencil me-2"></i>Editar fitxa
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($headerAuthTipo, ['professor', 'professor_pending'], true)): ?>
            <li>
                <a class="dropdown-item" href="/password">
                    <i class="lni lni-lock me-2"></i>Canviar contrasenya
                </a>
            </li>
        <?php endif; ?>

        <li><hr class="dropdown-divider"></li>

        <li>
            <a class="dropdown-item text-danger" href="/logout">
                <i class="lni lni-exit me-2"></i>Tancar sessió
            </a>
        </li>
    </ul>

<?php else: ?>

    <a href="/acces" class="btn btn-puig btn-outline-primary">
        Iniciar sessió
    </a>

<?php endif; ?>

</div>
<!-- profile end -->