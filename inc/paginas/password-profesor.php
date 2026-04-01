<?php
declare(strict_types=1);

/**
 * ============================================================
 * PÁGINA DE GENERACIÓN / CAMBIO DE CONTRASEÑA DEL PROFESORADO
 * ============================================================
 *
 * Esta pantalla se carga dentro de index.php y reutiliza
 * el layout general de la web.
 *
 * Puede entrar aquí un profesor en dos estados:
 *
 * 1. professor_pending
 *    Ha llegado mediante su enlace único y todavía está
 *    en el flujo de generar/cambiar contraseña.
 *
 * 2. professor
 *    Ya tiene acceso activado. En esta fase provisional
 *    también le permitimos ver esta pantalla para volver
 *    a cambiar la contraseña si entra otra vez por enlace.
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authTipo = $_SESSION['auth_tipo'] ?? '';
$professorId = isset($_SESSION['professor_id']) ? (int) $_SESSION['professor_id'] : 0;
$professorUuid = trim((string) ($_SESSION['professor_uuid'] ?? ''));

if (
    !in_array($authTipo, ['professor_pending', 'professor'], true) ||
    $professorId <= 0 ||
    $professorUuid === ''
) {
    echo '<div class="alert alert-danger">Accés no disponible.</div>';
    return;
}

if (!function_exists('h')) {
    function h(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id_profesor,
            nombre,
            apellidos,
            email,
            departamento,
            rol,
            imagen,
            activo
        FROM profesores
        WHERE id_profesor = :id_profesor
          AND uuid_acceso = :uuid_acceso
          AND activo = true
        LIMIT 1
    ");
    $stmt->execute([
        'id_profesor' => $professorId,
        'uuid_acceso' => $professorUuid
    ]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">S’ha produït un error en carregar les dades del professorat.</div>';
    return;
}

if (!$profesor) {
    echo '<div class="alert alert-warning">No s’ha pogut validar l’accés del professor.</div>';
    return;
}

$nombreCompleto = trim(
    ((string) ($profesor['nombre'] ?? '')) . ' ' . ((string) ($profesor['apellidos'] ?? ''))
);

$imagenProfesor = trim((string) ($profesor['imagen'] ?? ''));
$rutaImagenProfesor = '';

if ($imagenProfesor !== '') {
    if (
        str_starts_with($imagenProfesor, '/')
        || str_starts_with($imagenProfesor, 'http://')
        || str_starts_with($imagenProfesor, 'https://')
    ) {
        $rutaImagenProfesor = $imagenProfesor;
    } else {
        /**
         * Ajusta esta carpeta si tus fotos de profes viven en otra ruta.
         */
        $rutaImagenProfesor = 'https://elpuig.xeill.net/custom/img/profes/' . ltrim($imagenProfesor, '/');
    }
}

$msg = trim((string) ($_GET['msg'] ?? ''));
?>

<script>
window.PAGE_TITLE = 'Contrasenya del professorat';
</script>

<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">

            <div class="projectes-header mb-4 text-center">
                <h1 class="projectes-title mb-2">Accés del professorat</h1>
                <p class="projectes-subtitle mb-0">
                    Defineix o actualitza la teva contrasenya d’accés.
                </p>
            </div>

            <?php if ($msg === 'ok'): ?>
                <div class="alert alert-success">
                    La contrasenya s’ha guardat correctament.
                </div>
            <?php elseif ($msg === 'mismatch'): ?>
                <div class="alert alert-warning">
                    Les dues contrasenyes no coincideixen.
                </div>
            <?php elseif ($msg === 'short'): ?>
                <div class="alert alert-warning">
                    La contrasenya ha de tenir com a mínim 8 caràcters.
                </div>
            <?php elseif ($msg === 'invalid'): ?>
                <div class="alert alert-danger">
                    No s’ha pogut validar l’operació.
                </div>
            <?php elseif ($msg === 'error'): ?>
                <div class="alert alert-danger">
                    No s’ha pogut guardar la contrasenya.
                </div>
            <?php endif; ?>

            <div class="card-style mb-30">
                <div class="row g-4 align-items-center">

                    <div class="col-md-4 text-center">
                        <?php if ($rutaImagenProfesor !== ''): ?>
                            <img
                                src="<?= h($rutaImagenProfesor) ?>"
                                alt="<?= h($nombreCompleto) ?>"
                                class="img-fluid rounded-circle"
                                style="width: 160px; height: 160px; object-fit: cover; border: 4px solid #f3f4f6;"
                            >
                        <?php else: ?>
                            <div
                                class="mx-auto rounded-circle d-flex align-items-center justify-content-center text-muted"
                                style="width: 160px; height: 160px; background: #f3f4f6; border: 1px solid #e5e7eb; font-size: 2rem;"
                            >
                                👤
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-8">

                        <div class="mb-4">
                            <div class="edit-label-subtle mb-1">Professor/a</div>
                            <h2 style="font-size: 1.8rem; line-height: 1.2; margin-bottom: 0.5rem;">
                                <?= h($nombreCompleto) ?>
                            </h2>

                            <?php if (!empty($profesor['departamento'])): ?>
                                <div class="text-muted mb-1">
                                    <?= h((string) $profesor['departamento']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($profesor['email'])): ?>
                                <div class="text-muted">
                                    <?= h((string) $profesor['email']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form action="/index.php?main=password-profesor_accion" method="post">
                            <input type="hidden" name="id_profesor" value="<?= (int) $profesor['id_profesor'] ?>">
                            <input type="hidden" name="token" value="<?= h($professorUuid) ?>">

                            <div class="mb-3">
                                <label for="password" class="edit-label-subtle">Nova contrasenya</label>
                                <input
                                    type="password"
                                    class="form-control meta-input"
                                    id="password"
                                    name="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Mínim 8 caràcters"
                                >
                            </div>

                            <div class="mb-4">
                                <label for="password_repeat" class="edit-label-subtle">Repeteix la contrasenya</label>
                                <input
                                    type="password"
                                    class="form-control meta-input"
                                    id="password_repeat"
                                    name="password_repeat"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Repeteix la contrasenya"
                                >
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-puig px-4">
                                    Guardar contrasenya
                                </button>
                            </div>
                        </form>



                    </div>

                </div>
            </div>


        
        </div>
    </div>
</div>