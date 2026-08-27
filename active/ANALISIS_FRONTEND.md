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
