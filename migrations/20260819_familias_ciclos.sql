BEGIN;

-- Catálogo de familias profesionales que agrupan los ciclos formativos.
CREATE TABLE IF NOT EXISTS app.familias_ciclos (
    id_familia_ciclo integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre character varying(120) NOT NULL UNIQUE,
    activo boolean NOT NULL DEFAULT true,
    orden smallint NOT NULL DEFAULT 999,
    CONSTRAINT familias_ciclos_orden_check CHECK (orden > 0)
);

INSERT INTO app.familias_ciclos (nombre, activo, orden)
VALUES
    ('Informàtica', true, 1),
    ('Administració i gestió', true, 2)
ON CONFLICT (nombre) DO NOTHING;

-- Cada ciclo pertenece a una única familia; el borrado queda restringido.
ALTER TABLE app.ciclos
    ADD COLUMN IF NOT EXISTS familia_ciclo_id integer;

UPDATE app.ciclos
SET familia_ciclo_id = (
    SELECT id_familia_ciclo
    FROM app.familias_ciclos
    WHERE nombre = 'Informàtica'
)
WHERE familia_ciclo_id IS NULL;

ALTER TABLE app.ciclos
    ALTER COLUMN familia_ciclo_id SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'ciclos_familia_ciclo_fk'
          AND conrelid = 'app.ciclos'::regclass
    ) THEN
        ALTER TABLE app.ciclos
            ADD CONSTRAINT ciclos_familia_ciclo_fk
            FOREIGN KEY (familia_ciclo_id)
            REFERENCES app.familias_ciclos(id_familia_ciclo)
            ON DELETE RESTRICT;
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS ciclos_familia_ciclo_idx
    ON app.ciclos (familia_ciclo_id);

COMMIT;
