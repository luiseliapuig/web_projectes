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

Las URLs públicas describen el recurso o la función, no el rol. La landing
docente es `/resum`, el seguimiento docente `/seguiment-setmanal`, la revisión
de memoria `/revisio-memoria` y el recorrido docente de un proyecto vive bajo
`/projecte/{id}/fases/...`. Estas rutas siguen resolviendo `main` del área
`profesor`: la sesión, el área del router y la relación con el recurso aplican
la autorización server-side. El prefijo heredado `/profesor/...` solo se
mantiene temporalmente como redirección y ningún generador nuevo debe usarlo.

## Tutor y tribunal

Tutor y tribunal son funciones de un profesor, no valores nuevos de `auth_tipo`:

- Un profesor puede tutelar unos proyectos y evaluar otros como tribunal.
- La función se determina por la relación con el proyecto.
- No crees sesiones `tutor` o `tribunal`.
- No dupliques la identidad del profesor en tablas o sesiones específicas para cada función.
- La pertenencia al grupo habilita la visibilidad; el rol `tutor` de
  `rel_proyectos_profesores` habilita las intervenciones reservadas al tutor
  formal. Cada proyecto puede tener como máximo un tutor formal; al reasignarlo,
  el tutor anterior conserva la relación como `cotutor`.

## Menú de área (profesorado)

El submenú de un área se organiza en dos bloques, separados por una línea
vertical discreta:

- **bloque principal**: herramientas de uso habitual durante el seguimiento
  ordinario (por ejemplo, en el del profesorado: Resum, Autoseguiment,
  Memòria);
- **bloque secundario**: herramientas contextuales, temporales o de uso
  ocasional (por ejemplo, las ligadas a un período concreto como las
  defensas), cada una con su propia condición de aparición ya establecida.

El separador pertenece a la división entre ambos bloques, no a ninguna
herramienta concreta del bloque secundario: solo se renderiza cuando ese
bloque tiene al menos un elemento visible, para no dejarlo flotando solo.

Retirar una herramienta del menú no implica retirar su vista, ruta,
permisos ni funcionalidad: el menú es solo la puerta de entrada visible: una
herramienta puede seguir existiendo y siendo accesible por su URL aunque
ya no se ofrezca desde aquí.

## Revisión de Memòria del tutor

La vista de revisión renderiza siempre todos los apartados del proyecto y
decide automáticamente su visibilidad a partir del estado real que cada tarjeta
expone en el DOM:

- si existe alguna `revision_solicitada`, solo se muestran esas solicitudes
  pendientes;
- si no existe ninguna, se muestran todos los apartados como vista completa de
  consulta;
- al resolver una solicitud, se recalculan con esa misma fuente DOM tanto la
  visibilidad como el indicador de pendientes, sin recargar ni ofrecer un filtro
  manual.

Las tarjetas ocultas permanecen renderizadas para que, al resolver la última
solicitud, todas reaparezcan inmediatamente sin una nueva petición al backend.

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

## Fases y tareas

> **Antes de implementar o tocar cualquier fase (Fase 3-7 incluidas), lee
> primero [`docs/codex/canon-fases.md`](canon-fases.md).** Ese documento es el
> mapa operativo real: qué archivo hace qué, qué función es la fuente de
> verdad de cada estado, el inventario de clases CSS a reutilizar, el routing
> canónico y una checklist obligatoria antes de crear una fase nueva. Esta
> sección se queda con el contrato general (jerarquía, bloqueo, lenguaje
> visual); `canon-fases.md` referencia archivos concretos y no debe
> duplicarse aquí.

Una fase es un contenedor de una o varias tareas. La vista de una fase muestra
únicamente un resumen por tarea; nunca despliega directamente el formulario,
las instrucciones completas ni las acciones de una tarea, aunque esa fase
solo contenga una.

Jerarquía siempre en cuatro niveles:

```text
Projecte
  → Fase
      → Tasca
          → Detall / espai de treball
```

### El recorrido de fases pertenece al proyecto, no al rol

Alumnado y profesorado consultan la misma infraestructura de fases/tareas —
mismo `fase_base.php`, misma navegación lateral, mismas vistas de fase y de
tarea. El profesor no tiene una copia de la web del alumnado: entra en su
misma infraestructura como usuario autorizado, con la sesión, el menú
superior y los permisos propios del profesorado (nunca simula una sesión de
alumno ni cambia de rol).

El rol determina exclusivamente:

- **permisos**: qué proyectos puede consultar (autorización real contra la
  relación profesor↔grupo/proyecto, nunca solo el identificador recibido
  por la petición);
- **acciones disponibles**: qué puede ejecutar (por ejemplo, solo el tutor
  formal de un proyecto puede validar una tarea formalmente);
- **bloques adicionales**: qué capacidades extra aparecen dentro de una
  tarea concreta (por ejemplo, la caja de intervención del tutor).

El rol **nunca** crea una segunda versión del recorrido, ni un sidebar
propio, ni una copia de las vistas de fase o de tarea. Cuando una vista de
contenido necesita comportarse distinto según quién la consulta, lo hace
por una variable de rol que ya recibe (nunca un archivo paralelo), y aplica
igual tanto si esa vista se alcanza desde una fase como desde el detalle de
una tarea.

Un prerrequisito pedagógico del alumnado (por ejemplo, una tarea que exige
haber completado una fase anterior) no se traduce automáticamente en una
prohibición de consulta para el profesorado: el profesorado puede recorrer
y consultar cualquier fase o tarea de un proyecto que tenga autorizado,
esté o no desbloqueada para el alumnado — está revisando el recorrido, no
ejecutando la tarea en su lugar.

#### Dos accesos contextuales, una misma infraestructura

El profesorado entra en el recorrido de un proyecto por dos vías, que
convergen en la misma infraestructura y nunca la duplican:

- **acceso general**, desde un proyecto (por ejemplo, su nombre o el
  alumnado en un listado): aterriza en un punto natural del recorrido —
  preferentemente su primera fase definida — desde donde se puede navegar
  a cualquier otra fase de ese mismo proyecto;
- **acceso profundo**, desde una solicitud o notificación concreta (por
  ejemplo, una revisión pendiente): aterriza directamente en la
  fase/tarea correspondiente, con la misma navegación lateral completa
  disponible desde ahí para seguir recorriendo el resto del proyecto.

El contexto de ambos accesos es siempre el **proyecto**, nunca un
identificador de alumno: cambiar de fase dentro de ese recorrido conserva
el mismo proyecto consultado.

Cada nivel de esta jerarquía tiene una responsabilidad distinta frente a un
prerrequisito no cumplido (por ejemplo, una fase anterior sin completar):

```text
PROYECTO
  → FASES: consultables/navegables como parte del recorrido
      → TAREAS: pueden estar bloqueadas por prerrequisitos
          → DETALLE DE TAREA: solo accesible cuando la tarea está disponible
          → ACCIONES DE TAREA: siempre protegidas server-side por los mismos
            prerrequisitos
```

Una fase nunca se bloquea a sí misma: siempre se puede entrar a consultarla
(sidebar, título, introducción, targetas de sus tareas), esté o no
desbloqueada por el progreso del alumnado. El bloqueo se aplica a sus
tareas, no a la posibilidad de visitar la fase — una fase bloqueada no
oculta el recorrido futuro al alumnado, solo le impide ejecutar sus tareas
antes de tiempo.

### Targeta de tasca (resumen, no formulario)

Cada tarea se representa en la vista de fase mediante una targeta compacta.
Es un resumen, nunca un formulario. Contiene únicamente:

- nombre de la tarea;
- descripción breve;
- estado actual (pill), derivado siempre de datos ya existentes — nunca de
  un campo `*_estado` nuevo creado solo para representarlo;
- enlace al artefacto/documento actual de la tarea, si existe;
- acción `Entrar` hacia el detalle de la tarea.

Nunca aparecen en la targeta: instrucciones completas, enlaces a plantillas,
inputs, formularios, ni acciones de guardado, solicitud de revisión, subida
de evidencias o controles del tutor. Todo eso pertenece al detalle de la
tarea; las plantillas y demás recursos de trabajo son siempre del detalle,
nunca del resumen.

### Lenguaje visual de una tarea

El resumen (targeta) y el detalle de una misma tarea deben representar
siempre coherentemente la misma situación: nunca uno dice visualmente una
cosa y el otro otra. Ambos derivan su estado de los mismos datos reales, sin
introducir un campo `*_estado` nuevo solo para representarlo.

Una tarea usa tres colores de estado, reutilizando las clases ya existentes
para las cajas (`.bloc`) de las fases:

- **activa / en trabajo** (`.bloc-activitat`, pill `.badge-activitat`):
  granate corporativo. Es el estado normal de una tarea disponible que
  todavía no se ha completado — incluida la que aún no se ha empezado. El
  gris no se usa para representar que una tarea está simplemente disponible
  o pendiente de iniciar; queda reservado para lo realmente
  neutro/inactivo/bloqueado (`.bloc-informacio`, `.bloc-bloquejat`).
- **intervención pendiente del tutor** (`.bloc-atencio`, pill
  `text-bg-warning`): amarillo. Señala que hay algo que requiere la
  intervención de un tutor o tutora. Cuando una tarea usa un mecanismo de
  solicitud de revisión, este estado se deriva de la existencia de una
  solicitud abierta, no de un campo nuevo.
- **completada** (`.bloc-completat`, pill `text-bg-success`): verde, cuando
  se cumple el criterio real de completado propio de esa tarea.

Esta paleta es específica del estado de una tarea; no se extrapola
automáticamente a otras capacidades del sistema que aún no la usan.

### Botones del sistema de fases

Los botones que aparecen dentro del sistema de fases (navegación, targetas
de tarea, detalle, cajas del tutor) comparten una **geometría** común —
altura, padding, tamaño tipográfico y border-radius—, aportada por una clase
compuesta y reutilizable (`.btn-fase`), independiente de la clase que da el
color/jerarquía de cada botón (`.btn-puig`, `.btn-puig-solid`, u otras
variantes ya existentes en la aplicación). Compartir geometría **no**
implica compartir color ni jerarquía semántica: se mantienen distintos
niveles de acción —

- acción principal → variante sólida;
- acción secundaria → variante outline;
- otras jerarquías ya existentes en la aplicación (por ejemplo una acción ya
  completada que se puede revisar) conservan su propio color, con la misma
  geometría común;
- enlace informativo (por ejemplo, para consultar un documento o volver a la
  fase) → enlace (`.link-secundari-puig`), nunca un botón;
- un recurso/documento (ver más abajo) tampoco es nunca un botón.

Un botón fusionado a un campo de formulario (por ejemplo, un `input-group`)
puede quedar fuera de esta geometría común cuando forzarla rompería la
unión visual con el campo adyacente; en ese caso prevalece la coherencia con
el control del que forma parte.

No se resuelve creando una clase nueva por cada combinación de botón y
pantalla: la geometría común se compone con la clase de color, nunca se
duplica dentro de cada variante.

### Textarea autoajustable

La clase declarativa `.auto-grow` activa el ajuste vertical automático definido
en `assets/js/main.js`. El helper conserva la altura mínima del diseño, amplía
o reduce el campo según el contenido y evita el scroll vertical interno. Añadir
la clase es suficiente para los textarea visibles o que reciben el foco; si un
campo se muestra dinámicamente sin enfocarlo, se puede llamar a
`TextareaAutoGrow.refresh(textarea)` después de hacerlo visible.

La apariencia gris estable y neutra es una responsabilidad independiente y se
activa con `.textarea-neutral`. Esta clase conserva fondo y borde neutros en
reposo, hover y focus; puede combinarse con `.auto-grow` cuando también se
necesita crecimiento automático.

### Recursos/plantillas de una tarea

Las plantillas y demás recursos de apoyo pertenecen siempre al detalle de la
tarea, nunca al resumen. Se presentan como recursos/documentos (icono +
texto + enlace), nunca como botones ni como llamadas a la acción. Cuando un
recurso todavía no existe (URL vacía), simplemente no se muestra — no se
inventa una URL.

El texto y la URL de estos recursos deben quedar como variables PHP
claramente comentadas al principio del archivo de la tarea donde vivan,
separadas de la maquetación, para poder cambiarse sin tocar el HTML.

Cuando una tarea trabaja sobre un documento vivo, su ayuda puede incluir un
recordatorio de compartirlo con el tutor o tutora, con estilo de texto de
ayuda (tamaño secundario, color suave) y sin aspecto de alerta.

Una targeta de tarea puede exponer su artefacto documental con un nombre
estable (por ejemplo el propio nombre de la tarea), sin que ese texto tenga
que describir técnicamente si el recurso subyacente es todavía un documento
vivo o ya una evidencia definitiva. Solo cambia el destino del enlace a
medida que el recurso evoluciona; el texto visible no cambia. Este enlace
usa el mismo lenguaje visual de recurso que las plantillas del detalle
(icono + texto, color secundario, nunca apariencia de botón).

### Bloqueo por prerrequisito: de la tarea, no de la fase

La fase en sí misma nunca requiere gate por prerrequisito: siempre se puede
entrar a consultarla. El prerrequisito se evalúa a nivel de tarea, y de ahí
hacia abajo:

- la **targeta de la tarea** bloqueada se sigue mostrando (forma parte del
  recorrido que el alumnado necesita conocer), pero con lenguaje visual
  neutro — el mismo reservado para lo inactivo/bloqueado (`.bloc-bloquejat`)
  —, sin la acción `Entrar` ni ningún otro enlace operativo. Una tarea
  disponible pasa al lenguaje visual activo/de estado que le corresponda
  (ver "Lenguaje visual de una tarea");
- el **detalle/espai de treball** de la tarea (su ruta propia) sí lleva gate
  server-side: solo se renderiza cuando la tarea está disponible;
- las **acciones** de la tarea (todo lo que escribe) llevan gate
  server-side siempre, con independencia de si la tarea aparenta estar
  disponible en la interfaz — antes de cualquier escritura.

Ocultar o deshabilitar `Entrar` en la targeta es solo la representación
visual del bloqueo; nunca sustituye el gate real del detalle y de sus
acciones, que debe existir con independencia de lo que muestre la
interfaz.

Este apartado no fija cómo se calcula el prerrequisito de cada tarea futura
(cada tarea define y reutiliza su propio criterio real de completado/
disponibilidad, como ya hace la Fase 1 para la tarea que depende de ella);
establece únicamente en qué nivel se aplica el bloqueo y en cuál no.

### Documento vivo vs. evidencia definitiva

Cuando una tarea produce un documento de trabajo y, más adelante, una
evidencia definitiva (por ejemplo un PDF validado formalmente), la targeta
de resumen sigue esta regla:

- sin documento vivo ni evidencia definitiva: no se muestra ningún enlace
  documental;
- con documento vivo y sin evidencia definitiva: se muestra el enlace al
  documento vivo;
- con evidencia definitiva: esta sustituye al documento vivo como enlace
  principal de la targeta. El documento vivo NO se elimina de BD; simplemente
  deja de ser el enlace mostrado en el resumen.

### Navegación y rutas

La navegación lateral de la zona del alumnado trabaja siempre a nivel de
fase, nunca de tarea. La entrada al detalle de una tarea se realiza siempre
desde su targeta (`Entrar`), no desde la navegación lateral.

La ruta pública del detalle de una tarea cuelga de la ruta ya existente de su
fase, por ejemplo `/fases-del-projecte/fase-2/proposta`, siguiendo el mismo
patrón que ya usan las subpáginas de Fase 1 (`/fases-del-projecte/fase-1/definir-grup`).
No inventes un esquema de URL distinto para el detalle de tarea.

### Lenguaje visual de la navegación de fases

En la navegación lateral, tres dimensiones son independientes y no deben
confundirse:

- **estado**: en qué situación real está la fase (activa/disponible,
  bloqueada por prerrequisito, completada). Determina el **color**
  semántico — el mismo lenguaje granate/gris/verde que usan las targetas de
  tarea;
- **selección**: si es la fase que el usuario está consultando ahora mismo.
  Nunca cambia el color a uno que contradiga el estado; solo refuerza ese
  mismo color con más énfasis (marco/contraste más marcados). Visitar una
  fase no altera artificialmente su estado;
- **hover**: feedback de interacción ("puedo clicar"), transitorio y
  distinto de la selección ("estoy aquí"). Una fase bloqueada sigue siendo
  navegable y por tanto puede tener hover.

Combinando estado × selección deben poder expresarse, sin crear una clase
específica por combinación:

- activa + no seleccionada → lenguaje activo, sin énfasis de selección;
- activa + seleccionada → mismo lenguaje activo + marco/contraste como
  énfasis;
- bloqueada + no seleccionada → gris neutro;
- bloqueada + seleccionada → sigue siendo gris (nunca el color de activa),
  con marco/contraste más marcados que la no seleccionada — debe comunicar
  a la vez "estoy aquí" y "todavía bloqueada", sin mezclar ambos mensajes en
  un único color;
- completada + no seleccionada → lenguaje verde de completado;
- completada + seleccionada → mismo verde + marco/énfasis reforzado, nunca
  el color de activa por el simple hecho de estar seleccionada.

Esto se resuelve componiendo la clase de estado con la clase de selección
(nunca con una clase que combine ambas en una sola, del tipo
`fase2-bloqueada-seleccionada`), de modo que el patrón sea reutilizable para
cualquier fase futura sin tocar CSS.

No documentes aquí implementaciones concretas de tareas (por ejemplo,
"Proposta de projecte" de la Fase 2): este apartado es el contrato general;
cada tarea documenta su propio comportamiento donde corresponda, no aquí.

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
