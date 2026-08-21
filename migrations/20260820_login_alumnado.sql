BEGIN;

UPDATE app.alumnos SET email = lower(btrim(email));

ALTER TABLE app.alumnos
    ALTER COLUMN email SET NOT NULL,
    ADD COLUMN IF NOT EXISTS password_hash varchar(255);

CREATE UNIQUE INDEX IF NOT EXISTS alumnos_email_lower_uk
    ON app.alumnos (lower(email));

CREATE TABLE IF NOT EXISTS app.alumno_password_reset (
    id_reset bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    alumno_id integer NOT NULL REFERENCES app.alumnos(id_alumno) ON DELETE CASCADE,
    token_hash char(64) NOT NULL UNIQUE,
    solicitado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en timestamptz NOT NULL,
    usado_en timestamptz,
    CHECK (expira_en > solicitado_en)
);

CREATE INDEX IF NOT EXISTS alumno_password_reset_activo_idx
    ON app.alumno_password_reset (alumno_id, expira_en DESC)
    WHERE usado_en IS NULL;

COMMIT;
