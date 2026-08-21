BEGIN;

-- La pertenencia al proyecto sigue viviendo exclusivamente en esta relación.
-- Esta marca permite que cada miembro confirme individualmente la agrupación,
-- aunque el proyecto compartido se haya creado al confirmar el primer alumno.
ALTER TABLE app.rel_proyectos_alumnos
    ADD COLUMN IF NOT EXISTS grupo_trabajo_confirmado_en timestamp without time zone;

COMMENT ON COLUMN app.rel_proyectos_alumnos.grupo_trabajo_confirmado_en IS
    'Momento en que este alumno confirmó individualmente la composición de su grupo de trabajo.';

COMMIT;
