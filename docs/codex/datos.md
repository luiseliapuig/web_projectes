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
- La consulta docente del recorrido se limita a proyectos de grupos y cursos asignados mediante `rel_profesores_grupos`; las intervenciones formales exigen además la relación específica y el rol correspondiente en `rel_proyectos_profesores`.
- El grupo del proyecto se identifica exclusivamente mediante `proyectos.grupo_id`.
- El ciclo y la familia de un proyecto se derivan de `proyectos.grupo_id -> grupos.id_ciclo -> ciclos.familia_ciclo_id`; no se duplican en `proyectos`.
- `evaluacion_tribunal` impone `UNIQUE (proyecto_id, profesor_id)`.
- Los archivos viven en disco y la base de datos conserva sus rutas.
- Los documentos de trabajo y sus evidencias definitivas usan pares canónicos distintos: `propuesta_url`/`propuesta_pdf`, `funcional_url`/`funcional_pdf`, `entorno_desarrollo_url`/`entorno_desarrollo_pdf` y `memoria_url`/`memoria_pdf`. El campo `*_url` conserva el documento vivo y `*_pdf` la evidencia definitiva; `entorno_desarrollo_validado_en` registra la validación previa a su PDF.
- La planificación temporal y el tablero de gestión de la Fase 4 tienen como fuentes únicas V2 `proyectos.planificacion_url` y `proyectos.gestion_url`. Los adjuntos históricos `proyecto_adjuntos.tipo='planificacio'/'gestio'` son legacy y no se usan como fallback ni reciben nuevas escrituras desde Fase 4.
- Los repositorios Git de Fase 5 usan `proyectos.git_url`/`git_etiqueta` para el principal y filas `proyecto_adjuntos.tipo='git'` para los adicionales (`ruta` URL, `nom` etiqueta breve). `url_github` es legacy y no recibe escrituras V2.
- La URL pública de `Entrega del projecte` de Fase 5 usa el campo existente `proyectos.url_proyecto`; no se crea un duplicado V2. Guardar el input vacío persiste `NULL`.
- `Autoavaluació final` de Fase 5 usa los campos existentes `proyectos.autoev1..4`; se guardan conjuntamente, admiten `NULL` y la tarea solo se completa cuando los cuatro están informados.
- La revisión formal del documento de preparación del entorno usa `app.revisiones_solicitudes.tipo='entorn_desenvolupament'`; cerrar la solicitud solo informa `resuelto_en` y no altera URL, validación ni PDF.
- `ruta_funcional` y `ruta_memoria` son columnas legacy redundantes retiradas por `20260826_consolidar_pdfs_documentales.sql`; todos los consumidores, incluida la ficha V1, usan `funcional_pdf` y `memoria_pdf`.
- `ruta_imagen` y `ruta_ficha_entrega` siguen vigentes porque no existe un sustituto canónico equivalente. `proyecto_adjuntos.ruta` conserva la ruta propia de cada adjunto y tampoco equivale a esos documentos.
- `presentacion_pdf` conserva el PDF definitivo usado en la defensa. Es un concepto distinto, no un sustituto de `ruta_ficha_entrega`, y su escritura también pasa por `pdfGuardarDefinitiu()`.
- Las relaciones usan claves internas, no nombres o emails.
- Las notificaciones ordinarias se encolan y las procesa el worker. Las recuperaciones de contraseña e invitaciones de acceso se envían directamente porque forman parte de un flujo transaccional inmediato.

## Valoraciones

### Autoseguiment setmanal

La generació setmanal és idempotent: la restricció única de
`app.seguimiento_alumnos` sobre `(proyecto_id, alumno_id, semana)` i
`INSERT ... ON CONFLICT DO NOTHING` impedeixen duplicats i preserven els
seguiments existents. El cron i la reconciliació manual de superadministració
criden la mateixa operació canònica; el diagnòstic administratiu reutilitza la
mateixa definició de període, setmana i candidats.

El procés es pot executar qualsevol dia. Totes les execucions d’una mateixa
setmana natural de dilluns a diumenge apunten al dilluns–diumenge de la setmana
natural immediatament posterior. Això permet diversos cron i reconciliacions
manuals durant la setmana sense canviar el període objectiu.

`app.seguimiento_ejecuciones` és només un log d’observabilitat, mai la font de
veritat. L’estat funcional es calcula en viu comparant els candidats canònics
amb `app.seguimiento_alumnos`. Per això la integritat (esperats/existents) i el
nombre d’execucions de l’automatisme són senyals independents.

En cada execució, `candidatos` és el nombre de parelles alumne/projecte
elegibles; `creados`, les files inserides; `ya_existentes`, els conflictes
idempotents; i `errores`, els candidats que no s’han pogut processar.
`numero_ejecucion` és el comptador consecutiu que comença en 1 per cada parella
`(fecha_inicio, fecha_fin)`. El detall operatiu i de concurrència viu a
[`autoseguiment-generacio.md`](autoseguiment-generacio.md).

`app.seguimiento_alumnos.valoracion_tutor` és una valoració ordinal del tutor
o tutora amb quatre nivells:

- `0` — **Sense avanç**: absència efectiva d'avanç durant la setmana (vermell).
- `1` — **Poc avanç**: hi ha hagut treball, però l'avanç és insuficient (groc).
- `2` — **Avanç adequat**: comportament normal i progrés esperat (verd).
- `3` — **Avanç destacat**: avanç especialment bo (verd més intens).

Els colors són només la representació visual d'aquesta valoració docent. La
declaració de l'alumnat sobre els objectius (`Sí / Parcialment / No`) és una
dada informativa independent i no utilitza aquesta semàntica cromàtica. No hi
ha encara cap fórmula definida per agregar longitudinalment aquestes dades.

`app.seguimiento_alumnos.feedback_email_encolado_en` registra el moment en què
el feedback d'aquell seguiment es va generar correctament a `app.email_outbox`.
No acredita enviament, lliurament ni lectura. La columna auxiliar
`feedback_email_habilitado` delimita la cohort: les files existents en activar
la funcionalitat queden excloses i els seguiments creats posteriorment hi entren
per defecte, evitant notificacions històriques massives sense falsejar-les com a
enviades.

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
