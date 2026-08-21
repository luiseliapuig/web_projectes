BEGIN;

ALTER TABLE app.profesor_password_reset
    ADD COLUMN IF NOT EXISTS tipo varchar(20) NOT NULL DEFAULT 'recuperacion';

ALTER TABLE app.profesor_password_reset
    DROP CONSTRAINT IF EXISTS profesor_password_reset_tipo_check;

ALTER TABLE app.profesor_password_reset
    ADD CONSTRAINT profesor_password_reset_tipo_check
    CHECK (tipo IN ('recuperacion', 'invitacion'));

COMMIT;
