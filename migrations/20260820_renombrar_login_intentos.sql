BEGIN;

ALTER TABLE IF EXISTS app.profesor_login_intentos RENAME TO login_intentos;

COMMIT;
