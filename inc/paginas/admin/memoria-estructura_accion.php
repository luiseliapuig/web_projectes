<?php
declare(strict_types=1);

soloSuperadmin();

// Redirección fija al listado sin exponer detalles internos.
$redirigirMemoriaEstructura = static function (string $sufijo = ''): never {
    $url = '/index.php?main=memoria-estructura' . $sufijo;
    echo '<script>location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url='
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
};

// Renumera 1, 2, 3... els apartats d'una categoria seguint el seu ordre
// actual, tancant qualsevol forat deixat per una baixa o un canvi de
// categoria. S'usa sempre dins d'una transacció ja oberta pel cridant.
$normalitzarCategoria = static function (PDO $pdo, int $categoriaId): void {
    $pdo->prepare("
        UPDATE app.memoria_estructura AS m
        SET orden = t.rn
        FROM (
            SELECT id_memoria_estructura, ROW_NUMBER() OVER (ORDER BY orden, id_memoria_estructura) AS rn
            FROM app.memoria_estructura
            WHERE categoria_proyecto_id = :categoria_id
        ) AS t
        WHERE m.id_memoria_estructura = t.id_memoria_estructura
    ")->execute([':categoria_id' => $categoriaId]);
};

// Validación general de petición, token y modo.
$modo = isset($_POST['modo']) && is_string($_POST['modo']) ? $_POST['modo'] : '';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validarTokenCsrf($_POST['csrf_token'] ?? null)
    || !in_array($modo, ['new', 'edit', 'delete'], true)
) {
    $_SESSION['memoria_estructura_error'] = 'La sol·licitud no és vàlida o ha caducat.';
    $redirigirMemoriaEstructura();
}

$id = isset($_POST['id_memoria_estructura']) ? (int) $_POST['id_memoria_estructura'] : 0;

// El borrado normaliza después el orden de los apartados restantes de la
// misma categoría. No se hace ningún borrado en cascada adicional: la FK
// existente (ON DELETE CASCADE hacia memoria_seguimiento) no se toca aquí.
if ($modo === 'delete') {
    if ($id <= 0) {
        $_SESSION['memoria_estructura_error'] = 'Apartat no vàlid.';
        $redirigirMemoriaEstructura();
    }

    $stmt = $pdo->prepare("SELECT categoria_proyecto_id FROM app.memoria_estructura WHERE id_memoria_estructura = :id");
    $stmt->execute([':id' => $id]);
    $categoriaId = $stmt->fetchColumn();

    if ($categoriaId === false) {
        $_SESSION['memoria_estructura_error'] = 'Apartat no trobat.';
        $redirigirMemoriaEstructura();
    }
    $categoriaId = (int) $categoriaId;

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM app.memoria_estructura WHERE id_memoria_estructura = :id")->execute([':id' => $id]);
        $normalitzarCategoria($pdo, $categoriaId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error borrant apartat de memòria: ' . $e->getMessage());
        $_SESSION['memoria_estructura_error'] = 'No s’ha pogut borrar l’apartat.';
        $redirigirMemoriaEstructura();
    }
    $redirigirMemoriaEstructura('&msg=' . urlencode('Apartat borrat correctament.'));
}

// Validación de campos. El orden nunca llega por formulario: lo gestiona
// siempre el sistema (siguiente posición al crear o al cambiar de
// categoría; drag & drop desde el listado para reordenar).
$categoriaId = isset($_POST['categoria_proyecto_id']) ? (int) $_POST['categoria_proyecto_id'] : 0;
$titulo = isset($_POST['titulo']) && is_string($_POST['titulo']) ? trim($_POST['titulo']) : '';
$descripcion = isset($_POST['descripcion']) && is_string($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$enlaceGuia = isset($_POST['enlace_guia']) && is_string($_POST['enlace_guia']) ? trim($_POST['enlace_guia']) : '';
$activo = isset($_POST['activo']);

// La categoria debe estar activa, salvo la ya vinculada al apartado editado.
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM app.proyecto_categorias cp
    WHERE cp.id_categoria_proyecto = :categoria_id
      AND (
            cp.activo = true
            OR EXISTS (
                SELECT 1 FROM app.memoria_estructura me
                WHERE me.id_memoria_estructura = :id
                  AND me.categoria_proyecto_id = cp.id_categoria_proyecto
            )
      )
");
$stmt->execute([':categoria_id' => $categoriaId, ':id' => $id]);
$categoriaPermitida = (int) $stmt->fetchColumn() === 1;

if (
    $titulo === ''
    || mb_strlen($titulo) > 150
    || mb_strlen($descripcion) > 4000
    || ($enlaceGuia !== '' && (mb_strlen($enlaceGuia) > 2048 || !filter_var($enlaceGuia, FILTER_VALIDATE_URL)))
    || !$categoriaPermitida
    || ($modo === 'edit' && $id <= 0)
) {
    $_SESSION['memoria_estructura_error'] = 'Revisa els camps obligatoris de l’apartat (títol, categoria i, si l’informes, l’enllaç a la guia).';
    $redirigirMemoriaEstructura();
}

$descripcionValor = $descripcion !== '' ? $descripcion : null;
$enlaceGuiaValor = $enlaceGuia !== '' ? $enlaceGuia : null;

try {
    if ($modo === 'edit') {
        $stmt = $pdo->prepare("SELECT categoria_proyecto_id FROM app.memoria_estructura WHERE id_memoria_estructura = :id");
        $stmt->execute([':id' => $id]);
        $categoriaAnterior = $stmt->fetchColumn();
        if ($categoriaAnterior === false) {
            $_SESSION['memoria_estructura_error'] = 'Apartat no trobat.';
            $redirigirMemoriaEstructura();
        }
        $categoriaAnterior = (int) $categoriaAnterior;

        $pdo->beginTransaction();

        if ($categoriaAnterior !== $categoriaId) {
            // Canvi de categoria: es col·loca al final de la nova, i les
            // dues categories (l’anterior i la nova) queden normalitzades.
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM app.memoria_estructura WHERE categoria_proyecto_id = :categoria_id");
            $stmt->execute([':categoria_id' => $categoriaId]);
            $nouOrdre = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                UPDATE app.memoria_estructura
                SET categoria_proyecto_id = :categoria_id, titulo = :titulo, descripcion = :descripcion,
                    enlace_guia = :enlace_guia, activo = :activo, orden = :orden
                WHERE id_memoria_estructura = :id
            ");
            $stmt->execute([
                ':categoria_id' => $categoriaId,
                ':titulo' => $titulo,
                ':descripcion' => $descripcionValor,
                ':enlace_guia' => $enlaceGuiaValor,
                ':activo' => $activo,
                ':orden' => $nouOrdre,
                ':id' => $id,
            ]);

            $normalitzarCategoria($pdo, $categoriaAnterior);
            $normalitzarCategoria($pdo, $categoriaId);
        } else {
            // Sense canvi de categoria: l’ordre no es toca aquí, només es
            // gestiona des del drag & drop del llistat.
            $stmt = $pdo->prepare("
                UPDATE app.memoria_estructura
                SET titulo = :titulo, descripcion = :descripcion, enlace_guia = :enlace_guia, activo = :activo
                WHERE id_memoria_estructura = :id
            ");
            $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcionValor,
                ':enlace_guia' => $enlaceGuiaValor,
                ':activo' => $activo,
                ':id' => $id,
            ]);
        }

        $pdo->commit();
        $msg = 'Apartat actualitzat correctament.';
    } else {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM app.memoria_estructura WHERE categoria_proyecto_id = :categoria_id");
        $stmt->execute([':categoria_id' => $categoriaId]);
        $nouOrdre = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO app.memoria_estructura (categoria_proyecto_id, titulo, descripcion, enlace_guia, activo, orden)
            VALUES (:categoria_id, :titulo, :descripcion, :enlace_guia, :activo, :orden)
        ");
        $stmt->execute([
            ':categoria_id' => $categoriaId,
            ':titulo' => $titulo,
            ':descripcion' => $descripcionValor,
            ':enlace_guia' => $enlaceGuiaValor,
            ':activo' => $activo,
            ':orden' => $nouOrdre,
        ]);

        $pdo->commit();
        $msg = 'Apartat creat correctament.';
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error guardant apartat de memòria: ' . $e->getMessage());
    $_SESSION['memoria_estructura_error'] = 'No s’han pogut guardar les dades de l’apartat.';
    $redirigirMemoriaEstructura();
}

$redirigirMemoriaEstructura('&msg=' . urlencode($msg));
