# Modelo de datos vigente

Motor: PostgreSQL. Base: `web_proyectos`. Esquema: `app`.

## Entidades

- `app.proyectos`: núcleo del sistema; UUID público, información, rutas de archivos, curso, grupo académico, estado, defensa y valoración del tutor.
- `app.alumnos`: alumnado y datos organizativos.
- `app.profesores`: profesorado, contraseña, estado y rol.
- `app.rel_proyectos_alumnos`: relación N:M con PK `(proyecto_id, alumno_id)`.
- `app.rel_alumnos_grupos`: matrícula anual del alumno en un grupo, sin alterar su identidad histórica.
- `app.rel_profesores_grupos`: asignación anual del profesorado a los grupos donde imparte clase.
- `app.rel_proyectos_profesores`: profesorado vinculado a cada proyecto y su rol de tutor o cotutor.
- `app.evaluacion_tribunal`: una evaluación por proyecto y profesor.
- `app.aulas`: espacios de defensa con código único.
- `app.email_outbox`: cola e historial de correo saliente, con reintentos y clave opcional de idempotencia.
- `app.login_intentos`: limitación temporal de intentos fallidos compartida por ambos tipos de usuario.

## Invariantes

- `proyectos.uuid` es único y se genera en base de datos; el profesorado no usa UUID de acceso.
- `profesor_password_reset` conserva solo el hash del token, su caducidad y la fecha de uso.
- `alumno_password_reset` aplica las mismas garantías a la recuperación del alumnado.
- El email del profesorado es obligatorio y único sin distinguir mayúsculas de minúsculas.
- El email del alumnado es obligatorio y único sin distinguir mayúsculas de minúsculas.
- Las notas son enteros de 1 a 5.
- Un proyecto tiene como máximo un tutor en `rel_proyectos_profesores`; esta relación es la única fuente para tutores y cotutores.
- Un proyecto puede tener varios alumnos.
- `rel_proyectos_alumnos.grupo_trabajo_confirmado_en` registra la confirmación individual de la agrupación en la Fase 1. En una pareja, la primera confirmación crea y vincula el proyecto para ambos; cada miembro completa su propia relación por separado.
- `rel_proyectos_alumnos.compromiso_trabajo_aceptado` registra la aceptación individual del compromiso de trabajo dentro de ese proyecto y solo puede activarse después de confirmar la agrupación.
- Un proyecto activo debe conservar al menos un alumno; un proyecto inactivo puede quedar temporalmente sin alumnado.
- Al crear o editar un proyecto, el alumnado se selecciona entre las identidades activas matriculadas en su grupo; el proyecto no crea ni modifica fichas de alumnos.
- Un alumno no puede estar vinculado a más de un proyecto activo durante el mismo curso académico.
- Eliminar un proyecto elimina en cascada sus relaciones, incluidas las asignaciones de tribunal, pero conserva las identidades de `alumnos` y sus matrículas históricas.
- El acceso del profesorado a un proyecto se comprueba mediante `rel_proyectos_profesores`.
- El grupo del proyecto se identifica exclusivamente mediante `proyectos.grupo_id`.
- El ciclo y la familia de un proyecto se derivan de `proyectos.grupo_id -> grupos.id_ciclo -> ciclos.familia_ciclo_id`; no se duplican en `proyectos`.
- `evaluacion_tribunal` impone `UNIQUE (proyecto_id, profesor_id)`.
- Los archivos viven en disco y la base de datos conserva sus rutas.
- Las relaciones usan claves internas, no nombres o emails.
- Las notificaciones ordinarias se encolan y las procesa el worker. Las recuperaciones de contraseña e invitaciones de acceso se envían directamente porque forman parte de un flujo transaccional inmediato.

## Valoraciones

La valoración del tutor está actualmente en `proyectos`:

- `nota_tutor_funcional`
- `nota_tutor_memoria`
- `nota_tutor_proyecto`
- `nota_tutor_compromiso`
- `comentario_tutor`
- `fecha_valoracion_tutor`

La valoración del tribunal está en `evaluacion_tribunal`:

- `nota_memoria`
- `nota_proyecto`
- `nota_defensa`
- `comentario`
- `fecha_valoracion`

No mezcles ambas valoraciones ni inventes la fórmula final. Localiza la regla vigente o solicita una decisión de producto.

## Aspectos todavía no definidos

- No aparece una relación explícita de asignación de miembros de tribunal distinta de la evaluación. No asumas que evaluar equivale a estar asignado.
- No aparecen tablas para candidaturas y votación de premios.
- No está definida la fórmula de nota final.

Antes de implementar esas funciones, acuerda el modelo y crea una migración explícita.

## Cambios de esquema

- Usa el mecanismo de migraciones o SQL versionado del repositorio.
- Si no existe, entrega un script SQL explícito sin ejecutarlo en producción.
- Documenta nulabilidad, valores por defecto, índices y compatibilidad con datos existentes.
- Usa transacciones para escrituras dependientes.
- No cambies el esquema como efecto secundario de una tarea no relacionada.
