BEGIN;

-- Auditoría previa sobre la BD local (83 proyectos): 55 valores funcionales
-- y 75 memorias, todos idénticos 1:1 entre la columna legacy y la canónica;
-- ningún valor exclusivo ni divergente. Las comprobaciones siguientes hacen
-- que la migración aborte si otro entorno no cumple esa equivalencia.

ALTER TABLE app.proyectos
    ADD COLUMN IF NOT EXISTS funcional_pdf text,
    ADD COLUMN IF NOT EXISTS memoria_pdf text;

COMMENT ON COLUMN app.proyectos.funcional_pdf IS
    'Ruta o referencia al PDF definitivo del documento funcional.';
COMMENT ON COLUMN app.proyectos.memoria_pdf IS
    'Ruta o referencia al PDF definitivo de la memoria.';

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM app.proyectos
        WHERE NULLIF(BTRIM(ruta_funcional), '') IS NOT NULL
          AND NULLIF(BTRIM(funcional_pdf), '') IS NOT NULL
          AND ruta_funcional <> funcional_pdf
    ) THEN
        RAISE EXCEPTION 'Hay valores incompatibles entre ruta_funcional y funcional_pdf';
    END IF;

    IF EXISTS (
        SELECT 1 FROM app.proyectos
        WHERE NULLIF(BTRIM(ruta_memoria), '') IS NOT NULL
          AND NULLIF(BTRIM(memoria_pdf), '') IS NOT NULL
          AND ruta_memoria <> memoria_pdf
    ) THEN
        RAISE EXCEPTION 'Hay valores incompatibles entre ruta_memoria y memoria_pdf';
    END IF;
END
$$;

UPDATE app.proyectos
SET funcional_pdf = ruta_funcional
WHERE NULLIF(BTRIM(funcional_pdf), '') IS NULL
  AND NULLIF(BTRIM(ruta_funcional), '') IS NOT NULL;

UPDATE app.proyectos
SET memoria_pdf = ruta_memoria
WHERE NULLIF(BTRIM(memoria_pdf), '') IS NULL
  AND NULLIF(BTRIM(ruta_memoria), '') IS NOT NULL;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM app.proyectos
        WHERE NULLIF(BTRIM(ruta_funcional), '') IS NOT NULL
          AND funcional_pdf IS DISTINCT FROM ruta_funcional
    ) OR EXISTS (
        SELECT 1 FROM app.proyectos
        WHERE NULLIF(BTRIM(ruta_memoria), '') IS NOT NULL
          AND memoria_pdf IS DISTINCT FROM ruta_memoria
    ) THEN
        RAISE EXCEPTION 'La migración documental no ha conservado todos los valores legacy';
    END IF;
END
$$;

ALTER TABLE app.proyectos
    DROP COLUMN ruta_funcional,
    DROP COLUMN ruta_memoria;

COMMIT;
