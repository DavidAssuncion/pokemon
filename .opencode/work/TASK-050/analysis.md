# TASK-050 — Análisis

- **Analista:** `analyst` (sesión `ses_e15921c88ffeJrB7Xjou1uq5xR`).
- **Fecha:** 2026-11-17.
- **Estado:** completado. Sin archivos modificados por el analista.

## Resultado

Análisis completado con lectura directa de código. **No se ha modificado ningún archivo.**
Los tres bloques son implementables íntegramente en Blade/Tailwind/Alpine: no hacen falta
cambios de backend, contratos, rutas ni migraciones. Los endpoints y payloads actuales
cubren todo lo necesario.

## Resumen

1. **Gimnasio / etapas:** hoy son tarjetas apiladas en vertical (`space-y-3`), 4 etapas
   generadas siempre por bucle 1..4, con el estado (`superada`/`actual`/`bloqueada`)
   calculado en cliente a partir de `gym.etapa_actual`, y el botón "¡Combatir!" embebido
   en la fila de la etapa actual. Pasar a rejilla de 4 columnas (líder a la derecha por
   orden natural 4º) es viable sin backend. Requiere ajustar la estructura interna de
   cada tarjeta para que el botón y el avatar no se salgan.
2. **Modal de gimnasio:** sigue en `max-w-md` porque TASK-049 lo dejó explícitamente
   fuera de alcance. Cambiar solo el ancho no basta: el `@include` de `leyenda-ivs-evs`
   va con valores por defecto (1 columna, no colapsable), así que un modal ancho
   mostraría tablas de ~1.100 px y una leyenda larga dentro de un scroll de `70vh`.
   Conviene replicar el patrón flex/scroll de TASK-049 y pasar
   `columnas=3, colapsable=true`.
3. **Equipos:** la tarjeta tiene header + grid de 3 slots con controles inline siempre
   visibles; todo el estado vive en un único `x-data="favoritosApp()"`. El colapso es un
   mapa `{teamId: bool}` y el modal de edición puede reutilizar `<x-ui.modal>`, que ya
   aporta pila de modales, foco, Escape por tope y bloqueo de scroll del body. La badge
   "Composición" tiene un único consumidor y se puede eliminar solo en UI.

## Requisitos

- **Funcionales:**
  - R1. Ficha de gimnasio: las 4 etapas se muestran como columnas en escritorio, con
    "Líder" siempre en la columna derecha.
  - R2. Modal "Configurar Formación" de gimnasio más ancho, manteniendo el combate y la
    comparación de PC funcionando.
  - R3. `/equipos`: tarjetas colapsables (colapsadas por defecto, varias abiertas a la vez).
  - R4. Header de cada tarjeta con 3 iconos de Pokémon (slots 1-3) para identificación rápida.
  - R5. Eliminar la badge "Composición" de la tarjeta.
  - R6. Icono de editar en el header que abre un modal con formación
    (vanguardia/retaguardia), postura (atacar/guardia/cubrir) y objeto por slot,
    incluyendo slots vacíos.
- **No funcionales:**
  - Sin cambios de backend, rutas, contratos ni dependencias.
  - Sin tests (fuera de alcance por política del proyecto).
  - No tocar los cambios locales de TASK-049 (`habitats/partials/modal-formacion.blade.php`,
    `partials/leyenda-ivs-evs.blade.php`).
  - Frontend mínimo: la vista solo presenta datos y persiste elecciones; no replica
    reglas de negocio.
  - Accesibilidad: teclado, `aria-expanded`, `aria-labelledby`, foco en modales,
    contraste por tokens.
- **Criterios de aceptación:**
  - En escritorio se ven 4 etapas en una fila, con "Líder" a la derecha; en móvil se
    apilan; el botón "¡Combatir!" sigue accesible en la etapa actual.
  - El modal de gimnasio se abre ancho (≥`max-w-4xl`) sin desbordar en 320 px de ancho
    y con scroll interno.
  - Al entrar en `/equipos`, todas las tarjetas están colapsadas; se pueden abrir varias
    simultáneamente.
  - El header muestra 3 iconos con fallback `0.webp` y placeholder para slots vacíos.
  - No aparece "Composición" en ninguna vista.
  - El modal de edición guarda formación, posturas y objetos con los 3 endpoints
    existentes y refleja los valores persistidos al reabrir.
- **Ambigüedades:**
  - R1: ¿"columnas" implica 4 columnas en escritorio o 2×2? Se recomienda 4 (con líder 4º).
  - R1: ¿se puede ampliar el contenedor de la página (`max-w-4xl`) para dar aire a 4
    columnas? Es una decisión de diseño.
  - R6: ¿un único botón "Guardar" o guardado por sección? Se proponen ambas opciones.
  - R4: ¿los iconos son clicables o solo decorativos dentro del botón de expandir?

## Alcance afectado

- **Módulos:** Gimnasios (presentación), Equipos (presentación), partials compartidos de
  presentación.
- **Archivos (solo lectura hoy; a modificar en implementación):**
  - `resources/views/gimnasios/show.blade.php` (etapas + modal).
  - `resources/views/equipos/index.blade.php` (estado Alpine y funciones).
  - `resources/views/equipos/partials/panel-equipos.blade.php` (tarjeta).
  - Nuevo: `resources/views/equipos/partials/modal-editar-equipo.blade.php`.
  - Referencia sin tocar: `resources/views/habitats/partials/modal-formacion.blade.php`,
    `resources/views/partials/leyenda-ivs-evs.blade.php`.
- **Tablas/contratos:** ninguno nuevo. Se consumen `teams.formacion`, `teams.posturas`,
  `reclutados.obj_equipados`.
- **Vistas/endpoints:** `GET /api/gimnasios/{gym}`, `POST /api/gimnasios/{gym}/combatir`,
  `GET /api/gimnasios/{gym}/pc`, `PATCH /teams/{team}/formacion`,
  `PATCH /teams/{team}/posturas`, `PATCH /teams/{team}/items`.

## Evidencia

- `resources/views/gimnasios/show.blade.php:12` — contenedor de página `max-w-4xl mx-auto space-y-6`.
- `resources/views/gimnasios/show.blade.php:87` — bloque etapas: `<div class="space-y-3">` (apilado vertical).
- `resources/views/gimnasios/show.blade.php:88` — `x-for="(etapa, idx) in gym.etapas"`; `idx` declarado y no usado.
- `resources/views/gimnasios/show.blade.php:89-92` — tarjeta por etapa con borde/ring si es la actual.
- `resources/views/gimnasios/show.blade.php:93` — fila interna `px-4 py-3 flex items-center justify-between gap-3`.
- `resources/views/gimnasios/show.blade.php:96-114` — tres bloques `x-if` de icono de estado.
- `resources/views/gimnasios/show.blade.php:116-117` — nombre y descripción de etapa.
- `resources/views/gimnasios/show.blade.php:120-138` — avatar y botón "¡Combatir!" dentro de la misma fila, `@click="openCombatPopup()"`, `:disabled="combating"`.
- `resources/views/gimnasios/show.blade.php:148-245` — popup de formación: `max-w-md` (153), cuerpo scroll `max-h-[70vh]` (160), includes sin parámetros (221-222), botones fuera del scroll (226-242).
- `resources/views/gimnasios/show.blade.php:286-302` — `etapaEstado()` y `etapaDescripcion()`; estado derivado de `gym.etapa_actual`.
- `resources/views/gimnasios/show.blade.php:354-363` — `openCombatPopup()` solo si `gym.estado === 'disponible'`; carga comparación PC.
- `resources/views/gimnasios/show.blade.php:394-425` — `confirmarCombate()` → `POST /api/gimnasios/{slug}/combatir` con `{team_id, formacion}`.
- `src/Gimnasios/App/ObtenerGimnasioDetalle.php:38-44` — siempre 4 etapas (bucle `1..4`), nombres desde el dominio; orden de inserción 1→4.
- `src/Gimnasios/App/ObtenerGimnasioDetalle.php:56-59` — `etapa_actual = 5` si completado; estado `completado|bloqueado|disponible`.
- `src/Gimnasios/Domain/Gimnasio.php:32-38` — nombres `Entrenador 1..3`, `Líder` (etapa 4).
- `src/Gimnasios/Domain/DataTransferObjects/DetalleGimnasio.php:44-60` — `toArray()` con `etapas` en el shape `list<{etapa,nombre}>`.
- `src/Gimnasios/Domain/Collections/EtapaGimnasioCollection.php:19-24` — `toArray()` por `map`, conserva orden.
- `resources/views/habitats/partials/modal-formacion.blade.php:5` — patrón ancho TASK-049: `flex max-h-[90vh] w-full max-w-6xl flex-col rounded-panel`.
- `resources/views/habitats/partials/modal-formacion.blade.php:6-13` — cabecera `shrink-0` + cuerpo `min-h-0 flex-1 overflow-y-auto overscroll-contain`.
- `resources/views/habitats/partials/modal-formacion.blade.php:45` — leyenda con `['columnas' => 3, 'colapsable' => true]`.
- `resources/views/habitats/partials/modal-formacion.blade.php:49` — footer `shrink-0 border-t ... sm:flex-row sm:justify-end`.
- `resources/views/partials/leyenda-ivs-evs.blade.php:2` — defaults `columnas=1`, `colapsable=false`.
- `resources/views/partials/leyenda-ivs-evs.blade.php:100` — rejilla `xl:grid-cols-3` condicionada a `$columnas >= 3` (breakpoint por viewport).
- `resources/views/partials/comparacion-pc.blade.php:2-8` — bloque de ancho fluido, sin dependencia de contenedor.
- `resources/views/equipos/partials/panel-equipos.blade.php:43-49` — tarjeta por equipo; borde `selectedTeamId` (estado muerto, ver deuda).
- `resources/views/equipos/partials/panel-equipos.blade.php:51-87` — header: nombre (botón renombrar), badges INVÁLIDO / exploración / composición, botón eliminar.
- `resources/views/equipos/partials/panel-equipos.blade.php:70-76` — render de `composicionBadge(team)`.
- `resources/views/equipos/partials/panel-equipos.blade.php:89-187` — cuerpo: `grid grid-cols-3` con controles inline por slot.
- `resources/views/equipos/partials/panel-equipos.blade.php:124-149` — botones formación vanguardia/retaguardia.
- `resources/views/equipos/partials/panel-equipos.blade.php:150-174` — selects de postura y objeto (objeto llama a `guardarItemSlot`).
- `resources/views/equipos/partials/panel-equipos.blade.php:189-213` — botones "Guardar formación"/"Guardar posturas" y mensajes.
- `resources/views/equipos/index.blade.php:71-186` — estado `favoritosApp()`: `teams`, drafts, `selectedTeamId: null` (108), modales existentes.
- `resources/views/equipos/index.blade.php:85-101` — drafts `formacionDraft`, `posturasDraft`, `itemsDraft` y flags `*SavingTeamId`, `*SaveError/Success`.
- `resources/views/equipos/index.blade.php:58-64` — includes de modales dentro del `x-data` raíz.
- `resources/views/equipos/index.blade.php:248-256` — `enExploracion()`, `teamEnExploracion()`.
- `resources/views/equipos/index.blade.php:285-291` — `nombreFormateado()`.
- `resources/views/equipos/index.blade.php:294-298` — `iconoDe()`.
- `resources/views/equipos/index.blade.php:334-353` — `onSpriteError()` (shiny → normal → 0.webp → ocultar).
- `resources/views/equipos/index.blade.php:1368-1371` — `getMember(team, slot)` devuelve `member.reclutado` o `null`.
- `resources/views/equipos/index.blade.php:1374-1378` — `reclutadoDeSlot()` prioriza el reclutado enriquecido.
- `resources/views/equipos/index.blade.php:1385-1398` — `composicionBadge()`.
- `resources/views/equipos/index.blade.php:1701-1712` — `formacionDe`, `setFormacionSlot`.
- `resources/views/equipos/index.blade.php:1714-1759` — `guardarFormacion`: payload solo slots con miembro, PATCH y default `'vanguardia'`; alerta si no hay slots.
- `resources/views/equipos/index.blade.php:1763-1812` — `itemDe`, `guardarItemSlot` (PATCH de un slot; actualiza `member.obj_equipados`).
- `resources/views/equipos/index.blade.php:1817-1878` — `posturaDe`, `setPosturaSlot`, `guardarPosturas`.
- `resources/views/equipos/index.blade.php:849-866` — `openMemberDetail()` → `openDetail()`.
- `app/Http/Controllers/PlayerController.php:95-113` — shape de `teams` (id, name, formacion, posturas, members[id,team_id,pokemon_id,slot,behavior,reclutado], sinergia, sinergia_nombre); miembros ordenados por slot (78).
- `app/Http/Controllers/TeamController.php:167-187` — `updateFormacion`: valida `formacion.*` ∈ {vanguardia, retaguardia}; reemplaza el mapa completo.
- `app/Http/Controllers/TeamController.php:194-213` — `updatePosturas`: `posturas.*` ∈ {atacar, guardia, cubrir}; reemplaza.
- `app/Http/Controllers/TeamController.php:221-244` — `updateItems`: `items.*` ∈ catálogo o `''`; merges por slot y devuelve mapa completo.
- `src/Equipos/App/ActualizarFormacionEquipo.php:18-21` — `update(['formacion' => $formacion])` (reemplazo total).
- `src/Equipos/App/ActualizarPosturasEquipo.php:18-21` — idem posturas.
- `src/Equipos/App/ActualizarObjetosEquipo.php:31-58` — solo actualiza slots presentes y devuelve el mapa completo.
- `resources/views/components/ui/modal.blade.php:33-67` — modal compartido con `$store.modales`, `aria-modal`, Escape por tope, `trampaDeFoco`, `max-h-[85vh] overflow-y-auto`.
- `resources/js/stores/comun.js:93-160` — store `modales` (pila, foco previo) y `trampaDeFoco`.
- `resources/views/layouts/app.blade.php:20` — `overflow-hidden` del body cuando `$store.modales.pila.length > 0`.
- `resources/views/layouts/app.blade.php:12` — `[x-cloak] { display: none !important; }`.
- `resources/css/components.css:60-77` — `focus-ring`, `card-surface`, `scrim`.
- `resources/css/tokens.css:59-62` — radios `control|card|panel`; `73-77` tamaños de sprite (`inline` = 2rem).
- `resources/views/components/sprite.blade.php:23-31` — props `size`, `decorative`, `wrapperClass`; atributos `x-bind:*` se aplican al `<img>`.
- `resources/views/equipos/partials/modal-reemplazo.blade.php:3-5` — patrón de modal plano `fixed inset-0 z-50` + `bg-scrim` + `@keydown.escape.window`.
- `resources/views/equipos/partials/modal-eliminar-equipo.blade.php:3-5` — mismo patrón, `max-w-sm`.
- `app/Support/ReclutadoSerializer.php:55-62` — `reclutado` serializado incluye `nombre`, `pokemon_id`, `es_shiny`, `obj_equipados`.

## Dependencias

- **Alpine 3.16** (`package.json`), sin `@alpinejs/collapse` → usar `x-show` + `x-transition`, no `x-collapse`.
- **Tailwind 4** con tokens en `@theme static` (`tokens.css`): `bg-scrim` se genera desde
  `--color-scrim`; `scrim` existe como clase de componente. Convive con `.scrim`
  (gimnasio) y `bg-scrim` (equipos).
- **`<x-ui.modal>`** disponible globalmente (store registrado en
  `resources/js/bootstrap.js:17`); requisito para el modal de edición.
- **`<x-sprite>`** para iconos del header y del modal.
- **Catálogo de objetos** ya inyectado a la vista (`$objetos` vía
  `EquiposVistaPresenter`), sin cambios.
- **Contratos existentes:** los 3 endpoints PATCH cubren formación, posturas e items.
  `formacion`/`posturas` reemplazan el mapa completo; `items` hace merge por slot y
  devuelve todos.
- **Cambios locales TASK-049** en `leyenda-ivs-evs.blade.php`: el partial ahora es
  parametrizable (`$columnas`, `$colapsable`) y retrocompatible con los defaults; el
  modal de gimnasio puede beneficiarse sin tocar el archivo.

## Duplicidades y deuda técnica

- **`selectedTeamId` muerto** en `/equipos`: solo se declara (`index.blade.php:108`) y se
  usa para el borde de la tarjeta (`panel-equipos.blade.php:46-48`), pero nunca se asigna
  en esta vista. Con el colapso conviene eliminarlo o reutilizarlo; no confundir con
  `selectedTeamId` del modal de gimnasio (otra vista, otro `x-data`).
- **Lógica de payload duplicada**: `guardarFormacion` (1714-1759) y `guardarPosturas`
  (1830-1878) repiten el mismo patrón de construcción de mapa por slots; el modal de
  edición puede extraer `formacionCompletaDe(team)` / `posturasCompletasDe(team)`
  reutilizadas por ambas.
- **Dos familias de modales**: modales planos (`modal-*` de equipos, popup de gimnasio)
  vs `<x-ui.modal>` con pila/foco. La nueva funcionalidad debe preferir `<x-ui.modal>`;
  migrar las existentes queda fuera de alcance.
- **`etapaDescripcion`/`etapaEstado`** asumen `gym.etapas` ordenado; no hay `sort` defensivo.
- **Estilos muertos/legacy** en `panel-equipos.blade.php:154` y `166` (clases repetidas
  `border-line-strong bg-surface text-ink` duplicadas en el `class`).
- **`updateMemberRole`/`rolInicialDe`** marcados DEPRECATED en `index.blade.php:1663-1696`;
  no deben reutilizarse para formación.
- **`alert()`/`prompt()`** en el flujo de equipos (`startRename`, `guardarFormacion`)
  rompen el patrón de feedback inline; no es necesario tocarlo en esta tarea.

## Riesgos

- **[medio] Rejilla de 4 etapas en `max-w-4xl`**: 896 px / 4 ≈ 206 px por tarjeta; con
  `p-4` quedan ~174 px útiles y el botón "¡Combatir!" (~118 px) más el avatar (36 px) no
  caben en la misma fila. Impacto: texto apretado y posible desbordamiento. Mitigación:
  tarjeta en columna (icono+nombre arriba, avatar y botón en footer, botón `w-full` en
  `lg`), o ampliar el contenedor a `max-w-6xl` (decisión de diseño).
- **[medio] Modal ancho sin parametrizar la leyenda**: pasar a `max-w-6xl` dejando
  `leyenda` en 1 columna produce tablas de ~1.100 px y una leyenda muy larga. Impacto:
  legibilidad y scroll. Mitigación: incluir con `['columnas' => 3, 'colapsable' => true]`
  (mismo patrón que TASK-049).
- **[medio] Guardado parcial en el modal de edición** (3 endpoints, sin transacción): si
  falla el segundo PATCH, el primero ya está persistido. Impacto: estado inconsistente
  entre secciones. Mitigación: aplicar la respuesta de cada endpoint sección a sección,
  abortar en el primer error, mostrar qué sección falló y no cerrar el modal.
- **[medio] `formacion`/`posturas` vacías borran datos**: si se envía `{formacion: {}}`
  el backend hace `update(['formacion' => []])` y se pierde la configuración. Impacto:
  pérdida silenciosa al guardar un equipo sin miembros. Mitigación: no enviar payloads
  vacíos y deshabilitar "Guardar" sin miembros.
- **[bajo] Equipos con <3 miembros**: payloads con solo los slots existentes; el backend
  lo acepta. Impacto: formación/postura de slots futuros no existe. Mitigación:
  documentar que el mapa es parcial y el front rellena default en lectura.
- **[bajo] Miembros en exploración**: no hay bloqueo backend para formación/posturas/items
  (`ActualizarFormacionEquipo`, `ActualizarObjetosEquipo` no lo comprueban). Impacto:
  editar un miembro explorando es legal hoy. Mitigación: permitirlo y mostrar el aviso
  ya existente en el header; no añadir reglas nuevas en frontend.
- **[bajo] Miembro con `reclutado` null** (dato huérfano): `getMember` devuelve `null` y el
  slot se pinta como vacío mientras `team.members.length` cuenta el miembro. Impacto:
  inconsistencia visual. Mitigación: usar `getMember` también para calcular ocupación
  visible.
- **[bajo] Doble apertura de modales**: los modales planos de equipos no usan la pila; el
  nuevo modal sí. Si ambos se abren, comparten `z-50` y Escape cerraría ambos. Impacto:
  solapamiento. Mitigación: garantizar exclusividad por interacción (editar solo desde
  header; detalle solo desde sprite) y valorar `capa="z-[60]"` si conviven.
- **[bajo] Foco al cerrar el modal de edición**: `<x-ui.modal>` devuelve el foco al
  elemento previo; si ese botón estaba dentro de una tarjeta que se colapsó, el foco se
  pierde. Mitigación: no colapsar al abrir el modal y no tocar el colapso mientras está
  abierto.
- **[bajo] Accesibilidad del header con iconos**: 3 imágenes dentro de un botón pueden
  generar una etiqueta larga. Mitigación: `aria-expanded` + `aria-controls` en el botón,
  iconos con `alt` corto o decorativos y `title` por slot.
- **[bajo] Responsive del modal de edición**: selects de objeto con nombres largos en
  pantallas pequeñas. Mitigación: `grid grid-cols-1 sm:grid-cols-3` para slots y `w-full`
  en controles.
- **[bajo] `xl:grid-cols-3` depende del viewport**, no del contenedor: con modal
  `max-w-6xl` en una pantalla de 1.280-1.536 px la rejilla de 3 se activa aunque el modal
  sea más estrecho. Impacto: tablas de 320 px mínimo dentro de ~350 px. Aceptable (igual
  que TASK-049).
- **[bajo] Regresión TASK-049**: cualquier cambio debe limitarse a `gimnasios/show.blade.php`
  y a los archivos de equipos; no tocar los dos archivos con cambios locales.

## Opciones

1. **Cambios mínimos**: rejilla de etapas + ancho del modal sin parametrizar + colapso con
   `x-show` y modal de edición con acciones separadas. Menor esfuerzo, peor resultado
   visual en el modal ancho y más clics.
2. **Alineado con TASK-049 (recomendado)**: rejilla 4 columnas con tarjeta en columna,
   modal con patrón flex/scroll `max-w-6xl` + leyenda `columnas=3, colapsable=true`,
   colapso con `x-show`/`x-transition`, modal de edición sobre `<x-ui.modal>` con un único
   "Guardar cambios" secuencial y feedback por sección.
3. **Modal de edición como tres formularios independientes**: cada sección con su botón
   (reutiliza tal cual `guardarFormacion`, `guardarPosturas`, `guardarItemSlot`). Menos
   riesgo de guardado parcial, más fricción y UI más alta.
4. **Migrar todos los modales de equipos a `<x-ui.modal>`**: mejor accesibilidad global,
   pero amplía el alcance y el riesgo de regresión. No recomendado en esta tarea.

## Propuesta de implementación

### Bloque 1 — `resources/views/gimnasios/show.blade.php` (etapas en columnas)

Sustituir el contenedor de `:87` y reestructurar la tarjeta de `:89-140`:

- Contenedor: `<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" role="list">`.
- Tarjeta: `<article role="listitem" class="card-surface flex h-full flex-col gap-3 p-4" :class="...ring...">`.
- Cabecera de tarjeta: icono de estado (28 px) + `<p :id="'etapa-'+etapa.etapa+'-nombre'" class="text-sm font-semibold text-ink" x-text="etapa.nombre">`.
- Descripción: `<p class="text-xs text-ink-muted min-h-[2.5rem]" x-text="etapaDescripcion(etapa.etapa)">` (altura mínima para alinear columnas).
- Footer con `mt-auto flex items-center justify-between gap-2`: avatar 36 px y, si es la
  etapa actual y `gym.estado === 'disponible'`, `<button class="w-full rounded-control bg-danger px-3 py-2 text-sm font-bold ...">¡Combatir!</button>`.
- Líder a la derecha: se garantiza por orden del backend (etapa 4 en la 4.ª posición de la
  rejilla `lg`). Sin `order-*` salvo que se quiera blindar; si se blinda,
  `:class="idx === 3 ? 'lg:order-4' : ''"` (presentacional, no regla de negocio).
- Móvil: 1 columna (mismo orden actual); `sm`: 2 columnas (líder abajo-derecha); `lg`: 4 columnas.
- Opcional: ampliar `:12` a `max-w-5xl`/`max-w-6xl` para que 4 columnas respiren
  (decisión abierta).

### Bloque 2 — `resources/views/gimnasios/show.blade.php` (modal de formación)

Replicar el patrón de TASK-049 sin tocar el partial de hábitats:

- Panel (`:153`): `relative flex max-h-[90vh] w-full max-w-6xl flex-col rounded-panel bg-surface-raised shadow-modal`.
- Cabecera `shrink-0 px-6 pt-6` con título y descripción actuales.
- Cuerpo: `min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4` (sustituye
  `max-h-[70vh] ... pr-1`), conteniendo error, selección de equipo, formación,
  `@include('partials.comparacion-pc')` y
  `@include('partials.leyenda-ivs-evs', ['columnas' => 3, 'colapsable' => true])`.
- Footer: `flex shrink-0 flex-col gap-3 border-t border-line px-6 py-4 sm:flex-row sm:justify-end`,
  con Cancelar y ¡Combatir! (mismos handlers y estados).
- Riesgo controlado: `comparacion-pc` ocupará todo el ancho; si se ve vacío, envolverlo
  en `max-w-2xl` (solo presentación).

### Bloque 3 — `/equipos`

**`resources/views/equipos/index.blade.php` (estado y funciones)**

- Añadir a `favoritosApp()`:
  - `equiposAbiertos: {}`, `toggleEquipo(team)`, `equipoAbierto(team)`.
  - `equipoEnEdicion: null`, `showTeamEditModal: false`, `edicionGuardando: false`,
    `edicionError: ''`, `edicionFeedback: ''`.
  - `abrirEdicionEquipo(team)`: guarda el equipo, limpia drafts de ese equipo, limpia
    mensajes, abre.
  - `cerrarEdicionEquipo()`: cierra y limpia estado.
  - `formacionCompletaDe(team)` y `posturasCompletasDe(team)` extraídas de
    `guardarFormacion`/`guardarPosturas` (sin cambiar el payload).
  - `itemsCompletosDe(team)` con solo slots con miembro.
  - `guardarEdicionEquipo()`: `PATCH formacion` → `PATCH posturas` → `PATCH items` en
    secuencia; si un paso falla, para, muestra `edicionError` con la sección y no cierra;
    si todo va bien, actualiza `team.formacion`, `team.posturas` y `member.obj_equipados`
    con las respuestas.
- Eliminar `composicionBadge()` (1385-1398) y el estado muerto `selectedTeamId` si se
  confirma que no se usará para la tarjeta.
- Reutilizar `nombreFormateado`, `iconoDe`, `onSpriteError`, `getMember`, `enExploracion`.

**`resources/views/equipos/partials/panel-equipos.blade.php` (tarjeta)**

- Header como botón de expansión:
  `<button type="button" @click="toggleEquipo(team)" :aria-expanded="equipoAbierto(team) ? 'true' : 'false'" :aria-controls="'team-panel-'+team.id" class="...focus-ring">`
  con `sprite-stack` de 3 `<x-sprite size="inline">` (`getMember(team, slot)`) y
  placeholder `<span class="flex h-8 w-8 items-center justify-center rounded-full border border-dashed border-line-strong text-ink-subtle" aria-hidden="true">+</span>`
  para slots vacíos.
- Mantener nombre, badge INVÁLIDO y badge de exploración; eliminar el bloque
  `composicionBadge` (70-76).
- Icono editar en el header:
  `<button @click="abrirEdicionEquipo(team)" :aria-label="'Editar equipo '+team.name" class="..."><svg .../></button>`,
  junto al de eliminar.
- Cuerpo:
  `<div :id="'team-panel-'+team.id" x-show="equipoAbierto(team)" x-cloak x-transition.opacity.duration.150ms class="p-3">`
  con el grid de 3 slots actual (sprites, nombre, quitar miembro). **Quitar del cuerpo**
  los botones de formación, los selects de postura/objeto y los botones "Guardar
  formación/posturas" (se mueven al modal).
- Opcional: mostrar en el cuerpo un resumen read-only (formación/postura/objeto por slot)
  para no perder información visible.

**Nuevo `resources/views/equipos/partials/modal-editar-equipo.blade.php`**

- `<x-ui.modal id="equipo-editar" titulo="Editar equipo" abierto="showTeamEditModal" cerrar="cerrarEdicionEquipo()" tamano="max-w-2xl">`.
- Nombre del equipo en el cuerpo (`equipoEnEdicion?.name`).
- Para `slot in [1,2,3]`: tarjeta con sprite + nombre o "Slot vacío"; si hay miembro:
  - Formación: dos botones `aria-pressed` que llaman
    `setFormacionSlot(team, slot, 'vanguardia'|'retaguardia')` y leen `formacionDe`.
  - Postura: tres botones o `<select :value="posturaDe(...)" @change="setPosturaSlot(...)">`
    con Atacar/Guardia/Cubrir.
  - Objeto: `<select :value="itemDe(team, slot)" @change="itemsDraft[team.id][slot] = $event.target.value">`
    con `@foreach($objetos as $clave => $nombre)` (draft local, sin PATCH inmediato).
  - Slots vacíos: controles deshabilitados con `title="Slot vacío"`.
- Footer: Cancelar + "Guardar cambios" (`guardarEdicionEquipo()`),
  `:disabled="edicionGuardando || (equipoEnEdicion?.members?.length ?? 0) === 0"`, con
  `edicionError` en `role="alert"` y `edicionFeedback` en `role="status"`.
- Incluir el partial en `equipos/index.blade.php` junto a los demás modales (líneas 58-64).

**`resources/views/equipos/partials/modal-eliminar-equipo.blade.php` / `modal-reemplazo.blade.php`:**
sin cambios.

### Contratos exactos (confirmados)

- `PATCH /teams/{team}/formacion` → `{ "formacion": { "1": "vanguardia", "2": "retaguardia", "3": "vanguardia" } }`;
  validación `formacion.* ∈ {vanguardia, retaguardia}`; reemplaza el mapa completo;
  respuesta `{team:{id, formacion}}`.
- `PATCH /teams/{team}/posturas` → `{ "posturas": { "1": "atacar", "2": "guardia", "3": "cubrir" } }`;
  validación `posturas.* ∈ {atacar, guardia, cubrir}`; reemplaza; respuesta
  `{team:{id, posturas}}`.
- `PATCH /teams/{team}/items` → `{ "items": { "1": "life_orb", "2": "", "3": "leftovers" } }`;
  validación `items.* ∈ catálogo ∪ {''}`; merge por slot; respuesta `{team:{id, items}}`
  con el mapa completo.
- `GET /api/gimnasios/{gym}` → `{slug, medalla, tipo, tipo_nombre, tipo_slug, tipo_medalla, tipo_color, nivel_minimo, nivel_jugador, etapa_actual, estado, etapas:[{etapa,nombre}]}`.
- `GET /api/gimnasios/{gym}/pc?team_id=` → comparación de PC (consumida por
  `comparadorPcState`).
- `POST /api/gimnasios/{gym}/combatir` → `{team_id, formacion:{slot:'vanguardia'|'retaguardia'}}`.
- Shape de `teams` en `/equipos`:
  `[{id, name, formacion, posturas, members:[{id, team_id, pokemon_id, slot, behavior, reclutado}], sinergia, sinergia_nombre}]`.

## Criterios de aceptación verificables

1. `/gimnasios/{slug}` en ≥1024 px muestra 4 tarjetas en una fila con "Entrenador 1..3"
   y "Líder" en la última columna; en <640 px se ven apiladas en orden.
2. La tarjeta de la etapa actual conserva `ring-1 ring-primary` y el botón "¡Combatir!";
   pulsarlo abre el modal y el combate sigue funcionando.
3. El modal de gimnasio ocupa `max-w-6xl`, con cabecera y footer fijos y cuerpo con
   scroll; a 320 px no hay desbordamiento horizontal.
4. La leyenda IV/EV del modal de gimnasio aparece colapsada y con 3 columnas en `xl`;
   `comparacion-pc` sigue mostrando selección/carga/veredicto.
5. `/equipos` carga con todas las tarjetas colapsadas; abrir una no cierra las demás.
6. El header muestra 3 iconos (o placeholder en slots vacíos) y no muestra "Composición"
   en ningún equipo.
7. El icono de editar abre un modal con los 3 slots (incluidos vacíos), con formación,
   postura y objeto por slot.
8. "Guardar cambios" persiste y, tras recargar, los valores se mantienen; un fallo
   muestra error de la sección y no cierra el modal.
9. Editar un equipo con 1-2 miembros no genera errores 422 ni borra datos.
10. `git status` no muestra cambios en `resources/views/habitats/partials/modal-formacion.blade.php`
    ni en `resources/views/partials/leyenda-ivs-evs.blade.php` respecto al estado previo.

## Decisiones

- **Backend:** no se requiere ningún cambio. `formacion` y `posturas` reemplazan el mapa;
  `items` hace merge y devuelve el estado completo. No se eliminan `sinergia`/`sinergia_nombre`
  del payload (no es necesario y sería cambio de backend).
- **Badge "Composición":** eliminación solo de UI (función `composicionBadge` y bloque
  `x-if`); no hay otros consumidores en `resources/`, `app/` ni `src/`.
- **Modal de edición:** reutilizar `<x-ui.modal>` (pila, foco, Escape, bloqueo de scroll)
  en lugar del patrón plano de los modales existentes.
- **Colapso:** `x-show` + `x-transition` (no hay plugin `x-collapse` instalado).
- **Guardado:** un único "Guardar cambios" con tres PATCH secuenciales y feedback por
  sección (opción 2); la opción 3 queda como alternativa si se prioriza atomicidad por
  sección.

## Verificación ejecutada

- Lectura de los 12 archivos relevantes y de los DTO/colección del dominio de gimnasios.
- `php artisan route:list --except-vendor` filtrado: confirmadas
  `PATCH teams/{team}/formacion|posturas|items`, `POST teams/add-member`,
  `DELETE teams/{team}`, `GET|POST api/gimnasios/*`.
- `grep` de `composicionBadge|sinergia_nombre|sinergia` en `resources/`, `app/`, `src/`:
  único consumidor de UI en `panel-equipos.blade.php`.
- `grep` de `selectedTeamId` en `resources/views/equipos/`: solo declaración y uso en el
  borde de la tarjeta.
- `grep` de `x-collapse|x-transition` y `resources/js/app.js`: sin plugin collapse.
- Verificación de tokens CSS (`scrim`, `focus-ring`, `card-surface`, radios, tamaños de
  sprite) y de `[x-cloak]` en `layouts/app.blade.php:12`.
- `git status`: dos archivos modificados de TASK-049 y directorios
  `.opencode/work/TASK-049|050` sin seguimiento. Sin tests ejecutados.

## Problemas

- El contenedor `max-w-4xl` de la ficha de gimnasio deja poco margen para 4 columnas; hay
  que decidir entre tarjeta compacta en columna o ampliar el contenedor.
- `guardarFormacion` y `guardarPosturas` usan `alert()` y abortan si no hay miembros; no
  son reutilizables tal cual desde el modal sin extraer la construcción del payload.
- `selectedTeamId` en `/equipos` es estado muerto y puede confundir con el
  `selectedTeamId` del modal de gimnasio.
- El modal de edición convive con modales planos que no usan la pila (`z-50` compartido).
- `x-ui.modal` no permite un título dinámico con el nombre del equipo; el nombre debe ir
  en el cuerpo (o ampliar el componente, fuera de alcance).

## Suposiciones

- "Columnas" significa 4 columnas en escritorio (sm: 2, móvil: 1).
- El líder a la derecha se consigue con el orden natural 1..4; no se requiere invertir el DOM.
- Los iconos del header son los 3 miembros (slots 1-3), no la composición ni un icono genérico.
- "Colapsadas por defecto" aplica en cada carga de página; no se persiste el estado en
  backend ni en `localStorage`.
- Editar un miembro en exploración está permitido (el backend no lo bloquea para
  formación/posturas/items).
- El modal de edición no permite añadir/quitar miembros (eso sigue en el panel derecho y
  en el detalle).

## Preguntas pendientes

1. ¿Se puede ampliar el contenedor de la ficha de gimnasio de `max-w-4xl` a `max-w-6xl`
   para dar aire a las 4 columnas, o debe mantenerse y usar tarjetas compactas?
2. En el modal ancho de gimnasio, ¿se acepta que la leyenda IV/EV pase a colapsada por
   defecto con 3 columnas (como TASK-049), o debe seguir siempre expandida?
3. En el modal de edición, ¿un único "Guardar cambios" (3 PATCH secuenciales) o un botón
   por sección?
4. ¿El cuerpo expandido de la tarjeta debe conservar un resumen read-only de
   formación/postura/objeto, o basta con sprites y nombres?
5. ¿Se elimina el estado muerto `selectedTeamId` de `/equipos` en esta tarea?

## Recomendación

Adoptar la opción 2. Es un cambio exclusivamente de presentación: rejilla
`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` con tarjetas en columna y líder en la 4.ª
posición; modal de gimnasio con el patrón flex/scroll de TASK-049 (`max-w-6xl`, cabecera y
footer fijos, cuerpo con scroll) y leyenda parametrizada a 3 columnas colapsable; tarjetas
de equipo colapsadas por defecto con estado `equiposAbiertos`, header con `sprite-stack`
de 3 iconos y botón editar, y un `<x-ui.modal>` con formación/postura/objeto por slot que
guarde con los tres PATCH existentes aplicando las respuestas sección a sección. Cero
cambios de backend, cero dependencias nuevas, y sin tocar los dos archivos con cambios
locales de TASK-049. Antes de implementar, cerrar las preguntas 1-3.

## Archivos modificados

**Ninguno** (analista de solo lectura).

## Riesgos pendientes de decisión

- Ancho del contenedor de la ficha de gimnasio (afecta a la densidad de la rejilla).
- Comportamiento de la leyenda IV/EV en el modal de gimnasio (expandida vs colapsada).
- Estrategia de guardado del modal de edición (único vs por sección).
