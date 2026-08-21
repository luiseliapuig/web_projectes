BEGIN;

-- Un proyecto pertenece a un grupo. El ciclo y la familia se derivan de:
-- proyectos.grupo_id -> grupos.id_ciclo -> ciclos.familia_ciclo_id.
-- Los textos heredados solo se usan para completar relaciones aún vacías.
CREATE TEMP TABLE proyectos_grupo_backfill ON COMMIT DROP AS
SELECT
    p.id_proyecto,
    COUNT(g.id_grupo) AS coincidencias,
    MIN(g.id_grupo) AS grupo_id
FROM app.proyectos p
LEFT JOIN app.ciclos c
    ON UPPER(TRIM(c.abr)) = UPPER(TRIM(p.ciclo))
LEFT JOIN app.grupos g
    ON g.id_ciclo = c.id_ciclo
   AND UPPER(TRIM(COALESCE(g.grupo, ''))) = UPPER(TRIM(COALESCE(p.grupo, '')))
WHERE p.grupo_id IS NULL
GROUP BY p.id_proyecto;

DO $$
DECLARE
    proyectos_invalidos integer;
BEGIN
    SELECT COUNT(*)
    INTO proyectos_invalidos
    FROM proyectos_grupo_backfill
    WHERE coincidencias <> 1;

    IF proyectos_invalidos > 0 THEN
        RAISE EXCEPTION
            'No se puede normalizar proyectos: % proyecto(s) sin una coincidencia única de ciclo y grupo',
            proyectos_invalidos;
    END IF;
END
$$;

UPDATE app.proyectos p
SET grupo_id = b.grupo_id
FROM proyectos_grupo_backfill b
WHERE p.id_proyecto = b.id_proyecto
  AND b.coincidencias = 1;

ALTER TABLE app.proyectos
    ALTER COLUMN grupo_id SET NOT NULL,
    DROP COLUMN IF EXISTS ciclo,
    DROP COLUMN IF EXISTS grupo;

COMMENT ON COLUMN app.proyectos.grupo_id IS
    'Grupo académico del proyecto; ciclo y familia se obtienen mediante sus claves foráneas.';

COMMIT;
