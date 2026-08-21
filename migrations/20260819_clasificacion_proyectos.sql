BEGIN;

-- Primer eje de clasificación: finalidad general del proyecto.
CREATE TABLE IF NOT EXISTS app.categorias_proyectos (
    id_categoria_proyecto integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    familia_ciclo_id integer NOT NULL,
    nombre character varying(120) NOT NULL,
    activo boolean NOT NULL DEFAULT true,
    orden smallint NOT NULL DEFAULT 999,
    CONSTRAINT categorias_proyectos_familia_fk
        FOREIGN KEY (familia_ciclo_id)
        REFERENCES app.familias_ciclos(id_familia_ciclo)
        ON DELETE RESTRICT,
    CONSTRAINT categorias_proyectos_orden_check CHECK (orden > 0),
    CONSTRAINT categorias_proyectos_familia_nombre_key UNIQUE (familia_ciclo_id, nombre)
);

-- Segundo eje independiente: naturaleza técnica del proyecto.
CREATE TABLE IF NOT EXISTS app.tipos_proyectos (
    id_tipo_proyecto integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    familia_ciclo_id integer NOT NULL,
    nombre character varying(120) NOT NULL,
    activo boolean NOT NULL DEFAULT true,
    orden smallint NOT NULL DEFAULT 999,
    CONSTRAINT tipos_proyectos_familia_fk
        FOREIGN KEY (familia_ciclo_id)
        REFERENCES app.familias_ciclos(id_familia_ciclo)
        ON DELETE RESTRICT,
    CONSTRAINT tipos_proyectos_orden_check CHECK (orden > 0),
    CONSTRAINT tipos_proyectos_familia_nombre_key UNIQUE (familia_ciclo_id, nombre)
);

-- Clasificaciones iniciales de la familia Informàtica.
INSERT INTO app.categorias_proyectos (familia_ciclo_id, nombre, activo, orden)
SELECT f.id_familia_ciclo, datos.nombre, true, datos.orden
FROM app.familias_ciclos f
CROSS JOIN (VALUES
    ('Projecte d’investigació', 1),
    ('Projecte de desenvolupament', 2)
) AS datos(nombre, orden)
WHERE f.nombre = 'Informàtica'
ON CONFLICT (familia_ciclo_id, nombre) DO NOTHING;

INSERT INTO app.tipos_proyectos (familia_ciclo_id, nombre, activo, orden)
SELECT f.id_familia_ciclo, datos.nombre, true, datos.orden
FROM app.familias_ciclos f
CROSS JOIN (VALUES
    ('Aplicació web', 1),
    ('Aplicació mòbil', 2),
    ('Aplicació d’escriptori', 3),
    ('Videojoc', 4),
    ('Sistemes', 5),
    ('Maquinari', 6),
    ('Altres', 7)
) AS datos(nombre, orden)
WHERE f.nombre = 'Informàtica'
ON CONFLICT (familia_ciclo_id, nombre) DO NOTHING;

CREATE INDEX IF NOT EXISTS categorias_proyectos_familia_idx
    ON app.categorias_proyectos (familia_ciclo_id);
CREATE INDEX IF NOT EXISTS tipos_proyectos_familia_idx
    ON app.tipos_proyectos (familia_ciclo_id);

COMMIT;
