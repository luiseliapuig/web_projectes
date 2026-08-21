# Web Proyectos — instrucciones para Codex

## Objetivo

Web Proyectos es una herramienta interna para gestionar proyectos académicos, entregas, tutorías, tribunales, evaluaciones, defensas y premios.

Es un MVP preparado para evolucionar, no una aplicación enterprise. Prioriza: seguridad e integridad de datos, funcionamiento correcto, claridad, usabilidad y rapidez de implementación.

## Antes de editar

- Inspecciona el flujo y los archivos relacionados antes de cambiar código.
- Reutiliza patrones existentes cuando sean coherentes con estas instrucciones.
- No inventes tablas, columnas, rutas, roles, helpers ni reglas de negocio.
- Mantén el cambio limitado a la petición; no refactorices zonas ajenas.
- Si una petición contradice la arquitectura, el modelo o la seguridad, explica el conflicto y aplica o propone el cambio mínimo seguro.
- No interpretes “MVP” como permiso para omitir autorización, validación, consultas preparadas o seguridad de archivos.

## Arquitectura obligatoria

- PHP plano, PostgreSQL y Bootstrap 5.
- Sin frameworks, ORM ni nuevas dependencias salvo petición expresa.
- `index.php` es el único punto de entrada y renderiza el layout.
- Las páginas viven bajo `/inc/paginas/`, separadas por áreas de acceso:
  - `/inc/paginas/public/`
  - `/inc/paginas/alumnos/`
  - `/inc/paginas/profesores/`
  - `/inc/paginas/admin/`
- Tutor y tribunal no son tipos de sesión independientes: son funciones contextuales de un profesor. Sus pantallas viven en `/profesores/tutor/` y `/profesores/tribunal/`.
- El router aplica el permiso general del área antes de incluir la página.
- La carpeta no sustituye la autorización sobre el recurso: alumno, tutor y tribunal solo pueden operar sobre proyectos que les correspondan.
- El router usa una lista explícita de rutas permitidas. Nunca construye libremente un `include` desde `$_GET['main']`.
- Para CRUD nuevos conserva el patrón `concepto.php`, `concepto_form.php`, `concepto_accion.php` dentro de su área.
- Conserva rutas públicas y nombres heredados (`projecte`, `alumne`, etc.) cuando ya formen parte del sistema. No los renombres solo para uniformar.
- En acciones no uses `header('Location: ...')`; reutiliza `redirectTo` o el patrón JavaScript + `noscript` existente y termina con `exit`.

Lee [docs/codex/arquitectura.md](docs/codex/arquitectura.md) antes de modificar el router, páginas, rutas o CRUD.

## Código y datos

- Código directo, local y legible. Evita capas, clases y helpers genéricos sin una necesidad real.
- Usa el PDO existente en `$pdo`.
- Parametriza toda entrada variable mediante `prepare()` y `execute()`.
- No concatenes datos del usuario en SQL.
- Respeta el esquema PostgreSQL `app`, claves foráneas, unicidad y rangos.
- Escapa la salida HTML según su contexto, normalmente con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Valida en servidor los datos que afecten a persistencia o permisos.
- Usa transacciones cuando varias escrituras deban completarse juntas.

Lee [docs/codex/datos.md](docs/codex/datos.md) antes de modificar SQL, relaciones, evaluaciones o notas.

## Seguridad

- `/inc/seguridad.php` es la autoridad central y se carga desde `index.php` antes de renderizar.
- Estados de `$_SESSION['auth_tipo']`: sin valor, `professor` y `alumne`.
- La entrada a un área comprueba el tipo de sesión o rol global.
- Cada lectura o escritura sensible comprueba además la relación con el proyecto.
- Las acciones `*_accion.php` repiten la autorización en servidor; no confían en formularios, campos ocultos, URLs ni botones ocultos.
- Profesorado y alumnado establecen su primera contraseña mediante la recuperación por email; no existe acceso de usuario por UUID.
- Usa `password_hash()` y `password_verify()`.
- No expongas UUID, datos de sesión, SQL, rutas internas ni detalles de autorización.
- Si existe protección CSRF, úsala. Si no existe y la tarea afecta escrituras sensibles, señala la carencia y propone una solución coherente para todo el flujo.

Lee [docs/codex/seguridad.md](docs/codex/seguridad.md) antes de tocar login, sesiones, roles, acciones, rutas públicas o archivos.

## Interfaz

- Usa Bootstrap 5 y los componentes existentes.
- Prioriza claridad y funcionamiento sobre decoración.
- Reutiliza clases y terminología de la sección existente.
- No introduzcas un sistema de diseño o JavaScript complejo para una interacción sencilla.

## Entrega

- Valida el cambio de forma proporcional al riesgo.
- Como mínimo, comprueba la sintaxis de los PHP modificados si el entorno lo permite.
- Prueba casos permitidos y denegados cuando cambien permisos.
- Revisa el diff y elimina depuración y cambios accidentales.
- No afirmes que una comprobación se ejecutó si no se ejecutó.
- Resume qué cambió, qué se comprobó y qué riesgos o decisiones siguen pendientes.

Para cambios medianos o sensibles utiliza [docs/codex/checklist-entrega.md](docs/codex/checklist-entrega.md).
