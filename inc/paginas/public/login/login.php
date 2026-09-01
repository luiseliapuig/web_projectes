<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
    function h(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/destinos.php';

/**
 * Si ya hay una sesión autenticada activa,
 * no tiene sentido mostrar otra vez el login.
 */
if (esProfesor() || esAlumno()) {
    $destino = loginDestinoPostAutenticacion(
        (string) ($_SESSION['auth_tipo'] ?? ''),
        isset($_SESSION['professor_rol']) ? (string) $_SESSION['professor_rol'] : null
    );
    echo '<script>location.href=' . json_encode($destino) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . h($destino) . '"></noscript>';
    exit;
}

$msg = trim((string) ($_GET['msg'] ?? ''));
$emailOld = trim((string) ($_GET['email'] ?? ''));
?>

<script>
window.PAGE_TITLE = 'Login';
</script>

<div class="container-fluid px-4 py-4 mt-60 mb-40">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-6 col-xl-5">

            <div class="projectes-header mb-4 text-center">
                <h1 class="projectes-title mb-2">Accés</h1>
                <p class="projectes-subtitle mb-0">
                    Introdueix el teu correu i la teva contrasenya.
                </p>
            </div>

            <?php if (in_array($msg, ['invalid', 'not-found', 'no-password', 'wrong-password'], true)): ?>
                <div class="alert alert-danger">
                    No s’ha pogut validar l’accés.
                </div>
            <?php elseif ($msg === 'missing'): ?>
                <div class="alert alert-warning">
                    Has d’introduir el correu i la contrasenya.
                </div>
            <?php elseif ($msg === 'logout'): ?>
                <div class="alert alert-success">
                    Has tancat la sessió correctament.
                </div>
            <?php elseif ($msg === 'password-reset'): ?>
                <div class="alert alert-success">
                    La contrasenya s’ha actualitzat. Ja pots iniciar sessió.
                </div>
            <?php endif; ?>

            <div class="card-style mb-30">
                <form action="/index.php?main=login_accion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= h(tokenCsrf()) ?>">

                    <div class="mb-3">
                        <label for="email" class="edit-label-subtle">Correu electrònic</label>
                        <input
                            type="email"
                            class="form-control meta-input"
                            id="email"
                            name="email"
                            value="<?= h($emailOld) ?>"
                            required
                            autocomplete="email"
                            placeholder="nom@elpuig.xeill.net"
                        >
                    </div>

                    <div class="mb-4">
                        <label for="password" class="edit-label-subtle">Contrasenya</label>
                        <input
                            type="password"
                            class="form-control meta-input"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="La teva contrasenya"
                        >
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <a href="/recuperar-contrasenya" class="auth-secondary-link">Has oblidat la contrasenya?</a>
                        <button type="submit" class="btn btn-puig px-4">
                            Entrar
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>
