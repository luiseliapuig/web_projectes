<?php
declare(strict_types=1);

// Funcions compartides del seguiment de memòria, reutilitzades tant per la
// vista de l'alumnat com per la del professorat perquè cap de les dues
// dupliqui la mateixa lògica.

// Garanteix, de manera idempotent, que existeix una fila de
// app.memoria_seguimiento per a cada apartat actiu de la categoria donada,
// per a aquest projecte. Es pot cridar tantes vegades i des d'on calgui
// (l'alumnat o el professorat, qui hi entri primer) sense crear duplicats:
// INSERT ... ON CONFLICT (proyecto_id, memoria_estructura_id) DO NOTHING.
function memoriaGarantirSeguiment(PDO $pdo, int $proyectoId, int $categoriaId): void
{
    if ($proyectoId <= 0 || $categoriaId <= 0) {
        return;
    }
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
}

// Apartats actius de la categoria per a aquest projecte, amb l'estat actual
// del seguiment i l'últim comentari (pel seu creado_en). LEFT JOIN de reserva:
// si per qualsevol motiu memoriaGarantirSeguiment() no ha pogut crear la fila
// corresponent, l'apartat encara es mostra (com a "pendiente" implícit) en
// lloc de desaparèixer.
function memoriaObtenerApartados(PDO $pdo, int $proyectoId, int $categoriaId): array
{
    if ($categoriaId <= 0) {
        return [];
    }
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Etiquetes i colors dels estats reals de app.memoria_seguimiento (CHECK
// constraint: pendiente, revision_solicitada, corregir, completo). Únicament
// dues famílies de color: verd per a "completo", groc per a la resta.
// Historial complet de comentaris (tots, no només l'últim), agrupats per
// memoria_seguimiento_id i ordenats del més recent al més antic. Una sola
// consulta per a tots els apartats donats, mai N+1. Es carrega sempre
// juntament amb la pàgina; el desplegat "Veure comentaris anteriors" és
// només visual en client, no fa cap petició nova.
function memoriaObtenerComentarios(PDO $pdo, array $idsSeguimiento): array
{
    $idsSeguimiento = array_values(array_unique(array_filter(array_map('intval', $idsSeguimiento))));
    if ($idsSeguimiento === []) {
        return [];
    }
    $marcadores = implode(',', array_fill(0, count($idsSeguimiento), '?'));
    $stmt = $pdo->prepare("
        SELECT memoria_seguimiento_id, comentario, creado_en
        FROM app.memoria_comentarios
        WHERE memoria_seguimiento_id IN ($marcadores)
        ORDER BY memoria_seguimiento_id, creado_en DESC, id_memoria_comentario DESC
    ");
    $stmt->execute($idsSeguimiento);
    $resultado = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $resultado[(int) $fila['memoria_seguimiento_id']][] = $fila;
    }
    return $resultado;
}

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

function memoriaData(?string $data): string
{
    return dataCatalanaNatural($data);
}
