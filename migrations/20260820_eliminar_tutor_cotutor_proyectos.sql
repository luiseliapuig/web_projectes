BEGIN;

-- rel_proyectos_profesores es la única fuente de asignaciones docentes.
-- Antes de retirar las columnas heredadas, se recuperan únicamente relaciones
-- que todavía no existan. Las asignaciones ya presentes en la relación tienen
-- prioridad y no se sobrescriben con posibles datos antiguos divergentes.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'app'
          AND table_name = 'proyectos'
          AND column_name = 'tutor_id'
    ) THEN
        EXECUTE $sql$
            INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
            SELECT p.id_proyecto, p.tutor_id, 'tutor'
            FROM app.proyectos p
            WHERE p.tutor_id IS NOT NULL
              AND NOT EXISTS (
                    SELECT 1
                    FROM app.rel_proyectos_profesores rpp
                    WHERE rpp.proyecto_id = p.id_proyecto
                      AND rpp.rol = 'tutor'
              )
            ON CONFLICT (proyecto_id, profesor_id)
            DO UPDATE SET rol = 'tutor'
        $sql$;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'app'
          AND table_name = 'proyectos'
          AND column_name = 'cotutor_id'
    ) THEN
        EXECUTE $sql$
            INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
            SELECT p.id_proyecto, p.cotutor_id, 'cotutor'
            FROM app.proyectos p
            WHERE p.cotutor_id IS NOT NULL
              AND NOT EXISTS (
                    SELECT 1
                    FROM app.rel_proyectos_profesores rpp
                    WHERE rpp.proyecto_id = p.id_proyecto
                      AND rpp.profesor_id = p.cotutor_id
              )
            ON CONFLICT (proyecto_id, profesor_id) DO NOTHING
        $sql$;
    END IF;
END
$$;

ALTER TABLE app.proyectos
    DROP COLUMN IF EXISTS tutor_id,
    DROP COLUMN IF EXISTS cotutor_id;

COMMIT;
