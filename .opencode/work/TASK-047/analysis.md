# TASK-047 — Análisis

- **Tipo:** análisis de producto (QoL, diversión y RPG).
- **Fecha:** 2026-11-13.
- **Estado:** COMPLETADO (análisis; sin implementación).
- **Método:** dos análisis independientes (`analyst` A y B) sobre el estado real del repositorio. Sin tests, sin consultas a base de datos y sin modificar archivos. Las estimaciones están marcadas como tales.

---

# Parte A — Diagnóstico de fricción y mejoras QoL/UX

> **Nota de ejecución:** el analista alcanzó el máximo de pasos durante la fase final. Las afirmaciones se basan en lecturas realizadas y verificadas; no se modificó ningún archivo ni se ejecutaron comandos que muten estado. Las afirmaciones negativas se limitan al código revisado.

## 1. Resumen ejecutivo

El juego tiene un bucle central sólido (ruta 3v3 en `/habitats/{id}`) y ya resolvió la parte difícil de la navegación diaria: la navbar incluye stores Alpine de **Reclutamiento** y **Expediciones** con revalidación al foco y TTL de 15 s (`resources/js/stores/reclutamiento.js:10`, `resources/js/stores/expediciones.js:11`), y la cosecha global vive en el layout (`resources/views/layouts/app.blade.php:21`). Pero la fricción real está en cuatro sitios:

1. **`/habitats/{id}` es una página de 1.576 líneas** con 7 modales, 20 `fetch` y 12 `alert()`; el modo (ruta/entrenadores/exploración) es un toggle de estado Alpine que también bloquea botones (`resources/views/habitats/partials/columna-izquierda.blade.php:35-42`).
2. **El cambio de equipo es un formulario de 3 guardados separados** (formación, posturas, objetos) con botones redundantes (`panel-equipos.blade.php:189-213`), y el cambio de equipo **solo se puede hacer antes** de iniciar el combate.
3. **No hay inventario visible**: no existe ruta ni vista de inventario; los saldos solo se intuyen en forja/granja/equipos.
4. **La información de PS/estado/cooldowns/probabilidades no se ve fuera del combate**; el jugador debe recordarla.

## 2. Flujos y fricciones actuales (con evidencia)

### 2.1 Flujos diarios y pasos/clics

| Flujo | Pasos hoy | Evidencia |
|---|---|---|
| **Combatir en ruta** | Navbar Hábitats → provincia → hábitat → seleccionar equipo → seleccionar nivel → botón "Combate de Ruta" → popup formación → confirmar → redirección a `/combate` | `layouts/app.blade.php:47-55`; `habitats/index.blade.php:33-53`; `columna-derecha.blade.php:56-122` (equipo), `:223-278` (nivel), `:330-338` (botón); `show.blade.php:169-181` (popup), `:280-315` (POST + redirect) |
| **Cambiar equipo** | Navbar Equipos → panel izquierdo → abrir detalle del Pokémon (sprite) → reemplazar en modal → cerrar → volver al panel → guardar formación → guardar posturas → (objetos se guardan por select) | `equipos/index.blade.php:17-49`; `panel-equipos.blade.php:90-187`; `modal-reemplazo.blade.php`; `panel-equipos.blade.php:189-213` |
| **Reclutar** | Navbar icono → modal (o `/reclutamiento`) → "Reclutar" por cada especie | `layouts/partials/navbar-acciones.blade.php:15-25`; `reclutamiento/partials/_grid.blade.php:32-48`; `stores/reclutamiento.js:98-123` |
| **Curar** | **No existe** flujo de curación; no hay centro Pokémon, ni ítem de curación en la UI revisada | búsqueda negativa en `resources/views`, `app/`, `src/` (solo `heal` de batalla y `curarHp` interno) |
| **Reclamar recompensas** | Victoria → modal de victoria en `/combate` → cerrar → volver al hábitat manualmente | `livewire/combate.blade.php:124-140`; `layouts/app.blade.php:30` (navbar oculta en combate) |
| **Revisar expediciones** | Navbar icono brújula → panel 44rem → detalle en modal | `navbar-acciones.blade.php:27-38`; `panel-expediciones.blade.php:22-30` |
| **Incubar huevos** | Navbar Equipos → tab Huevos → "Poner huevo" → esperar → volver a Equipos → tab Huevos → "Eclosionar" | `equipos/index.blade.php:24-46`; `panel-huevos.blade.php:116-123`, `:78-87` |
| **Forjar** | Navbar Forja → buscar receta → desplegar → fabricar | `forja/index.blade.php:12-36`, `:111-156` |

### 2.2 Fricciones verificadas

**Navegación**
- La navbar **no marca la sección activa** con `aria-current` (solo color); `aria-current` solo aparece en Pokédex y en la leyenda IV/EV. No hay skip-link ni breadcrumbs.
- La navbar oculta el botón de cosecha en `/combate*` (`layouts/app.blade.php:30`) y el combate **no tiene enlace de vuelta** visible al hábitat: el flujo de retorno depende de `redirect` del backend.
- `/equipos` tiene **un solo `selectedTeamId`** y la sección de Huevos es un tab (`equipos/index.blade.php:24-46`), pero Huevos no tiene acceso propio en la navbar: se descubre por casualidad dentro de Equipos.

**Recargas completas**
- `location.reload()` en 3 puntos de Equipos: cambio de rol (`:1680`), evolución (`:1977`) y envío a exploración (`:2152`). Esto destruye el estado de modales y scroll.
- `window.location.href` en 5 puntos para iniciar combate (`habitats/show.blade.php:274,308,436`; `gimnasios/show.blade.php:419`) — es correcto (cambio de página) pero pierde el contexto de selección.

**Estados vacíos y errores**
- **`alert()` nativo en 12 puntos** de `habitats/show.blade.php` (`:269,276,1055,1093,1132,1148,1165,1286,1290,1296,1354,1358`) y en `equipos/index.blade.php` (`:1574,1683,1687,1723,1840,1939,2012,2020,2035,2126,2155,2159`). No hay patrón de toast/alert reutilizable fuera de `x-ui.alert` y el toast de cosecha.
- Errores genéricos "Función en preparación." (`habitats/show.blade.php:1042,1121`) que se muestran incluso si el endpoint existe pero falla.
- `window.confirm` en 5 puntos (`show.blade.php:921,1302`; `equipos/index.blade.php:1991`; `misiones/index.blade.php:424`; `comun.js:18`), sin componente de confirmación consistente.

**Falta de feedback**
- El feedback de guardado de formación/posturas/objetos existe pero es **global por equipo** (`formacionSaveSuccess`, `posturaSaveSuccess`), no por slot; no se limpia automáticamente (`panel-equipos.blade.php:199-212`).
- El modal de detalle Pokémon **recalcula y repinta 3 gráficos** (`modal-detalle-pokemon.blade.php:245,299,316`) y tiene 332 líneas; no hay carga diferida por pestaña.
- El bug conocido del scroll del detalle está documentado por el desarrollador (`bugs.txt:5`: "en /equipos ver el detalle del pokemon el scroll bar mata la experiencia del dorado").

**Acciones repetitivas**
- Descartar reclutables: hay "Descartar" por especie y "Descartar todos" (`reclutamiento/index.blade.php:15-24`), pero el modal de descarte usa porcentajes 25/50/75/100 (`stores/reclutamiento.js:12`), no cantidad libre.
- Reclamar expediciones: **una a una** (`_tarjeta-terminada.blade.php` y `panel-expediciones`), no hay "recoger todo".
- Cosecha: **sí** hay "cosechar todo" global (`layouts/app.blade.php:21`), lo cual es el patrón correcto a replicar.

**Móvil**
- El panel de expediciones es `fixed` en móvil y `absolute` en escritorio (`panel-expediciones.blade.php:22`), bien resuelto.
- La navbar usa `overflow-x-auto` (`layouts/app.blade.php:29`) y **oculta** la cosecha y acciones en pantallas estrechas sin menú hamburguesa. No hay `md:hidden` ni drawer.
- El grid de equipos usa 3 columnas fijas con selects de 2xs (`panel-equipos.blade.php:90-174`), muy denso en móvil.

**Accesibilidad**
- El componente `x-ui/modal` tiene trampa de foco real (`components/ui/modal.blade.php:1-40`), pero **la mayoría de modales de hábitats y equipos no lo usan**: `modal-formacion`, `modal-mazmorra`, `modal-granja`, `modal-baya`, `modal-exploracion` son `<div x-show>` propios sin `x-trap`.
- `role="tablist"` en Equipos (`equipos/index.blade.php:24`) pero las pestañas no tienen `aria-controls` ni `tabindex` gestionado.
- El botón "Admin - Gestion" en el hábitat está visible para todos los usuarios (`columna-izquierda.blade.php:101-112`) sin control de rol en la vista; es un problema de discoverability inversa (acción interna expuesta).

**Discoverability**
- No hay tutorial ni onboarding más allá del equipo inicial (`routes/player.php:19-21`).
- Las Sendas explican el sistema (`sendas/index.blade.php:10-13`), pero Granjas, Forja, Huevos y Mazmorras **no tienen introducción** en la UI.
- El "modo" del hábitat es un estado oculto: el jugador debe pulsar un botón lateral para cambiar entre ruta/entrenadores/exploración, y en modo ruta el panel de equipos muestra `x-show` condicional (`columna-derecha.blade.php:53`).

**Pérdida de contexto**
- En `/combate` no hay navbar (`layouts/app.blade.php:30`) ni breadcrumb de qué hábitat/entrenador/gimnasio se está combatiendo.
- El modal de victoria se abre con `modal-pendiente` y se cierra solo tras 8 s (`combate.blade.php:125-127`), lo que puede perder la recompensa de vista.
- Tras `location.reload()` en Equipos se pierde el tab activo y el Pokémon seleccionado.

## 3. Información crítica no visible

| Información | Dónde está | Dónde falta | Evidencia |
|---|---|---|---|
| **PS actuales de tu equipo** | Solo dentro del combate (`_pokemon-card.blade.php:170-172`) | Hábitat, Equipos, navbar | `_pokemon-card.blade.php` solo se usa en combate |
| **Estado alterado** | Solo en combate | Equipos, hábitat | — |
| **Cooldown de mazmorra** | En el modal de mazmorra, como texto (`show.blade.php:381-389`) | Fuera del modal | `estadoPisoTexto` |
| **Cooldown de derrota de entrenador** | No existe en UI (solo `won` bloquea el día) | — | `ObtenerEntrenadoresHabitat` |
| **Probabilidad de captura** | En la Pokédex y en la ficha de especie, no en el hábitat | Hábitat al elegir nivel | `Reclutamiento/context.md:29-31` |
| **Coste de mejorar nido/refugio/complemento** | En el modal de granja (`show.blade.php:941-948` muestra motivo, no coste) | Antes de abrir granja | `textoBotonComplemento` |
| **Caramelos por familia** | En granja (`saldoFamilia`, `show.blade.php:933-939`) | Equipos, evolución | El modal de evolución sí muestra `caramelosDisponibles` (`modal-detalle-pokemon.blade.php:91-97`) |
| **EXP restante para subir nivel de cuenta** | Barra de 5 px en navbar (`layouts/app.blade.php:96-98`) sin cifras | En cualquier página | Solo porcentaje |
| **Recompensas de gimnasio** | No se muestran antes de combatir | `/gimnasios/{slug}` | — |
| **Progreso de mazmorra** | Solo al abrir el modal | Hábitat | — |
| **Huevos listos** | Solo al entrar en Equipos → tab Huevos | Navbar / badge | `panel-huevos.blade.php` |
| **Expediciones listas para recoger** | Badge en panel, pero el icono no tiene contador en navbar | Navbar | `navbar-acciones.blade.php:27-38` |

## 4. Tabla de mejoras QoL

| # | Propuesta | Problema que resuelve | Evidencia | Impacto | Esfuerzo | Dependencias | Riesgos |
|---|---|---|---|---|---|---|---|
| Q1 | **Toast/confirm reutilizable** (`x-ui.toast`, `x-ui.confirm`) y sustituir los 24 `alert`/`confirm` nativos | Ruptura de estética, bloqueo de hilo, no accesible | `show.blade.php:269,276,1055…`; `equipos/index.blade.php:1574,1683…`; `comun.js:18` | Alto | S | Ninguna | Bajo: cambio de presentación |
| Q2 | **Guardado único de equipo** (formación + posturas + objetos en un solo PATCH o botón "Guardar cambios") | 3 botones redundantes y feedback fragmentado | `panel-equipos.blade.php:189-213`; `routes/player.php:48-52` | Alto | M | Backend: endpoint combinado o PATCH idempotente | Medio: contrato de `TeamController` |
| Q3 | **Indicador de sección activa + breadcrumb en combate** (`aria-current`, migas, enlace "volver al hábitat") | Pérdida de contexto y desorientación | `layouts/app.blade.php:57-65`; `combate.blade.php:1-115` | Medio | S | Pasar `habitat_id` al combate | Bajo |
| Q4 | **Badge/contador en navbar** de expediciones listas, huevos listos, reclutables pendientes | Discoverability y acciones perdidas | `navbar-acciones.blade.php:15-38`; `panel-huevos.blade.php` | Alto | S | Endpoints ya existen (`/api/exploraciones`, `/api/huevos`, `/api/reclutamiento`) | Bajo |
| Q5 | **"Recoger todo" en expediciones** terminadas | Acción repetitiva una a una | `_tarjeta-terminada.blade.php`; `panel-expediciones` | Medio | S | Backend: endpoint batch o bucle cliente | Bajo |
| Q6 | **Pestaña Huevos accesible desde navbar** (o icono propio) | Sistema oculto dentro de Equipos | `equipos/index.blade.php:24-46`; `layouts/app.blade.php:47-55` | Medio | S | Ninguna | Bajo |
| Q7 | **Inventario visible** (página `/inventario` o modal desde navbar) con saldos de caramelos/bayas/bonguris/objetos | No hay vista de inventario; el jugador no sabe qué tiene | búsqueda negativa de ruta/vista | Alto | M | Endpoint de inventario (`player_inventory`) | Medio: definir agrupación |
| Q8 | **Resumen de equipo con PS/estado** en Equipos y hábitat (solo lectura, del último combate o estimado) | El jugador no sabe si su equipo está "sano" | `_pokemon-card.blade.php:170` solo en combate | Alto | L | Persistir PS o calcular estado post-combate | Alto: hoy no hay modelo de PS persistente |
| Q9 | **Modal de confirmación con resumen de recompensas** antes de combatir (PC estimado ya existe) | Falta previsión de riesgo/recompensa | `bugs.txt:1`; `show.blade.php:151-167` (comparación PC) | Medio | S | `CalcularComparacionPc*` ya existen | Bajo |
| Q10 | **Unificar modales de hábitat con `x-ui/modal`** (foco atrapado, Escape, pila) | Accesibilidad y comportamiento inconsistente | `components/ui/modal.blade.php`; modales de hábitat sin trap | Medio | M | Refactor de 7 parciales | Medio: regresión visual |
| Q11 | **Sustituir `location.reload()` por actualización reactiva** en Equipos | Pérdida de tab/scroll/modales | `equipos/index.blade.php:1680,1977,2152` | Medio | M | Refactor de estado Alpine | Medio |
| Q12 | **"Añadir al equipo" desde la ficha del Pokémon** (no solo desde hover del grid) | Descubrimiento del hover, difícil en móvil | `panel-pokemon.blade.php:127-158` | Medio | S | Ninguna | Bajo |
| Q13 | **Selector de cantidad libre en descarte** (además de 25/50/75/100) | Fricción para descartes parciales | `stores/reclutamiento.js:12,142-157` | Bajo | S | Ninguna | Bajo |
| Q14 | **Indicador de coste/progreso en granja y forja antes de abrir** | El jugador entra a ciegas | `show.blade.php:941-948`; `forja/index.blade.php:180-195` | Medio | M | Exponer costes en el DTO de hábitat | Bajo |
| Q15 | **Atajos de teclado en combate** (1-4 movimientos, Tab objetivo, Espacio confirmar) | Ritmo lento del combate por turnos | `moves-panel.blade.php:39-90`; `combate.js` (2 líneas) | Medio | M | Cambios en `Combate` Livewire | Medio: accesibilidad |
| Q16 | **Auto-selección de objetivo cuando solo hay uno válido** | Clic extra en cada ataque | `GestorSeleccionBatalla.php:60-85` | Alto | S | Ninguna | Bajo |
| Q17 | **Feedback de guardado por slot** y limpieza automática | Mensajes ambiguos | `panel-equipos.blade.php:199-212` | Bajo | S | Ninguna | Bajo |
| Q18 | **Menú hamburguesa / drawer en móvil** para navbar | Acciones ocultas en pantallas estrechas | `layouts/app.blade.php:29` | Medio | M | Ninguna | Bajo |
| Q19 | **Reducir animación de combate configurable** ("modo rápido") | Combate percibido lento (700 ms por acción) | `combate.blade.php:5` (`DURACION_ACCION = 700`) | Alto | M | Preferencia de usuario | Bajo |
| Q20 | **Ocultar "Admin - Gestion" a no-admin** | Acción interna expuesta | `columna-izquierda.blade.php:101-112` | Bajo | S | Policy/rol | Bajo |

## 5. Top 5 quick wins y top 5 estratégicas

### Quick wins (bajo esfuerzo, alto impacto)

1. **Q16 — Auto-selección de objetivo único.** El flujo de combate ya calcula el objetivo (`GestorSeleccionBatalla.php:60-85`); eliminar un clic por ataque. Sin backend nuevo.
2. **Q4 — Badges en navbar** (expediciones listas, huevos listos, reclutables). Endpoints existentes; solo UI.
3. **Q1 — Toast/confirm reutilizable.** Sustituye 24 `alert`/`confirm`; mejora estética y accesibilidad.
4. **Q6 — Huevos en navbar.** El sistema ya existe, solo está escondido en un tab.
5. **Q5 — "Recoger todo" en expediciones.** Replicar el patrón de "cosechar todo" (`layouts/app.blade.php:21`).

### Estratégicas (mayor esfuerzo)

1. **Q8 — Estado del equipo (PS/estado) persistente y visible.** Es el mayor hueco de información; requiere modelo de estado post-combate.
2. **Q7 — Inventario visible.** No existe hoy; es la pieza que falta para que la economía sea legible.
3. **Q2 — Guardado único de equipo.** Reduce de 3 acciones a 1 y unifica el contrato de `TeamController`.
4. **Q19 — Velocidad de combate configurable.** Ataca la percepción de lentitud sin cambiar reglas.
5. **Q10 — Unificar modales con `x-ui/modal`.** Consolida accesibilidad y comportamiento en 7 parciales.

## 6. Duplicidades y dependencias

### Duplicidades con trabajo pendiente

- **Q19 (velocidad de combate)**: `Tareas_pdte/Velocidad en combates` ya propone cambios de velocidad; **no duplicar**, referenciar.
- **Q9 (PC estimado)**: `bugs.txt:1` ya lo pide; el código ya tiene `CalcularComparacionPcRuta`/`Entrenador` (untracked). Está **en curso**.
- **Q15 (selector de movimientos)**: `bugs.txt:8` pide selector STAB/cobertura; no duplicar.
- **Q3 (breadcrumb/volver)**: relacionado con `bugs.txt:13` ("Reformular nav bar") y TASK-043 §10 (UX/UI).
- **Q7 (inventario)**: no aparece en TASK-043..046 ni en `Tareas_pdte`; es nuevo, pero debe encajar con la economía de TASK-046.
- **Q2 (guardado único)**: no está en pendientes; es nuevo.
- **Q4/Q5/Q6**: el desarrollador ya pidió "cachear reclutamiento y expediciones" (`bugs.txt:17-25`); el store ya existe (`reclutamiento.js`, `expediciones.js`). Solo falta el badge.

### Dependencias técnicas

- Q7 requiere endpoint de inventario (hoy solo `player_inventory` por consulta directa en controladores).
- Q8 requiere decidir si el PS se persiste o se deriva; hoy no hay tabla.
- Q2 requiere coordinar `TeamController` y `routes/player.php:48-52`.
- Q10 depende del componente `x-ui/modal` (untracked, `resources/views/components/ui/modal.blade.php`).

## 7. Preguntas abiertas (máx. 8)

1. **¿El estado del equipo (PS) debe persistirse entre combates** o el juego asume curación completa al terminar? Hoy no hay modelo de PS fuera de batalla.
2. **¿El inventario debe ser una página propia** (`/inventario`) o un modal de navbar?
3. **¿El guardado único de equipo** puede cambiar el contrato de `TeamController` o debe mantener los 3 endpoints?
4. **¿La velocidad de combate** debe ser una preferencia de usuario o un ajuste global?
5. **¿"Recoger todo" en expediciones** debe ser backend batch o bucle cliente?
6. **¿Los badges de navbar** deben mostrar solo "listo" o también "en curso"?
7. **¿El botón "Admin - Gestion"** debe ocultarse por rol o eliminarse de la vista pública?
8. **¿Hay presupuesto para refactor de modales** (Q10) o se prioriza solo lo nuevo?

## 8. Archivos consultados y riesgos del análisis

**Consultados (lectura):**
`routes/web.php`, `routes/habitats.php`, `routes/ruta.php`, `routes/entrenadores.php`, `routes/exploraciones.php`, `routes/huevos.php`, `routes/player.php`, `routes/gimnasios.php`, `routes/granjas.php`, `routes/forja.php`, `routes/mazmorras.php`; `app/Http/Controllers/{PlayerController,ExploracionActivaController,ReclutamientoController}.php`; `app/Livewire/Combate.php`, `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php`; `resources/views/layouts/app.blade.php`, `layouts/partials/navbar-acciones.blade.php`, `panel-expediciones.blade.php`; `resources/views/habitats/{show,index}.blade.php`, `partials/{columna-izquierda,columna-derecha,modal-exploracion}.blade.php`; `resources/views/equipos/index.blade.php` y `partials/{panel-equipos,panel-pokemon,panel-huevos,modal-detalle-pokemon}.blade.php`; `resources/views/reclutamiento/index.blade.php`, `partials/_grid.blade.php`; `resources/views/exploraciones/index.blade.php`, `partials/{_contenido,_tarjeta-activa}.blade.php`; `resources/views/gimnasios/{index,show}.blade.php`; `resources/views/forja/index.blade.php`; `resources/views/sendas/index.blade.php`; `resources/views/pokedex/index.blade.php`; `resources/views/livewire/combate.blade.php`, `partials/{moves-panel,preparacion-modal,turn-bar}.blade.php`; `resources/js/{bootstrap.js,combate.js}`, `stores/{comun,expediciones,reclutamiento}.js`; `resources/views/components/ui/modal.blade.php`; `Tareas_pdte/mejoras`, `Tareas_pdte/Velocidad en combates`, `bugs.txt`; `docs/conventions.md`; `.opencode/work/TASK-043/analysis.md`, `TASK-044..047/{request,plan}.md`.

**Riesgos del análisis:**
- **Alto:** no se verificó el estado real de la BD (prohibido ejecutar consultas); afirmaciones sobre "no existe curación" o "no hay inventario" se basan en búsqueda negativa en el código revisado.
- **Medio:** el árbol de trabajo está sucio (26 archivos modificados, incl. `layouts/app.blade.php`, `equipos/index.blade.php`, `reclutamiento/index.blade.php`, `exploraciones/index.blade.php`, `gimnasios/show.blade.php`, `habitats/show.blade.php`); algunas líneas citadas pueden diferir del estado consolidado.
- **Medio:** no se revisaron en profundidad `resources/views/equipos/partials/modal-exploracion.blade.php` ni `modal-reemplazo.blade.php`.
- **Bajo:** no se analizó `src/Battle` en detalle; Q15/Q16 se apoyan en `GestorSeleccionBatalla.php`, no en todo el ciclo de turnos.

---

# Parte B — Diagnóstico del bucle, progresión, combate y capa RPG

> **Nota de ejecución:** el analista alcanzó el máximo de pasos durante la fase final de citas. El informe se basa exclusivamente en las lecturas y cálculos realizados; no se modificó ningún archivo, no se ejecutaron tests ni comandos que muten estado. Donde no se pudo verificar número de línea exacto se indica como `(s/l)`.

## 1. Resumen ejecutivo

- El motor y la economía están consolidados; lo que falta es **dirección**: objetivos, metas por zona y voz narrativa.
- El bucle real es **ruta 3v3 sin límite** (`IniciarCombateRuta.php:20-23`). El primer objetivo significativo es el **gimnasio de nivel 10**; por curva y EXP media, exige del orden de **18-35 combates** (estimación, §2).
- La progresión es **ancha y plana**: 18 gimnasios solo bloqueados por nivel, sin prerrequisitos (`ObtenerGimnasios.php:31`); 56 hábitats abiertos; `min_lvl_1/2/3` existe pero **ningún seeder lo escribe** (`HabitatSeeder.php:19-45`).
- La válvula de progresión diseñada (mazmorra ×70) está **implementada pero apagada: 0 de 56 hábitats configurados** (`TASK-044/analysis.md:33`; `add_mazmorra_to_habitats_table.php:17`).
- El combate es profundo en motor (clima, posturas, objetos, IA, jefes), pero el PvE es homogéneo: **el rival de ruta y de entrenador se genera al nivel del jugador** (`IniciarCombateEntrenador.php:55`); el nivel 1/2/3 del hábitat cambia IVs, captura y recompensa, no el nivel.
- La capa RPG es casi nula: sin NPCs, sin nombres de líderes, sin misiones, sin facciones implementadas.
- **Hallazgo de coordinación:** `TASK-046/analysis.md` y `architecture.md` están **PENDIENTE/vacíos**; las misiones de progresión, eventos de 3 días y recompensas por nivel de hábitat **no están diseñadas** en el repositorio, solo pedidas (`TASK-046/request.md:11-20`).
- Prioridad propuesta: activar mazmorras → metas por hábitat/Pokédex → MVP de misiones → desbloqueo blando → nombres de líderes y emisor narrativo.

---

## 2. Bucle actual y ritmo

**Bucle:** login → `/habitats` → elegir hábitat y nivel → combate de ruta 3v3 → EXP/caramelos/capturas → reforzar equipo → repetir; los sistemas laterales (entrenadores, exploración, granja, forja, huevos, Sendas) se alimentan del mismo flujo (`TASK-043/analysis.md:21-23,39-47`).

**Primer objetivo significativo.** No hay misión ni meta inicial. El primer hito externo es el gimnasio Bicho (nivel 10, `CatalogoGimnasios.php:31`). Estimación con datos reales:
- `experienciaParaNivel(n) = 10·n³` (`NivelHelper.php:6-12,30-33`): nivel 10 = **10.000 EXP**.
- Una victoria de ruta reparte `3 × base_experience × n / 5`, con 50 % para la cuenta y ×1,0 (`OtorgarRecompensasRuta.php:54`; `EconomiaRecompensas::legado`). Con la media de `pokemon.csv` (156,8) son ≈ **47·n EXP** por victoria.
- Sumando de nivel 1 a 10: **≈ 35 victorias de ruta** (≈ 18 si se usan entrenadores ×2). Es una estimación de orden de magnitud, no una medición; el `base_experience` real depende del pool.

**Dónde se rompe el ritmo:**
1. **Sin objetivo tras cada combate.** La ruta no registra derrotados ni victorias (`IniciarCombateRuta.php:20-23`); la Pokédex solo registra (`TASK-043/analysis.md:177`).
2. **Sin razón para cambiar de zona.** Todos los hábitats están abiertos (`HabitatsController` listado; `TASK-043/analysis.md:137-143`).
3. **La cadencia diaria no limita de verdad.** Entrenadores: 9/día **por hábitat** (`ObtenerEntrenadoresHabitat.php:26-49`); con 56 hábitats son hasta 504 victorias/día. La ruta no tiene tope.
4. **Esperas reales** en exploración (30 min/encuentro, `ReglasExploracion`; `TASK-043/analysis.md:122`), plantaciones (1 cosecha/24 h, stock 3) y huevos (temporizadores reales); la mazmorra solo castiga con 1 h tras derrota (`ReglasMazmorra.php:16-27`).
5. **La mazmorra no existe en la práctica:** sin `mazmorra` configurada, el módulo devuelve lista vacía (`ObtenerPisosMazmorra::obtener`; `TASK-044/analysis.md:33`).

**Consecuencia:** el jugador nuevo no tiene un "¿qué abro después?"; tiene un "¿qué repito?".

---

## 3. Progresión y economía

| Eje | Curva / coste | Qué abre | Coherencia observada |
|---|---|---|---|
| **Nivel de cuenta** | `10·n³`; nivel 100 = 10.000.000; sin tope (`NivelHelper.php:6-12`) | Gimnasios por `nivel_min` (`ObtenerGimnasios.php:31`); niveles de exploración (si `min_lvl` estuviera escrito) | Único eje que abre puertas; el resto no condiciona |
| **Medallas** | 18 gimnasios, 4 etapas; solo nivel mínimo, **sin prerrequisito entre gimnasios** | Nada más | Un jugador puede subir a nivel 100 y hacer los 18 seguidos; la escalera no ordena el recorrido |
| **Hábitats** | — | Nada | `min_lvl_1/2/3` existe (`add_min_lvl_columns...:15-19`) pero no se escribe (`HabitatSeeder.php:19-45`); 12 hábitats sin pool (`TASK-044/analysis.md:165`) |
| **Mazmorra** | Progreso por hábitat; cooldown 1 h tras derrota | Siguiente piso; recompensa ×70 | 0/56 configuradas: eje inaccesible |
| **Granjas** | Nidos 25/75/225/450/900 caramelos; refugios hasta 2.700; 1 cosecha/24 h, stock 3 | IV/EV/aparición/captura | Sumidero correcto, pero sin meta que empuje a usarlo |
| **Huevos** | 1/512 shiny; tiempos reales | Reclutado con IV según nido | Segundo camino de mejora de IV, compite con nidos |
| **Forja** | 46 recetas / 39 resultados | Objetos de combate | Sumidero de bayas/bonguris; sin objetivo que lo active |
| **Caramelos** | Familia/EV/tipo desde la misma batalla (`CalculadorRecompensas.php:273-302,330-369`) | Nidos, EV, Sendas | Tres monedas del mismo evento; redundancia de canales |
| **Sendas** | 5 niveles: 0/9.000/18.000/36.000/72.000; 100 EXP por caramelo (`config/sendas.php:3-8`) | Movimientos y bonos 10/20 % | Para maximizar una Senda hacen falta ~720 caramelos de tipo; con ~6 por combate son ~120 combates por tipo |
| **Legendarios** | — | — | Sin sistema; el hábitat 49 es un pool normal de 100 especies, incluidas UBs y paradojas (`TASK-044/analysis.md:29,248-253`) |

**Redundante:**
- Tres tipos de caramelo desde el mismo combate, todos con sumideros propios.
- Dos vías de mejora de IV (nido y huevo).
- Tres escaleras de jefe potenciales (gimnasio, mazmorra, base de facción) con la misma textura.
- Dos semánticas de "captura" (`reclutables` vs `reclutados`, `TASK-043/analysis.md:377-381`).
- EXP de cuenta y EXP de Senda salen del mismo combate.

**Falta:**
- Metas por zona, familia y nivel de hábitat.
- Desbloqueo de hábitats.
- Contenido de mazmorra.
- Estado de legendario por jugador.
- Recompensa por completar Pokédex de una zona.
- Modos de dificultad o restricciones.
- Una razón económica para visitar granja/forja/huevos (hoy son opcionales sin gancho).

**Riesgo de economía:** la recompensa ×70 de mazmorra y las recompensas de misión (si se suman a ruta/entrenador) pueden romper la curva de nidos (900 caramelos) y Sendas (72.000 EXP). `docs/analysis/misiones-y-facciones.md:344-357` ya propone separar escaleras y topes; conviene respetarlo.

---

## 4. Combate y variedad

**Lo que el motor ya soporta** (`src/Battle/README.md:1-10`): turnos, clima y terreno, posiciones vanguardia/retaguardia, efectos de movimiento/equipo, objetos, jefes con fases y bonus, y una IA con analizador de amenazas, evaluador, simulador y memoria (`Domain/AI/`).

**Decisiones que toma el jugador hoy:**
- Hábitat y **nivel 1/2/3** (riesgo/recompensa: IVs `Nulo/TopDos/Completo`, factor de captura 1,0/0,5/0,3 y multiplicadores de caramelo 1,0/3,3/3,35; `PoliticaIvsEvs`, `MultiplicadoresDificultad`, `ProbabilidadCaptura.php:24-28`).
- Equipo de exactamente 3 y formación (`ReglasEquipo`; `ConstruirEquipoJugador`).
- Postura (`ATACAR/GUARDIA/CUBRIR`), movimiento y objetivo, objeto, y acciones de turno (`app/Livewire/Combate.php:99-144`).
- 8 objetos de combate en catálogo (`CatalogoObjetos.php:20-29`).

**Lo que falta o es débil:**
- **El rival no escala en dificultad por zona:** en ruta y entrenador se genera al nivel del jugador (`IniciarCombateEntrenador.php:55`; `GeneradorEquipoRuta` recibe `nivelRival` del jugador en `IniciarCombateRuta`). Solo los gimnasios escalan a la mitad (`EscaladorNivelRival`).
- **No hay arquetipos de entrenador** ligados al hábitat; el plan lo pide explícitamente (`Tareas_pdte/plan ruta.txt`, apartado 0.2).
- **No hay modos**: ni monotipo, ni nivel fijado, ni Élite 4 (roadmap 0.5), ni PvP (no encontrado en código, `TASK-043/analysis.md:69`).
- **No hay objetivos dentro del combate** (capturar durante la pelea, proteger a un NPC, sobrevivir N turnos).
- **El clima existe en el motor pero no verifiqué que la generación PvE lo active**; no puedo afirmar que se use hoy.
- **No verifiqué un cambio de Pokémon activo** en el listado de métodos de `Combate.php`; `swapTurno`/`retrasarTurno` pueden ser orden de turno, no cambio de combatiente.
- **Repetición de pool:** la ruta elige con reemplazo ponderado por `capture_rate/hatch` (`GeneradorEquipoRuta`, comentario de clase), lo que puede repetir especies dentro del mismo combate.

**Veredicto:** el combate es más profundo de lo que el jugador percibe porque el PvE no le obliga a usar la profundidad. La variedad falta en **contexto y restricciones**, no en el motor.

---

## 5. Capa RPG y narrativa

**Lo que existe:**
- Nombres de provincias y hábitats con ambientación (`ProvinceSeeder.php:17-34`; `storage/data/habitats.csv`).
- Nombres de medallas por tipo y peligro 1-5 derivado del nombre (`CatalogoGimnasios.php`; `HabitatSeeder.php:34-45`).
- Textos funcionales del diario de exploración (`EventoExploracionVista.php:151,320-331`).
- Identidad mínima del jugador: nombre de equipo, apodo de reclutado, favoritos, Pokédex.

**Lo que no existe:**
- NPCs con nombre, diálogos o trasfondo.
- Nombres de líderes de gimnasio: `Gimnasio::nombreEtapa()` devuelve `Entrenador 1/2/3/Líder` (`Gimnasio.php:32-41`).
- Entrenadores con identidad: `"Entrenador Nivel {$nivel}"` (`IniciarCombateEntrenador.php:79`); su equipo cambia a diario por semilla (`GeneradorEquipoEntrenador.php:77`).
- Facciones, misiones, decisiones o estado de mundo.
- La vista `misiones` es un stub huérfano: consume endpoints inexistentes y el nav está comentado (`resources/views/misiones/index.blade.php:252,269,393,427`; `resources/views/layouts/app.blade.php:54`).

**Mínimo para que se sienta RPG (sin diálogos ramificados):**
1. **Un emisor reconocible** (Consejo/archivista/mentor) y **una línea por misión** (≤140 caracteres), como ya propone `TASK-044` Parte B §4.
2. **Nombres y una línea para los 18 líderes de gimnasio**: son equipos fijos, así que nombrarlos no rompe la ficción (a diferencia de los entrenadores diarios).
3. **Estado de mundo persistente**: hábitats abiertos, cicatrices de facción, medallas visibles. Sin esto, las misiones no dejan huella.
4. **Un gancho en el primer día**: abrir 2 hábitats o dar la primera misión antes del gimnasio 10 (`docs/analysis/misiones-y-facciones.md:209-215`).
5. **Una decisión con consecuencia** (aunque sea binaria: piedad vs castigo en una facción, `TASK-044/architecture.md:90`).

---

## 6. Tabla de mejoras

`s/l` = sin línea exacta verificada. Impacto/esfuerzo en escala alto/medio/bajo.

| # | Propuesta | Eje | Problema que resuelve | Evidencia | Impacto | Esfuerzo | Dependencias | Riesgos |
|---|---|---|---|---|---|---|---|---|
| 1 | **Sembrar mazmorras en 5-8 hábitats** (config `mazmorra` con `species_id` existentes) | Progresión / diversión | Válvula ×70 apagada; sin jefes accesibles | `ReglasMazmorra.php:16-27`; `TASK-044/analysis.md:33`; `routes/mazmorras.php:7-10` | Alto | Bajo-Medio | Catálogo de especies existente; pisos por hábitat | Jefe ×10 stats puede ser injusto; ×70 infla si se farmea |
| 2 | **Recompensa por completar un nivel de hábitat** (25/50/100 caramelos para niveles 1/2/3) | Progresión | No hay meta por zona; Pokédex pasiva | `TASK-046/request.md:13,20`; 787 asignaciones, 44 hábitats con pool (cálculo sobre `pokemon_habitat.csv`) | Alto | Bajo | Contador de vistos/atrapados por hábitat+nivel; semántica de "completar" | Farmeo de nivel 1; inflación de familia |
| 3 | **Metas de Pokédex por hábitat/familia** ("avista/captura N del nivel") | Progresión | Pokédex solo registra | `TASK-043/analysis.md:177,190`; tabla `pokedex` | Alto | Bajo-Medio | Consulta a `pokedex` o ledger | Captura máx. 9,8 % hace metas lentas (`ProbabilidadCaptura.php:24-28`) |
| 4 | **MVP de misiones diarias** (3 encargos + 1 parte, aceptación pasiva, cobro automático) | Diversión / progresión | Sin objetivos; ruta infinita sin propósito | `TASK-043` plan; `docs/analysis/misiones-y-facciones.md:365-371` | Alto | Medio | Ledger de eventos idempotente (`TASK-043/analysis.md:329`) | Chores; inflación; doble conteo |
| 5 | **Desbloqueo blando de hábitats** (condición que llegue antes: hito o nivel+válvula) | Progresión / RPG | Mapa plano, todo abierto | `TASK-043/analysis.md:137-143`; `docs/analysis/misiones-y-facciones.md:201-207` | Alto | Medio | Campaña o tabla de unlocks; decidir relación con `min_lvl` | Tres fuentes de bloqueo sin regla |
| 6 | **Nombrar a los 18 líderes y dar una línea** | RPG | "Líder" genérico | `Gimnasio.php:32-41`; `CatalogoGimnasios.php` (sin nombre) | Medio | Bajo | Catálogo en código | Tono/IP; no renombrar entrenadores diarios |
| 7 | **Emisor narrativo + una línea por misión** | RPG | No hay quién pide nada | `TASK-044` Parte B §4 | Medio | Bajo | Misiones (#4) | Texto repetitivo; deriva de tono |
| 8 | **Expediente Yell** (3 actos, base 3 pisos, líder 3v3, sin legendario) | RPG | Sin arco villano ni clímax | `TASK-044/architecture.md:24-30,100-157,373-385` | Alto | Alto | #4, #5, entidad de base y estado de facción | Autoría; canibaliza gimnasio; 3v3 |
| 9 | **Sellar el hábitat Legendarios y definir ancla** | Progresión / RPG | 100 especies tratadas como pool normal | `TASK-044/analysis.md:29,248-253`; `TASK-043/analysis.md:140` | Alto | Medio | Unlock + estado de legendario | Power creep; duplicidad con gimnasios |
| 10 | **Eventos de 3 días** (control de población: derrota X de familia Y) | Diversión | Sin eventos temporales; ruta monótona | `TASK-046/request.md:12`; `Tareas_pdte/misiones` vacío | Medio-Alto | Medio | Ledger + generador determinista | FOMO; farmeo; sin scheduler garantizado |
| 11 | **Reto del día por hábitat** (modificador + primera victoria con bono) | Diversión | Ruta sin cadencia ni variedad | `IniciarCombateRuta.php:20-23`; rival = nivel jugador | Medio | Bajo | Config de modificadores; semilla diaria existente | Trivializar o frustrar; requiere balance |
| 12 | **Modos de combate** (monotipo, nivel fijado, Élite 4) | Diversión | PvE homogéneo | `plan ruta.txt` 0.5; motor `Battle` | Alto | Medio-Alto | Reglas de generación y recompensa | Autoría de equipos; puede canibalizar gimnasios |
| 13 | **CP visible y ordenación** | Progresión | CP existe pero no se explota | `Tareas_pdte/propuesta cp`; `CalculadorCP.php` modificado sin commit | Medio | Bajo | Cálculo/job; no tocar cambios locales | Duplica cálculo; datos desfasados |
| 14 | **Equipamiento por familia** (arma/armadura/amuleto) | Progresión | Stats planos; Forja ya existe | `Tareas_pdte/propuesta mejora craft`; `CatalogoRecetas` | Medio | Alto | Modelo de ítems equipables | Solapa Forja; power creep |
| 15 | **PvP** | Diversión | No hay PvP | `TASK-043/analysis.md:69` | Alto | Alto | Matchmaking, equipos, antítrampas | Fuera de alcance actual |

---

## 7. Top 5 priorizado

1. **Sembrar mazmorras (5-8 hábitats).** Es el mayor impacto por esfuerzo: el módulo ya existe, el combate de jefe y la recompensa ×70 también; solo falta el JSON de configuración. Sin esto, la curva cúbica se convierte en grindeo de ruta ×1.
2. **Recompensa por nivel de hábitat + metas de Pokédex.** Es barato, usa datos ya existentes (pool por nivel y `pokedex`) y da al jugador un objetivo en la primera sesión, justo donde hoy no hay ninguno. Es también la petición explícita del desarrollador (`TASK-046/request.md:13,20`).
3. **MVP de misiones diarias (3 encargos + parte).** Es la columna vertebral: sin ledger de eventos, ni facciones, ni eventos de 3 días, ni desbloqueo tienen dónde engancharse. Ya hay un plan (`TASK-043`) y un informe de equipo (`docs/analysis/misiones-y-facciones.md`).
4. **Desbloqueo blando de hábitats.** Convierte el mapa en progreso y ataca el "todo abierto desde el minuto uno". Depende de #3 para tener hitos; puede empezar con una tabla de unlocks y válvula de nivel.
5. **Nombrar líderes + emisor narrativo.** Es el mínimo RPG más barato: catálogo en código, sin tablas nuevas, y hace que gimnasios y misiones tengan quién habla. Precede a cualquier facción.

**Por qué no otras ahora:**
- **Facciones completas (#8):** alto esfuerzo y dependen de #3 y #5; además `TASK-044` ya las diseñó y `docs/analysis/misiones-y-facciones.md` las consolidó. Hacerlas antes de misiones deja arcos sin emisor ni tablero.
- **Legendarios (#9):** requiere decidir captura, unicidad y relación con gimnasios; `TASK-044` advierte del power creep y del rechazo acumulable.
- **PvP (#15):** no hay infraestructura ni modelo de equipos rivales.
- **CP/gear (#13, #14):** solapan Forja y hay cambios locales sin commitear en `CalculadorCP.php`; no conviene construir encima sin consolidar.
- **Eventos de 3 días (#10):** valiosos, pero sin ledger (#3) se implementarían como contadores ad hoc.

---

## 8. Duplicidades, dependencias y riesgos

### Duplicidades y solapamientos

| Fuente | Qué cubre | Relación con este informe |
|---|---|---|
| `TASK-043/analysis.md` | Resumen funcional, progresión, economía, ritmo, narrativa, hueco de misiones | Base de §2-§5; no repetir; misión MVP ya está en su plan |
| `TASK-044/analysis.md` + `architecture.md` | 8 facciones, legendarios, roster, bases, 3 actos, 3v3, tope de rechazo | Cubre #8 y #9; no rediseñar; usar sus cifras y decisiones |
| `TASK-045` / `docs/analysis/misiones-y-facciones.md` | Informe de equipo: misiones (5 piezas, 6 tarjetas), facciones, MVP, riesgos | Documento de referencia; cualquier propuesta nueva debe actualizarlo, no duplicarlo |
| `TASK-046` | Petición de misiones de progresión, eventos de 3 días, control de población, recompensa por nivel | **Solo existe `request.md`; `analysis.md` y `architecture.md` están PENDIENTE.** Este informe cubre el diagnóstico, no el diseño; hay que completar TASK-046 |
| `Tareas_pdte/mejoras` | 8 facciones, equipos de 5, legendarios, regla de rechazo, recompensa de gym, granjas | Superado en formato por `TASK-044` (3v3, 3 actos); conservar como intención |
| `Tareas_pdte/misiones` | Solo cabeceras Diarias/Semanales/Mensuales | Vacío; no hay duplicidad, hay hueco |
| `Tareas_pdte/gyms.txt` | 18 medallas y equipos | Duplica `CatalogoGimnasios.php`; la fuente de verdad es el código |
| `Tareas_pdte/plan ruta.txt` | Roadmap 0.1-0.7: Élite 4 en 0.5, legendarios 0.6, mazmorras 0.7 | Desfasado: mazmorras ya están implementadas (sin contenido); legendarios no |
| `Tareas_pdte/propuesta cp` | Fórmula de CP, campo y job | Parcialmente en curso: `CalculadorCP.php` está modificado sin commit |
| `Tareas_pdte/propuesta mejora craft` | Equipamiento por familia con stats base | Solapa el módulo Forja (46 recetas) y el sistema de objetos de combate |
| `Tareas_pdte/propuesta_evolucion_exploraciones.md` | 2.051 líneas | **No leído**; posible solape con exploración y evolución; verificar antes de proponer cambios allí |
| `Tareas_pdte/tabla legendarios.md` | Lista canónica | Insumo de `TASK-044`, no propuesta nueva |

### Dependencias
- #2, #3, #4, #10 dependen de un **registro de eventos** o de consultas a `pokedex`/logs; hoy solo existe `trainer_combat_log`, `dungeon_log` y la bitácora de exploración (`TASK-043/analysis.md:213,329`).
- #5 y #8 dependen de un **estado de desbloqueo/campaña** por usuario.
- #8 y #9 dependen de decidir la **semántica de captura** (`reclutables` vs `reclutados`) y la **unicidad** de legendario.
- #1 depende de que el scheduler no sea necesario: el estado de mazmorra ya se calcula on-read (`ObtenerPisosMazmorra`).

### Riesgos
- **[Alto] Inflación económica** al sumar recompensas de misión a ruta/entrenador/gimnasio/mazmorra. Mitigación: topes por periodo y pago en la moneda del sistema (`docs/analysis/misiones-y-facciones.md:344-357`).
- **[Alto] Ausencia de idempotencia** en recompensas; repetir un cierre duplica EXP/items (`TASK-043/analysis.md:476-481`).
- **[Alto] Ambigüedad de "captura"**; progreso inconsistente en misiones y Pokédex (`TASK-043/analysis.md:377-381,683`).
- **[Alto] Tres escaleras de jefe** (gimnasio, mazmorra, facción) con la misma textura; riesgo de canibalización (`TASK-044/analysis.md:525`).
- **[Medio] Hábitat 49 sin filtro**: incluye UBs y paradojas; si se abre, el jugador captura legendarios como salvajes normales (`TASK-044/analysis.md:248-253`).
- **[Medio] 12 hábitats vacíos** limitan mazmorras, facciones y metas de zona (`TASK-044/analysis.md:165`).
- **[Medio] Scheduler no garantizado**: no depender de cron para misiones/eventos (`TASK-043/analysis.md:521`).
- **[Medio] Árbol de trabajo sucio**: 26 archivos modificados y TASK-032..047 sin trackear; las citas a `IniciarCombateRuta`, `GeneradorEquipoRuta`, `CalculadorCP`, `PresentadorExploraciones` y rutas reflejan el working tree, no `HEAD`. No tocar.

---

## 9. Preguntas abiertas (máx. 8)

1. ¿El primer objetivo debe seguir siendo el gimnasio de nivel 10, o quieres una meta antes (misión de apertura / recompensa por nivel de hábitat)?
2. ¿Qué va primero: el MVP de misiones diarias (`TASK-043`) o las recompensas por nivel de hábitat (`TASK-046`)?
3. ¿Se siembran mazmorras ya, o se reserva el módulo para las bases de facción de `TASK-044`?
4. ¿El hábitat "Legendarios" se sella hasta el epílogo o se deja accesible por URL?
5. ¿"Capturar" cuenta la cola (`reclutables`) o el reclutado (`reclutados`)?
6. ¿Se adopta el rechazo de ancla con tope 5 de `TASK-044`, o se descarta?
7. ¿CP y equipamiento por familia entran ahora, sabiendo que solapan Forja y hay cambios locales sin commitear?
8. ¿PvP y Élite 4 son objetivo de este ciclo o quedan fuera?

---

## 10. Archivos consultados

**Artefactos de trabajo**
- `.opencode/work/TASK-043/analysis.md` (completo).
- `.opencode/work/TASK-044/analysis.md` (completo) y `architecture.md` (1-180).
- `.opencode/work/TASK-045/` (`analysis`, `plan`, `execution`, `final-report`).
- `.opencode/work/TASK-046/` (`request`, `analysis`, `architecture`, `plan`, `tasks`, `execution`, `final-report`).
- `docs/analysis/misiones-y-facciones.md` (completo).
- `Tareas_pdte/mejoras`, `misiones`, `gyms.txt` (cabecera), `plan ruta.txt`, `propuesta cp`, `propuesta mejora craft`, `tabla legendarios.md` (referenciado).

**Código y configuración**
- `src/Shared/Domain/NivelHelper.php`, `ProbabilidadesShiny.php`, `ProbabilidadCaptura.php`, `PoliticaIvsEvs.php`, `MultiplicadoresDificultad.php`, `EscaladorNivelRival.php`, `ReglasEquipo.php`.
- `config/sendas.php`.
- `src/CombateRuta/App/{IniciarCombateRuta,GeneradorEquipoRuta,OtorgarRecompensasRuta}.php`.
- `src/CombateEntrenadores/App/{ObtenerEntrenadoresHabitat,OtorgarRecompensasEntrenador}.php`; `IniciarCombateEntrenador.php` (grep).
- `src/Gimnasios/Domain/{CatalogoGimnasios,Gimnasio}.php`; `App/{IniciarCombateGimnasio,ObtenerGimnasios,RegistrarResultadoGimnasio}.php`.
- `src/Mazmorras/Domain/ReglasMazmorra.php`; `App/ObtenerPisosMazmorra.php`; `routes/mazmorras.php`.
- `src/Exploraciones/Domain/{CalculadorRecompensas,EconomiaRecompensas}.php`.
- `src/Battle/README.md`; `src/Battle/Domain/Catalog/CatalogoObjetos.php`; `app/Livewire/Combate.php`; `app/Livewire/Presenters/PresentadorResultadoBatalla.php` (gimnasio).
- `src/Forja/Domain/CatalogoRecetas.php` (cabecera); `src/Equipos/README.md`; `src/Habitats/README.md`.
- `routes/web.php`; `app/Http/Controllers/OnboardingController.php`; `resources/views/layouts/app.blade.php` (referenciado por TASK-043).
- Migraciones: `2026_08_29_000010_add_min_lvl_columns_to_habitats_table.php`, `2026_09_06_000001_add_mazmorra_to_habitats_table.php`; seeders: listado y `HabitatSeeder.php` (referenciado).

**Datos**
- `storage/data/pokemon.csv` (media `base_experience` 156,8; 1.176 filas).
- `storage/data/pokemon_habitat.csv` (787 asignaciones; 44 hábitats con pool; nivel 1 media 6,1; nivel 2 media 8,9; nivel 3 media 3,4 y 7 hábitats con 0).
- `storage/data/habitats.csv` (56 hábitats; nombres).

**No leído / pendiente de verificación**
- `Tareas_pdte/propuesta_evolucion_exploraciones.md` (2.051 líneas).
- `docs/architecture.md` completo (usado vía citas de TASK-043).
- `README.md`/`context.md` de la mayoría de módulos.
- Estado real de la BD (`min_lvl`, `mazmorra`, medallas): no se consultó.

---

# Parte C — Addendum mazmorras (2026-11-16)

> **Síntesis verificada del analista.** Lectura estática + consultas de solo lectura a PostgreSQL. No se modificó ningún archivo ni se escribió en la BD. El informe completo se conserva en la sesión del analista; aquí se recogen todas sus conclusiones y cifras.

## C.1 Hallazgo bloqueante: la BD no coincide con el CSV

| Métrica | BD actual | CSV actual |
|---|---|---|
| Filas `pokemon_habitat` | 1006 | 787 |
| Pares solo en BD | 423 | — |
| Pares solo en CSV | — | 204 |
| Pares con `level` distinto | 114 | 114 |
| Hábitats con pool | **56 / 56** | **44 / 56** |

Los "12 hábitats sin pool" y los "7 sin nivel 3" del contexto **solo existen en el CSV**; en la BD todos los hábitats tienen pool y nivel 3. Decisión adoptada: **usar la BD actual**, que es lo que juega el jugador.

## C.2 Contrato y generador

- JSON: `{"pisos":[{"piso":1,"species_id":25}, ...]}`. `ConfiguracionMazmorra::desdeJson()` exige `pisos`, lee `piso`/`species_id`, descarta inválidos, indexa por número (el último piso repetido gana) y ordena (`ConfiguracionMazmorra.php:32-50`).
- `GeneradorJefeMazmorra::generar()` resuelve `Pokemon::where('species_id', $speciesId)->first()` sin `orderBy` (`GeneradorJefeMazmorra.php:29-33`).
- En la BD actual `id == species_id` en las 1083 filas y `species_id` no tiene duplicados. El `first()` es seguro hoy, pero no hay índice único que lo garantice.
- El número de piso debe ser contiguo 1..N: `IniciarCombateMazmorra` valida `piso <= totalPisos()` y `ObtenerPisosMazmorra` recorre 1..actual.

## C.3 Stats y BST

Verificado: 1083 Pokémon, **0** con distinto de 6 stats, **0** sin stats, **0** stats inválidos o duplicados; 6498 filas = 1083 × 6. `StatEnum` cubre 1..6 (`StatEnum.php:9-14`).

BST seguro = `SUM(base_stat)` con `stat BETWEEN 1 AND 6` agrupado por `pokemon_id`. En PHP, `stats->sum('base_stat')` es equivalente porque hay exactamente 6 filas válidas.

## C.4 Cobertura real (BD)

- 56 hábitats (ids 1–58, sin 32 ni 41); 55 elegibles excluyendo el 49.
- 887 filas fuera del 49; **3** son de ids excluidos (132 Ditto, 201 Unown, 235 Smeargle) → **884 pisos** tras exclusión.
- Sin duplicados de especie dentro de un hábitat (`id == species_id` y unique `(pokemon_id, habitat_id)`).
- Distribución por nivel (incluyendo las 3 filas excluidas): N1 276, N2 312, N3 299. Tras exclusión ≈ 275/311/298 (desviación máxima ±3, no verificada por hábitat).
- Sin nivel 3: **0** hábitats. Pisos por hábitat: mínimo 9, máximo 25, mediana ≈ 17; el 49 tendría 119 y se excluye.
- Sin huérfanos de `pokemon_id` ni `habitat_id`.
- Las formas regionales son `species_id` propios (p. ej. `10174 zigzagoon-galar`); decisión adoptada: **un piso por forma**.

## C.5 Persistencia e idempotencia

- No hace falta migración: `habitats.mazmorra` es `json nullable` (`2026_09_06_000001_add_mazmorra_to_habitats_table.php:18`) y el modelo la castea a `array` (`Habitat.php:30`).
- **Es `json`, no `jsonb`**: usar `IS NULL` / `IS NOT NULL` y `json_array_length`; las comparaciones con `'null'` fallan.
- `HabitatSeeder` no toca `mazmorra` (`HabitatSeeder.php:22-33`).
- Registrar en `CatalogoSeeder` **después de `PokemonHabitatSeeder`**, porque este borra las filas de 132/201/235 (`PokemonHabitatSeeder.php:18,28-30`).
- Escribir solo donde `mazmorra IS NULL`; hoy hay 0 configuradas. Re-ejecutar es no-op.
- El seeder debe excluir explícitamente 132/201/235: la BD aún conserva 3 filas de esos ids.

## C.6 Verificación posterior (solo lectura)

```sql
-- Hábitats y pisos
SELECT count(*) FROM habitats WHERE mazmorra IS NOT NULL;                 -- 55
SELECT sum(json_array_length(mazmorra->'pisos')) FROM habitats WHERE mazmorra IS NOT NULL; -- 887 (ver C.9)
SELECT count(*) FROM habitats WHERE id = 49 AND mazmorra IS NULL;         -- 1

-- Numeración contigua
SELECT h.id FROM habitats h, json_array_elements(h.mazmorra->'pisos') WITH ORDINALITY t(p,o)
WHERE (p->>'piso')::int <> t.o;                                           -- 0 filas

-- Species existentes y sin excluidos
SELECT count(*) FROM habitats h, json_array_elements(h.mazmorra->'pisos') p
WHERE NOT EXISTS (SELECT 1 FROM pokemon pk WHERE pk.species_id = (p->>'species_id')::int); -- 0
SELECT count(*) FROM habitats h, json_array_elements(h.mazmorra->'pisos') p
WHERE (p->>'species_id')::int IN (132,201,235);                           -- 0

-- Orden por nivel y BST (window functions)
WITH pisos AS (
  SELECT h.id AS habitat_id, (p->>'piso')::int AS piso, (p->>'species_id')::int AS species_id
  FROM habitats h, json_array_elements(h.mazmorra->'pisos') p WHERE h.mazmorra IS NOT NULL
), datos AS (
  SELECT pi.habitat_id, pi.piso, pi.species_id, ph.level, sum(ps.base_stat) AS bst
  FROM pisos pi
  JOIN pokemon_habitat ph ON ph.habitat_id = pi.habitat_id AND ph.pokemon_id = pi.species_id
  JOIN pokemon_stats ps ON ps.pokemon_id = ph.pokemon_id AND ps.stat BETWEEN 1 AND 6
  GROUP BY pi.habitat_id, pi.piso, pi.species_id, ph.level
), con_previo AS (
  SELECT d.*, lag(d.level) OVER (PARTITION BY d.habitat_id ORDER BY d.piso) AS prev_level,
         lag(d.bst) OVER (PARTITION BY d.habitat_id ORDER BY d.piso) AS prev_bst
  FROM datos d
)
SELECT * FROM con_previo
WHERE prev_level IS NOT NULL AND (level < prev_level OR (level = prev_level AND bst < prev_bst)); -- 0 filas
```

## C.7 Riesgos

| # | Severidad | Riesgo | Mitigación |
|---|---|---|---|
| R1 | Alto | BD y CSV divergen | Fuente = BD (decidido) |
| R2 | Alto | 3 filas de 132/201/235 siguen en el pivote | Excluir explícitamente en el seeder |
| R3 | Medio | `species_id` sin índice único y `first()` sin orden | Seeder resuelve desde BD; deuda documentada |
| R4 | Medio | Columna `json`, no `jsonb` | Usar `IS NULL` y `json_array_length` |
| R5 | Medio | Orden de seeders | Registrar después de `PokemonHabitatSeeder` |
| R6 | Bajo | 20+ pisos secuenciales por hábitat | La UI solo muestra hasta el piso actual |
| R7 | Bajo | Formas regionales como pisos propios | Aceptado por producto |
| R8 | Bajo | `HabitatSeeder` renombraría 57/58 | Cosmético; no afecta a `mazmorra` |

## C.8 Decisiones adoptadas

1. Fuente: **BD actual** (`pokemon_habitat` + `pokemon` + `pokemon_stats`), no CSV.
2. Un piso por forma/especie de la BD.
3. Excluir hábitat 49 y especies 132/201/235.
4. BST ascendente dentro de cada nivel 1→2→3; desempate por `species_id` ascendente.
5. Escribir solo donde `mazmorra IS NULL`.
6. Registrar el seeder después de `PokemonHabitatSeeder`.
7. Sin migración, sin tests, sin cambios de UI.


## C.9 Corrección verificada post-ejecución (2026-11-16)

La estimación de 884 pisos de C.4 era incorrecta: las 3 filas de las especies
132/201/235 están **dentro del hábitat 49** (`habitat_id=49`, `level=2`), que ya se
excluye. Las **887** filas fuera del 49 son todas elegibles. Resultado real tras
ejecutar el seeder: **55 hábitats / 887 pisos**. La regla funcional no cambia.
