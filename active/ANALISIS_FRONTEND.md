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
