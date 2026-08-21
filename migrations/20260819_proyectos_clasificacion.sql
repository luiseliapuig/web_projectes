BEGIN;

-- Clasificación del proyecto mediante dos ejes independientes por familia.
ALTER TABLE app.proyectos
    ADD COLUMN IF NOT EXISTS categoria_proyecto_id integer,
    ADD COLUMN IF NOT EXISTS tipo_proyecto_id integer;

-- Los proyectos existentes pertenecen inicialmente a desarrollo de Informàtica.
UPDATE app.proyectos
SET categoria_proyecto_id = (
    SELECT cp.id_categoria_proyecto
    FROM app.categorias_proyectos cp
    INNER JOIN app.familias_ciclos f
        ON f.id_familia_ciclo = cp.familia_ciclo_id
    WHERE f.nombre = 'Informàtica'
      AND cp.nombre = 'Projecte de desenvolupament'
    LIMIT 1
)
WHERE categoria_proyecto_id IS NULL;

-- Las restricciones preservan las clasificaciones usadas como histórico.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'proyectos_categoria_proyecto_fk'
          AND conrelid = 'app.proyectos'::regclass
    ) THEN
        ALTER TABLE app.proyectos
            ADD CONSTRAINT proyectos_categoria_proyecto_fk
            FOREIGN KEY (categoria_proyecto_id)
            REFERENCES app.categorias_proyectos(id_categoria_proyecto)
            ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'proyectos_tipo_proyecto_fk'
          AND conrelid = 'app.proyectos'::regclass
    ) THEN
        ALTER TABLE app.proyectos
            ADD CONSTRAINT proyectos_tipo_proyecto_fk
            FOREIGN KEY (tipo_proyecto_id)
            REFERENCES app.tipos_proyectos(id_tipo_proyecto)
            ON DELETE RESTRICT;
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS proyectos_categoria_proyecto_idx
    ON app.proyectos (categoria_proyecto_id);
CREATE INDEX IF NOT EXISTS proyectos_tipo_proyecto_idx
    ON app.proyectos (tipo_proyecto_id);

COMMIT;
