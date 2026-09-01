# Canon de fases (V2) — como implementar Fase 3-7

> **Lectura obligatoria antes de tocar cualquier fase.** Este documento describe
> el código REAL tal como quedó tras consolidar Fase 1 y Fase 2. No es una
> propuesta ni una intención: cada afirmación está verificada contra el
> repositorio en la fecha de escritura. Si el código cambia, actualiza este
> documento en el mismo cambio.
>
> Relación con [`arquitectura.md`](arquitectura.md): ese documento fija el
> **contrato general** (jerarquía Projecte→Fase→Tasca→Detall, reglas de
> bloqueo, lenguaje visual de una tarea). Este documento es su **mapa
> operativo**: qué archivo hace qué, qué función es la fuente de verdad, qué
> URL existe, qué clase CSS usar. Ante cualquier duda de implementación,
> empieza aquí; para el porqué conceptual, ve a `arquitectura.md`.

## Índice

1. [Mapa arquitectónico de una fase](#1-mapa-arquitectónico-de-una-fase)
2. [Fase 1 y Fase 2 como referencias](#2-fase-1-y-fase-2-como-referencias)
3. [Estados: fuente real](#3-estados-fuente-real)
4. [Lenguaje visual](#4-lenguaje-visual)
5. [Tarjetas](#5-tarjetas)
6. [Botones y enlaces](#6-botones-y-enlaces)
7. [Metadatos de fase (número/título/descripción)](#7-metadatos-de-fase-númerotítulodescripción)
8. [«Fases del projecte»](#8-fases-del-projecte)
9. [Sidebar (`fases_navegacion.php`)](#9-sidebar-fases_navegacionphp)
10. [Breadcrumbs](#10-breadcrumbs)
11. [Alumno vs profesor](#11-alumno-vs-profesor)
12. [Routing canónico](#12-routing-canónico)
13. [Capa PDF (norma obligatoria)](#13-capa-pdf-norma-obligatoria)
14. [CSS: inventario por función](#14-css-inventario-por-función)
15. [Nomenclatura de archivos](#15-nomenclatura-de-archivos)
16. [Checklist antes de implementar una fase nueva](#16-checklist-antes-de-implementar-una-fase-nueva)
17. [Antipatrones / qué no hacer](#17-antipatrones--qué-no-hacer)
18. [Deuda y discrepancias detectadas (sin corregir)](#18-deuda-y-discrepancias-detectadas-sin-corregir)

---

## 1. Mapa arquitectónico de una fase

```text
Fases del projecte  (fases_projecte.php)
    ↓ CTA «Entrar»
Fase N               (fase-N.php  →  fase_base.php  →  fase-N_contingut.php)
    ↓ targeta-resum de tasca, CTA «Entrar»
Tasca                (fase-N_tasques.php dentro del _contingut, si la fase
                       tiene varias tareas)
    ↓ CTA «Entrar» de la targeta
Detall / espai de treball   (fase-N_xxx.php → fase_base.php → fase-N_xxx_detall.php)
    ↓ formularios / fetch()
Acció                (fase-N_xxx_accion.php — escritura, siempre server-gated)
```

| Pregunta | Respuesta real |
|---|---|
| ¿Quién define metadatos de las 7 fases? | [`inc/paginas/alumnos/informatica/fases.php`](../../inc/paginas/alumnos/informatica/fases.php) — array `número => ['titulo','descripcio','ruta','main','archivo']` |
| ¿Dónde vive el shell común? | [`inc/paginas/alumnos/informatica/fase_base.php`](../../inc/paginas/alumnos/informatica/fase_base.php) |
| ¿Quién pinta el sidebar? | [`inc/paginas/alumnos/fases_navegacion.php`](../../inc/paginas/alumnos/fases_navegacion.php), incluido por `fase_base.php` |
| ¿Quién calcula los estados? | Un helper propio por fase (`fase1…` hasta `fase6…`), reducidos a apariencia visual por `fasesEstatAparenca()` en [`fase-1_funcions.php`](../../inc/paginas/alumnos/informatica/fase-1_funcions.php) |
| ¿Cómo se carga el contenido específico? | El wrapper `fase-N.php` fija `$faseContenidoArchivo` y llama a `fase_base.php`, que hace `include $faseContenidoArchivo` dentro de su `.card` |
| ¿Cómo se representa una tarea interna? | Una `<section class="bloc ...">` compacta (targeta-resum) dentro del `_contingut`/`_tasques` de la fase — nunca el formulario completo |
| ¿Diferencia `_form`/`_accion`/`_contingut`/`_detall`? | Ver [§15](#15-nomenclatura-de-archivos) |
| ¿Qué comparte alumno con profesor? | Todo: `fase_base.php`, `fases_navegacion.php`, `fase-N_contingut.php`/`_detall.php`. Solo cambia cómo se resuelve `$proyectoAlumno` y el valor de `$rolVisualitzacio` — ver [§11](#11-alumno-vs-profesor) |
| ¿Qué debe copiar una fase nueva? | El **patrón** (wrapper delgado + `_contingut` con `.bloc`s + helpers de estado propios). Nunca el archivo físico de Fase 1/2 vía copy-paste |

---

## 2. Fase 1 y Fase 2 como referencias

No son plantillas para clonar: cada una enseña un patrón distinto, verificado en el código real.

### Fase 1 — referencia de "varias tareas simples dentro de una fase"

Archivos: [`fase-1.php`](../../inc/paginas/alumnos/informatica/fase-1.php), [`fase-1_contingut.php`](../../inc/paginas/alumnos/informatica/fase-1_contingut.php), [`fase-1_funcions.php`](../../inc/paginas/alumnos/informatica/fase-1_funcions.php), tarea "Defineix el grup" (`fase-1_grup_form.php`/`_accion.php`/`_contingut.php`) y tarea "Compromís de treball" (`fase-1_compromis_form.php`/`_accion.php`/`_contingut.php`).

Enseña:
- dos tareas independientes dentro de una misma fase, cada una con su propio wrapper `_form.php` (gate + datos), `_accion.php` (escritura) y `_contingut.php` (markup);
- un formulario real con confirmación modal (`fase-1_grup_contingut.php`);
- un criterio de completado con dependencia entre tareas (el compromiso exige el grupo confirmado) resuelto con gate server-side, no solo visual;
- un resultado textual reutilizable ("Projecte individual/en parella: …") extraído a `fase1ResultadoGrupoTrabajo()` para que la vista interna y la targeta-resumen de "Fases del projecte" lean el mismo texto;
- una vista de sólo-consulta reutilizada por el profesorado (`fase-1_compromis_form.php` sirve a alumnado y profesorado con la misma plantilla, cambiando solo `$rolVisualitzacio`).

### Fase 2 — referencia de "tarea por pasos con intervención de tutor y PDF definitivo"

Archivos: [`fase-2.php`](../../inc/paginas/alumnos/informatica/fase-2.php), [`fase-2_tasques.php`](../../inc/paginas/alumnos/informatica/fase-2_tasques.php) (targeta-resum), [`fase-2_proposta.php`](../../inc/paginas/alumnos/informatica/fase-2_proposta.php) (wrapper del detalle), [`fase-2_proposta_detall.php`](../../inc/paginas/alumnos/informatica/fase-2_proposta_detall.php) (los 3 pasos), [`fase-2_accion.php`](../../inc/paginas/alumnos/informatica/fase-2_accion.php), [`fase-2_proposta_funcions.php`](../../inc/paginas/alumnos/informatica/fase-2_proposta_funcions.php).

Enseña:
- una tarea con **pasos internos** (Pas 1 clasificación, Pas 2 documento vivo + solicitud de revisión, Pas 3 PDF definitivo) — nunca tres tareas ni tres rutas nuevas, todo cuelga de `/fases-del-projecte/fase-2/proposta`;
- clasificación real contra catálogo de BD (`categoria_proyecto_id`/`tipo_proyecto_id`), nunca comparación de strings — ver `fase2ClassificacioObtenirEstat()`;
- documento vivo (URL editable) vs. evidencia definitiva (PDF), con la regla de sustitución documentada en `arquitectura.md` ("Documento vivo vs. evidencia definitiva");
- solicitud de revisión con tabla genérica `app.revisiones_solicitudes` (índice único parcial para idempotencia) y aviso por email vía `EmailQueue`;
- intervención del tutor como **sección interna del mismo bloque** que revisa (`.bloc-zona.bloc-zona-atencio`), nunca un bloque hermano — solo visible para `esTutorFormalDelProyecto()`, nunca cotutor;
- **la lección más importante de Fase 2**: un recurso de un paso concreto (por ejemplo, el enlace del documento vivo del Pas 2) toma el color de **su propio paso**, no el estado global de la tarea — ver `$pas2ClasseOutline`/`$pas3ClasseOutline` en `fase-2_proposta_detall.php`, calculados aparte de `$estat['classe_cta']` (estado global);
- subida del PDF definitivo pasando por la capa común (`pdfGuardarDefinitiu()`) — ver [§13](#13-capa-pdf-norma-obligatoria);
- resumen final de evidencias reutilizado tal cual por la targeta-resum (`fase-2_tasques.php`) y por «Fases del projecte» (`fases_projecte.php`), ambos leyendo `fase2PropostaObtenirEstat()`/`fase2ClassificacioObtenirEstat()`.

### Fase 4 — dos tareas simples basadas en URL

`Planificació temporal del projecte` y `Gestió del projecte` son tareas independientes. Sus fuentes únicas V2 son, respectivamente, `proyectos.planificacion_url` y `proyectos.gestion_url`; los adjuntos históricos `proyecto_adjuntos.planificacio/gestio` no se consultan ni se usan como fallback. Cada tarea queda completada cuando su URL no está vacía y la fase solo queda completada cuando ambas lo están. No hay revisión, intervención docente, PDF ni adjuntos: el alumnado puede modificar las URLs y el profesorado autorizado solo las consulta.

### Fase 5 — cuatro tareas visibles en paralelo

El orden canónico es: `Repositoris Git`, `Tecnologies i eines`, `Autoavaluació final` y `Entrega del projecte`. Las cuatro tareas están accesibles en paralelo desde que Fase 5 queda desbloqueada; ninguna actúa como prerrequisito de otra.

- `Repositoris Git`: reúne `proyectos.git_url`/`git_etiqueta` y todos los `proyecto_adjuntos.tipo='git'` mediante `fase5RepositorisObtenirEstat()`. Visualmente todos son repositorios equivalentes y la tarea queda completada cuando la colección contiene al menos uno.
- `Tecnologies i eines`: usa exclusivamente `app.rel_proyectos_tecnologias` y `app.rel_proyectos_herramientas` mediante `fase5StackObtenirEstat()`. Se completa con al menos una tecnología; las herramientas son opcionales. `proyectos.stack` no es fuente V2.
- `Autoavaluació final`: usa `proyectos.autoev1..4`. `fase5AutoavaluacioPreguntes()` centraliza las preguntas y `fase5AutoavaluacioObtenirEstat()` exige las cuatro respuestas para completar la tarea.
- `Entrega del projecte`: usa `proyectos.url_proyecto` mediante `fase5ProduccioObtenirEstat()` y queda completada cuando la URL está informada.

`fase5ObtenirEstat()` es el agregador canónico: Fase 5 solo queda completada cuando las cuatro tareas anteriores están completas. Lo consumen la página de fase, «Fases del projecte», el sidebar y el eyebrow interior a través de `fasesEstatAparenca()`.

`Preparació de l’entorn de desenvolupament` permanece implementada y conserva routing, permisos, revisión y pipeline PDF, pero queda fuera del recorrido visible, del resumen y del completado global de Fase 5.

### Fase 6 — Document de la memòria

La primera tarea real de Fase 6 pone en marcha el documento vivo de la memoria. Usa exclusivamente `proyectos.memoria_url`: `fase6MemoriaObtenirEstat()` la considera completada cuando la URL está informada y pendiente cuando está vacía. El alumnado puede crearla, modificarla o vaciarla; el profesorado autorizado reutiliza el mismo detalle en modo de solo lectura. Las dos plantillas oficiales viven como variables en `fase-6_recursos.php`.

Esta tarea no representa la entrega definitiva y no consulta ni modifica `memoria_pdf` o `memoria_validada_en`.

### Fase 6 — Fitxa pública del projecte

La segunda tarea real de Fase 6 completa únicamente los datos editoriales que todavía necesita la presentación pública: `proyectos.nombre`, `proyectos.resumen`, `proyectos.descripcion` y `proyectos.ruta_imagen`. `fase6FitxaPublicaObtenirEstat()` es la fuente única del estado de la tarea y solo la considera completada cuando los cuatro valores están informados. No duplica tecnologías, herramientas, repositorios ni el resto de información construida durante el recorrido.

Alumno y profesor reutilizan el mismo detalle con `$rolVisualitzacio`: el alumno edita y el profesor consulta en solo lectura. El procesamiento de imagen se comparte con la ficha legacy mediante `inc/imagenes/funciones.php` (JPG/PNG/WEBP, 20 MB, máximo 1600×1200 y salida JPEG al 85 %). `ruta_imagen` conserva únicamente la ruta real; el render añade `?time=filemtime(...)` para invalidar la caché cuando el archivo local existe. La ficha pública V1 no se modifica como parte de esta tarea.

Las tres tareas actuales de Fase 6 quedan disponibles en paralelo una vez completada Fase 4: la fitxa pública y la entrega definitiva no dependen de haber completado el documento vivo. `fase6ObtenirEstat()` es el agregador canónico y Fase 6 solo queda completada cuando están completos simultáneamente el documento vivo (`memoria_url`), la fitxa pública (`nombre`, `resumen`, `descripcion` y `ruta_imagen`) y la entrega definitiva (`memoria_pdf`). Esta regla alimenta la página de fase, «Fases del projecte», el sidebar y el eyebrow interior mediante `fasesEstatAparenca()`. No consulta `memoria_validada_en`.

### Fase 6 — Entrega de la memòria

La tercera tarea de Fase 6 publica la versión definitiva en `proyectos.memoria_pdf`; nunca usa `memoria_url` ni `memoria_validada_en`. Alumno y profesor comparten la misma vista: el alumnado puede entregar o sustituir el PDF y el profesorado lo consulta en solo lectura. La tarjeta exterior y el interior comparten `fase-6_memoria_cta.php` para representar el documento entregado.

La subida pasa obligatoriamente por `pdfGuardarDefinitiu()` (PDF real, máximo 20 MB, directorio canónico `uploads/{curso}/{ciclo}/{proyecto}` y optimización best effort). Cada sustitución se guarda con un nombre nuevo; solo después de persistir la nueva ruta limpia en una transacción se elimina el archivo anterior mediante `pdfResoldreRutaLocalSegura()`. Así, un fallo antes del commit conserva la entrega anterior y nunca permite borrar fuera de `uploads/`.

La tarea se considera completada cuando `memoria_pdf` está informado. El desbloqueo de Fase 7 exige el estado global completo tanto de Fase 5 como de Fase 6.

### Fase 7 — Presentació de la defensa

La única actividad funcional de Fase 7 usa el campo canónico `proyectos.presentacion_pdf`. Sin ruta está pendiente; con ruta está completada y, al ser la única actividad, completa también la fase. El alumnado entrega o sustituye el PDF mediante `pdfGuardarDefinitiu()` y el profesorado autorizado reutiliza la misma vista en modo de solo lectura. No existen revisión, validación del tutor, estados intermedios ni reglas temporales.

---

## 3. Estados: fuente real

**Regla de oro: ESTADO ≠ SELECCIÓN.** El estado es la situación real de la fase (¿está completada?, ¿hay algo pendiente?). La selección es solo "¿es la fase que estoy consultando ahora?". Entrar en una fase completada **nunca** la vuelve granate.

### Fuentes de verdad (por fase)

| Fase | Helper de completado | Firma | Fichero |
|---|---|---|---|
| 1 | `fase1CompletadaAlumnoProyecto()` (sesión alumno) / `fase1CompletadaProyecte()` (sin sesión — contexto profesor) | `(PDO $pdo, int $alumnoId, int $idProyecto): bool` / `(PDO $pdo, int $idProyecto): bool` | `fase-1_funcions.php` |
| 2 | `fase2PropostaObtenirEstat()` — devuelve array completo (`completada`, `atencion`, `pdf`, `url`, `validada`, `classe_badge`, `classe_cta`, `classe_outline`, …) | `(PDO $pdo, int $idProyecto): array` | `fase-2_proposta_funcions.php` |
| 3 | `fase3DocumentFuncionalObtenirEstat()['completada']`: requiere `funcional_validado_en` y `funcional_pdf`. | Revisión funcional abierta o validación sin PDF definitivo. | `funcional_url`, `funcional_validado_en`, `funcional_pdf`, revisión `tipo='funcional'` |
| 4 | `fase4PlanificacioGestioObtenirEstat()['completada']`: requiere ambas URLs no vacías. | Dos tareas simples e independientes, sin revisión docente. | `planificacion_url`, `gestion_url` |
| 5 | `fase5ObtenirEstat()['completada']`: repositorio + tecnología + cuatro respuestas de autoevaluación + URL de producción. | Cuatro tareas visibles e independientes. | Git actual, `rel_proyectos_tecnologias`, `autoev1..4`, `url_proyecto` |
| 6 | `fase6ObtenirEstat()['completada']`, que exige simultáneamente documento vivo, fitxa pública y memoria definitiva. No altera la regla de Fase 7. | Documento vivo, fitxa pública y entrega definitiva editables e independientes. | `memoria_url`; `nombre`, `resumen`, `descripcion`, `ruta_imagen`; `memoria_pdf` |
| 7 | `fase7PresentacioDefensaObtenirEstat()['completada']`: existe `presentacion_pdf`. Se desbloquea únicamente con Fase 5 y Fase 6 completas. | PDF de la presentación de defensa, sustituible mientras la fase sea editable. | `presentacion_pdf` |

### Reducción a apariencia visual — `fasesEstatAparenca()`

```php
fasesEstatAparenca(int $numeroFase, bool $faseUnoCompletada, bool $faseDosCompletada, bool $faseDosAtencio, bool $faseTresCompletada = false, bool $faseTresAtencio = false, bool $faseQuatreCompletada = false, bool $faseCincCompletada = false, bool $faseSisCompletada = false, bool $faseSetCompletada = false): array
// → ['bloquejada' => bool, 'completada' => bool, 'atencio' => bool, 'activa' => bool]
```

Definida en `fase-1_funcions.php`. Es la **única** fuente de la cascada de desbloqueo y de la prioridad de estados (bloqueada > completada > atención > activa). La consumen **los tres** puntos que necesitan pintar el estado de una fase, todos con los mismos argumentos:

- [`fases_navegacion.php`](../../inc/paginas/alumnos/fases_navegacion.php) (sidebar, dentro del `foreach` de fases);
- [`fases_projecte.php`](../../inc/paginas/alumnos/fases_projecte.php) (targetas de "Fases del projecte");
- [`fase_base.php`](../../inc/paginas/alumnos/informatica/fase_base.php) (color del eyebrow "Fase N" de la cabecera interior).

El recorrido no es completamente lineal:

```text
1 → 2 → 3 → 4 → {5 + 6 en paralelo} → 7
```

Las fases 1-4 mantienen la progresión secuencial. Al completar Fase 4 se desbloquean simultáneamente Fase 5 y Fase 6; Fase 6 no depende de completar Fase 5. Las páginas raíz de las siete fases son siempre explorables, incluso sin proyecto activo: muestran el shell y sus tareas bloqueadas, pero nunca acciones ni enlaces operativos antes del prerrequisito. Los detalles de tarea y sus acciones repiten el gate correspondiente en servidor. Fase 7 se desbloquea únicamente cuando Fase 5 y Fase 6 están completas; su detalle y su acción conservan ese prerrequisito y no lo condicionan a fechas.

**Ninguna fase futura debe recalcular esta cascada.** Si Fase 3 aporta un criterio real de completado, créalo como helper propio (`fase3XxxCompletada()`, mismo patrón que Fase 1/2) y pásalo como argumento adicional a una versión ampliada de `fasesEstatAparenca()` — nunca un `if` especial dentro de sus consumidores.

### Significado de cada estado

| Estado | Significado | Color/clase (targeta) | Ejemplo de badge textual |
|---|---|---|---|
| Completada | Criterio real de la fase/tarea cumplido | `.bloc-completat` / verde | `text-bg-success` |
| Activa | Fase disponible, sin completar (incluida la que aún no se ha empezado) | `.bloc-activitat` / granate | `.badge-activitat` |
| Atenció | Hay una solicitud abierta que requiere intervención del tutor (Fase 2, Pas 2) | `.bloc-atencio` / amarillo | `text-bg-warning` |
| Bloquejada | No alcanzable todavía por la cascada de desbloqueo | `.bloc-bloquejat` / gris | — (sin badge, icono candado) |

### De dónde lo obtiene el sidebar y de dónde el eyebrow

Ambos llaman a `fasesEstatAparenca()` con los mismos argumentos — no hay una fuente para el sidebar y otra para el eyebrow. La diferencia es solo el CONSUMO:

- el sidebar compone el resultado con clases de nav-pill (`.projecte-fase-enllac-completada`, etc.) — ver [§9](#9-sidebar-fases_navegacionphp);
- `fase_base.php` compone el mismo resultado con las clases `.fase-etiqueta--completada`/`--atencio`/`--bloquejada` (el caso "activa" es el color por defecto de `.fase-etiqueta`, sin modificador).

### Selección (independiente del estado)

En el sidebar, `class="active"` es pura selección (`$faseActiva === $numeroFase`), compuesta con la clase de estado. Nunca sustituye el color de estado — solo añade énfasis (marco/contraste). Ver `.projecte-fase-enllac.active`, `.projecte-fase-enllac-completada.active`, etc. en `estilos.css`.

---

## 4. Lenguaje visual

No uses hexadecimales como referencia — usa la clase/componente. Los hex existen en `estilos.css` por si hace falta consultarlos, pero la norma es la clase.

| Significado | Clase de estado (`.bloc`/`.bloc-tipus`) | Pill/badge | Eyebrow interior |
|---|---|---|---|
| **Verde** — completado / resultado / evidencia disponible | `.bloc-completat` | `text-bg-success` | `.fase-etiqueta--completada` |
| **Granate** — actividad actual / acción pendiente del alumno | `.bloc-activitat` | `.badge-activitat` | `.fase-etiqueta` (color por defecto, sin modificador) |
| **Amarillo** — atención / revisión / intervención del tutor | `.bloc-atencio` | `text-bg-warning` | `.fase-etiqueta--atencio` |
| **Gris** — futuro / bloqueado / no disponible | `.bloc-bloquejat` (o `.bloc-informacio` para contexto neutro no bloqueado) | — | `.fase-etiqueta--bloquejada` |

`.bloc-informacio` (gris) **no es lo mismo** que `.bloc-bloquejat` (también gris): el primero es contenido neutro/informativo (por ejemplo "Projectes d'altres cursos" en Fase 1); el segundo es específicamente "bloqueado por prerrequisito". No los intercambies.

---

## 5. Tarjetas

Un único patrón (`.bloc` + `.bloc-contingut`), reutilizado en tres contextos: targeta de tasca (dentro de una fase), pasos internos de una tasca (dentro del detalle) y targeta de fase (en "Fases del projecte"). Referencia directa: `fase-1_contingut.php` (targeta simple) y `fase-2_proposta_detall.php` (pasos).

Jerarquía interna, de arriba a abajo — **sin overrides de margen que la compriman**:

```text
.bloc-tipus          (eyebrow: "Completada" / "PAS 2 · Activitat" / "Fase 1 · Completada")
<h2>                 (sin clases; hereda .bloc h2 { margin:0 0 8px; font-size:20px })
<p class="mb-3">     (descripción, solo si aporta contexto real)
resultados/evidencias  (.fase-resultat-completat y/o .tasca-recurs-link, agrupados)
CTA «Entrar» / acción  (.btn-fase + color de estado)
```

Reglas verificadas en el código:

- **borde superior según estado**: `.bloc::before` (6px, color por variante — `.bloc-activitat::before`, etc.);
- **eyebrow**: `.bloc-tipus`, texto libre ("Completada", "PAS 1 · Activitat", "Fase 2 · Atenció") — el color lo hereda del `.bloc-*` padre, nunca se fija aparte;
- **badge** (pill redondeada) **solo** cuando el texto de estado necesita más presencia que el eyebrow (ver `fase-2_proposta_detall.php`, único consumidor real de `<span class="badge rounded-pill ...">`) — no lo dupliques con el eyebrow en la misma tarjeta si no aporta nada nuevo (las targetas de "Fases del projecte" NO llevan badge, solo eyebrow);
- **nunca crear subcajas** dentro de un `.bloc-contingut` para cosas que son variaciones del mismo estado — usa `.bloc-zona`/`.bloc-zona-atencio` (franja interna con márgenes negativos que compensan el padding) cuando algo necesita destacarse dentro del mismo bloque, nunca un `<div class="card">` anidado;
- **intervención del tutor como parte de la propia tarjeta**: ver Pas 2 de `fase-2_proposta_detall.php` — la intervención vive dentro del `.bloc` del paso que revisa, con `.bloc-zona.bloc-zona-atencio`, nunca un bloque hermano nuevo;
- **tarea completada**: el resultado se muestra como texto (`.fase-resultat-completat`, icono `bi-check-circle-fill`) o enlace de documento (`.tasca-recurs-link`), nunca reconvertido en botón.

**Norma explícita**: antes de crear un componente visual nuevo, busca una `.bloc` equivalente ya existente (targeta de tasca, paso interno, targeta de "Fases del projecte") y reutiliza su estructura y clases. Solo crea una variante cuando hay una diferencia semántica real (por ejemplo, los tres modificadores `.fase-etiqueta--*` se crearon porque el eyebrow de cabecera no tenía previamente ningún mecanismo de color por estado).

---

## 6. Botones y enlaces

| Elemento | Clase de geometría | Clase de color | Dónde se usa |
|---|---|---|---|
| CTA principal (formulario) | `.btn-fase` | `.btn-puig-solid` (activo) / `.btn-atencio-solid` (atención) / `.btn-outline-success` (completado, ej. "Veure compromís") | Detalle de tarea: "Confirmar el compromís", "Pujar PDF", "Sol·licitar revisió", "Validar proposta" |
| CTA «Entrar» — targeta de tasca (dentro de una fase) | `.btn-fase` | `$estat['classe_cta']`: sólido si activa/atención, **outline si completada** | `fase-2_tasques.php` |
| CTA «Entrar» — targeta de fase (pantalla «Fases del projecte») | `.btn-fase` | **siempre outline**: `.btn-outline-success` / `.btn-atencio` / `.btn-puig`, nunca variante `-solid` | `fases_projecte.php` — ver [§8](#8-fases-del-projecte) |
| Acción secundaria / cancelar | `.btn-fase` | `.btn-puig` (outline) | "Cancel·lar" en formularios |
| Enlace informativo (volver, ver documento) | — | `.link-secundari-puig` | nunca un botón |
| Recurso/documento de un paso (plantilla, PDF) | `.tasca-recurs-link` (+ `.tasca-recurs-resultat--completat`/`--atencio`/`--activitat` si debe heredar el color de SU paso) | icono + texto, nunca botón |

**Regla clave (lección de Fase 2)**: el color de un recurso deriva del estado de **su propio contenedor/paso**, no del estado global de la tarea si ambos pueden divergir. Ejemplo real: en `fase-2_proposta_detall.php`, el enlace del documento vivo del Pas 2 usa `$pas2ClasseOutline` (calculado sobre el propio Pas 2), no `$estat['classe_cta']` (estado global de toda la tarea "Proposta de projecte").

**Discrepancia detectada**: existen dos convenciones de CTA distintas y ambas son intencionadas — la targeta-resum-de-tasca (dentro de una fase) puede mostrar `Entrar` sólido, mientras que la targeta-resum-de-fase (pantalla raíz) nunca lo hace. No "corrijas" una para que coincida con la otra: son contextos distintos (trabajo activo vs. resumen/mapa).

Geometría común: `.btn-fase` (padding/font-size/border-radius), nunca duplicada dentro de cada variante de color.

---

## 7. Metadatos de fase (número/título/descripción)

**Fuente única**: [`inc/paginas/alumnos/informatica/fases.php`](../../inc/paginas/alumnos/informatica/fases.php), array `número => ['titulo', 'descripcio', 'ruta', 'main', 'archivo']`.

- `titulo`: usado por el sidebar (con `\n` para el salto de línea en la pastilla) y por las targetas de "Fases del projecte" (con `str_replace("\n",' ',...)`);
- `descripcio`: el resumen que se muestra tanto en la cabecera interior de la fase (`$faseIntroduccion`) como en su targeta en "Fases del projecte". Fase 3 ya dispone de redacción canónica; permanece vacío en Fase 4-7 mientras no exista contenido real.

Cómo se consume:

```php
// fase-1.php / fase-2.php (cabecera interior)
require_once dirname(__DIR__, 3) . '/fases/funciones.php';
$faseIntroduccion = obtenerFasesArquitectura('informatica')[$faseNumero]['descripcio'] ?? '';
```

```php
// fases_projecte.php (targeta de "Fases del projecte")
$fasesProjecte = obtenerFasesProyecto($proyectoAlumno); // ya viene de fases.php
// dentro del foreach:
$descripcioFase = trim((string) ($fase['descripcio'] ?? ''));
```

**Regla**: no hardcodear una segunda descripción de una fase en ningún sitio. Cuando Fase 3 tenga descripción real, escríbela **una sola vez** en `fases.php` y haz que `fase-3.php` la lea igual que `fase-1.php`/`fase-2.php`.

---

## 8. «Fases del projecte»

Archivo: [`inc/paginas/alumnos/fases_projecte.php`](../../inc/paginas/alumnos/fases_projecte.php). Ruta: `/fases-del-projecte`.

No es un menú: es **mapa del recorrido + resumen acumulativo del proyecto + punto de entrada**. Cada targeta de fase distingue tres capas de información, todas reales, nunca inventadas:

| Capa | Contenido | Fuente |
|---|---|---|
| A) Información general de la fase | Título + descripción | `fases.php` (§7) |
| B) Historia/evidencias de ESTE proyecto | Resultados reales en verde, solo si la fase está completada | Fase 1: `fase1ResultadoGrupoTrabajo()`. Fase 2: `fase2ClassificacioObtenirEstat()` + `fase2PropostaObtenirEstat()['pdf']` |
| C) Navegación | CTA «Entrar», ausente si la fase está bloqueada | `fasesEstatAparenca()` |

Tratamiento por estado (función local `fasesProjecteTargeta()` dentro de `fases_projecte.php`):

- **completada**: `.bloc-completat`, evidencias en verde (`.fase-resultat-completat` para texto, `.tasca-recurs-link.tasca-recurs-resultat--completat` para el PDF), CTA `.btn-outline-success`;
- **activa**: `.bloc-activitat`, sin evidencias, CTA `.btn-puig` (outline granate);
- **atención**: `.bloc-atencio`, CTA `.btn-atencio` (solo aplicable hoy a Fase 2);
- **futura/bloqueada**: `.bloc-bloquejat`, sin evidencias, **sin CTA** (a diferencia del sidebar, que sigue siendo navegable — ver [§9](#9-sidebar-fases_navegacionphp)).

Cabecera actual (dentro de la `.card` de contenido, no confundir con el `<h1>` de la página):

```html
<h2 class="h4 mb-2">Fases del projecte</h2>
<p class="text-muted mb-4">{ciclo} {grupo}</p>
```

Sin eyebrow "PROJECTE", sin "El teu projecte" (legacy retirado en esta consolidación). No la reintroduzcas.

---

## 9. Sidebar (`fases_navegacion.php`)

Archivo: [`inc/paginas/alumnos/fases_navegacion.php`](../../inc/paginas/alumnos/fases_navegacion.php). Incluido siempre por `fase_base.php` (`<aside>`), y también directamente por `fases_projecte.php`.

Reglas verificadas:

- **siempre navegable**: no existe ningún interruptor de "modo formulario" que lo bloquee (el antiguo `$fasesNavegacionBloqueada` fue eliminado; el propio comentario del archivo lo deja explícito: "aquest sidebar SEMPRE calcula els estats reals i SEMPRE és navegable");
- **estados siempre reales**: calculados con `fasesEstatAparenca()` (§3), nunca degradados por estar dentro de una tarea;
- **selección y estado independientes** (§3): `class="active"` nunca cambia el color de estado;
- alumno y profesor reutilizan exactamente el mismo render; solo cambia `$hrefFase` (ver §11) y la fuente de `$faseUnoCompletada`/`$faseDosCompletada` (sesión de alumno vs. proyecto completo).

**Cómo integrar una fase nueva sin duplicar el sidebar**: no lo toques. Al añadir `descripcio`/criterio real en `fases.php` y en el helper de estado de la nueva fase, y ampliar `fasesEstatAparenca()` con el nuevo argumento, el sidebar (y el eyebrow, y "Fases del projecte") lo reflejan automáticamente — es exactamente el problema que resuelve tener una única función de apariencia.

---

## 10. Breadcrumbs

Construido íntegramente en [`fase_base.php`](../../inc/paginas/alumnos/informatica/fase_base.php) (variable `$breadcrumbItems`), nunca por cada vista de tarea.

**Alumno**:
- en la vista de fase (sin tarea): **sin breadcrumb** (el sidebar + menú ya dan contexto suficiente);
- dentro de una tarea: `Fases del projecte › Fase N › {Tasca}`.

**Profesor**: el breadcrumb existe TAMBIÉN a nivel de fase (no solo dentro de tarea), porque el profesor necesita recorrer la jerarquía proyecto/alumnado desde cualquier punto:
- vista de fase: `Resum › {nombres del proyecto} › Fase N` (el segmento "Fase N" no es clicable — es la página actual);
- dentro de una tarea: `Resum › {nombres del proyecto} › Fase N › {Tasca}`.

El primer segmento profesor ("Resum") sustituye al antiguo botón global "Tornar al Resum" de `fase-tutor_capcalera.php` — ese botón solo se muestra hoy en `fases-tutor.php` (el listado general de fases, que no pasa por `fase_base.php` y por tanto no tiene breadcrumb propio) mediante el flag `$capcaleraOcultarTornarResum`.

**Qué debe declarar una tarea nueva**: solo `$breadcrumbTasca = 'Nombre de la tarea';` antes de `require fase_base.php`. Todo lo demás (segmentos "Resum"/"Fases del projecte", el nombre del proyecto para profesor, los `href`) lo resuelve `fase_base.php` solo, a partir de `$faseActiva`, `$rolVisualitzacio`, `$proyectoAlumno['id_proyecto']` y (en contexto profesor) `$titolProjecteCapcalera` — ya resuelto por `fase-tutor_capcalera.php`, nunca se vuelve a consultar.

---

## 11. Alumno vs profesor

El profesor **no tiene una segunda implementación visual**. Entra en la misma infraestructura como "visitante con derechos".

Mecanismo real: la variable `$rolVisualitzacio` (`'alumne'` por defecto, `'professor'` cuando el shell contextual del profesorado la fija antes de incluir la infraestructura común). La consultan: `fase_base.php` (breadcrumb, `$hrefFase`), `fases_navegacion.php` (`$hrefFase`), `fases_projecte.php` (`$hrefFase`), y cada `_contingut.php`/`_detall.php` que necesita distinguir "puede escribir" de "solo consulta" (`$esAlumnat = $rolVisualitzacio === 'alumne'`).

La **consulta** del recorrido se autoriza por la asignación real del profesor al grupo y curso (`rel_profesores_grupos`, mediante `fasesResolverContextTutor()`). Eso no concede autoridad formal: validar o ejecutar otra intervención específica exige la relación correspondiente en `rel_proyectos_profesores` y, cuando proceda, ser el tutor formal.

Lo que cambia por rol:

| Aspecto | Alumno | Profesor |
|---|---|---|
| Contexto | `projecte_context.php` (sesión de alumno) | `fasesResolverContextTutor()` (autorización real contra `rel_profesores_grupos`, nunca solo el `proyecto_id` recibido) |
| `$hrefFase` | `$fase['ruta']` (`/fases-del-projecte/fase-N`) | `/projecte/{id}/fases/fase-N` |
| Acciones disponibles | Escribir sus propios datos | Solo intervenciones propias (ej. validar) — nunca escribe `propuesta_url` ni sube el PDF |
| Breadcrumb | Sin nivel de fase | Con nivel de fase (§10) |
| Bloqueo de tarea | Aplica (ej. Fase 2 exige Fase 1 completada) | **No aplica** — el profesor puede consultar cualquier fase/tarea autorizada, esté o no desbloqueada para el alumnado (está revisando, no ejecutando) |

**Archivo de referencia al crear una acción de tutor nueva**: [`fase-2-tutor_accion.php`](../../inc/paginas/profesores/tutor/fase-2-tutor_accion.php) (ruta `tipo=>'api'`, autoriza con `esTutorFormalDelProyecto()`, nunca `esTutorDelProyecto()` si la acción exige autoridad formal única). Para un shell de vista contextual nuevo (fase o tarea), usa como referencia [`fase-2-tutor_proposta.php`](../../inc/paginas/profesores/tutor/fase-2-tutor_proposta.php) (deep-link a una tarea) o [`fase-tutor.php`](../../inc/paginas/profesores/tutor/fase-tutor.php) (acceso general a cualquier fase de un proyecto).

### Revisión formal del tutor

Cuando una tarea requiere revisión formal, la solicitud abierta se representa dentro del paso correspondiente mediante una zona amarilla `.bloc-zona.bloc-zona-atencio`. La acción principal valida el documento. La acción secundaria es una X discreta en la esquina superior derecha que abre una confirmación Bootstrap y permite cerrar únicamente la solicitud abierta sin validar.

Cerrar una solicitud no equivale a rechazar, suspender ni pedir cambios formalmente: solo establece `resuelto_en` en `app.revisiones_solicitudes`, no crea un estado funcional nuevo, no altera la URL ni el documento, no desbloquea el PDF y no envía un correo de rechazo. El alumnado puede continuar trabajando y volver a solicitar revisión posteriormente. Tanto la X como su acción servidor solo están disponibles para quien tenga autoridad formal para intervenir; ocultarla en la vista nunca sustituye el gate servidor.

Referencias reales: Fase 2 · Proposta de projecte ([`fase-2_proposta_detall.php`](../../inc/paginas/alumnos/informatica/fase-2_proposta_detall.php), [`fase-2-tutor_accion.php`](../../inc/paginas/profesores/tutor/fase-2-tutor_accion.php)), Fase 3 · Document funcional ([`fase-3_document_funcional_detall.php`](../../inc/paginas/alumnos/informatica/fase-3_document_funcional_detall.php), [`fase-3-tutor_accion.php`](../../inc/paginas/profesores/tutor/fase-3-tutor_accion.php)) y la primera etapa de Fase 5 ([`fase-5_preparacio_entorn_detall.php`](../../inc/paginas/alumnos/informatica/fase-5_preparacio_entorn_detall.php), [`fase-5-tutor_entorn_accion.php`](../../inc/paginas/profesores/tutor/fase-5-tutor_entorn_accion.php)).

Cabecera contextual del profesorado: [`fase-tutor_capcalera.php`](../../inc/paginas/profesores/tutor/fase-tutor_capcalera.php) — resuelve `$titolProjecteCapcalera` (nombres del alumnado del proyecto) y expone el flag `$capcaleraOcultarTornarResum` (§10).

---

## 12. Routing canónico

### Alumno (`.htaccess` → `main=` → archivo)

| URL | `main=` | Archivo |
|---|---|---|
| `/fases-del-projecte` | `alumne-fases-projecte` | `fases_projecte.php` |
| `/fases-del-projecte/fase-N` (1-7) | `alumne-fase-N` | `fase-N.php` |
| `/fases-del-projecte/fase-1/definir-grup` | `alumne-fase-1-grup-form` | `fase-1_grup_form.php` |
| `/fases-del-projecte/fase-1/compromis` | `alumne-fase-1-compromis-form` | `fase-1_compromis_form.php` |
| `/fases-del-projecte/fase-2/proposta` | `alumne-fase-2-proposta` | `fase-2_proposta.php` |
| `/fases-del-projecte/fase-3/document-funcional` | `alumne-fase-3-document-funcional` | `fase-3_document_funcional.php` |
| `/fases-del-projecte/fase-4/planificacio-temporal` | `alumne-fase-4-planificacio` | `fase-4_planificacio.php` |
| `/fases-del-projecte/fase-4/gestio-projecte` | `alumne-fase-4-gestio` | `fase-4_gestio.php` |
| `/fases-del-projecte/fase-5/preparacio-entorn` | `alumne-fase-5-preparacio-entorn` | `fase-5_preparacio_entorn.php` |
| `/fases-del-projecte/fase-5/repositoris-git` | `alumne-fase-5-repositoris` | `fase-5_repositoris.php` |
| `/fases-del-projecte/fase-5/tecnologies-eines` | `alumne-fase-5-tecnologies-eines` | `fase-5_tecnologies_eines.php` |
| `/fases-del-projecte/fase-5/projecte-en-produccio` | `alumne-fase-5-projecte-produccio` | `fase-5_projecte_produccio.php` |
| `/fases-del-projecte/fase-5/autoavaluacio-final` | `alumne-fase-5-autoavaluacio` | `fase-5_autoavaluacio.php` |
| `/fases-del-projecte/fase-6/document-memoria` | `alumne-fase-6-document-memoria` | `fase-6_document_memoria.php` |
| `/fases-del-projecte/fase-7/presentacio-defensa` | `alumne-fase-7-presentacio-defensa` | `fase-7_presentacio_defensa.php` |
| `/autoseguiment` | `alumne-autoseguiment` | `autoseguiment.php` |
| `/memoria` | `alumne-memoria` | `memoria.php` |

Las acciones de escritura (`*_accion.php`) **no** tienen URL amigable propia — se invocan por `fetch()`/`<form action="/index.php?main=...">` directamente contra su clave `main=` (ver `fase-1_grup_contingut.php`, `fase-2_proposta_detall.php`). Esto es intencionado, no legacy: `main=` sigue existiendo para endpoints internos que nunca se visitan como página.

### Profesor

| URL | `main=` | Archivo |
|---|---|---|
| `/resum` | `resum-tutor` | `resum-tutor.php` |
| `/projecte/{id}/fases` | `fases-tutor` | `fases-tutor.php` → `fases_projecte.php` |
| `/projecte/{id}/fases/fase-N` (1-7) | `fase-tutor` (+ `fase={N}`) | `fase-tutor.php` |
| `/projecte/{id}/fases/fase-1/compromis` | `fase-1-tutor-compromis` | `fase-1-tutor_compromis.php` |
| `/projecte/{id}/fases/fase-2/proposta` | `fase-2-tutor-proposta` | `fase-2-tutor_proposta.php` |
| `/projecte/{id}/fases/fase-3/document-funcional` | `fase-3-tutor-document-funcional` | `fase-3-tutor_document_funcional.php` |
| `/projecte/{id}/fases/fase-4/planificacio-temporal` | `fase-4-tutor-planificacio` | `fase-4-tutor_planificacio.php` |
| `/projecte/{id}/fases/fase-4/gestio-projecte` | `fase-4-tutor-gestio` | `fase-4-tutor_gestio.php` |
| `/projecte/{id}/fases/fase-5/preparacio-entorn` | `fase-5-tutor-preparacio-entorn` | `fase-5-tutor_preparacio_entorn.php` |
| `/projecte/{id}/fases/fase-5/repositoris-git` | `fase-5-tutor-repositoris` | `fase-5-tutor_repositoris.php` |
| `/projecte/{id}/fases/fase-5/tecnologies-eines` | `fase-5-tutor-tecnologies-eines` | `fase-5-tutor_tecnologies_eines.php` |
| `/projecte/{id}/fases/fase-5/projecte-en-produccio` | `fase-5-tutor-projecte-produccio` | `fase-5-tutor_projecte_produccio.php` |
| `/projecte/{id}/fases/fase-5/autoavaluacio-final` | `fase-5-tutor-autoavaluacio` | `fase-5-tutor_autoavaluacio.php` |
| `/projecte/{id}/fases/fase-6/document-memoria` | `fase-6-tutor-document-memoria` | `fase-6-tutor_document_memoria.php` |
| `/projecte/{id}/fases/fase-7/presentacio-defensa` | `fase-7-tutor-presentacio-defensa` | `fase-7-tutor_presentacio_defensa.php` |

Contexto siempre por `{id}` de proyecto — **nunca** nombre de alumno en la URL.

**Al añadir una fase/tarea nueva con URL propia**, toca exactamente dos sitios, siempre en el mismo cambio:
1. `router.php`: añade la clave `main=` → archivo (`'area' => 'alumno'`/`'profesor'`, `'tipo' => 'api'` si es una acción JSON sin layout);
2. `.htaccess`: añade el `RewriteRule` de la URL amigable → `main=` correspondiente.

Las URLs `/fases-del-projecte/fase-{1-7}` y `/projecte/{id}/fases/fase-{1-7}` **ya existen como regla genérica** (`fase-([1-7])`) — Fase 3-7 no necesitan una nueva `RewriteRule` para su landing page, solo para las rutas de tarea que añadan dentro (siguiendo el patrón `fase-N/{slug-tarea}`, nunca un esquema de URL distinto).

Las URLs docentes canónicas no incluyen el rol: `/resum`,
`/seguiment-setmanal`, `/revisio-memoria` y `/projecte/{id}/fases/...`.
Continúan apuntando a los shells y `main` del área `profesor`; el rol y la
relación con el recurso se autorizan en servidor. `/profesor/...` solo existe
como redirección temporal de compatibilidad y no debe producirse en enlaces
nuevos.

---

## 13. Capa PDF (norma obligatoria)

Archivo: [`inc/pdf/funciones.php`](../../inc/pdf/funciones.php). **Ninguna fase futura implementa su propio `move_uploaded_file`, validación MIME/extensión, Ghostscript, nombre temporal o publicación.** Todo PDF definitivo de proyecto pasa por aquí.

Función orquestadora real:

```php
/**
 * @return array{ok:bool, ruta_rel:?string, ruta_abs:?string, optimitzat:bool, error:?string}
 */
function pdfGuardarDefinitiu(
    array $file,              // el propio $_FILES[...]
    string $cursoAcademico,
    string $ciclo,
    int $idProyecto,
    string $nombreArchivo,    // nombre final, ej. 'proposta.pdf'
    int $midaMaxima = PDF_MIDA_MAXIMA_BYTES
): array
```

Ejemplo real de uso (consumidor V2 actual): [`fase-2_accion.php`](../../inc/paginas/alumnos/informatica/fase-2_accion.php), acción `pujar_pdf`:

```php
$resultat = pdfGuardarDefinitiu(
    $file,
    (string) $proyecto['curso_academico'],
    (string) $proyecto['ciclo'],
    $proyectoId,
    'proposta.pdf'
);
if (!$resultat['ok']) { /* $resultat['error'] ya es un mensaje presentable */ }
$rutaRel = $resultat['ruta_rel'] . '?v=' . time();
```

Consumidores actuales (todos vía `pdfGuardarDefinitiu()`, ninguno con lógica propia):

| Consumidor | PDF que guarda | Arquitectura |
|---|---|---|
| `fase-2_accion.php` | `propuesta_pdf` (Proposta de projecte) | V2 |
| `fase-7_presentacio_defensa_accion.php` | `presentacion_pdf` (Presentació de la defensa) | V2 |
| `ficha_proyecto_accion.php` | `funcional_pdf`, `memoria_pdf`, `ruta_ficha_entrega` | Interfaz V1 adaptada al modelo documental canónico |
| `ficha_proyecto_adjunto_accion.php` | Adjuntos tipo `arxiu` en `proyecto_adjuntos` | V1 (legacy, todavía en producción) |
| `ficha_proyecto_defensa_accion.php` | `presentacion_pdf` | V1 adaptada al pipeline PDF común |

**Importante para Fase 3 (Document funcional) y Fase 6 (Memòria)**: las evidencias definitivas tienen como fuentes únicas `proyectos.funcional_pdf` y `proyectos.memoria_pdf`. La ficha V1 superviviente ya lee y escribe esas mismas columnas mediante `pdfGuardarDefinitiu()`. Las antiguas `ruta_funcional`/`ruta_memoria` se retiraron tras comprobar y migrar sus datos; no deben recrearse ni usarse como fallback.

Qué hace la capa por dentro (sin que el consumidor lo reimplemente):

1. **validación real**: extensión, `is_uploaded_file()`, tamaño máximo, MIME real (`finfo`) + cabecera mágica `%PDF-` — nunca solo el nombre `.pdf`;
2. **temporal**: se mueve a un `.upload.tmp` dentro del propio directorio final del proyecto (mismo filesystem, `rename()` real al publicar);
3. **optimización Ghostscript** (`pdfOptimitzar()`), *best effort*, sobre el temporal — nunca sobre el fichero ya publicado. Binario configurable por `PDF_GS_BIN` (por defecto `gs`);
4. **conservación del original**: si Ghostscript falla, no existe, o el resultado es más grande/inválido, se conserva el original válido tal cual (`optimitzat:false`, `ok:true`);
5. **publicación final segura**: solo tras validar+optimizar, el definitivo anterior se mueve a una reserva controlada `.previous.tmp`; el temporal se publica mediante `rename()` y la reserva solo se elimina después del éxito;
6. **recuperación**: si falla la publicación nueva, se restaura el definitivo anterior antes de devolver el error. Una reserva dejada por una interrupción se recupera en el siguiente intento;
7. **política ante error**: si `ok:false`, el consumidor **no debe** escribir ningún path en BD — nunca queda una fila apuntando a un fichero inexistente por una publicación fallida.

Estructura de destino (fija, no la reconstruyas a mano): `uploads/{curso_academico}/{abr_ciclo}/{id_proyecto}/` — resuelta por `pdfResoldreDirectoriProjecte()`, interna a la capa.

---

## 14. CSS: inventario por función

Solo las clases relevantes para construir una fase nueva. **Antes de añadir CSS, haz `grep` de la clase/patrón en `estilos.css` — casi seguro ya existe.**

| Grupo | Clases reales | Dónde se usan | Referencia |
|---|---|---|---|
| **Layout/shell de fase** | `.container-fluid`, `.row.g-4`, `.card.shadow-sm.border-0.rounded-4` | Envoltorio de `fase_base.php` y de `fases_projecte.php` | `fase_base.php` |
| **Eyebrow de cabecera interior** | `.fase-etiqueta`, `.fase-etiqueta--completada`, `.fase-etiqueta--atencio`, `.fase-etiqueta--bloquejada` | `<h1>` de cada `fase-N.php` | `fase_base.php` |
| **Navegación lateral** | `.projecte-fases-nav`, `.projecte-fase-enllac`, `-pendent`, `-completada`, `-atencio`, `.active`, `.projecte-fase-fletxa` | Sidebar | `fases_navegacion.php` |
| **Tarjetas de estado (`.bloc`)** | `.bloc`, `.bloc-contingut`, `.bloc-tipus`, `.bloc-activitat`, `.bloc-completat`, `.bloc-atencio`, `.bloc-bloquejat`, `.bloc-informacio` | Toda targeta de tasca, paso interno o targeta de fase | `fase-1_contingut.php`, `fase-2_proposta_detall.php`, `fases_projecte.php` |
| **Franja interna de un bloque** | `.bloc-zona`, `.bloc-zona-atencio`, `.bloc-zona-titol` | Intervención del tutor dentro del Pas 2 | `fase-2_proposta_detall.php` |
| **Resultados/evidencias** | `.fase-resultat-completat` (texto+icono verde), `.tasca-recurs-link`, `.tasca-recurs-resultat--completat`/`--atencio`/`--activitat` | Evidencia completada, enlace a documento/PDF | `fase-1_contingut.php`, `fase-2_tasques.php`, `fases_projecte.php` |
| **Badges de estado** | `.badge-activitat` (granate, sin equivalente Bootstrap), `text-bg-success`, `text-bg-warning` (Bootstrap) | Pill de estado de una tarea | `fase-2_proposta_detall.php` |
| **Botones** | `.btn-fase` (geometría), `.btn-puig`, `.btn-puig-solid`, `.btn-outline-success`, `.btn-atencio`, `.btn-atencio-solid` | Ver tabla completa en [§6](#6-botones-y-enlaces) | — |
| **Enlaces informativos** | `.link-secundari-puig` | "Volver", "Ver documento" | Varios |
| **Documentos/recursos con estado** | `.tasca-recursos`, `.tasca-recurs-link`, `.tasca-recursos-separador` | Plantillas del Pas 2 | `fase-2_proposta_detall.php` |
| **Breadcrumb** | `.fase-breadcrumb`, `.fase-breadcrumb-separador`, `[aria-current="page"]` | Cabecera interior | `fase_base.php` |
| **Formularios / select completado** | `.form-control.form-completat`, `.form-select.form-completat` | Selects de clasificación una vez cerrados (Pas 1) | `fase-2_proposta_detall.php` |
| **Listas de compromiso/instrucciones** | `.compromis-llista`, `.fase-llista` | Texto del compromiso, instrucciones de un paso | `fase-1_compromis_contingut.php`, `fase-2_proposta_detall.php` |
| **Introducción de fase** | `.fase-introduccio` | Párrafo de `$faseIntroduccion` dentro del `_contingut` | `fase-1_contingut.php`, `fase-2_tasques.php` |

---

## 15. Nomenclatura de archivos

Convención real emergida (no forzada — verificada en los ficheros existentes):

| Sufijo | Responsabilidad real | Ejemplo |
|---|---|---|
| `fase-N.php` | Wrapper delgado de la fase: fija `$faseNumero`, `$faseTitulo`, `$faseIntroduccion` (desde `fases.php`), `$faseContenidoArchivo`, y `require fase_base.php`. Nunca contiene HTML propio. | `fase-1.php`, `fase-2.php` |
| `fase-N_contingut.php` / `fase-N_tasques.php` | Contenido de la vista de FASE: listado de targetas-resumen de sus tareas (o, en Fase 1, las propias cajas de tarea simples). Incluido por `fase_base.php` vía `$faseContenidoArchivo`. | `fase-1_contingut.php`, `fase-2_tasques.php` |
| `fase-N_xxx_form.php` | Wrapper de una TAREA/subpágina: gate de acceso + carga de datos + fija `$breadcrumbTasca` + `require fase_base.php`. Equivalente a `fase-N.php` pero a nivel de tarea. | `fase-1_grup_form.php`, `fase-1_compromis_form.php` |
| `fase-N_xxx.php` (sin `_form`) | Mismo rol que `_form.php` cuando la tarea tiene su propio detalle de varios pasos. | `fase-2_proposta.php` |
| `fase-N_xxx_contingut.php` / `_detall.php` | El HTML real de una tarea/subpágina — nunca lo pinta el wrapper. `_contingut` para una tarea simple, `_detall` cuando hay varios pasos internos. | `fase-1_grup_contingut.php`, `fase-2_proposta_detall.php` |
| `fase-N_xxx_accion.php` | Endpoint de escritura puro (sin `projecte_context.php`, sin render de layout salvo redirect). Gate de arquitectura + autorización + transacción. | `fase-1_grup_accion.php`, `fase-2_accion.php` (esta última es `tipo=>api`, JSON) |
| `fase-N_funcions.php` / `fase-N_xxx_funcions.php` | Helpers puros (sin HTML): criterios de completado, resultados/evidencias reutilizables. Única fuente de verdad de su fase/tarea. | `fase-1_funcions.php`, `fase-2_proposta_funcions.php` |
| `fase-N-tutor.php` / `fase-N-tutor_xxx.php` (con **guion**, no guion bajo, tras `fase-N`) | Shell contextual del profesorado: resuelve `$proyectoAlumno` vía `fasesResolverContextTutor()`, fija `$rolVisualitzacio='professor'`, y reutiliza el `fase-N.php`/`fase-N_xxx.php` real del alumnado. | `fase-tutor.php`, `fase-2-tutor_proposta.php`, `fase-1-tutor_compromis.php` |
| `fase-N-tutor_accion.php` | Acción de escritura exclusiva del profesorado (validar, etc.), `tipo=>api`. | `fase-2-tutor_accion.php` |

**Cuándo crear un archivo nuevo vs. ampliar uno existente**: crea un archivo nuevo por cada TAREA nueva (nunca mezcles dos tareas en un mismo `_contingut`/`_accion`). Amplía el archivo existente cuando añades un PASO dentro de una tarea que ya existe (como los tres pasos de `fase-2_proposta_detall.php`, todos en un único fichero).

---

## 16. Checklist antes de implementar una fase nueva

1. Lee este documento completo y [`arquitectura.md`](arquitectura.md) (sección "Fases y tareas").
2. Inspecciona la fase equivalente más parecida ya implementada (§2): ¿tiene varias tareas simples (Fase 1) o una tarea con pasos (Fase 2)?
3. Añade/actualiza la entrada de la fase en [`fases.php`](../../inc/paginas/alumnos/informatica/fases.php) (`titulo`, `descripcio`, `ruta`, `main`, `archivo`) — nunca la reescribas a mano en otro sitio.
4. Reutiliza [`fase_base.php`](../../inc/paginas/alumnos/informatica/fase_base.php) tal cual: tu `fase-N.php` solo fija variables y hace `require`.
5. No toques [`fases_navegacion.php`](../../inc/paginas/alumnos/fases_navegacion.php) — si tu fase aporta un criterio real de completado, amplía `fasesEstatAparenca()` con un argumento nuevo (§3), nunca un `if` paralelo.
6. Reutiliza los estados existentes (completada/activa/atención/bloqueada) — no inventes un quinto estado sin verificar antes si el problema ya se resuelve con estos cuatro.
7. Reutiliza las tarjetas (`.bloc`) y la jerarquía de §5 — no inventes otro contenedor visual.
8. Reutiliza los botones de §6 (`.btn-fase` + color derivado del estado de SU contenedor).
9. No construyas breadcrumb propio: solo declara `$breadcrumbTasca` si tu archivo es el detalle de una tarea (§10).
10. Si tu tarea sube un PDF definitivo, pasa por `pdfGuardarDefinitiu()` (§13) — nunca reimplementes validación/optimización.
11. Comprueba el resultado tanto en alumno como en profesor (mismo archivo, `$rolVisualitzacio` distinto) — nunca crees una vista paralela para el profesorado.
12. Comprueba que tu targeta aparece correctamente resumida en "Fases del projecte" (§8) — añade su bloque de evidencias en `fasesProjecteTargeta()` si tu fase ya tiene resultados reales que mostrar.
13. Comprueba responsive: las evidencias deben hacer wrap con `d-flex flex-column gap-2` o equivalente, nunca un grid forzado.
14. Ejecuta `php -l` sobre cada archivo tocado.
15. Solo entonces, si de verdad no existe nada reutilizable para tu caso, crea la excepción nueva — y documenta por qué en un comentario junto al código.

**No copiar y pegar infraestructura común dentro de una fase.** `fase_base.php`, `fases_navegacion.php`, `fasesEstatAparenca()`, `pdfGuardarDefinitiu()` se **llaman**, nunca se duplican ni se reimplementan "a medida" dentro de `fase-N_xxx.php`.

---

## 17. Antipatrones / qué no hacer

Verificados contra decisiones reales tomadas (y corregidas) durante esta consolidación:

- **No bloquear el sidebar al entrar en una tarea.** Existió un `$fasesNavegacionBloqueada` que lo hacía; fue eliminado. No lo reintroduzcas.
- **No confundir fase seleccionada con fase activa.** `class="active"` (selección) y el color de estado son dimensiones independientes (§3, §9).
- **No colorear el eyebrow en granate por el simple hecho de estar visitando la fase.** El color del eyebrow deriva de `fasesEstatAparenca()`, nunca de `$faseActiva === $numeroFase`.
- **No duplicar el `<h1>` dentro de una tarea.** `fase_base.php` ya lo pinta con `$faseTitulo`; el `_contingut`/`_detall` de la tarea empieza directamente en su primer `.bloc` o subtítulo.
- **No añadir "Tornar a Fase N"/"Tornar al Resum" si el breadcrumb ya resuelve la navegación.** Ver §10 — el botón global de `fase-tutor_capcalera.php` se ocultó exactamente por esto en las vistas que ya tienen breadcrumb con nivel de fase.
- **No duplicar descripciones de fase.** Fuente única en `fases.php` (§7).
- **No derivar el color de un recurso del estado global de la tarea si pertenece a un paso concreto.** Lección explícita de Fase 2 (§6).
- **No mostrar el PDF definitivo dentro del paso del documento vivo.** El PDF pertenece al paso de entrega definitiva (Pas 3 en Fase 2); el documento vivo (Pas 2) solo muestra su propio enlace mientras no hay PDF.
- **No crear subcajas innecesarias dentro de una targeta.** Usa `.bloc-zona` si algo necesita destacarse dentro del mismo bloque.
- **No crear CSS casi idéntico.** Antes de añadir una regla, `grep` el patrón en `estilos.css` (§14).
- **No reimplementar subida/optimización de PDF.** Capa única en `inc/pdf/funciones.php` (§13).
- **No crear URLs con nombres de alumnos.** El contexto siempre es el `id_proyecto` (§12).
- **No recuperar nomenclatura legacy "El meu projecte" / "El teu projecte".** Retirada explícitamente de la cabecera de "Fases del projecte" (§8).
- **No poner botones sólidos en la pantalla "Fases del projecte".** Es la única pantalla donde el CTA es siempre outline, con independencia del estado (§6, §8) — no generalices esta regla a las targetas-resumen dentro de una fase, que sí usan sólido en su estado activo/atención.

---

## 18. Deuda y discrepancias detectadas (sin corregir)

Detectadas durante la inspección de este documento. **No se han tocado** — quedan anotadas para una revisión futura explícita.

1. **`fase2PropostaObtenirEstat()` devuelve `classe_outline`, pero ningún consumidor lo lee hoy** (verificado por `grep` en todo `inc/paginas`). `fases_projecte.php` recalcula una lógica equivalente (completada→outline-success/atención→btn-atencio/activa→btn-puig) de forma independiente en su función local `fasesProjecteTargeta()`, en parte porque necesita cubrir también Fase 1 y Fase 3-7 (que no tienen ese campo). No es un bug funcional, pero es una fuente potencial de divergencia si algún día cambia una de las dos fórmulas sin tocar la otra.
2. **`$nombreProyecto` queda calculado pero sin usar** en `fases_projecte.php` tras retirar su visualización de la cabecera (tarea de consistencia visual más reciente). No se ha eliminado la línea para no ampliar el alcance de esa tarea.
3. **Documento vivo y evidencia definitiva son conceptualmente distintos** (Pas 2 vs. Pas 3 de Fase 2), pero ambos se guardan en columnas de `proyectos` (`propuesta_url`, `propuesta_pdf`) sin una tabla de versionado — coherente con el resto del modelo de datos actual, solo señalado aquí porque una fase futura con un patrón similar debería replicar exactamente este mismo modelo (dos columnas), no inventar una tabla nueva sin necesidad real.

---

## Si mañana implemento Fase 3, estos son los archivos que debo leer primero

1. Este documento completo (`docs/codex/canon-fases.md`).
2. [`docs/codex/arquitectura.md`](arquitectura.md), sección "Fases y tareas".
3. [`inc/paginas/alumnos/informatica/fases.php`](../../inc/paginas/alumnos/informatica/fases.php) — para ver/editar la entrada de Fase 3.
4. [`inc/paginas/alumnos/informatica/fase_base.php`](../../inc/paginas/alumnos/informatica/fase_base.php) — el shell que vas a reutilizar tal cual.
5. [`inc/paginas/alumnos/informatica/fase-2.php`](../../inc/paginas/alumnos/informatica/fase-2.php) + [`fase-2_tasques.php`](../../inc/paginas/alumnos/informatica/fase-2_tasques.php) + [`fase-2_proposta_detall.php`](../../inc/paginas/alumnos/informatica/fase-2_proposta_detall.php) — la referencia más cercana a "un documento con validación de tutor y PDF definitivo" (previsiblemente el patrón de Document funcional).
6. [`inc/paginas/alumnos/informatica/fase-1_funcions.php`](../../inc/paginas/alumnos/informatica/fase-1_funcions.php) — para ver `fasesEstatAparenca()` antes de ampliarla.
7. [`inc/pdf/funciones.php`](../../inc/pdf/funciones.php) — si tu fase sube un PDF definitivo.
8. [`inc/paginas/alumnos/fases_projecte.php`](../../inc/paginas/alumnos/fases_projecte.php) — para saber dónde añadir las evidencias de tu fase en el resumen.
9. `.htaccess` + `inc/router.php` — solo si tu fase necesita una URL de tarea nueva.
