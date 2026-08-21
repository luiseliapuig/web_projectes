BEGIN;

ALTER TABLE app.alumno_password_reset
    ADD COLUMN IF NOT EXISTS tipo varchar(20) NOT NULL DEFAULT 'recuperacion';

ALTER TABLE app.alumno_password_reset
    DROP CONSTRAINT IF EXISTS alumno_password_reset_tipo_check;

ALTER TABLE app.alumno_password_reset
    ADD CONSTRAINT alumno_password_reset_tipo_check
    CHECK (tipo IN ('recuperacion', 'invitacion'));

COMMIT;
