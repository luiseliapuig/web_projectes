BEGIN;

ALTER TABLE app.rel_proyectos_alumnos
    ADD COLUMN IF NOT EXISTS compromiso_trabajo_aceptado boolean NOT NULL DEFAULT false;

COMMENT ON COLUMN app.rel_proyectos_alumnos.compromiso_trabajo_aceptado IS
    'Indica si el alumno ha aceptado individualmente el compromiso de trabajo para este proyecto.';

COMMIT;
