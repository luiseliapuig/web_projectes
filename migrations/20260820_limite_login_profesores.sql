BEGIN;

CREATE TABLE IF NOT EXISTS app.profesor_login_intentos (
    email_hash char(64) PRIMARY KEY,
    intentos smallint NOT NULL DEFAULT 0 CHECK (intentos >= 0),
    ventana_inicio timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta timestamptz,
    actualizado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMIT;
