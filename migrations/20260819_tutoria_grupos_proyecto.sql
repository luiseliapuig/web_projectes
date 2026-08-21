BEGIN;

-- El grupo docente queda relacionado por clave interna, manteniendo las
-- columnas heredadas ciclo/grupo mientras se migran las pantallas antiguas.
ALTER TABLE app.proyectos
    ADD COLUMN IF NOT EXISTS grupo_id integer;

UPDATE app.proyectos p
SET grupo_id = g.id_grupo
FROM app.grupos g
INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
WHERE p.grupo_id IS NULL
  AND c.abr = p.ciclo
  AND g.grupo IS NOT DISTINCT FROM p.grupo;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'proyectos_grupo_fk'
          AND conrelid = 'app.proyectos'::regclass
    ) THEN
        ALTER TABLE app.proyectos
            ADD CONSTRAINT proyectos_grupo_fk
            FOREIGN KEY (grupo_id)
            REFERENCES app.grupos(id_grupo)
            ON DELETE RESTRICT;
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS proyectos_curso_grupo_idx
    ON app.proyectos (curso_academico, grupo_id);

-- La identidad del alumno es estable y su matrícula a un grupo se conserva
-- por curso, evitando que un repetidor altere la información histórica.
CREATE TABLE IF NOT EXISTS app.rel_alumnos_grupos (
    alumno_id integer NOT NULL,
    grupo_id integer NOT NULL,
    curso_academico character varying(7) NOT NULL,
    CONSTRAINT rel_alumnos_grupos_pkey
        PRIMARY KEY (alumno_id, curso_academico),
    CONSTRAINT rel_alumnos_grupos_alumno_fk
        FOREIGN KEY (alumno_id)
        REFERENCES app.alumnos(id_alumno)
        ON DELETE RESTRICT,
    CONSTRAINT rel_alumnos_grupos_grupo_fk
        FOREIGN KEY (grupo_id)
        REFERENCES app.grupos(id_grupo)
        ON DELETE RESTRICT,
    CONSTRAINT rel_alumnos_grupos_curso_check
        CHECK (curso_academico ~ '^[0-9]{4}-[0-9]{2}$')
);

INSERT INTO app.rel_alumnos_grupos (alumno_id, grupo_id, curso_academico)
SELECT a.id_alumno, g.id_grupo, a.curso_academico
FROM app.alumnos a
INNER JOIN app.ciclos c ON c.abr = a.ciclo
INNER JOIN app.grupos g
    ON g.id_ciclo = c.id_ciclo
   AND g.grupo IS NOT DISTINCT FROM a.grupo
WHERE a.curso_academico ~ '^[0-9]{4}-[0-9]{2}$'
ON CONFLICT (alumno_id, curso_academico)
DO UPDATE SET grupo_id = EXCLUDED.grupo_id;

CREATE INDEX IF NOT EXISTS rel_alumnos_grupos_curso_grupo_idx
    ON app.rel_alumnos_grupos (curso_academico, grupo_id);

-- El correo identifica al alumno sin distinguir mayúsculas. Los datos
-- existentes se normalizan antes de imponer la unicidad.
UPDATE app.alumnos
SET email = lower(trim(email))
WHERE email IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS alumnos_email_unico_idx
    ON app.alumnos (lower(email))
    WHERE email IS NOT NULL;

-- La relación conserva de forma inequívoca todo el profesorado vinculado al
-- proyecto y distingue como máximo a un tutor principal.
CREATE TABLE IF NOT EXISTS app.rel_proyectos_profesores (
    proyecto_id integer NOT NULL,
    profesor_id integer NOT NULL,
    rol character varying(10) NOT NULL DEFAULT 'cotutor',
    fecha_asignacion timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT rel_proyectos_profesores_pkey
        PRIMARY KEY (proyecto_id, profesor_id),
    CONSTRAINT rel_proyectos_profesores_proyecto_fk
        FOREIGN KEY (proyecto_id)
        REFERENCES app.proyectos(id_proyecto)
        ON DELETE CASCADE,
    CONSTRAINT rel_proyectos_profesores_profesor_fk
        FOREIGN KEY (profesor_id)
        REFERENCES app.profesores(id_profesor)
        ON DELETE RESTRICT,
    CONSTRAINT rel_proyectos_profesores_rol_check
        CHECK (rol IN ('tutor', 'cotutor'))
);

CREATE UNIQUE INDEX IF NOT EXISTS rel_proyectos_profesores_tutor_unico_idx
    ON app.rel_proyectos_profesores (proyecto_id)
    WHERE rol = 'tutor';

CREATE INDEX IF NOT EXISTS rel_proyectos_profesores_profesor_idx
    ON app.rel_proyectos_profesores (profesor_id, proyecto_id);

-- Primero se recuperan las asignaciones expresas del modelo anterior.
INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
SELECT p.id_proyecto, p.tutor_id, 'tutor'
FROM app.proyectos p
WHERE p.tutor_id IS NOT NULL
ON CONFLICT (proyecto_id, profesor_id)
DO UPDATE SET rol = 'tutor';

INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
SELECT p.id_proyecto, p.cotutor_id, 'cotutor'
FROM app.proyectos p
WHERE p.cotutor_id IS NOT NULL
  AND p.cotutor_id IS DISTINCT FROM p.tutor_id
ON CONFLICT (proyecto_id, profesor_id) DO NOTHING;

-- Para los cursos que ya disponen de asignación anual, todos los profesores
-- del grupo quedan vinculados; las asignaciones anteriores se respetan.
INSERT INTO app.rel_proyectos_profesores (proyecto_id, profesor_id, rol)
SELECT p.id_proyecto, rpg.profesor_id, 'cotutor'
FROM app.proyectos p
INNER JOIN app.rel_profesores_grupos rpg
    ON rpg.grupo_id = p.grupo_id
   AND rpg.curso_academico = p.curso_academico
WHERE p.grupo_id IS NOT NULL
ON CONFLICT (proyecto_id, profesor_id) DO NOTHING;

COMMIT;
