BEGIN;

ALTER TABLE app.seguimiento_alumnos
    ADD COLUMN curso_academico varchar(7);

UPDATE app.seguimiento_alumnos sa
SET curso_academico = p.curso_academico
FROM app.proyectos p
WHERE p.id_proyecto = sa.proyecto_id
  AND sa.curso_academico IS NULL;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM app.seguimiento_alumnos
        WHERE curso_academico IS NULL
    ) THEN
        RAISE EXCEPTION 'No se puede determinar el curso académico de todos los autoseguimientos existentes.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM app.seguimiento_alumnos
        GROUP BY alumno_id, curso_academico, fecha_inicio, fecha_fin
        HAVING COUNT(*) > 1
    ) THEN
        RAISE EXCEPTION 'Existen autoseguimientos duplicados para la nueva identidad alumno/curso/periodo.';
    END IF;
END
$$;

ALTER TABLE app.seguimiento_alumnos
    ALTER COLUMN curso_academico SET NOT NULL,
    ALTER COLUMN proyecto_id DROP NOT NULL,
    ADD CONSTRAINT seguimiento_alumnos_curso_check
        CHECK (curso_academico ~ '^[0-9]{4}-[0-9]{2}$');

ALTER TABLE app.seguimiento_alumnos
    DROP CONSTRAINT uq_seguimiento_alumno_semana,
    ADD CONSTRAINT uq_seguimiento_alumno_curso_periodo
        UNIQUE (alumno_id, curso_academico, fecha_inicio, fecha_fin),
    DROP CONSTRAINT fk_seguimiento_proyecto,
    ADD CONSTRAINT fk_seguimiento_proyecto
        FOREIGN KEY (proyecto_id)
        REFERENCES app.proyectos(id_proyecto)
        ON DELETE SET NULL;

DROP INDEX app.idx_seguimiento_valoracion_pendiente;

CREATE INDEX idx_seguimiento_alumno_curso_fecha
    ON app.seguimiento_alumnos (alumno_id, curso_academico, fecha_inicio DESC);

CREATE INDEX idx_seguimiento_valoracion_pendiente
    ON app.seguimiento_alumnos (curso_academico, alumno_id, fecha_fin)
    WHERE valoracion_tutor IS NULL;

COMMENT ON TABLE app.seguimiento_alumnos IS
    'Autoseguimiento semanal individual del alumnado durante el curso académico, exista o no proyecto.';
COMMENT ON COLUMN app.seguimiento_alumnos.curso_academico IS
    'Curso académico al que pertenece el seguimiento individual.';
COMMENT ON COLUMN app.seguimiento_alumnos.proyecto_id IS
    'Contexto opcional del proyecto activo al crear la fila; no forma parte de su identidad.';

COMMIT;
