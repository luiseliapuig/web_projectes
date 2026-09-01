<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Registre únic de les arquitectures de fases que existeixen realment en codi.
//
// app.ciclos.fases_clave és un pont estable entre BD i aquest registre: mai
// és una ruta, un nom d'arxiu ni un ID, només una clau tècnica curta. Aquest
// fitxer és l'única font de veritat sobre quines arquitectures existeixen;
// cap altra part de l'aplicació ha de duplicar aquest array ni construir un
// directori a partir del valor cru de BD sense passar per aquí.
//
// De moment només hi ha 'informatica', amb la seva implementació a
// inc/paginas/alumnos/informatica/ (fase-1.php...fase-7.php + fases.php).
// -----------------------------------------------------------------------------

function fasesArquitecturasRegistradas(): array
{
    return [
        'informatica' => [
            'clave' => 'informatica',
            'nombre' => 'Informàtica',
            'directorio' => 'informatica',
        ],
    ];
}

// Comprova si una clau (normalment procedent de BD o d'un formulari) existeix
// realment al registre. NULL sempre és fals ("sense arquitectura").
function existeArquitecturaFases(?string $clave): bool
{
    if ($clave === null || $clave === '') {
        return false;
    }
    return array_key_exists($clave, fasesArquitecturasRegistradas());
}

// Resol una clau a la seva arquitectura registrada: ['clave', 'nombre',
// 'directorio']. Retorna NULL de manera neta tant si la clau és NULL com si
// no existeix al registre (mai warnings, mai un resultat inventat).
function obtenerArquitecturaFases(?string $clave): ?array
{
    if (!existeArquitecturaFases($clave)) {
        return null;
    }
    return fasesArquitecturasRegistradas()[$clave];
}

// Llista completa de les arquitectures registrades, en l'ordre en què
// apareixen al registre. Útil per construir selects (p. ex. el CRUD de
// cicles) sense que cap altre lloc hagi de conèixer el contingut de l'array.
function listarArquitecturasFases(): array
{
    return fasesArquitecturasRegistradas();
}

// Únic camí recomanat per obtenir un directori a partir de fases_clave: ja
// ha passat per la validació del registre. Si la clau és NULL o desconeguda,
// retorna NULL en lloc de construir una ruta arbitrària amb el valor de BD.
function fasesDirectorioSeguro(?string $clave): ?string
{
    return obtenerArquitecturaFases($clave)['directorio'] ?? null;
}

// -----------------------------------------------------------------------------
// Resolució de fases: proyecto/alumne → cicle → fases_clave → arquitectura
// registrada → directori segur → definició informatica/fases.php → fases.
//
// Reutilitzable des de qualsevol zona (alumnat ara, professorat més
// endavant): només depenen d'una clau/array ja resolts, no de sessió ni de
// cap variable específica de l'àrea d'alumnat.
// -----------------------------------------------------------------------------

// Carrega la definició d'una arquitectura de fases de manera segura: el
// directori ja ha passat per fasesDirectorioSeguro() (mai es construeix la
// ruta amb el valor cru de BD/request). Retorna [] de manera neta si la clau
// és NULL, desconeguda, o si el fitxer de definició encara no existeix (una
// arquitectura vàlida amb zero fases també s'ha de poder resoldre net).
function obtenerFasesArquitectura(?string $clave): array
{
    $directorio = fasesDirectorioSeguro($clave);
    if ($directorio === null) {
        return [];
    }
    $archivo = dirname(__DIR__) . '/paginas/alumnos/' . $directorio . '/fases.php';
    if (!is_file($archivo)) {
        return [];
    }
    $fases = require $archivo;
    return is_array($fases) ? $fases : [];
}

// Atall per obtenir les fases directament a partir d'un projecte/alumne ja
// resolt (per exemple $proyectoAlumno de projecte_context.php, o l'equivalent
// que faci servir en el futur la vista del professorat), sense que cada
// consumidor hagi de repetir l'extracció de fases_clave.
function obtenerFasesProyecto(array $proyecto): array
{
    $clave = isset($proyecto['fases_clave']) && is_string($proyecto['fases_clave'])
        ? $proyecto['fases_clave']
        : null;
    return obtenerFasesArquitectura($clave);
}

// Comprova que un projecte pertany realment a l'arquitectura indicada, a
// partir de fases_clave. Es fa servir per protegir l'accés directe a una URL
// de fase que no correspon al cicle real del projecte (mai n'hi ha prou
// amagant l'enllaç a la navegació).
function proyectoPerteneceArquitecturaFases(array $proyecto, string $claveEsperada): bool
{
    $clave = isset($proyecto['fases_clave']) && is_string($proyecto['fases_clave'])
        ? $proyecto['fases_clave']
        : null;
    $arquitectura = obtenerArquitecturaFases($clave);
    return $arquitectura !== null && $arquitectura['clave'] === $claveEsperada;
}

// -----------------------------------------------------------------------------
// Context de professorat per a les vistes contextuals de fase/tasca
// (fase-N-tutor.php, fase-N-tutor_xxx.php). Genèric per a qualsevol fase, no
// específic de 'informatica' ni de cap tasca concreta: només depèn del criteri
// d'accés ampli ja establert (rel_profesores_grupos), igual que
// autoseguiment-tutor.php / memoria-tutor.php.
// -----------------------------------------------------------------------------

// Resol el projecte amb el mateix format que $proyectoAlumno de
// projecte_context.php (id_proyecto, nombre, estado, grupo_id, ciclo, grupo,
// fases_clave), autoritzant sempre contra rel_profesores_grupos: mai es
// confia només en el proyecto_id rebut per GET. Retorna null si no hi ha
// accés legítim.
function fasesResolverContextTutor(PDO $pdo, int $profesorId, string $cursoAcademico, int $proyectoId): ?array
{
    if ($proyectoId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT p.id_proyecto, p.nombre, p.estado, p.grupo_id, c.abr AS ciclo, g.grupo, c.fases_clave
        FROM app.proyectos p
        INNER JOIN app.grupos g ON g.id_grupo = p.grupo_id
        INNER JOIN app.ciclos c ON c.id_ciclo = g.id_ciclo
        INNER JOIN app.rel_profesores_grupos rpg
            ON rpg.grupo_id = p.grupo_id
           AND rpg.curso_academico = p.curso_academico
           AND rpg.profesor_id = :profesor_id
        WHERE p.id_proyecto = :proyecto_id
          AND p.curso_academico = :curso_academico
          AND p.estado = 'activo'
        LIMIT 1
    ");
    $stmt->execute([
        ':profesor_id' => $profesorId,
        ':proyecto_id' => $proyectoId,
        ':curso_academico' => $cursoAcademico,
    ]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    return $fila ?: null;
}

// Noms dels membres actius d'un projecte, per deixar clar en les vistes
// contextuals de professorat quin projecte/alumnat s'està consultant.
function fasesNomsAlumnesProjecte(PDO $pdo, int $proyectoId): array
{
    $stmt = $pdo->prepare("
        SELECT a.nombre, a.apellidos
        FROM app.rel_proyectos_alumnos rpa
        INNER JOIN app.alumnos a ON a.id_alumno = rpa.alumno_id
        WHERE rpa.proyecto_id = :id AND a.activo = true
        ORDER BY a.nombre, a.apellidos
    ");
    $stmt->execute([':id' => $proyectoId]);
    return array_map(
        static fn (array $a): string => trim((string) $a['nombre'] . ' ' . (string) $a['apellidos']),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

// -----------------------------------------------------------------------------
// app.revisiones_solicitudes és una taula genèrica, pensada per a qualsevol
// tasca que necessiti demanar intervenció (no és específica de cap fase ni
// de cap tasca concreta). Aquest helper només respon "hi ha alguna
// sol·licitud oberta d'aquest tipus per a aquest projecte?" — no sap a
// quina fase pertany cada tipus; qui el crida (per exemple, el llenguatge
// visual d'"atenció pendent" de fases_navegacion.php) és qui coneix aquesta
// relació. Reutilitzable per qualsevol tasca futura que faci servir el
// mateix mecanisme de sol·licituds.
// -----------------------------------------------------------------------------
function existeSolicitudAbierta(PDO $pdo, int $idProyecto, string $tipo): bool
{
    if ($idProyecto <= 0 || $tipo === '') {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT 1 FROM app.revisiones_solicitudes
        WHERE proyecto_id = :proyecto_id AND tipo = :tipo AND resuelto_en IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        ':proyecto_id' => $idProyecto,
        ':tipo' => $tipo,
    ]);
    return (bool) $stmt->fetchColumn();
}
