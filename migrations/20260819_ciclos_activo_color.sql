BEGIN;

-- Estado y color controlado para la administración de ciclos.
ALTER TABLE app.ciclos
    ADD COLUMN IF NOT EXISTS activo boolean NOT NULL DEFAULT true,
    ADD COLUMN IF NOT EXISTS color character varying(20) NOT NULL DEFAULT 'secondary';

-- Colores que ya utilizaba el listado Grups/cicle antes de la migración.
UPDATE app.ciclos
SET color = CASE abr
    WHEN 'SMX' THEN 'info'
    WHEN 'ASIX' THEN 'warning'
    WHEN 'DAM' THEN 'primary'
    WHEN 'DAW' THEN 'success'
    WHEN 'DEV' THEN 'danger'
    ELSE 'secondary'
END;

-- La restricción evita almacenar clases CSS o valores no previstos.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'ciclos_color_check'
          AND conrelid = 'app.ciclos'::regclass
    ) THEN
        ALTER TABLE app.ciclos
            ADD CONSTRAINT ciclos_color_check
            CHECK (color IN ('primary', 'secondary', 'success', 'danger', 'warning', 'info'));
    END IF;
END
$$;

COMMIT;
