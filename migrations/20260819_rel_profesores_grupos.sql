BEGIN;

-- Asignación anual de profesores a los grupos donde imparten clase.
CREATE TABLE IF NOT EXISTS app.rel_profesores_grupos (
    profesor_id integer NOT NULL,
    grupo_id integer NOT NULL,
    curso_academico character varying(7) NOT NULL,
    CONSTRAINT rel_profesores_grupos_pkey
        PRIMARY KEY (profesor_id, grupo_id, curso_academico),
    CONSTRAINT rel_profesores_grupos_profesor_fk
        FOREIGN KEY (profesor_id)
        REFERENCES app.profesores(id_profesor)
        ON DELETE RESTRICT,
    CONSTRAINT rel_profesores_grupos_grupo_fk
        FOREIGN KEY (grupo_id)
        REFERENCES app.grupos(id_grupo)
        ON DELETE RESTRICT,
    CONSTRAINT rel_profesores_grupos_curso_check
        CHECK (curso_academico ~ '^[0-9]{4}-[0-9]{2}$')
);

-- Facilita consultas de profesores por grupo y promoción.
CREATE INDEX IF NOT EXISTS rel_profesores_grupos_curso_grupo_idx
    ON app.rel_profesores_grupos (curso_academico, grupo_id);

COMMIT;
