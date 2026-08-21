BEGIN;

ALTER TABLE app.rel_profesores_tribunal
    DROP CONSTRAINT IF EXISTS rel_profesores_tribunal_id_proyecto_fkey;

ALTER TABLE app.rel_profesores_tribunal
    ADD CONSTRAINT rel_profesores_tribunal_id_proyecto_fkey
    FOREIGN KEY (id_proyecto)
    REFERENCES app.proyectos(id_proyecto)
    ON DELETE CASCADE;

COMMIT;
