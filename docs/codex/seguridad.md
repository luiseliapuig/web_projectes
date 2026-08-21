# Autenticación y autorización

La autoridad central de autenticación y autorización es `/inc/seguridad.php` y se carga desde `index.php` antes de resolver las páginas.

## Estado real del acceso

- Alumno: accede con email y contraseña; la sesión conserva su identidad y, si existe, su proyecto activo.
- Profesor nuevo: usa la recuperación por email para establecer su primera contraseña.
- Profesor configurado: accede con email y contraseña y obtiene sesión `professor`.

Esta implementación vigente prevalece sobre la idea inicial de acceso exclusivamente mediante enlaces.

## Sesión

Valores de `$_SESSION['auth_tipo']`:

- sin valor: no autenticado;
- `professor`: profesor autenticado;
- `alumne`: alumno autenticado mediante el UUID de su proyecto.

Profesor:

- `professor_id`
- `professor_nom`
- `professor_email`
- `professor_imatge`
- `professor_rol`
- `professor_departament`

Alumno:

- `alumno_id`
- `alumno_nom`
- `alumno_email`
- `projecte_id`
- `projecte_nom`

No cambies estas claves sin revisar todos sus consumidores.

## Seguridad por áreas

- `/public/`: solo las rutas declaradas como públicas, incluido el acceso y la recuperación de contraseña.
- `/alumnos/`: exige `auth_tipo === 'alumne'`.
- `/profesores/`: exige `auth_tipo === 'professor'`.
- `/admin/`: exige profesor autenticado y uno de los roles administrativos autorizados.

El router ejecuta estas comprobaciones antes de incluir la página. La misma política cubre páginas y acciones.

## Seguridad sobre recursos

La autorización general del área no basta:

- Un alumno solo puede consultar o modificar proyectos vinculados mediante `rel_proyectos_alumnos`.
- Un tutor o cotutor solo puede actuar si existe su asignación en `rel_proyectos_profesores`; los campos heredados de `proyectos` no son la autoridad del flujo nuevo.
- El panel docente de alumnado se limita a las matrículas de `rel_alumnos_grupos` cuyos grupos y cursos estén asignados al profesor mediante `rel_profesores_grupos`. Listado, formulario y acción repiten este alcance.
- Un tutor puede desactivar una identidad de su alumnado. Solo puede eliminarla si no pertenece a ningún proyecto ni conserva matrículas de otros cursos; en caso contrario debe desactivarla para preservar el historial.
- El envío colectivo de invitaciones exige un grupo concreto asignado al profesor y solo selecciona alumnado activo de ese grupo que todavía no tenga contraseña.
- Un tribunal solo puede actuar si existe una asignación vigente del profesor al proyecto.
- Una acción vuelve a comprobar la relación en base de datos antes de escribir.
- Nunca confíes como prueba de permiso en un ID, UUID, campo oculto, botón o URL.

Si todavía no existe un modelo de asignación de tribunal, no concedas acceso basándote únicamente en la existencia de una evaluación. Define primero la regla de datos.

## Flujos de acceso

- `/login` busca primero un profesor activo y, si no existe, un alumno activo con el mismo email.
- Si un email excepcionalmente aparece en ambas tablas, prevalece la identidad de profesor y nunca se prueba la contraseña contra alumnado.
- `/recuperar-contrasenya` responde siempre de forma neutra, crea un token aleatorio cuyo hash se guarda en base de datos y envía un enlace de un solo uso.
- `/restablir-contrasenya` exige un token vigente, no utilizado y asociado a una identidad activa; al guardar usa `password_hash()` e invalida todos sus tokens pendientes.
- El alta de profesorado puede enviar una invitación directa con un token distinto al de recuperación. Es de un solo uso, se almacena como hash y caduca a las cinco horas. Reenviar una invitación invalida los enlaces anteriores.
- Al incorporar alumnado a un proyecto se puede enviar la misma clase de invitación. El token se guarda exclusivamente en `alumno_password_reset` y solo se genera para una incorporación nueva que todavía no tenga contraseña.
- Una invitación caducada dirige al profesor o alumno al flujo normal de recuperación de contraseña, que conserva su caducidad más corta.
- `/login` usa email, `password_verify()` y comprueba que la identidad encontrada está activa.
- Tras autenticarse, profesorado y alumnado se dirigen siempre a `/inici`; no se conserva una URL anterior en la sesión. Mientras se construye la v2, esta ruta muestra la portada vigente.
- Cinco intentos fallidos de acceso dentro de quince minutos bloquean temporalmente ese identificador de email.
- `/logout` destruye la sesión y redirige a `/login`.

Regenera el ID de sesión al pasar a una sesión autenticada completa. Conserva las opciones seguras existentes de cookies (`HttpOnly`, `Secure` bajo HTTPS y `SameSite`). Si faltan y la tarea toca autenticación, señala la carencia.

## Escrituras

En cada acción:

1. Comprueba el área y tipo de sesión.
2. Comprueba rol o relación con el proyecto.
3. Valida los datos.
4. Verifica CSRF si el sistema dispone de protección.
5. Ejecuta SQL parametrizado.
6. Redirige sin exponer detalles internos.

Ocultar un botón nunca sustituye estas comprobaciones.

## Archivos

Para imagen, memoria, documento funcional y ficha de entrega:

- usa una lista permitida de tipos y un tamaño máximo definido;
- valida contenido en servidor, no solo extensión o MIME del navegador;
- genera el nombre de almacenamiento en servidor;
- impide traversal y sobrescrituras accidentales;
- guarda fuera de rutas ejecutables o bloquea la ejecución de scripts;
- autoriza la descarga según actor y proyecto;
- no elimines el archivo anterior hasta que el reemplazo y la actualización de datos sean seguros.

Si faltan tipos o límites, solicita o propone una decisión explícita; no aceptes cualquier archivo.

## Errores

- Las denegaciones siguen el patrón de redirección silenciosa del sistema.
- No muestres SQL, excepciones internas, hashes, UUID sensibles, rutas del servidor ni datos de sesión.
- Registra detalles solo mediante el mecanismo existente y sin secretos.
