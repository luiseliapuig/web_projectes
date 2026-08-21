BEGIN;

CREATE TABLE IF NOT EXISTS app.profesor_password_reset (
    id_reset bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    profesor_id integer NOT NULL REFERENCES app.profesores(id_profesor) ON DELETE CASCADE,
    token_hash char(64) NOT NULL UNIQUE,
    solicitado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en timestamptz NOT NULL,
    usado_en timestamptz,
    CHECK (expira_en > solicitado_en)
);

CREATE INDEX IF NOT EXISTS profesor_password_reset_activo_idx
    ON app.profesor_password_reset (profesor_id, expira_en DESC)
    WHERE usado_en IS NULL;

ALTER TABLE app.profesores
    DROP COLUMN IF EXISTS uuid_acceso;

COMMIT;
