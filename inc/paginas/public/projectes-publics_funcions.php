<?php
declare(strict_types=1);

function projectesPublicsCicles(): array
{
    return ['SMX', 'DAM', 'DAW', 'ASIX', 'DEV'];
}

/**
 * Condició SQL canònica dels llistats públics de projectes.
 *
 * El curs es compara pel seu any inicial després de validar el format YYYY-YY;
 * el paràmetre procedeix sempre de cursoAcademicoActual().
 */
function projectesPublicsCondicioSql(string $alias = 'p'): string
{
    if (!preg_match('/^[a-z][a-z0-9_]*$/i', $alias)) {
        throw new InvalidArgumentException('Àlies SQL no vàlid.');
    }

    return "$alias.publicado = true
        AND $alias.curso_academico ~ '^[0-9]{4}-[0-9]{2}$'
        AND substring($alias.curso_academico FROM 1 FOR 4)::integer < :curs_public_actual_inici";
}

function projectesPublicsParametres(): array
{
    $cursActual = cursoAcademicoActual();
    if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $cursActual)) {
        throw new RuntimeException('El curs acadèmic actual no té el format canònic.');
    }

    return [':curs_public_actual_inici' => (int) substr($cursActual, 0, 4)];
}

/**
 * Catàleg actiu de tecnologies amb el nombre de projectes públics associats.
 */
function projectesPublicsTecnologies(PDO $pdo): array
{
    $condicioProjectePublic = projectesPublicsCondicioSql('p');
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
    $stmt->execute(projectesPublicsParametres());

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Tecnologies més utilitzades: ús descendent, nom ascendent i id estable.
 */
function projectesPublicsTecnologiesDestacades(array $tecnologies, int $limit = 10): array
{
    usort(
        $tecnologies,
        static function (array $a, array $b): int {
            $perProjectes = (int) $b['projectes_publics'] <=> (int) $a['projectes_publics'];
            if ($perProjectes !== 0) {
                return $perProjectes;
            }

            $perNom = strnatcasecmp((string) $a['nombre'], (string) $b['nombre']);
            return $perNom !== 0 ? $perNom : (int) $a['id'] <=> (int) $b['id'];
        }
    );

    return array_slice($tecnologies, 0, $limit);
}

/**
 * Unitats terminals del catàleg públic: subtipus de categories que en tenen
 * i categories sense cap subtipus actiu. No inclou la clàusula WITH exterior.
 */
function projectesPublicsUnitatsSql(): string
{
    return <<<'SQL'
        SELECT
            'tipus'::text AS nivell,
            tp.id_tipo_proyecto AS unitat_id,
            tp.nombre AS unitat_nom,
            cp.id_categoria_proyecto AS categoria_id,
            0 AS bloc_ordre,
            cp.orden AS categoria_ordre,
            tp.orden AS unitat_ordre
        FROM app.proyecto_tipos tp
        INNER JOIN app.proyecto_categorias cp
            ON cp.id_categoria_proyecto = tp.categoria_proyecto_id
        WHERE cp.activo = true
          AND tp.activo = true

        UNION ALL

        SELECT
            'categoria'::text AS nivell,
            cp.id_categoria_proyecto AS unitat_id,
            cp.nombre AS unitat_nom,
            cp.id_categoria_proyecto AS categoria_id,
            1 AS bloc_ordre,
            cp.orden AS categoria_ordre,
            cp.orden AS unitat_ordre
        FROM app.proyecto_categorias cp
        WHERE cp.activo = true
          AND NOT EXISTS (
              SELECT 1
              FROM app.proyecto_tipos tp
              WHERE tp.categoria_proyecto_id = cp.id_categoria_proyecto
                AND tp.activo = true
          )
        SQL;
}
