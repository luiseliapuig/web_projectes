BEGIN;

ALTER TABLE app.seguimiento_alumnos
    DROP CONSTRAINT chk_valoracion_tutor;

ALTER TABLE app.seguimiento_alumnos
    ADD CONSTRAINT chk_valoracion_tutor
    CHECK (valoracion_tutor IS NULL OR valoracion_tutor BETWEEN 0 AND 3);

COMMIT;
