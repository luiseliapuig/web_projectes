BEGIN;

CREATE TABLE IF NOT EXISTS app.email_outbox (
    id_email bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    destinatario varchar(320) NOT NULL,
    nombre_destinatario varchar(200),
    asunto varchar(255) NOT NULL,
    cuerpo_html text NOT NULL,
    cuerpo_texto text NOT NULL,
    tipo varchar(100) NOT NULL DEFAULT 'manual',
    proyecto_id integer REFERENCES app.proyectos(id_proyecto) ON DELETE SET NULL,
    creado_por integer REFERENCES app.profesores(id_profesor) ON DELETE SET NULL,
    clave_idempotencia varchar(255) UNIQUE,
    estado varchar(20) NOT NULL DEFAULT 'pendiente'
        CHECK (estado IN ('pendiente', 'enviando', 'enviado', 'error')),
    intentos smallint NOT NULL DEFAULT 0 CHECK (intentos >= 0),
    max_intentos smallint NOT NULL DEFAULT 5 CHECK (max_intentos BETWEEN 1 AND 20),
    disponible_desde timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_en timestamptz,
    enviado_en timestamptz,
    error_ultimo varchar(1000),
    creado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS email_outbox_pendientes_idx
    ON app.email_outbox (disponible_desde, id_email)
    WHERE estado = 'pendiente';

CREATE INDEX IF NOT EXISTS email_outbox_estado_creado_idx
    ON app.email_outbox (estado, creado_en DESC);

COMMIT;
