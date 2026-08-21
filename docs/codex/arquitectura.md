# Arquitectura de Web Proyectos

## Estructura objetivo

```text
/index.php
/inc/global/
/inc/paginas/
├── public/
├── alumnos/
├── profesores/
│   ├── tutor/
│   └── tribunal/
└── admin/
/inc/seguridad.php
/.htaccess
```

- `index.php`: entrada única, carga la seguridad, resuelve la ruta y renderiza el layout.
- `/inc/global/`: cabecera, menú, pie y elementos globales.
- `/inc/paginas/public/`: pantallas sin sesión completa, como login y onboarding.
- `/inc/paginas/alumnos/`: ficha y acciones del alumno sobre su propio proyecto.
- `/inc/paginas/profesores/`: inicio y funciones comunes del profesorado.
- `/inc/paginas/profesores/tutor/`: funciones disponibles cuando el profesor es tutor del proyecto.
- `/inc/paginas/profesores/tribunal/`: funciones disponibles cuando está asignado al tribunal.
- `/inc/paginas/admin/`: gestión reservada a roles administrativos.

## Principio de autorización

La arquitectura tiene dos niveles:

1. El área controla el tipo general de usuario: alumno, profesor o administrador.
2. La relación con el recurso controla qué proyecto puede consultar o modificar.

Por tanto, estar dentro de `/profesores/tutor/` no demuestra que el profesor esté asignado al proyecto solicitado. Esa relación debe comprobarse con `rel_proyectos_profesores`. Del mismo modo, el tribunal debe comprobarse mediante la asignación real definida en base de datos.

Centraliza estas reglas en funciones pequeñas y específicas cuando existan varios consumidores, por ejemplo:

```php
requireAlumno();
requireProfessor();
requireAdmin();
requireOwnProject($projectId);
requireProjectTutor($projectId);
requireTribunalAssignment($projectId);
```

Los nombres son orientativos: antes de crearlos, comprueba si el repositorio ya ofrece equivalentes. Las funciones deben validar en servidor y detener o redirigir el flujo si falla el permiso.

## Router

La navegación interna mantiene la forma:

```text
/index.php?main=alumnos/proyecto
/index.php?main=profesores/tutor/evaluacion_form
/index.php?main=admin/proyectos/proyecto_form
```

No conviertas directamente `main` en una ruta de disco. Usa un mapa explícito que asocie cada identificador permitido con archivo y área, o una lista permitida equivalente. Rechaza rutas desconocidas.

El control de área se ejecuta antes del `include`. También se aplica a los archivos `*_accion.php`.

Las rutas públicas vigentes, como `/acces`, `/recuperar-contrasenya` o `/projecte/{id}`, pueden apuntar internamente a la organización nueva. Profesorado y alumnado comparten el acceso por email y contraseña; no existen enlaces UUID de autenticación.

## Tutor y tribunal

Tutor y tribunal son funciones de un profesor, no valores nuevos de `auth_tipo`:

- Un profesor puede tutelar unos proyectos y evaluar otros como tribunal.
- La función se determina por la relación con el proyecto.
- No crees sesiones `tutor` o `tribunal`.
- No dupliques la identidad del profesor en tablas o sesiones específicas para cada función.

## Patrón CRUD

Dentro de cada sección:

- `concepto.php`: listado.
- `concepto_form.php`: formulario único con modos `new`, `edit` y `delete` cuando proceda.
- `concepto_accion.php`: inserción, actualización o borrado.

Evita `form_concepto.php` y `accion_concepto.php`.

Cada acción admite únicamente modos conocidos, autoriza antes de escribir, valida datos, ejecuta SQL parametrizado y redirige a un destino fijo o incluido en una lista permitida.

## Componentes de las fases del alumnado

Las páginas de las siete fases reutilizan las cajas definidas en `assets/css/estilos.css`:

- `.bloc.bloc-informacio`: caja con ribete gris para contexto, explicaciones, orientaciones y recursos.
- `.bloc.bloc-activitat`: caja con ribete granate para contenidos que requieren una acción del alumnado.
- El contenido interior usa `.bloc-contingut` y la etiqueta superior `.bloc-tipus`.

No intercambies el significado visual de ambas variantes al crear contenidos nuevos.

## Redirecciones

No uses `header('Location: ...')` en acciones. Reutiliza `redirectTo` si existe. Si no existe, conserva el patrón del proyecto:

```php
$to = '/index.php?main=' . urlencode($returnMain) . '&msg=' . urlencode($msg);

echo '<script>location.href=' . json_encode($to) . ';</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '"></noscript>';
exit;
```

`$returnMain` no debe proceder libremente de la petición.

## Migración desde la carpeta plana

- Migra por flujos completos, no moviendo todos los archivos a la vez.
- Actualiza en el mismo cambio rutas, enlaces, formularios, acciones e inclusiones.
- Mantén compatibilidad con las URLs públicas existentes cuando sea necesaria.
- No aproveches el traslado para renombrar entidades o refactorizar lógica no relacionada.
- Verifica cada área y sus accesos denegados antes de retirar la ruta anterior.
