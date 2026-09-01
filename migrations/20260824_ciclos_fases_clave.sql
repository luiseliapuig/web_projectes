BEGIN;

-- Puente estable entre BD y las arquitecturas de fases hardcodeadas en PHP.
-- No es una ruta, un nombre de archivo ni un ID: es una clave técnica corta
-- que el registro de arquitecturas (código) resuelve a un directorio seguro.
-- NULL significa que el ciclo todavía no tiene una arquitectura de fases
-- definida. Sin FK a propósito: las arquitecturas disponibles viven en
-- código, no en una tabla; la validez de la clave la comprueba la aplicación.
ALTER TABLE app.ciclos
    ADD COLUMN IF NOT EXISTS fases_clave varchar(60);

COMMENT ON COLUMN app.ciclos.fases_clave IS
    'Clave técnica estable que identifica la arquitectura de fases (registro hardcodeado en PHP) que usa este ciclo. NULL = sin arquitectura de fases definida todavía. No es una ruta, archivo ni ID; solo cambia si el Superadmin selecciona explícitamente otra arquitectura desde el CRUD de ciclos.';

-- Los 5 ciclos actuales pertenecen a la familia Informàtica y hoy usan,
-- indistintamente y sin ninguna rama por ciclo, la misma implementación de
-- fases (fase-1.php...fase-7.php). Se les asigna la arquitectura actual.
UPDATE app.ciclos
SET fases_clave = 'informatica'
WHERE abr IN ('SMX', 'DAM', 'DAW', 'ASIX', 'DEV')
  AND fases_clave IS NULL;

COMMIT;
