<?php
declare(strict_types=1);

// Dades i helpers de la tasca "Proposta de projecte" (Fase 2), compartits
// entre la seva targeta-resum (fase-2_tasques.php) i el seu detall
// (fase-2_proposta_detall.php), perquè cap dels dos hagi de duplicar la
// consulta ni la derivació de l'estat. Implementació concreta d'una tasca;
// no és un contracte general (vegeu docs/codex/arquitectura.md).

function fase2PropostaObtenirEstat(PDO $pdo, int $idProjecte): array
{
    $proposta = [];
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare("SELECT propuesta_url, propuesta_pdf, propuesta_validada_en FROM app.proyectos WHERE id_proyecto = :id");
        $stmt->execute([':id' => $idProjecte]);
        $proposta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $url = trim((string) ($proposta['propuesta_url'] ?? ''));
    $pdf = trim((string) ($proposta['propuesta_pdf'] ?? ''));
    $validada = ($proposta['propuesta_validada_en'] ?? null) !== null;

    $solicitudOberta = null;
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare("
            SELECT id_revision_solicitud, solicitado_en
            FROM app.revisiones_solicitudes
            WHERE proyecto_id = :id AND tipo = 'proposta' AND resuelto_en IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $idProjecte]);
        $solicitudOberta = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Estat derivat sempre de dades reals ja existents: mai un camp
    // "propuesta_estado" nou.
    //
    // Completada exigeix TOTES DUES condicions (validació + PDF definitiu),
    // no només la validació: el tutor validar el contingut és un pas
    // intermedi, no el final del cicle — encara falta que l'alumnat
    // dipositi l'evidència definitiva.
    $completada = $validada && $pdf !== '';
    // Groc només quan el tutor ha d'actuar; un cop validada, si encara falta
    // el PDF definitiu, la tasca torna al llenguatge actiu/granate perquè la
    // intervenció pendent correspon a l'alumnat.
    $pendentPdf = $validada && !$completada;
    $atencion = $solicitudOberta !== null;

    // Llenguatge cromàtic de tasca (vegeu docs/codex/arquitectura.md):
    // granate = activa/en treball (també validada i pendent de PDF); groc =
    // revisió pendent del tutor; verd = completada (validada + PDF definitiu).
    $texto = $completada
        ? 'Validada'
        : ($pendentPdf
            ? 'Pendent de PDF'
            : ($solicitudOberta !== null ? 'Revisió sol·licitada' : ($url !== '' ? 'En curs' : 'Pendent')));
    $classeBadge = $completada
        ? 'text-bg-success'
        : ($atencion ? 'text-bg-warning' : 'badge-activitat');
    $classeBloc = $completada
        ? 'bloc-completat'
        : ($atencion ? 'bloc-atencio' : 'bloc-activitat');
    // CTA principal (per exemple, "Entrar" a la targeta-resum, o "Validar
    // proposta" al detall): mateixa composició estat + geometria comuna
    // (.btn-fase) que la resta del sistema — mai un botó específic
    // d'aquesta tasca. classe_outline és la mateixa composició en la seva
    // variant outline, per a recursos/documents i per a l'acció secundària
    // del tutor: mai CTA principal, però ha de reflectir el mateix estat.
    $classeCta = $completada
        ? 'btn-outline-success'
        : ($atencion ? 'btn-atencio-solid' : 'btn-puig-solid');
    $classeOutline = $completada
        ? 'btn-outline-success'
        : ($atencion ? 'btn-atencio' : 'btn-puig');

    return [
        'url' => $url,
        'pdf' => $pdf,
        'validada' => $validada,
        'completada' => $completada,
        'atencion' => $atencion,
        'solicitud_oberta' => $solicitudOberta,
        'text' => $texto,
        'classe_badge' => $classeBadge,
        'classe_bloc' => $classeBloc,
        'classe_cta' => $classeCta,
        'classe_outline' => $classeOutline,
    ];
}

// Classificacio del projecte (Pas 1 de la tasca): reutilitza el catàleg ja
// administrable a app.proyecto_categorias / app.proyecto_tipos (el mateix
// que fa servir la Memòria per triar l'estructura d'apartats), mai una
// taxonomia nova. Un tipus sempre pertany a UNA categoria concreta
// (proyecto_tipos.categoria_proyecto_id): si una categoria no en té cap
// d'activa, simplement no requereix subtipus — no cal comprovar el nom
// "Investigació"/"Desenvolupament" enlloc.
function fase2ClassificacioObtenirEstat(PDO $pdo, int $idProjecte): array
{
    $familiaId = 0;
    $categoriaId = null;
    $tipoId = null;
    if ($idProjecte > 0) {
        $stmt = $pdo->prepare("
            SELECT c.familia_ciclo_id, p.categoria_proyecto_id, p.tipo_proyecto_id
            FROM app.proyectos p
            INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
            INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
            WHERE p.id_proyecto = :id
        ");
        $stmt->execute([':id' => $idProjecte]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $familiaId = (int) ($fila['familia_ciclo_id'] ?? 0);
        $categoriaId = $fila['categoria_proyecto_id'] !== null ? (int) $fila['categoria_proyecto_id'] : null;
        $tipoId = $fila['tipo_proyecto_id'] !== null ? (int) $fila['tipo_proyecto_id'] : null;
    }

    // Categories actives de la família d'aquest projecte. Grandfathering:
    // si la categoria ja desada s'ha desactivat després, es continua
    // mostrant (mateix criteri que ja fa servir tipus-projectes_form.php).
    $categories = [];
    if ($familiaId > 0) {
        $stmt = $pdo->prepare("
            SELECT id_categoria_proyecto, nombre
            FROM app.proyecto_categorias
            WHERE familia_ciclo_id = :familia AND (activo = true OR id_categoria_proyecto = :categoria_actual)
            ORDER BY orden, nombre
        ");
        $stmt->execute([':familia' => $familiaId, ':categoria_actual' => $categoriaId ?? 0]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Tipus actius agrupats per categoria: calen TOTS els de la família (no
    // només els de la categoria actual) perquè el segon selector ja mostri
    // les opcions correctes en canviar de categoria sense una petició nova.
    $tiposPerCategoria = [];
    if ($categories !== []) {
        $idsCategories = array_map(static fn (array $c): int => (int) $c['id_categoria_proyecto'], $categories);
        $placeholders = implode(',', array_fill(0, count($idsCategories), '?'));
        $params = $idsCategories;
        $params[] = $tipoId ?? 0;
        $stmt = $pdo->prepare("
            SELECT id_tipo_proyecto, nombre, categoria_proyecto_id
            FROM app.proyecto_tipos
            WHERE categoria_proyecto_id IN ($placeholders) AND (activo = true OR id_tipo_proyecto = ?)
            ORDER BY orden, nombre
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $tiposPerCategoria[(int) $fila['categoria_proyecto_id']][] = [
                'id' => (int) $fila['id_tipo_proyecto'],
                'nombre' => (string) $fila['nombre'],
            ];
        }
    }

    $categoriaNom = null;
    foreach ($categories as $c) {
        if ((int) $c['id_categoria_proyecto'] === $categoriaId) {
            $categoriaNom = (string) $c['nombre'];
            break;
        }
    }

    // El tipus només és vàlid si realment pertany a la categoria actual: un
    // tipo_proyecto_id "orfe" (d'una categoria diferent, per exemple per un
    // estat legacy o una edició manual) no compta com a subtipus triat.
    $tipoNom = null;
    if ($categoriaId !== null) {
        foreach ($tiposPerCategoria[$categoriaId] ?? [] as $t) {
            if ($t['id'] === $tipoId) {
                $tipoNom = $t['nombre'];
                break;
            }
        }
    }
    $tipoValid = $tipoNom !== null;

    $requereixSubtipus = $categoriaId !== null && !empty($tiposPerCategoria[$categoriaId]);
    $completat = $categoriaId !== null && (!$requereixSubtipus || $tipoValid);

    // Valor inicial VISUAL quan encara no hi ha res desat: la primera
    // categoria de la família que tingui subtipus (normalment
    // "Desenvolupament"), determinat per les dades reals, no per nom fix.
    // És només el valor inicial de la interfície: no es desa automàticament.
    $categoriaPerDefecte = $categoriaId;
    if ($categoriaPerDefecte === null) {
        foreach ($categories as $c) {
            if (!empty($tiposPerCategoria[(int) $c['id_categoria_proyecto']])) {
                $categoriaPerDefecte = (int) $c['id_categoria_proyecto'];
                break;
            }
        }
        if ($categoriaPerDefecte === null && $categories !== []) {
            $categoriaPerDefecte = (int) $categories[0]['id_categoria_proyecto'];
        }
    }

    return [
        'familia_ciclo_id' => $familiaId,
        'categories' => $categories,
        'tipos_per_categoria' => $tiposPerCategoria,
        'categoria_id' => $categoriaId,
        'categoria_nombre' => $categoriaNom,
        'tipo_id' => $tipoId,
        'tipo_nombre' => $tipoNom,
        'categoria_per_defecte' => $categoriaPerDefecte,
        'requereix_subtipus' => $requereixSubtipus,
        'completat' => $completat,
        'classe_bloc' => $completat ? 'bloc-completat' : 'bloc-activitat',
        'classe_badge' => $completat ? 'text-bg-success' : 'badge-activitat',
    ];
}

function fase2PropostaData(?string $data): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $marca = strtotime($data);
    return $marca !== false ? date('d/m/Y', $marca) : $data;
}
