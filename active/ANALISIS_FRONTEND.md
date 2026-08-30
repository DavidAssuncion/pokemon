# Análisis Frontend — Pokédex asíncrona (datagrid server-side) + lazy loading de iconos

## Contexto

El BACKEND implementa en paralelo la API Datagrid (`GET /datagrid/pokemon`, `GET /datagrid/pokemon/{id}/detalle`) y refactoriza `PlayerController::pokedex()` para pasar SOLO datos iniciales (primera página + `meta.counts`). Yo (FRONTEND) NO toco controladores. Verificado con `php artisan route:list`: la ruta `datagrid` NO existe aún → asumo el contrato acordado y documento la dependencia.

## Vistas a tocar

### 1. `resources/views/pokedex/index.blade.php` — REWORK COMPLETO (asíncrono)
- Pestañas server-side: **Vistos** (default) / No vistos / Atrapados → `filter[visto]=1`, `filter[visto]=0`, `filter[atrapado]=1`. Cada cambio resetea paginación y re-fetcha.
- Header con contadores desde `meta.counts` (total / vistos / atrapados), actualizados tras cada fetch.
- Scroll infinito: IntersectionObserver sobre `x-ref="sentinel"` (rootMargin 300px), flag `loading`, dedupe por id (Set), indicador "No hay más Pokémon" cuando `page >= last_page`.
- Búsqueda `@input.debounce.300ms` + AbortController (cancela fetch anterior al escribir). Dropdown de tipos conservado pero ahora server-side (`filter[types]=<label>`).
- Grid de cards:
  - NO visto → placeholder CSS (div con gradiente `bg-linear-to-br` + "?"), SIN `<img>` (no descarga icono).
  - Visto → `<img :src="iconUrl(pokemon)" @error="onIconError(...)">` con `loading="lazy"` + `decoding="async"`. `iconUrl` usa `pokemon.icon` (webp según contrato) y `onIconError` cae a `.png`; si el png también falla, oculta la imagen.
  - Badge ★ atrapados, hover/transición, role="button", keydown, aria-labels (conservados).
- Modal detalle bajo demanda: fetch `GET /datagrid/pokemon/{id}/detalle` al abrir; skeleton (animate-pulse) mientras carga; error visible con botón Reintentar; renderiza tipos (`getTypeClass`), barras stats (`getStatColor`) y hábitat.
  - **Decisión**: si `!pokemon.visto` NO se hace fetch (no descargar datos de no avistados); se muestra "Este Pokémon aún no ha sido avistado." Documentado en riesgos para validación del Arquitecto.
- Estados vacíos por pestaña (mensaje según tab; si hay search/type activos → "No se encontraron Pokémon").
- Eliminado filtrado en cliente (todo por API). Guard defensivo: el seed inicial se filtra por la pestaña default (vistos) por si el backend entrega página sin filtro (consistencia visual durante la transición).
- Fallback de contadores: si `$counts` no llega (backend no actualizado), se calculan del seed.

### 2. Lazy loading (solo añadir atributos, NO cambiar URLs)
| Vista | Imgs |
|---|---|
| `equipos/index.blade.php` | 4 (miembro, disponible, asignado, tooltip) |
| `reclutamiento/index.blade.php` | 1 |
| `reclutados/index.blade.php` | 1 |
| `habitats/show.blade.php` | miembros equipo (1) + niveles (1) + 3 botones construcción (iconos) |
| `habitats/_level-preview.blade.php` | 1 |
| `livewire/habitats/family-modal.blade.php` | 4 (3 cards familia + preview nivel) |

## DTOs consumidos (contrato asumido)

```
GET /datagrid/pokemon?page&per_page=120&search&filter[visto]&filter[atrapado]&filter[types]&sort=id&order=asc
→ { data: [{id, name, visto, atrapado, types[], icon}], meta: {total, page, per_page, last_page, counts:{total, vistos, atrapados, no_vistos}} }
GET /datagrid/pokemon/{id}/detalle
→ { id, name, visto, atrapado, types[], stats:[{name,value}], habitat_name }
```

Vista inicial desde Blade: `$pokemons` (primera página), `$counts` (opcional, `meta.counts`), tipos vía `\App\Enums\TipoEnum::options()` (ya disponible).

## Tests

- Dusk NO está instalado (no existe `tests/Browser`). Documentado; se sustituye por test de feature de render:
  - **Nuevo** `tests/Feature/PokedexViewTest.php` (aislado, sin tocar el test del backend): render de `/pokedex` verifica marcadores del nuevo componente (x-data pokedexApp, sentinel, loading lazy, decoding async, tab vistos) y que la vista compila con el contrato inicial.
- Verificación: `php artisan view:clear` (y `view:cache`), `php artisan test --compact --filter=PokedexViewTest`, `--filter=PlayerController` (estado; puede fallar si backend no termina — se documenta).

## Estados UI cubiertos

- Grid: inicial (seed), loading más (spinner), no hay más, vacío por pestaña, error de fetch con reintento (banner no bloqueante).
- Iconos: visto con icon (webp→png fallback), visto sin icon (png directo), png faltante (se oculta), no visto (placeholder CSS sin img).
- Modal: loading skeleton, error+reintento, no avistado (sin fetch), detalle completo (tipos/stats/hábitat).

## Riesgos accesibilidad/UX

- **Webp no existe aún** en `public/images/iconos/` (0 archivos .webp, 1032 .png): `onIconError` hará 1 request extra 404 por icono si el backend sirve `.webp` sin generarlos. Mitigación implementada; validar con backend cuándo generan webp.
- **Transición con backend viejo**: al hacer scroll, fetch al datagrid fallará (404) → banner de error no bloqueante (la lista sigue visible). Se resuelve cuando el backend despliegue.
- `loading="lazy"` en imágenes de tooltip/modal: inofensivo (están en viewport al mostrarse).
- `bg-linear-to-br` es la utilidad Tailwind v4 (renombrada de bg-gradient-to-br); se combina con `bg-gray-200` como fallback visual si la clase no existiera.
- AbortController: se aborta el fetch anterior al resetear (tab/search/type) para evitar carreras; guard `controller === this.controller` en finally evita que respuestas obsoletas limpien `loading`.
- Modal: role="dialog", aria-modal, aria-labelledby, escape/backdrop cierran; aria-busy en carga.

---

# Análisis Frontend — Página "Exploraciones activas" (/exploraciones)

## Fecha
2026-08-27

## Contexto
Nueva página `/exploraciones` (GET). El backend agent añadirá la ruta + método en `ExploracionActivaController` (o PlayerController) y pasará `['activas' => [...], 'terminadas' => [...]]` con el contrato documentado en la tarea. Yo (FRONTEND) NO toco controladores ni rutas; la vista consume el contrato asumido y se verifica por render directo (`$this->view()`), no por request HTTP (la ruta aún no existe → solo `POST /exploraciones` existe hoy).

## Vistas/componentes a tocar
1. **`resources/views/exploraciones/index.blade.php`** (NUEVO) — página completa:
   - Header: "Exploraciones" + subtítulo "Gestión de expediciones de tus equipos".
   - Sección Activas: cards con equipo→hábitat, badge Nivel X, badge estado (Explorando azul / Volviendo naranja), barra progreso, línea de tiempos (Inicio/Vuelta/Fin vía JS `Date`), botón rojo "Recoger resultados" SOLO si `indefinido`, bitácora colapsable (Alpine `x-data="{ open: false }"` + `x-show` + `x-cloak`; NO `x-collapse`, Alpine se carga core vía Vite).
   - Sección Terminadas: cards con resumen de resultados (avistados, capturados con badge ×N, caramelos familia, caramelos EV, EXP) + botón "Cerrar resultados".
2. **`resources/views/layouts/app.blade.php`** — añadir nav item `['route' => '/exploraciones', 'label' => 'Exploraciones']` después de Hábitats.

## DTOs/contrato consumido (asumido; el backend debe emitir EXACTAMENTE esto)
```
[
  'activas' => [[
     'id' => int, 'equipo' => string, 'habitat' => string, 'habitat_id' => int, 'nivel' => int,
     'indefinido' => bool, 'duracion_horas' => ?int,
     'inicio' => ?string(ISO8601), 'inicio_vuelta' => ?string(ISO8601), 'fin' => ?string(ISO8601),
     'estado' => 'explorando'|'volviendo', 'progreso' => int 0-100,
     'bitacora' => [[ 'tipo' => 'pokemon', 'pokemon_id' => int, 'nombre' => string, 'timestamp' => ?ISO8601 ]
                  |[ 'tipo' => 'caramelo_familia', 'nombre' => string, 'cantidad' => int, 'timestamp' => ?ISO8601 ]
                  |[ 'tipo' => 'caramelo_ev', 'stat' => int, 'stat_nombre' => ?string, 'cantidad' => int, 'timestamp' => ?ISO8601 ]],
  ]],
  'terminadas' => [[
     'id' => int, 'equipo' => string, 'habitat' => string, 'nivel' => int,
     'resultado' => [
        'avistados' => [['pokemon_id' => int, 'nombre' => string]],
        'capturados' => [['pokemon_id' => int, 'nombre' => string, 'cantidad' => int]],
        'caramelos_familia' => [['evolution_chain_id' => int, 'nombre' => string, 'cantidad' => int]],
        'caramelos_ev' => [['stat' => int, 'stat_nombre' => string, 'cantidad' => int]],
        'exp' => int,
     ],
  ]],
]
```
- Endpoints POST asumidos (backend agent): `/exploraciones/{id}/recoger` (indefinidas) y `/exploraciones/{id}/cerrar` (terminadas). La vista los llama con `fetch` + header `X-CSRF-TOKEN` + `location.reload()` al OK; alert con `body.message` si falla.

## Tests (Dusk NO instalado — ver tests/Browser inexistente; se sustituye por feature test de render, precedente: PokedexViewTest)
- **NUEVO** `tests/Feature/ExploracionesViewTest.php`:
  1. Card activa: equipo/hábitat/Nivel, badge estado (Explorando + Volviendo), barra progreso, bitácora (pokemon/caramelo_familia/caramelo_ev).
  2. Resultados: avistados/capturados ×N/caramelos/EXP y botón Cerrar → `/exploraciones/{id}/cerrar`.
  3. Estados vacíos: activas vacías ("No hay exploraciones activas" + link /habitats) y terminadas vacías.
  4. "Recoger resultados" SOLO con `indefinido=true` (assertDontSee en caso contrario).

## Estados UI cubiertos
- Activas: con cards (explorando/volviendo, indefinida con botón rojo) / vacío (empty state con CTA a hábitats).
- Bitácora: colapsada por defecto, expansible; eventos pokemon (imagen), caramelos familia, caramelos EV (con `stat_nombre` o fallback JS `statName()`), evento desconocido (fallback genérico), sin bitácora (mensaje "Sin eventos").
- Terminadas: con resultado completo / resultado parcial (bloques ausentes se omiten) / resultado vacío ("Sin resultados") / sin terminadas (empty state).
- Timestamps nulos/inválidos → `--:--` (defensa en `fmtTime`).

## Riesgos accesibilidad/UX
- `fmtTime` con `toLocaleTimeString('es-ES')` → hora local; los ISO del backend son UTC (Z): se convierte a hora local del navegador (consistente con reloj del jugador). Documentado para validación del Arquitecto.
- Iconos pokémon: `onerror` oculta si falta el PNG (patrón existente) + `loading="lazy" decoding="async"`.
- Botón bitácora: `aria-expanded` + `aria-controls` (id único por card).
- "Cerrar resultados" elimina el registro → `window.confirm` antes del POST.
- `@forelse(($activas ?? []) as $exp)` defensivo: si el backend aún no entrega la clave, la vista no revienta (count(null) en PHP 8 lanzaría TypeError).

---

# Análisis Frontend — Modal Admin-Gestión de hábitat: pestaña "Ya Asignados" por niveles + optimización sin refresco

## Fecha
2026-08-29

## Contexto
El BACKEND añade en paralelo `PATCH /api/habitats/{habitatId}/pokemon/{pokemonId}` (body `{level:1|2|3}`, `X-CSRF-TOKEN`, `Accept: application/json`, 200 OK / 422 {message}). La ruta aún NO existe en `routes/habitats.php` (verificado) y el repo ya tiene `movePokemonToLevel()` + `DTOPokemonNivelActualizado` → contrato asumido. Yo (FRONTEND) toco SOLO `resources/views/habitats/show.blade.php`.

## Vistas a tocar
1. **`resources/views/habitats/show.blade.php`** (única):
   - Renombrar label pestaña interna 'unassign': "Desasignar" → **"Ya Asignados"** (el valor interno `gestionTab === 'unassign'` se conserva para no tocar la lógica).
   - Reestructurar la pestaña: 3 secciones (Nivel 1/2/3). Dentro de cada una, una tarjeta por CADA pokémon (base + evoluciones) cuyo `level` encaje. Tarjeta = icono webp con onerror + nombre + selector de nivel (3 botones 1/2/3, azul el activo) + X **solo en la tarjeta base** (quita TODA la familia).
   - Getter Alpine `assignedByLevel` que agrupa `assignedFamilies` → `{1:[],2:[],3:[]}` decorando cada entrada con `evolution_chain_id` (para PATCH/X) e `is_base`.
   - **Optimización "sin refresco pesado"**: `openGestionModal()` sigue cargando ambas listas SOLO al abrir. `assignFamily`/`removeFamily`/nuevo `updatePokemonLevel` mutan estado local y NO llaman a `load*()`.

## DTOs consumidos (contrato verificado en src/)
```
GET /api/habitats/unassigned-families → [{evolution_chain_id, base:{id,name,icon}, evolutions:[{id,name,icon}], types:[{id,name}]}]
GET /api/habitats/{id}/families       → [{evolution_chain_id, base:{id,name,icon,level}, evolutions:[{id,name,icon,level}]}]  (SIN types)
POST /api/habitats/{id}/families      → 201 {habitat_id, evolution_chain_id, assigned_count}  (NO devuelve la familia → reconstruyo local)
DELETE /api/habitats/{id}/families/{chainId} → 200 {habitat_id, evolution_chain_id, removed_count}
PATCH /api/habitats/{id}/pokemon/{pokemonId} → 200 (body no usado) / 422 {message}
```
- `level` llega en base/evolutions del endpoint "families" (repo `levelForStage`: totalStages===1 → 2; si no, min(stage,3)).
- Al **asignar**, el POST no devuelve la familia: reconstruyo el entry con `buildAssignedFamilyFromUnassigned` (aprox. client-side del `levelForStage` del backend para cadenas lineales; self-heals al reabrir el modal, que sí recarga). Defensivo: si el body trajera `evolution_chain_id`+`base`, lo uso directo.
- Al **quitar**, reconstruyo el entry de `unassignedFamilies` desde el objeto asignado (le quito `level` y pongo `types: []`, porque el DTO asignado no trae types). Los chips de tipos de esa familia no aparecerán hasta reabrir (sancionado: "reconstruirla desde el objeto que tenías").
- Al **mover nivel**, muto `family.base.level` / `family.evolutions[i].level` (proxy reactivo Alpine → el getter re-agrupa solo).

## Estados UI cubiertos
- Ya Asignados: vacío global ("No hay familias asignadas"), vacío por nivel ("Sin pokémon en este nivel" solo si hay familias pero ninguna en ese nivel), carga (`gestionLoading` deshabilita X y selectores, opacity).
- Selector de nivel: estado activo (azul) vs inactivo (gris); deshabilitado durante operaciones.
- Errores: cualquier !response.ok → `alert(err.message)` y SIN mutar estado (asignar/quitar/mover).
- Asignar: intacta (filtros búsqueda/tipo, chips de types, grid, empty); solo cambia que `assignFamily` ya no recarga.

## Riesgos accesibilidad/UX
- X solo en la tarjeta base: `aria-label="Quitar la familia completa de {nombre}"`, `title="Quitar familia completa"` para dejar claro que retira TODA la familia (requisito negocio). La base puede haberse movido de nivel vía PATCH → la X la sigue (is_base, no `level`).
- `assignedByLevel` descarta entradas sin level 1-3 válido (defensa contra objetos mínimos); con `buildAssignedFamilyFromUnassigned` nunca se produce en el flujo normal.
- Repetición del div del selector: 3 botones hardcodeados (patrón `[1,2,3]` del panel Niveles existente) en vez de `x-for` anidado para evitar problemas de scope/keys en Alpine.
- Andes: `Number(family.base?.id)` etc. defensivos (base siempre existe en DTO pero no cuesta).
- Posible discrepancia temporal de niveles al asignar (aprox. lineal) → se corrige al reabrir el modal.

## Verificación (sin entorno de tests frontend; Dusk no instalado)
- Lectura cuidadosa: escapes Blade (`{{ }}`, `@json` intactos), `x-for`/`x-if`/`:key`/`:class`/`x-text` correctos, URLs con `{{ $habitat['id'] }}` como ya hace el archivo.
- `php artisan view:cache` (compila todas las vistas; si alguna otra falla, se reporta) y revisión `git diff`.
- NO ejecuto `npm run build` (no toco assets ni manifest).

---

# Análisis Frontend — Header: badge de nivel + barra de progreso 5px como border inferior

## Fecha
2026-08-27

## Contexto
El BACKEND comparte vía `View::share` dos variables globales: `$nivelJugador` (int) y `$progresoNivel` (int 0-100). El header del layout `layouts/app.blade.php` debe mostrar el nivel junto al toggle de tema y representar el progreso 0-100 como el borde inferior del header (5px).

## Vistas a tocar
1. **`resources/views/layouts/app.blade.php`** (único cambio):
   - Header: quitar `border-b border-gray-200 dark:border-gray-700` (el borde inferior pasa a ser la barra de progreso), añadir `relative` (el header ya es `sticky`, que es positioned; `relative` es explícito y en Tailwind v4 `sticky` gana el conflicto de orden → no pierde el comportamiento sticky).
   - Reestructurar el área derecha: contenedor flex `absolute right-0 top-1/2 -translate-y-1/2 flex items-center gap-2` con badge de nivel (`Nv X`, azul, rounded-full, title "Nivel X") + el botón de toggle dark EXISTENTE sin tocar su `x-data`/`@click`.
   - Barra de progreso: capa inferior absoluta `bottom-0 left-0 right-0 h-[5px]` con fondo `bg-gray-200 dark:bg-gray-700` (sustituye al separador) y fill `bg-blue-500 transition-all duration-500` con `width: {{ $progresoNivel ?? 0 }}%`.
   - Fallback defensivo: `{{ $nivelJugador ?? 1 }}` y `{{ $progresoNivel ?? 0 }}`.

## DTOs/variables consumidas
```
View::share: $nivelJugador (int), $progresoNivel (int 0-100)
```

## Tests (Dusk NO instalado — se sustituye por feature test de render, precedente: PokedexViewTest/ExploracionesViewTest)
- **NUEVO** `tests/Feature/HeaderNivelViewTest.php`:
  1. Render del layout con `$nivelJugador=7, $progresoNivel=45` → assertSee "Nv 7", título "Nivel 7", width 45%.
  2. Render SIN variables (simula render fuera del share) → no revienta; badge "Nv 1" (fallback), width 0%.
  3. `$progresoNivel=0` y `100` → width 0% / 100%.

## Estados UI cubiertos
- Badge: nivel normal (Nv X), fallback sin variable (Nv 1).
- Barra: progreso 0% (solo track gris), 50%, 100% (fill completo azul), track gris siempre presente (separador visual).
- Toggle dark: intacto (mismas clases `x-data`/`@click`/aria-label).

## Riesgos accesibilidad/UX
- Barra decorativa → `aria-hidden="true"` (el título del badge ya comunica el nivel).
- Contenedor flex absoluto: en viewports muy pequeños el badge + toggle podrían solaparse con los links centrados. El nav ya tiene `overflow-x-auto` en los links; el badge es compacto (`px-2.5 py-1 text-xs`). Aceptado: patrón común; si el Arquitecto quiere, se puede ocultar el badge en `sm:` con `hidden sm:inline-flex` — NO aplicado por defecto para no inventar convenciones.
- `title` en el badge da contexto semántico ("Nivel X") — accesible vía tooltip nativo.

---

# Análisis Frontend — Iconos de caramelos (bitácora + resultados) y alineación de botones de construcción

## Fecha
2026-08-29

## Contexto
El BACKEND en paralelo añade claves a eventos/resultados: `caramelo_ev.stat_slug` (hp|atk|def|atksp|defsp|spd), `caramelo_familia.pokemon_id` (bitácora y resultados), `caramelos_familia[].pokemon_id` (id del miembro de menor species_id), `caramelos_ev[].stat_slug`. Assets verificados en `public/images/`:
- `candy_ev/{hp,atk,def,atksp,defsp,spd}.webp` — EXISTEN (6).
- `candy_pokemon/{id}.webp` — EXISTE (parcial, incluye `0.webp` genérico).
- `candy_type/{slug}.webp` — EXISTEN (18 slugs español).
- `type_candy/` — NO EXISTE (ruta rota confirmada; la vista apunta ahí con .png).

Contrato asumido (aditivo; la vista debe seguir funcionando si el backend aún no entrega las claves nuevas): `?pokemon_id`, `?stat_slug` se leen con `?? ''` / `!empty()` y el fallback visual cubre la ausencia.

## Vistas a tocar (solo 2; NO tocar PHP ni tests — instrucción explícita del usuario)

### 1. `resources/views/exploraciones/index.blade.php`
- **Bitácora `caramelo_familia`** (~L112-119): SVG amber → `@if(!empty($evento['pokemon_id']))` `<img src="/images/candy_pokemon/{id}.webp">` (w-10 h-10, lazy/async, alt/title "Caramelo de {nombre}", onerror→`/images/candy_pokemon/0.webp` + `this.onerror=null`) `@else` SVG amber actual `@endif`. Texto "Caramelos de {nombre} ×N" intacto.
- **Bitácora `caramelo_ev`** (~L120-132): SVG cyan → `@if(!empty($evento['stat_slug']))` `<img src="/images/candy_ev/{stat_slug}.webp">` (mismo patrón, alt/title "Caramelo EV {stat_nombre}") `@else` SVG cyan actual `@endif`. Texto "Caramelo EV {nombre} ×N" (con fallback JS `statName` existente) intacto. Timestamp intacto.
- **Resultados `caramelos_familia`** (~L246-256): SVG amber → `<img src="/images/candy_pokemon/{{ $caramelo['pokemon_id'] ?? 0 }}.webp">` (w-8 h-8, alt/title "Caramelo de {nombre}", onerror→0.webp). Texto "Caramelos de {nombre}" + ×N intacto.
- **Resultados `caramelos_ev`** (~L265-274): SVG cyan → `<img src="/images/candy_ev/{{ $caramelo['stat_slug'] ?? '' }}.webp">` (w-8 h-8, alt/title "Caramelo EV {stat_nombre}", onerror→0.webp). Texto + ×N intacto. Si `stat_slug` vacío → `/images/candy_ev/.webp` 404 → onerror cae a placeholder 0.webp (defensivo, sin rama @if porque el 404 es instantáneo y el placeholder es el mismo).
- **Resultados `caramelos_tipo`** (~L288): CORRECCIÓN DE RUTA `type_candy/{slug}.png` → `candy_type/{slug}.webp`; onerror cambiado del patrón "ocultar" al patrón placeholder (`0.webp`) para cumplir "SIEMPRE imagen con fallback".

### 2. `resources/views/habitats/show.blade.php` — alineación de los 4 botones apilados (L39-75)
- Problema: `flex items-center justify-center gap-2` con icono+texto inline → el icono se desalinea según la longitud del texto (el centro del icono no coincide con el centro del bloque icono+texto).
- Patrón: `$constructionButtonClass` (L9) pasa de `flex items-center justify-center gap-2` a `flex items-center gap-3`; cada botón: icono envuelto en `<span class="w-8 shrink-0 flex justify-center">` y texto en `<span class="flex-1 text-sm text-center font-medium text-gray-700 dark:text-gray-300">` (Admin: `font-bold uppercase tracking-wide`). El SVG engranaje w-6 h-6 queda centrado en el slot de 32px. Se conservan TODAS las clases de color/estado, `@click`, `disabled`, `title`.

## Estados UI cubiertos
- Bitácora: `pokemon_id` presente (img real) / ausente (SVG amber fallback); `stat_slug` presente (img EV) / ausente (SVG cyan fallback); asset faltante en servidor → onerror → `0.webp` genérico (y `onerror=null` evita loop infinito si 0.webp faltara).
- Resultados: idem con `?? 0`/`?? ''` + onerror placeholder; caramelos tipo con ruta corregida y placeholder.
- Botones hábitat: alineación idéntica para "Granjas", "Entrenadores", "Mazmorras" (bloqueados con disabled/opacity si hay exploraciones activas) y "Admin - Gestion" (siempre activo, azul) — estructura de slot igual en los 4.

## Riesgos accesibilidad/UX
- `alt`/`title` descriptivos en todos los `<img>` nuevos; `loading="lazy" decoding="async"` (patrón existente).
- `onerror="this.src=...; this.onerror=null;"` → si el placeholder genérico también fallara no hay bucle de reemplazo (la imagen queda rota pero sin peticiones infinitas).
- Los SVG de fallback conservan `aria-hidden="true"` y `shrink-0` (sin cambio de tamaño `w-5 h-5` para no alterar la línea de la bitácora).
- Botones: el slot `w-8` fijo + `flex-1 text-center` centra el texto en el área restante; el icono queda alineado verticalmente entre los 4 botones (misma posición x en todos).

## Verificación (sin Dusk instalado y sin tocar PHP — instrucción del usuario)
- Lectura cuidadosa del Blade: `@if/@else/@endif` balanceados (nueva rama en bitácora), `{{ }}` correctos, comillas en atributos onerror (comillas simples dentro de dobles).
- `php artisan view:cache` (compila todas las vistas; reporta si algo no compila).
- `git diff` para revisar que solo cambiaron las 2 vistas (+ este análisis).
- NO `npm run build` (no toco assets).

---

# Análisis Frontend — Multi-jugador FASE 2: auth (login/register), header con usuario y bloqueo de niveles por min_lvl

## Fecha
2026-08-29

## Contexto
El proyecto se convierte a multi-jugador. FASE 1 (datos) commiteada (`03d8457`): tablas con
`user_id`, `reclutados.exp` es cast DTO (`ExpReclutado`), `player_inventory` con `item_key`,
`habitats.min_lvl_1/2/3` nullable (solo columnas; la lógica la añade el BACKEND en paralelo, fase C).
Los BACKENDS B (auth), C (min_lvl) y D (inventario) corren EN PARALELO. Yo (FRONTEND) NO toco
PHP (controladores/rutas/modelos). Verificado: `routes/*` NO tienen auth aún; `$nivelJugador`/
`$progresoNivel` se comparten vía `AppServiceProvider::boot` (hoy con `User::first()`, el backend B
lo cambiará al usuario autenticado); `DTOHabitatDetalle` aún NO expone `min_lvl_*` (backend C).

## Vistas a tocar

### 1. `resources/views/layouts/auth.blade.php` (NUEVO) + `auth/login.blade.php` + `auth/register.blade.php` (NUEVAS)
- Layout auth: mismo head que `layouts/app` (csrf meta, vite, x-cloak, script de tema) pero SIN
  header/nav: body centrado (`min-h-screen flex items-center justify-center`), card max-w-md,
  dark mode, flash `session('status')` + `session('success'/'error')` tolerantes.
- Formularios POST a `/login` y `/register` (URLs planas, NO `route()`: las rutas las crea el
  backend B y `route()` reventaría si el nombre aún no existe). Campos: name + password (login);
  name + password + password_confirmation (register). Errores con guard `isset($errors)` (tolerante
  a render directo de test donde el middleware `ShareErrorsFromSession` NO comparte `$errors`),
  `old('name')`, link cruzado login↔register.

### 2. `resources/views/layouts/app.blade.php` — header con usuario autenticado
- Bloque derecho (junto al badge Nv y toggle dark): `@auth` → nombre (`auth()->user()->name`,
  `hidden sm:inline-flex` + truncate) + form POST `url('/logout')` con `@csrf` y botón "Salir";
  `@guest` → nada (preview). Badge Nv y barra de progreso intactos.

### 3. `resources/views/habitats/show.blade.php` — bloqueo visual por nivel (min_lvl)
- Datos desde Blade al componente Alpine `habitatShow()` (mutación mínima):
  - `minLvls: { 1: <min_lvl_1|null>, 2: ..., 3: ... }` desde `$habitat['min_lvl_N'] ?? null`.
  - `nivelJugador: {{ $nivelJugador ?? 1 }}`, `avisoNivel: ''`.
- Nuevo método `levelBlocked(level)`: `min !== null && nivelJugador < min`.
- `selectLevel(level)`: si bloqueado → NO selecciona, setea `avisoNivel` ("Requiere Nv X...") y
  retorna; si libre → limpia aviso y sigue.
- `checkAndOpenModal()`: guard defensivo si `selectedLevel` quedara bloqueado (no abre modal).
- `canStartExploration` getter: añade `!this.levelBlocked(this.selectedLevel)`.
- Panel Niveles (Blade, por nivel): badge naranja "Requiere Nv X" + icono candado (SVG ya existente
  en el archivo), clases `opacity-60 cursor-not-allowed border-dashed`, `disabled` + `title`.
- Aviso inline bajo el header del panel: `x-show="avisoNivel" x-cloak x-text="avisoNivel"` (aria-live
  no: role="status" basta; patrón del proyecto).

### 4. `resources/views/exploraciones/index.blade.php` — badge requisito (tolerante)
- En cards Activas y Terminadas, junto al badge "Nivel X": si `($exp['min_lvl'] ?? null) !== null`
  y `($nivelJugador ?? 1) < min_lvl` → badge naranja "Requiere Nv X" (SVG candado, sin emoji).
  Lógica de recogida/bitácora INTACTA.

### 5. `resources/views/reclutado/show.blade.php` — defaults defensivos (sin cambio visual)
- `$requisito['actual'] ?? 0`, `['necesario'] ?? 0`, `['caramelosDisponibles'] ?? 0` (texto y
  condición `disabled`), `$reclutado['exp_total'] ?? 0`. El shape del contrato (tipo/slug/necesario/
  actual/caramelosDisponibles) lo calcula el backend D desde `player_inventory`; la UI no cambia.

## DTOs/variables consumidas (contrato asumido)
```
$nivelJugador (int), $progresoNivel (int 0-100) — View::share (backend B: del usuario autenticado)
auth()->user()->name — Blade; POST /logout con CSRF (backend B)
$habitat['min_lvl_1'|'min_lvl_2'|'min_lvl_3'] — int|null (backend C; HOY ausentes → ?? null)
$exp['min_lvl'] (activas/terminadas) — int|null (backend C; HOY ausente → ?? null)
$requisitos[]: {tipo, slug, necesario, actual, caramelosDisponibles} — backend D (defaults 0)
```

## Tests
- `tests/Feature/HeaderNivelViewTest.php` (ACTUALIZAR): +2 tests con `actingAs` y `RefreshDatabase`:
  usuario autenticado → nombre + `action="/logout"` + botón "Salir" + Nv; invitado → `assertDontSee`
  del bloque logout. Los 3 tests existentes (render directo sin usuario) deben seguir verdes.
- `tests/Feature/AuthViewsTest.php` (NUEVO, render directo, sin rutas HTTP): login → `action="/login"`,
  campos name/password, link a /register; register → +password_confirmation y link a /login;
  con `withViewErrors` → el error de validación se muestra; sin `$errors` (render directo) NO revienta.
- `ExploracionesPageTest` / `PokedexViewTest`: sin actingAs → el bloque `@auth` no se renderiza;
  no deberían romper. Se ejecutan para confirmar.
- NO creo tests de controladores (instrucción explícita).

## Estados UI cubiertos
- Header: autenticado (nombre + logout + Nv + progreso) / invitado (solo Nv + toggle) / sin vars
  compartidas (fallbacks Nv 1, 0%).
- Auth: errores de validación (por campo y global), flash status, valores `old`, dark mode.
- Habitats niveles: desbloqueado (normal), bloqueado (visual + disabled + aviso al hacer click),
  sin datos min_lvl (backend C aún no entrega → todo desbloqueado).
- Exploraciones: con min_lvl insuficiente → badge requisito; sin min_lvl → vista actual.
- Reclutado: shape completo / keys ausentes → defaults 0 sin romper.

## Riesgos accesibilidad/UX
- Botones de nivel bloqueados: `disabled` + `title` + `aria-disabled`; el aviso inline usa
  `role="status"` (no interrumpe lectores de pantalla con aria-live agresivo).
- El nombre de usuario con truncate `max-w-[10rem]` evita desbordes en viewports pequeños; el badge
  Nv permanece siempre visible.
- Formularios auth: `autocomplete` (username/current-password/new-password), `autofocus` en login,
  labels asociados (for/id).
- Las URLs planas (`/login`, `/register`, `/logout`) evitan dependencia de nombres de ruta que el
  backend B aún no define; si al final el backend nombra rutas distintas, es un cambio de 1 línea.

## Verificación
- `php artisan view:cache` (compila todas las vistas).
- `php artisan test --compact --filter='HeaderNivel|AuthViews|ExploracionesPage|PokedexView'`.
- NO `npm run build` (no toco assets ni manifest; Alpine ya se carga vía Vite).

---

# Análisis Frontend — Corrección UI del módulo de Combate (2026-08-30)

## Contexto

El módulo de combate está archivado: ruta `/combate` comentada en `routes/web.php` y bug en el
weather banner. Se reactiva el acceso y se corrige la UI. NO toco lógica de dominio (`src/Battle/`)
ni `app/Livewire/Combate.php` (el backend corrige en paralelo; evito conflictos de merge).

## 1. Bug del weather banner

### Archivo

`resources/views/livewire/partials/battle-field.blade.php`

### Causa raíz

El bloque `@switch($weather)` comparaba con valores ingleses (`'sandstorm'`, `'sun'`, `'rain'`,
`'hail'`), pero `$weather` se asigna en `Combate::syncFromBattle()` como
`$battle->weather()->value`, que devuelve el valor del enum `Src\Battle\Domain\Enums\TipoClima`
en español: `'sequia'`, `'diluvio'`, `'niebla'`, `'granizo'`, `'tormenta_arena'`, `'turbulencias'`
(o `'none'`). El banner NUNCA se mostraba porque ningún case coincidía.

### Solución

- Uso `TipoClima::tryFrom($weather)?->label()` para el texto en español (sequía, diluvio, niebla,
  granizo, tormenta de arena, turbulencias) con fallback al valor crudo.
- Mantengo la clase `weather-{{ $weather }}` para estilizado (banner + CSS legacy).
- Icono por clima vía `match` en español (☀️🌧🌫❄️🌪💨).
- Nota de potencia para `tormenta_arena` (+500 potencia a movimientos Roca), preservando el
  texto original del banner.

### CSS

- `resources/css/app.css` (Tailwind 4 + Vite, compilado por el layout vía `@vite`): añadidas
  las 6 clases nuevas `weather-sequia`, `weather-diluvio`, `weather-niebla`, `weather-granizo`,
  `weather-tormenta_arena`, `weather-turbulencias` con los mismos esquemas de color que las
  clases inglesas del CSS legacy.
- `public/css/app.css` (CSS legacy que SÍ referencia `.weather-banner`, `.weather-sandstorm`,
  `.weather-sun`, `.weather-rain`, `.weather-hail`) **NO se carga en el layout** (solo se usa
  Vite) → se descartó tocar ese archivo; el banner usa Tailwind utilities inline
  (`mb-2 rounded-lg border px-3 py-1.5 ...`) + las clases `weather-*` nuevas de
  `resources/css/app.css`.
- ⚠️ Requiere `npm run build` o `npm run dev` (el CSS se compila vía Vite).

## 2. Reactivar ruta /combate

### Archivo

`routes/web.php` — descomentada la línea 30 dentro del grupo `middleware('auth')`:
`Route::get('/combate', \App\Livewire\Combate::class);`

## 3. Navegación

### Archivo

`resources/views/layouts/app.blade.php` — añadido al array `$navItems`
`['route' => '/combate', 'label' => 'Combate']` entre Equipos y Reclutamiento. Mismo estilo que
los demás enlaces (href directo + clases condicionales por ruta activa).

## 4. combate_page.blade.php

### Archivo

`resources/views/combate_page.blade.php` — correcto: `@extends('layouts.app')` +
`@livewire('combate')` + título "Combate". No necesita cambios.

## 5. Observación del componente Combate (NO modificado)

- `public string $weather = 'none'` (default) y se actualiza desde `$battle->weather()->value`
  (línea 628 de `app/Livewire/Combate.php`) → `$weather` llega con los valores del enum español.
- `$phase`, `$animAttackerNombre`, `$animDefenderNombre`, `$animMoveNombre`, `$team1`, `$team2`
  son las variables que consume el partial `battle-field`; verificadas en `combate.blade.php`.
- El banner se renderizará cuando el backend entregue un clima activo (distinto de `'none'`).
  Si el componente no llegara a entregar `$weather` en algún estado, NO se cambia (dominio).

## 6. Archivos modificados

1. `resources/views/livewire/partials/battle-field.blade.php` — banner corregido (español).
2. `resources/css/app.css` — 6 clases `weather-*` nuevas (Tailwind 4 + Vite).
3. `routes/web.php` — ruta `/combate` descomentada (dentro de auth).
4. `resources/views/layouts/app.blade.php` — enlace "Combate" en el nav.
5. `active/ANALISIS_FRONTEND.md` — esta sección (documentación, no código).

## 7. Estados UI / verificación

- Banner: visible cuando `$weather` es un clima activo; oculto cuando `'none'`/null.
- Nav: enlace activo resaltado en `/combate` (patrón `request()->is('combate*')` del layout).
- Ruta: registro verificado con `php -l routes/web.php` (sintaxis OK) y
  `php artisan view:cache` (vistas compiladas sin errores).
- CSS: requiere `npm run build` o `npm run dev` para regenerar
  `public/build/manifest.json` con las nuevas clases `weather-*`.

## 8. Nota de build para el usuario

- `resources/css/app.css` se compila con Vite → **obligatorio** ejecutar
  `npm run build` o `npm run dev` para que las clases `weather-*` estén disponibles.
- `public/css/app.css` (legacy, NO se carga en el layout) no requiere build.

---

# Análisis Frontend — Robustez Pokédex (bootstrap guard) + Hábitat show (1/5–4/5, título overlay, volver visual)

## Fecha
2026-08-30

## Contexto
El BACKEND cambia en paralelo el seed de la Pokédex para que venga YA filtrado por
`filter[visto]=1` y con `per_page=120`. Mi código debe seguir funcionando durante la
transición (seed filtrado O sin filtrar). En paralelo, el cliente pide ajustes visuales en
el detalle de hábitat. NO toco controladores ni rutas; solo las 2 vistas.

## Tarea 1 — `resources/views/pokedex/index.blade.php`

### Cambio
En `init()`, tras seedear (`this.items = seed.filter(p => p && p.visto)` — guard defensivo
intacto), añado un **bootstrap guard**: si la pestaña activa es `'vistos'` y
`this.items.length === 0` pero hay avistamientos (`(this.counts?.vistos ?? 0) > 0` o
`initial?.meta?.total > 0`), llamo UNA vez `this.resetAndFetch()` para forzar que la página 1
filtrada se pida al servidor. `resetAndFetch()` no re-entra en `init()` → sin bucles.
`buildParams` y `per_page: '120'` intactos (no tocar; R4 ya cubierto).

### Estados cubiertos
- Seed sin filtrar + usuario con avistamientos → guard manda fetch de página 1 filtrada.
- Seed filtrado (nuevo contrato) → `items` ya poblados → guard no actúa.
- Usuario sin avistamientos → `counts.vistos === 0` y `total === 0` → guard no actúa
  (empty state correcto, sin fetch innecesario).

## Tarea 2 — `resources/views/habitats/show.blade.php`

### Cambios
1. **R5 proporción**: `grid lg:grid-cols-4` → `lg:grid-cols-5`; columna izquierda conserva
   `lg:col-span-1`, derecha `lg:col-span-3` → `lg:col-span-4` (1/5 vs 4/5). Apilado móvil intacto.
2. **R6 título en imagen**: moví el `<h1>` DENTRO de la card de la imagen como overlay
   inferior absoluto (`absolute bottom-0 inset-x-0`, gradiente `bg-gradient-to-t from-black/70 to-transparent`,
   texto blanco `truncate`, `title`). La card pasa a `relative` y `min-h-[6rem]`.
   Caso límite imagen ausente: `@if(!empty($habitat['image']))` → si no hay image se renderiza
   un placeholder (`w-48 h-32`, emoji 🏔️); si la imagen existe pero falla al cargar, el
   `onerror="this.style.display='none'"` la oculta pero la card conserva `min-h-[6rem]` +
   padding → el título overlay (y el fondo) siguen legibles.
3. **R7 volver visual**: el enlace plano `<a>` se sustituye por una TARJETA visual con el
   lenguaje del archivo (`$cardPanelClass`), icono de flecha en slot `w-8 shrink-0`, texto
   "Volver a hábitats", hover con sombra + borde azul y dark-mode, `aria-label` + `title`.

### NO tocados
- Panel de botones de construcción, modal "Admin - Gestión", modal de exploración, lógica
  Alpine `habitatShow()`, nombres/claves de datos. Solo cambió CSS/estructura de la columna izquierda.

### Observaciones UX / edge cases (overlay)
- El overlay usa `text-sm font-bold` en lugar del `text-2xl` original por caber en la card de
  imagen `w-fit` (no ancha); es legible y `truncate` + `title` cubren nombres largos.
- Si un `$habitat['image']` es un 404, el navegador dispara `onerror` y oculta el `<img>`;
  `min-h-[6rem]` + `p-3` garantizan que el overlay no quede en una card colapsada. El resto de
  la card (fondo blanco/gris) queda visible bajo el gradiente.
- Accesibilidad: la card sigue siendo un `<a href="/habitats">` real con `aria-label`/`title`;
  el `h1` del nombre se conserva (semántica de título de página dentro de la región de imagen).
- El placeholder 🏔️ es un emoji (patrón del proyecto en headers); si el Arquitecto prefiere un
  SVG genérico, es un cambio de 1 línea.

## Verificación
- `php artisan view:cache` (compila todas las vistas).
- `php artisan test --compact --filter=PokedexViewTest` (no romper).
- `php artisan test --compact --filter=HabitatsControllerTest` (no romper; `assertSee('Bosque')`).
- `git diff` para confirmar que solo cambiaron las 2 vistas (+ este análisis).
- NO `npm run build` (no toco assets ni manifest).

---

## Fix: `$wire is not defined` en Combate (Livewire 4 + Vite + Alpine)

### Causa raíz (confirmada)
1. `resources/js/bootstrap.js` importaba `alpinejs` de node_modules e iniciaba `Alpine.start()`.
2. En `/combate` (full-page Livewire), Livewire auto-inyectaba `livewire.js` (clásico) con SU Alpine bundled → **dos instancias de Alpine**.
3. La instancia de Alpine del bundle Vite (sin magic `$wire`) procesaba el DOM y `x-init` de `combate.blade.php` (`$wire.$watch(...)`, `$wire.get(...)`) → `ReferenceError: $wire is not defined`.

### Solución aplicada — Opción A (manual bundling oficial Livewire 4)
- `resources/js/bootstrap.js`: se elimina `import Alpine from 'alpinejs'` y `Alpine.start()`. Ahora importa `{ Livewire, Alpine }` desde `../../vendor/livewire/livewire/dist/livewire.esm`, expone `window.Alpine` / `window.Livewire` y llama `Livewire.start()` (arranca Livewire + su Alpine bundled → **una sola instancia**, registra `$wire`).
- `resources/views/layouts/app.blade.php`: `@livewireStyles` en `<head>` (tras `@vite`) y `@livewireScriptConfig` antes de `</body>` (tras `@stack('scripts')`). La directiva `@livewireScriptConfig` setea `window.livewireScriptConfig` y marca `hasRenderedScripts=true`, lo que **suprime la auto-inyección** del `livewire.js` clásico (evita doble Livewire) y evita el auto-start del ESM en DOMContentLoaded (por eso `Livewire.start()` manual es necesario).

### Por qué no se rompen las otras vistas
- Los componentes Alpine inline (`pokedexApp()`, `equiposApp()`, `habitatShow()`, etc.) son funciones globales en `@push('scripts')`; Alpine (el de Livewire, `window.Alpine`) las resuelve igual.
- `Livewire.start()` llama `Alpine.start()` incondicionalmente → Alpine disponible también en páginas SIN componente Livewire (Pokédex, Hábitats, etc.).
- Dark mode del layout y dropdowns `x-data` siguen funcionando con la instancia única.

### Verificación
- `npm run build` regenera el bundle Vite (quita `alpinejs` de node_modules del bundle; incluye el ESM de Livewire).
- `php artisan view:cache` compila las vistas (directivas `@livewireStyles`/`@livewireScriptConfig`).
- Chequear en consola del navegador: sin warning "Detected multiple instances of Alpine/Livewire" y `$wire` definido en `x-init` del combate.

---

# Análisis Frontend — Rediseño "Resultados por revisar" (2 columnas + caramelos compactos)

## Fecha
2026-08-30

## Contexto
Rediseño del bloque "Resultados por revisar" de `resources/views/exploraciones/index.blade.php`
(§ "Terminadas", líneas ~188-369). NO toco backend (controlador/transformador) ni
`ExploracionesPageTest`/`ExploracionesTest`. Único test de vista en alcance:
`tests/Feature/ExploracionesViewTest.php`.

## Vistas/componentes a tocar
1. `resources/views/exploraciones/index.blade.php` — § "Terminadas" (única).
   - Eliminar bloque "Avistados" del cuerpo.
   - Mover EXP al header como badge `+N EXP`.
   - Unificar caramelos (familia/EV/tipo) al formato compacto "caramelo de tipo".
   - Layout 2 columnas (Capturados 1/3 + Recompensas 2/3).
   - Casos sin capturados / sin recompensas.
2. `tests/Feature/ExploracionesViewTest.php` — actualizar el test principal + defensivo.

## DTOs/contrato consumido (resultado de terminada)
```
'resultado' => [
   'avistados' => [['pokemon_id'=>int,'nombre'=>string]],      // SE IGNORA (nunca renderizar)
   'capturados' => [['pokemon_id'=>int,'nombre'=>string,'cantidad'=>int]],
   'caramelos_familia' => [['pokemon_id'=>?int,'nombre'=>?string,'cantidad'=>int]],
   'caramelos_ev' => [['stat'=>int,'stat_slug'=>?string,'stat_nombre'=>?string,'cantidad'=>int]],
   'caramelos_tipo' => [['slug'=>string,'tipo'=>string,'cantidad'=>int]],  // cuidado: existe? ver abajo
   'exp' => int,
]
```
- Nota: el contrato documentado en `docs/architecture.md` menciona `caramelos_familia[].pokemon_id`
  y `caramelos_ev[].stat_slug`. El test actual usa `caramelos_familia[].evolution_chain_id` y
  `caramelos_ev[].stat`+`stat_nombre`. La vista debe ser defensiva a ambos.
- Reviso el transformador para confirmar `caramelos_tipo` shape (slug/tipo) y `caramelos_familia`
  (`nombre`, `pokemon_id`). (Solo lectura; no lo toco.)

## Estados UI a cubrir
- Con capturados + recompensas completas (layout 2 col).
- Sin capturados → solo Recompensas (ocupa lg:col-span-3).
- Sin recompensas → solo Capturados.
- Sin ambos → "Sin resultados" (`resultado` vacío/[]).
- Caramelo familia sin `pokemon_id`/`nombre` → `$candyFallback` en img + sin nombre.
- Caramelo EV sin `slug`/`stat_nombre` → fallback de slug a `$candyFallback` + `statName(stat)` para nombre.
- `avistados` presente → se ignora (NUNCA renderizar), sin error.
- EXP `0` → `+0 EXP`.

## Tests (TDD: primero rojo)
Actualizar `tests/Feature/ExploracionesViewTest.php`:
1. `test_exploraciones_view_renders_active_and_finished_explorations`:
   - Quitar asserts de 'Avistados', 'Caramelos de familia', 'Caramelos EV'.
   - `assertDontSee('Avistados')`.
   - Verificar 'Rattata', 'Ataque', `×12`, `×8`, valor tipo, '+250 EXP' (header), 'Capturados', `×3`.
2. `test_exploraciones_view_is_defensive_with_partial_contract`:
   - Mantener '+0 EXP', 'Sin resultados'. Verificar que `avistados` no rompe.
   - Mantener fallback `statName(2)` (bitácora), 'Evento registrado'.
3. Añadir caso: caramelo familia sin `pokemon_id`/`nombre` → no pinta nombre (placeholder);
   caramelo EV sin `stat_nombre` → `statName`.
4. Añadir caso: terminada con capturados vacíos y recompensas presentes → Recompensas legible;
   terminada con solo recompensas vacías.
5. Badge compacto de familia/EV: imagen + badge `×N` + nombre debajo.

## Riesgos accesibilidad/UX
- Badges `aria-label` descriptivo en EXP.
- Imágenes con `alt`/`title` descriptivos + `loading="lazy" decoding="async"`.
- `onerror` → `$candyFallback` (placeholder `candy_pokemon/0.webp` + `onerror=null` evita loop).
- `text-center`, `truncate w-12` para nombres largos.
- Sin etiquetas de sección en "Recompensas" (familia/EV/tipo), separación con `<hr>` entre grupos no vacíos.

## Verificación
- `php artisan test --compact tests/Feature/ExploracionesViewTest.php`
- `php artisan test --compact --filter=Exploraciones`
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse` (solo mis archivos)
- `php artisan view:cache`
- NO `npm run build` (no toco assets/JS).

---

## Iteración 2026-08-30 — Evolución de Exploraciones a "expediciones con riesgo" (UI)

### Contexto

El BACKEND implementa en paralelo (mismo contrato definido en el brief): `GET /exploraciones/preview`,
nuevos tipos de evento en la bitácora activa, y resultado terminado aditivo. `docs/spec_evolucion_exploraciones.md`
NO existe aún → construyo contra el contrato del brief. NO toco controladores (`ExploracionActivaController`),
rutas, enums ni lógica backend. Verificado: `routes/exploraciones.php` NO tiene `/preview` todavía → dependencia
documentada; la vista es defensiva si el endpoint devuelve 404 (estado de error visible sin romper el modal).

### Vistas a tocar

1. `resources/views/habitats/show.blade.php` — Modal de exploración: panel de PREVIEW de expedición
   (fetch `/exploraciones/preview` al abrir el modal con equipo+nivel), botón "Enviar expedición"
   habilitado SOLO tras cargar preview; confirmación reforzada si riesgo "Extremo".
2. `resources/views/exploraciones/index.blade.php` — Bitácora activa con los NUEVOS tipos de evento
   (huida/emboscada/contratiempo/retirada/grupo/hallazgo) y resultados terminados con badge de
   categoría + incidentes + línea de tiempo. Extraigo el render de cada evento a un partial
   `_evento.blade.php` (crecimiento del `@if/@elseif`).
3. `resources/views/reclutados/index.blade.php` — Select de behavior: quitar SOPORTE, añadir RASTREADOR.
4. `resources/views/equipos/index.blade.php` — SOLO revisar (sin cambios): el behavior está hardcodeado
   `'VANGUARDIA'` por defecto en el JS (se MANTIENE, instrucción del brief).

### Contrato asumido (JSON fetch / shape de toActiva·toTerminada)

```
GET /exploraciones/preview?team_id=&habitat_id=&level=
→ { peligro_estrellas: int(1..5), afinidad: string,
    advertencias: string[], roles: string[],
    riesgo: "Bajo"|"Medio"|"Alto"|"Extremo",
    recompensa_esperada: "Baja"|"Media"|"Alta" }
```

Bitácora activa (eventos con `tipo`, `timestamp` ISO):
- `huida` { pokemon_id, resolucion: "sin_combate" } → "Un {nombre} salvaje huye antes de que comience el combate."
- `emboscada` { pokemon_id|pokemon_ids[], duration_loss?, resolucion? } → "¡Emboscada!" + subtítulo según
  resolucion ("El equipo repele el ataque (-10 min)" / "El equipo escapa perdiendo tiempo") + mini-iconos si pokemon_ids.
- `contratiempo` { subtype: desorientacion|terreno|clima|bloqueo, duration_loss? } → texto por subtype + "-X min".
- `retirada` { reason } → "El equipo se retira: {reason}".
- `grupo` { pokemon_ids[] } → mini-iconos de cada miembro.
- `hallazgo` { subtype: caramelo_familia|caramelo_ev|caramelo_tipo, pokemon_id|stat|tipo/slug, cantidad } →
  mismo render de caramelos existente.

Resultado terminado (aditivo, campos ausentes → defaults):
- `resultado` ∈ exito_excepcional|exito|exito_parcial|fracaso|retirada
- `duration_real` (min), `tiempo_perdido` (min) → "X min efectivos · Y min perdidos" (efectivos = duration_real − tiempo_perdido)
- `incidentes` { encuentros, victorias, huidas, emboscadas, contratiempos }

### Estados UI cubiertos

- Preview modal: loading (spinner/skeleton), error (mensaje + sin botón de envío), success (panel completo),
  riesgo Extremo (confirm nativo antes de enviar), preview sin advertencias (feedback verde "bien preparado").
- Bitácora: cada tipo nuevo renderiza; tipos legacy intactos; evento desconocido → "Evento registrado".
- Resultados: con/sin badge (legacy), con/sin incidentes, con/sin duración (efectivos = max(0, real−perdido)).
- Roles: select con RASTREADOR y sin SOPORTE.

### Riesgos accesibilidad/UX

- `x-html` para estrellas solo con string generado por el propio método (sin input del usuario).
- Botones con `aria-label`, imágenes con `alt`/`title` + `loading="lazy"` + `onerror` (ocultar/fallback).
- Confirm de riesgo Extremo es `window.confirm` nativo (accesible, no inventa UI nueva).
- Botón deshabilitado con `:disabled` + clases `disabled:` (cursor-not-allowed) → estado visible.
- El preview se re-fetcha en cada apertura del modal (datos frescos si cambia equipo/nivel).
- Defensivo ante contrato incompleto: si `preview` no llega (backend en paralelo), el botón queda
  deshabilitado y se muestra el error; la exploración legacy (sin preview) nunca rompe la vista.

### Tests (Dusk NO instalado → view render tests con `$this->view()`, patrón de ExploracionesViewTest)

- `tests/Feature/ExploracionesViewTest.php` +3:
  1. `test_exploraciones_view_renders_risk_bitacora_event_types` — huida/emboscada/contratiempo/retirada/grupo/hallazgo.
  2. `test_exploraciones_view_renders_result_category_incidents_and_timeline` — badge + incidentes + línea de tiempo.
  3. `test_exploraciones_view_defensive_without_risk_fields` — datos legacy sin nuevos campos → sin badge/incidentes/timeline.
- `tests/Feature/HabitatsViewTest.php` (NUEVO) — render de `habitats.show` con el modal: verifica marcadores
  del preview (`/exploraciones/preview`, `previewLoading`, `Enviar expedición`) y que el botón legacy "Explorar" ya no existe.

### Verificación

- `php artisan test --compact --filter=ExploracionesViewTest`
- `php artisan test --compact --filter=HabitatsViewTest`
- `php artisan view:cache`
- `npm run build` (NO toco assets CSS/JS → no necesario; solo vistas Blade)

---

# Análisis Frontend — Reconstrucción del combate con Bootstrap 5 + Livewire (2026-08-30)

## Contexto

El usuario está frustrado con la UI del combate actual (Tailwind sin estilos, fondo oscuro con texto negro, imágenes apiladas, sin indicación de dónde pulsar). Quiere reconstruir el frontend del combate con Bootstrap 5 + Livewire, manteniendo el backend intacto.

## Archivos a modificar

### Instalación/configuración
1. `package.json` — añadir `bootstrap` y `@popperjs/core` como dependencias
2. `resources/js/app.js` — importar Bootstrap JS
3. `resources/css/app.css` — importar Bootstrap CSS + CSS adicional de combate

### Vistas a reconstruir (6 archivos)
1. `resources/views/livewire/combate.blade.php` — contenedor principal (Bootstrap grid)
2. `resources/views/livewire/partials/turn-bar.blade.php` — barra de turnos (badges)
3. `resources/views/livewire/partials/battle-field.blade.php` — campo de combate (2 columnas)
4. `resources/views/livewire/partials/moves-panel.blade.php` — panel de movimientos (botones Bootstrap)
5. `resources/views/livewire/partials/battle-log.blade.php` — registro de batalla (list-group)
6. `resources/views/livewire/_pokemon-card.blade.php` — tarjeta de pokémon (card Bootstrap)

### Tests
7. `tests/Feature/CombateLivewireTest.php` — ACTUALIZAR assertions de texto para reflejar nuevos textos Bootstrap (assertSee 'CAMPO DE COMBATE' → 'Campo de Combate')

## DTOs/contrato consumido (NO modificar)

### `$team1`/`$team2` (de `aArrayVista()`):
```php
[
    'refId' => string,          // ej: 'player_1'
    'nombre' => string,         // ej: 'Gengar'
    'icon' => string,           // ej: '/images/iconos_webp/94.webp'
    'hp' => float, 'maxHp' => float,
    'defHp' => float, 'maxDefHp' => float,
    'spDefHp' => float, 'maxSpDefHp' => float,
    'posicion' => 'vanguardia'|'retaguardia',
    'alive' => bool,
    'speed' => float,
    'accumulatedSpeed' => float,
    'status' => string,          // 'none'|'burn'|'poison'|'bad_poison'|'paralysis'|'sleep'|'freeze'|'confusion'
    'statusTurns' => int,
    'stages' => array<string, int>,  // ['attack' => 0, 'defense' => 0, ...]
    'team' => 0|1,
    'item' => string,           // '' o 'leftovers'|'life_orb'|'focus_sash'
    'canTarget' => bool         // SOLO en team2 durante player_target (añadido por syncViewData)
]
```

### `$currentMoves` (de `previewTarget()`):
```php
[
    'nombre' => string,          // ej: 'Bola Sombra'
    'tipo' => string,            // valor de TipoPokemon como string: '1', '2', ... '18'
    'potencia' => int,           // 0 para movimientos de estado
    'categoria' => string,       // 'fisico'|'especial'|'estado'
    'daño' => float,             // daño calculado (0 para estado)
    'efectividad' => float,      // 2.0|1.0|0.5|0.0
    'stab' => bool,
    'directo' => bool,           // si el atacante tiene perforación de armadura
    'statusEffect' => string,    // 'none'|'burn'|'poison'|'bad_poison'|'paralysis'|'sleep'|'freeze'|'confusion'
    'selfStatChanges' => array,  // [{stat: string, stages: int}]
    'targetStatChanges' => array,// [{stat: string, stages: int}]
]
```

### `$turnQueue`:
```php
[{team: 0|1, index: int}, ...]  // ordenado por velocidad acumulada descendente
```

### Otras variables:
- `$phase` — 'init'|'player_target'|'player_move'|'animating'|'battle_over'
- `$round` — int
- `$log` — array de strings
- `$weather` — string ('none'|'sequia'|'diluvio'|'niebla'|'granizo'|'tormenta_arena'|'turbulencias')
- `$actingRefId` — string (refId del actor actual)
- `$animAttackerId`, `$animDefenderId` — string
- `$animAttackerNombre`, `$animDefenderNombre` — string
- `$animMoveNombre` — string (NUNCA se setea en el backend; siempre vacío)
- `$animTick` — int
- `$selectedTargetRefId` — string

## Observación importante: `$animMoveNombre` siempre vacío

El backend (`Combate::setAnimState()`) nunca asigna `$this->animMoveNombre`; solo lo resetea en `resetAnimState()`. Por tanto, `$animMoveNombre` siempre es `''`. En el banner de animación, mostraremos "Atacante → ataca → Defensor" (reemplazando el movimiento con un texto genérico ya que no llega del backend).

## Estados UI a cubrir

### `combate.blade.php`:
- Carga inicial (phase='init' — aunque el backend pasa directo a player_target tras mount)
- Ronda normal (Bootstrap grid 2 columnas + turn bar + log)
- Animación (phase='animating' — banner info + spinner)
- Batalla terminada (phase='battle_over' — alert success)

### `turn-bar.blade.php`:
- Normal: badges de próximos turnos, primero destacado
- Vacío: "Esperando..."
- Sincronización: `$actingRefId` para destacar actor actual

### `battle-field.blade.php`:
- Normal: 2 columnas jugador/rival, pokémon en tarjetas
- Clima activo: banner alert-info/alert-warning
- Animación: banner "Atacante → ataca → Defensor"
- Selección de objetivo (player_target): tarjetas rivales clickeables con border-primary
- Pokémon debilitado: opacity-50, text-muted
- Pokémon seleccionado: border-primary

### `_pokemon-card.blade.php`:
- Normal: icono, nombre, HP bar, barreras, stats
- Debilitado (!alive): opacity-50, text-muted
- Con estado (status !== 'none'): badge emoji + tooltip
- Con stages: badges pequeños (+1, -2, etc.)
- Con item: badge emoji
- Targeteable: cursor-pointer, wire:click, border-primary al seleccionar
- Bloqueado (!canTarget): opacity-50, cursor-not-allowed

### `moves-panel.blade.php`:
- player_move: botones de movimientos con preview de daño, tipo, efectividad
- player_target: "Selecciona un objetivo" + nota de retaguardia
- animating: spinner + "Ejecutando turno..."
- battle_over: alert "¡Batalla terminada!"
- Siempre: badge de ronda

### `battle-log.blade.php`:
- Normal: últimos 10 entradas en list-group
- Vacío: "Sin eventos en el registro"

## Riesgos y consideraciones

### Bootstrap global vs Tailwind en el resto del sitio
- Importar Bootstrap en `app.css` globalmente afecta a todas las páginas (no solo combate).
- `@import "bootstrap/dist/css/bootstrap.min.css"` va ANTES de `@import "tailwindcss"` → Tailwind preflight sobreescribe Bootstrap reboot.
- Bootstrap clases de componentes (`.card`, `.btn`, `.badge`, `.progress`, `.list-group`) funcionan por especificidad de clase, no se ven afectadas por Tailwind preflight.
- Utilidades Bootstrap con `!important` (`.d-flex`, `.text-center`) ganan a Tailwind sin `!important`.
- **Riesgo menor**: el resto del sitio (pokedex, habitats, exploraciones) puede verse afectado ligeramente por Bootstrap Reboot (tipografía base, márgenes de headings). Como Tailwind preflight viene después, anula la mayoría.
- **Mitigación**: si el Arquitecto reporta problemas, se puede mover Bootstrap a un archivo scoped `resources/css/combate.css` y solo cargarlo en `combate_page.blade.php`.

### Tests de Combate
- `tests/Feature/CombateLivewireTest.php` usa `assertSee('CAMPO DE COMBATE')` — el texto estaba en mayúsculas. Con Bootstrap pongo título legible "Campo de Combate". Actualizo el assert.
- `assertSee('¡Comienza la batalla!')` — se mantiene (es del log, no cambia).
- `assertCount('team1', 3)` y `assertCount('team2', 3)` — lógica, no toca.

### `animMoveNombre` nunca se setea
- Debido a que el backend no setea `$this->animMoveNombre`, en el banner de animación uso texto genérico "ataca" en lugar del nombre del movimiento.

## Tests a modificar

1. `tests/Feature/CombateLivewireTest.php` — actualizar `assertSee('CAMPO DE COMBATE')` a 'Campo de Combate'.

## Tests de Battle (150) — NO deben romperse
Los tests de Battle (`php artisan test --filter=Battle`) prueban la lógica de dominio, no las vistas. No deben verse afectados.

## Plan de implementación

1. **Análisis previo** (este documento) ✅
2. **Instalar Bootstrap**: `npm install bootstrap @popperjs/core`
3. **Configurar Vite**: `resources/js/app.js` + `resources/css/app.css`
4. **Reconstruir vistas** (6 archivos):
   - `_pokemon-card.blade.php` primero (es el partial más usado)
   - `turn-bar.blade.php`
   - `battle-field.blade.php`
   - `moves-panel.blade.php`
   - `battle-log.blade.php`
   - `combate.blade.php` (contenedor, incluye los 4 partials)
5. **Actualizar test** `CombateLivewireTest.php`
6. **Build**: `npm run build`
7. **Verificar tests**: `php artisan test --filter=Battle` y `--filter=CombateLivewire`
8. **Commit** atómico

---

# Análisis Frontend — 6 correcciones UI del combate + aislamiento de Bootstrap (2026-08-30)

## Contexto

El usuario reportó 6 problemas en la UI del combate (Laravel 12, Livewire 4, Bootstrap 5 +
Tailwind 4, Vite). El más crítico: el import global de Bootstrap en `resources/css/app.css`
rompe el dark mode de Tailwind en el resto del sitio. NO toco `app/Livewire/Combate.php` ni
`src/` (backend/dominio). NO romper `$wire` (combate.js importa `./bootstrap`).

## Tarea 1 — Aislar Bootstrap SOLO al combate (dark mode global)

### Archivos
1. `resources/css/app.css` — quitar `@import "bootstrap/dist/css/bootstrap.min.css";` y TODOS
   los estilos de combate (pasan a combate.css). Queda solo Tailwind + `@custom-variant dark`.
2. `resources/css/combate.css` (NUEVO) — `@import "bootstrap/dist/css/bootstrap.min.css";` +
   todos los estilos de combate de app.css (border-primary, cursor-pointer/not-allowed,
   bg-hp-*, stage-up/down, weather-*) + nuevas clases (`.active-turn`, `.targeted-card`).
3. `vite.config.js` — añadir `resources/css/combate.css` como tercer input.
4. `resources/views/layouts/app.blade.php` — añadir `@stack('styles')` en `<head>` tras `@vite`.
5. `resources/views/combate_page.blade.php` — `@push('styles')` con
   `@vite(['resources/css/combate.css', 'resources/js/combate.js'])`.
6. `resources/js/app.js` — dejar solo `import './bootstrap';` (resto del sitio).
7. `resources/js/combate.js` (NUEVO) — `import './bootstrap';`
   + `import 'bootstrap/dist/js/bootstrap.bundle.min.js';` (el `$wire` lo inyecta bootstrap.js:
   Livewire ESM + `Livewire.start()`; al importarlo ANTES que Bootstrap JS, el magic `$wire`
   sigue disponible en el combate).

### Riesgo `$wire is not defined`
El combate usa `x-init` con `$wire.$watch` en `combate.blade.php`. `bootstrap.js` importa
`livewire.esm` y llama `Livewire.start()` (registra `$wire`). Si `combate.js` no importara
`./bootstrap`, el `$wire` fallaría. Verificación: orden de imports en combate.js.

## Tarea 2 — HP bars y barreras (`_pokemon-card.blade.php`)

- Barreras (defHp física, spDefHp especial): **50% del ancho máximo** cada una, `height: 10px`
  (antes 4px), con número restante `ceil(...)/ceil(...)` como texto pequeño (label DEF / DEF.ESP
  + número junto a cada barra), **ARRIBA** de la barra de vida.
- Barra de vida (hp): debajo de las barreras, `height: 8px`, con número restante
  `ceil($p['hp'])/ceil($p['maxHp'])`.
- Colores conservados: barrera física `bg-info`, especial `bg-primary`, vida `bg-hp-low/mid/high`
  según %.
- Disposición: las dos barreras en `d-flex gap-1` con cada `w-50`; HP bar debajo a ancho completo.
- Quito el número de HP del header (nombre) porque ahora vive en la barra de vida (evita duplicado).

## Tarea 3 — Layout 4 columnas (`battle-field.blade.php`)

- `row g-2` con 4 `col-6 col-md-3`:
  1. Tú · Retaguardia (team1 retaguardia)
  2. Tú · Vanguardia (team1 vanguardia)
  3. Rival · Vanguardia (team2 vanguardia)
  4. Rival · Retaguardia (team2 retaguardia)
- Cada columna con título `text-uppercase small text-muted fw-semibold` ("Tú · Retaguardia",
  "Tú · Vanguardia", "Rival · Vanguardia", "Rival · Retaguardia") — elimino los encabezados
  "Tú"/"Rival" de card-header (los títulos de columna ya lo indican).
- Centrado con 1 solo integrante: `d-flex flex-column align-items-center` dentro de cada columna
  (las cards se centran al ser flex column centrada).

## Tarea 4 — Turno activo y objetivo seleccionado

1. `turn-bar.blade.php`: badge del turno activo (el que tiene `acting` o el primero de la cola)
   → clase `active-turn` con `background: #fecc3833` (translúcido amarillento) en combate.css.
2. `_pokemon-card.blade.php`: card con `$p['refId'] === $selectedTargetRefId` → clase
   `targeted-card` (`background: #ff000033; border: 2px solid #dc3545 !important;`) en combate.css.
   Reemplaza al `border border-primary` anterior para el target seleccionado.

## Tarea 5 — Icono del tipo en ataques (`moves-panel.blade.php`)

- Map `$tipoSlugs` (value int 1-18 → slug español minúscula):
  `[1=>'normal',2=>'lucha',3=>'volador',4=>'veneno',5=>'tierra',6=>'roca',7=>'bicho',8=>'fantasma',
   9=>'acero',10=>'fuego',11=>'agua',12=>'planta',13=>'electrico',14=>'psiquico',15=>'hielo',
   16=>'dragon',17=>'siniestro',18=>'hada']`.
- `<img src="/images/type/{{ $tipoSlug }}.webp" alt="{{ $tipoLabel }}" style="width:24px;height:24px" class="me-1">`
  en la primera línea del botón (junto al nombre).
- Assets verificados: `public/images/type/` tiene los 18 `.webp`.

## Tarea 6 — Rediseño botones de ataque (`moves-panel.blade.php`, estado `player_move`)

1. **Primera línea**: icono de tipo + nombre + (al final de la línea) badge de categoría y
   potencia. Ya no van en el bloque de badges inferior.
2. **Segunda línea**: daño destacado (`fs-5 fw-bold`, `{{ ceil($move['daño']) }} daño`) +
   efectividad + STAB + DIRECTO + estados + stat changes (en `small`).
3. **Movimiento más efectivo**: `$maxDmg = max(daños)`; si `$move['daño'] > 0 && daño === max`
   → clase destacada `border-primary border-2 shadow-sm` (mantiene `btn-outline-primary` de base;
   el fondo por tipo se aplica con `style`).
4. **Fondo por tipo**: map `$tipoBg` de colores suaves por value (Agua `#e3f2fd`, Fuego `#ffebee`,
   Planta `#e8f5e9`, Eléctrico `#fff8e1`, Psíquico `#fce4ec`, Normal `#fafafa`, resto con tonos
   claros coherentes; default `#fafafa`). Se aplica con `style="background-color: ..."` en el botón.
5. Mantengo `wire:click="selectMove({{ $idx }})"`.

## Tests (no se modifican; se verifican)

- `php artisan test --compact --filter=Battle` → 150 (lógica de dominio; las vistas no intervienen).
- `php artisan test --compact --filter=Combate` → 6+ (CombateLivewireTest; asserts sobre texto
  'Campo de Combate' y '¡Comienza la batalla!' se conservan intactos).
- `php artisan view:cache` (compila todas las vistas).

## Verificación build

- `npm run build` regenera el manifest con `combate.css` + `combate.js` como nuevos inputs.
- Dark mode del layout (toggle `document.documentElement.classList.toggle('dark')`) vuelve a
  funcionar en Pokédex/Hábitats/etc. al quitar Bootstrap del CSS global (Tailwind `dark:*`).
- Combate carga Bootstrap solo en `/combate` sin romper `$wire` (combate.js importa `./bootstrap`).

## Estados UI cubiertos

- Dark mode: otras páginas OK (sin Bootstrap global); combate con Bootstrap + Tailwind.
- Pokemon card: barreras al 50% (def/spdef con números), HP debajo con número, debilitado
  (opacity-50), target seleccionado (targeted-card rojo translúcido), selectable (border-primary),
  bloqued (opacity-50 + cursor-not-allowed), acting (border-warning), statuses/stages/items.
- Battle field: 4 columnas centradas, clima (weather-*), animación, targeteable.
- Turn bar: activo destacado (active-turn), esperando (empty).
- Moves: primera línea icono+nombre+cat+pot; daño destacado; máximo destacado; fondo por tipo;
  efectividad/STAB/estados/stat changes.

## Riesgos accesibilidad/UX

- Las barras conservan `role="progressbar"` + `aria-*` + `title` con valores exactos.
- Números en `<span class="small text-muted">` legibles; `style="font-size:.6rem"` solo para
  compactar (título en la barra sigue dando el valor accesible).
- Icono tipo con `alt` = label español.
- Botones de ataque con `aria` implícita (button real) y `wire:click` intacto.

---

# Análisis Frontend — Volver a Bootstrap global + corregir dark mode (2026-08-30)

## Contexto

El usuario reporta: "Bootstrap ya esta instalado, pero por algun motivo en combate no lo usas,
estaba bien antes, solo hacia falta corregir algunas cosas". El proyecto pasó por 2 iteraciones:

1. **Primera** (commit `3140940`): Bootstrap global en `resources/css/app.css` + `resources/js/app.js`.
   El combate se veía bien con Bootstrap. **PERO** rompió el dark mode de las otras páginas
   (Pokédex, Hábitats) por la interacción de CSS cascade layers: Tailwind 4 coloca utilities en
   `@layer utilities`, mientras que Bootstrap importado sin `@layer` queda **unlayered** — en CSS
   cascade layers los estilos unlayered tienen prioridad sobre los layered, por lo que Bootstrap
   Reboot (`body { background-color: var(--bs-body-bg) }`) ganaba a Tailwind `dark:bg-gray-900`
   (layered) independientemente de especificidad.

2. **Segunda / actual** (commit `ba92d32`): Se aisló Bootstrap a `combate.css` + `combate.js`,
   cargados solo en `/combate` vía `@push('styles')`. El dark mode de otras páginas funcionaba,
   pero el combate dejó de usar Bootstrap porque el aislamiento requería entradas Vite separadas
   y el usuario notó la regresión.

## Decisión

Volver al enfoque global (iteración 1) y corregir el dark mode de las demás páginas con overrides
mínimos de CSS, en lugar de depender de cascade layers.

## Vistas/componentes a tocar

| Archivo | Acción |
|---------|--------|
| `resources/css/app.css` | Restaurar `@import "bootstrap/dist/css/bootstrap.min.css";` antes de `@import "tailwindcss"`. Añadir todos los estilos de combate (desde `combate.css`). Añadir overrides dark mode. |
| `resources/js/app.js` | Restaurar `import 'bootstrap/dist/js/bootstrap.bundle.min.js';` después de `import './bootstrap';` |
| `vite.config.js` | Volver a `input: ['resources/css/app.css', 'resources/js/app.js']` (quitar combate.css/js) |
| `resources/views/combate_page.blade.php` | Quitar `@push('styles')` con `@vite([...combate...])` |
| `resources/views/layouts/app.blade.php` | Quitar `@stack('styles')` (solo lo usaba combate_page) |
| `resources/css/combate.css` | **ELIMINAR** (ya no es necesario) |
| `resources/js/combate.js` | **ELIMINAR** (ya no es necesario) |
| `active/ANALISIS_FRONTEND.md` | Añadir esta sección |

## DTOs/contratos consumidos

Ninguno. No se modifican Livewire ni backend.

## Estados UI cubiertos

- **Light mode**: body hereda de Bootstrap Reboot (#fff, ligeramente distinto de `bg-gray-50` #f9fafb,
  aceptable). El header y nav mantienen su color Tailwind porque Bootstrap Reboot no estiliza
  `<header>` ni clases de texto con especificidad de elemento.
- **Dark mode** (`.dark` en `<html>`): 
  - `body` → `background-color: #111827 !important; color: #f9fafb;` (override unlayered con `!important`
    gana a Bootstrap Reboot unlayered).
  - `color-scheme: dark` en `.dark` (gana a Bootstrap `:root { color-scheme: light }` porque
    ambos son unlayered y el nuestro va después en orden de carga).
  - Los enlaces (`<a>`) del nav y resto del sitio recuperan `color: inherit; text-decoration: inherit`
    (override unlayered restaura el preflight de Tailwind, ganando a Bootstrap Reboot `a { color: ... }`).
- **Combate**: Bootstrap global disponible. El combate en dark mode tendrá body oscuro con cards
  Bootstrap blancas (contraste aceptable). No hay `<a>` en las vistas de combate, por lo que el
  override de enlaces no le afecta.
- **Transiciones**: toggle `.dark` en html → body cambia instantáneamente (CSS puro, sin JS).
- **Error**: no aplica (cambios son CSS estático, no hay fetching).

## Tests

- `php artisan test --compact --filter=Battle` → 150 passed (baseline confirmado).
- `php artisan test --compact --filter=Combate` → 8 passed (3 de CombateLivewireTest + 5 de otros).
- `npm run build` → debe regenerar manifest SIN entradas combate.css/js.
- `php artisan view:cache` → compila todas las vistas sin errores.

## Verificación build

- `npm run build` regenera `public/build/manifest.json` con solo `resources/css/app.css` y
  `resources/js/app.js`.
- Tras build, `npm run dev` o `composer run dev` recarga Vite en modo dev.

## Riesgos accesibilidad/UX

- **Body light mode**: pierde `bg-gray-50` (#f9fafb) → blanco puro (#fff). Diferencia sutil.
  No se considera regresión (user aceptó la iteración 1 así).
- **Combate en dark mode**: fondo oscuro (#111827) en lugar de blanco (#fff) como en la iteración
  de aislamiento. Las cards Bootstrap blancas contrastan bien. El cambio es aceptable y no afecta
  la jugabilidad.
- **Enlaces**: el override `a { color: inherit; text-decoration: inherit }` asegura que los links
  del nav y otras páginas mantengan el estilo Tailwind previsto, en lugar de azul+subrayado de
  Bootstrap Reboot.
- **Headings**: Bootstrap Reboot `h1-h6` (unlayered) gana a Tailwind `text-*` (layered). En combate
  se usan `h3.h5` (clase Bootstrap `.h5` gana). En otras páginas, los headings con clases Tailwind
  `text-*` quedarán con estilo Bootstrap (font-weight 500, margin). Este es un riesgo documentado
  pero no se interviene porque el usuario no lo reportó y el alcance es "overrides mínimos".

# Análisis Frontend — Aislar Bootstrap SOLO al combate de forma robusta (2026-08-30)

## Contexto

El commit `a83c40d` volvió Bootstrap a global (`resources/css/app.css`) para arreglar
el dark mode, pero rompió otras páginas: dark mode en nav / registro de batalla / próximos
turnos, `/equipos` (estilos, tipografía, búsqueda) y `/habitats` (bordes de niveles perdidos).
El intento anterior de aislamiento (`ba92d32`, combate.css) falló porque el CSS **no se cargaba**
(no había `@stack('styles')` en el layout ni inputs dedicados en Vite). Esta iteración aísla
Bootstrap de forma robusta: archivos propios (combate.css/combate.js), inputs Vite dedicados,
y stacks en el layout.

## Vistas/componentes a tocar

| Archivo | Acción |
|---------|--------|
| `resources/css/app.css` | QUITAR `@import bootstrap.min.css`, QUITAR estilos de combate y el parche `a { ... }`; CONSERVAR `@import "tailwindcss"`, `@custom-variant dark` y los overrides dark del body |
| `resources/css/combate.css` | CREAR con Bootstrap + estilos de combate (los que Bootstrap no cubre) |
| `resources/js/combate.js` | CREAR: `import './bootstrap'` PRIMERO (Livewire+Alpine, `$wire`), luego Bootstrap JS |
| `vite.config.js` | Añadir inputs `combate.css` y `combate.js` |
| `resources/views/layouts/app.blade.php` | Añadir `@stack('styles')` en `<head>` tras `@vite(app)` y antes de `@livewireStyles` (ya tiene `@stack('scripts')` en línea 88) |
| `resources/views/combate_page.blade.php` | Añadir `@push('styles')` con combate.css, `@push('scripts')` con combate.js, y reestructurar a `@section('content')` |
| `resources/views/livewire/partials/moves-panel.blade.php` | Ordenar `$currentMoves` por daño desc + empate alfabético (antes del `@if($phase==='player_move')`, después de `$maxDmg`). ⚠️ Se usa `uasort` y no `usort` para preservar claves originales (index = posición en `moves()`), porque `selectMove($index)` usa `moves()->get($index)` — ver `Combate.php:471`. |

NO se tocan: `app/Livewire/Combate.php`, `src/`.

## DTOs/contratos consumidos

Ninguno nuevo. `$currentMoves`, `$maxDmg`, `$phase` ya se pasan al partial.

## Estados UI cubiertos

- **Otras páginas** (`/equipos`, `/habitats`, resto): SOLO Tailwind. Sin Bootstrap → dark mode,
  tipografía, bordes y búsqueda correctos.
- **Combate** (`/combate`): Bootstrap 5.3.8 + estilos de combate + Tailwind base (app.css sigue
  presente vía layout). Bootstrap JS (tooltips/collapse/alert) solo aquí.
- **Dark mode**: toggle del header (Alpine) sigue en todas las páginas; overrides `.dark` del
  body en app.css se conservan y no dependen de Bootstrap.

## Riesgos accesibilidad/UX

- **Orden de carga**: en combate, `@stack('styles')` carga combate.css DESPUÉS de app.css →
  los estilos de combate (y Bootstrap Reboot) ganan a Tailwind layered solo donde hay conflicto
  (esperado). El `@custom-variant dark` de Tailwind no se ve afectado.
- **`$wire`**: combate.js importa `./bootstrap` PRIMERO (arranca Livewire/Alpine) y luego
  Bootstrap JS, evitando el error "Livewire not started" / "$wire is not defined".
- **Carga de scripts**: `@push('scripts')` → `@stack('scripts')` (línea 88 del layout) → combate.js
  al final del body, correcto.
- **app.js intacto**: `resources/js/app.js` sigue importando `./bootstrap` + Bootstrap JS bundle
  (solo comportamiento JS, sin CSS global); el aislamiento efectivo del CSS es vía combate.css.
  No se modifica para minimizar cambios.

## Tests

- `php artisan test --compact --filter=Battle` → 150 passed.
- `php artisan test --compact --filter=Combate` → 8 passed.
- `npm run build` → manifest debe incluir `combate.css` y `combate.js`.

## Verificación

- HTML de `/combate`: carga app.css/app.js + combate.css/combate.js.
- `/equipos` y `/habitats`: NO cargan combate.css (sin Bootstrap).
- Dark mode OK en resto de páginas (toggle del header).
