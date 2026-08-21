BEGIN;

-- Las asignaciones anuales son históricas: borrar su profesor o grupo no debe
-- eliminarlas en cascada. La desactivación sustituye al borrado en esos casos.
ALTER TABLE app.rel_profesores_grupos
    DROP CONSTRAINT IF EXISTS rel_profesores_grupos_profesor_fk,
    DROP CONSTRAINT IF EXISTS rel_profesores_grupos_grupo_fk;

ALTER TABLE app.rel_profesores_grupos
    ADD CONSTRAINT rel_profesores_grupos_profesor_fk
        FOREIGN KEY (profesor_id)
        REFERENCES app.profesores(id_profesor)
        ON DELETE RESTRICT,
    ADD CONSTRAINT rel_profesores_grupos_grupo_fk
        FOREIGN KEY (grupo_id)
        REFERENCES app.grupos(id_grupo)
        ON DELETE RESTRICT;

COMMIT;
