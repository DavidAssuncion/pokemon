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
