BEGIN;

UPDATE app.profesores SET email = lower(btrim(email));

ALTER TABLE app.profesores ALTER COLUMN email SET NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS profesores_email_lower_uk
    ON app.profesores (lower(email));

COMMIT;
