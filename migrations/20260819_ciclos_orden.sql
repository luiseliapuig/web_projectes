BEGIN;

-- Orden funcional de los ciclos para listados y selectores de toda la aplicación.
ALTER TABLE app.ciclos
    ADD COLUMN IF NOT EXISTS orden smallint;

UPDATE app.ciclos
SET orden = CASE abr
    WHEN 'SMX' THEN 1
    WHEN 'DAM' THEN 2
    WHEN 'DAW' THEN 3
    WHEN 'ASIX' THEN 4
    WHEN 'DEV' THEN 5
    ELSE 999
END
WHERE orden IS NULL;

ALTER TABLE app.ciclos
    ALTER COLUMN orden SET DEFAULT 999,
    ALTER COLUMN orden SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'ciclos_orden_check'
          AND conrelid = 'app.ciclos'::regclass
    ) THEN
        ALTER TABLE app.ciclos
            ADD CONSTRAINT ciclos_orden_check CHECK (orden > 0);
    END IF;
END
$$;

COMMIT;
