# Análisis Frontend — Imágenes 128px + fix JS habitat detail

## Vistas a tocar

### `resources/views/reclutamiento/index.blade.php` (Tarea 1a)
- Grid actual: `grid grid-cols-3 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-9 gap-3` con celdas < 128px en todos los breakpoints.
- Área imagen actual: `aspect-square relative bg-gray-50 dark:bg-gray-900 p-2` con `<img class="w-full h-full object-contain">`.
- Fix: área → `w-32 h-32 mx-auto relative` (transparente, patrón habitats/equipos), img conserva `w-full h-full object-contain`, badge de cantidad conservado (necesita `relative`).
- Grid nueva (celdas ≥ 128px verificadas, contenedor full-width px-4 sm:px-6):
  - base 343px: 2 cols → 165px ✓ | sm 592: 4 → 139 ✓ | md 720: 5 → 134 ✓
  - lg 976: 6 → 152 ✓ | xl 1232: 7 → 165 ✓ | 2xl 1488: 9 → 154 ✓
  - Cadena: `grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9`

### `resources/views/equipos/index.blade.php` (Tarea 1b)
- 5 ubicaciones `w-24 h-24` → `w-32 h-32` (128px):
  1. Icono miembro de equipo (línea ~112): `w-24 h-24 object-contain mx-auto` → w-32 h-32.
  2. Placeholder slot vacío (línea ~128): `w-24 h-24 mx-auto rounded border-2 border-dashed` → w-32 h-32.
  3. Wrapper disponible (línea ~213): `<div class="w-24 h-24 mx-auto">` → w-32 h-32.
  4. Wrapper asignado (línea ~265): `<div class="w-24 h-24 mx-auto">` → w-32 h-32 (conservar `grayscale` + card `opacity-60`).
  5. Tooltip (línea ~343): `w-24 h-24 object-contain mx-auto` → w-32 h-32 (cabe en card w-56).
- Grids disponible/asignado actuales: `grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9` → celdas < 128px (99-113px). Nueva cadena (columna derecha 2/3 en lg+):
  - base 343: 2 → 167 ✓ | sm 592: 3 → 192 ✓ | md 720: 4 → 174 ✓
  - lg (col ≈635px): 4 → 152 ✓ | xl (≈805px): 5 → 154 ✓ | 2xl (≈976px): 6 → 156 ✓
  - Cadena: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6`
- Limitación conocida: columna izquierda (cards de equipo, 1/3 en lg) tiene celdas ≈92px en lg → la imagen de 128px se recorta ligeramente por `overflow-hidden` de la card (ya ocurría con 96px en lg). Se cumple la instrucción literal: bump a w-32 h-32 sin restructurar.

### `resources/views/habitats/show.blade.php` (Tarea 2 — JS roto)
**CAUSA RAÍZ (verificada renderizando la vista):** línea 341
```js
get availableTeams() {
    return @json($teams)->filter(t => !this.equiposEnExploracion.includes(t.id));
},
```
`->` es operador de PHP, **no es JavaScript válido** → el render produce `return [{...}]->filter(...)` → **SyntaxError al parsear TODO el `<script>`** → `habitatShow()` nunca se define → `x-data="habitatShow()"` falla → TODOS los clics muertos (cards de equipo, filas de nivel, botón explorar).

**Causa secundaria:** `@click="selectTeam({{ $team->id }}, '{{ addslashes($team->name) }}')"` — `addslashes` NO protege atributos HTML: un nombre con `"` rompe el atributo (la card deja de responder silenciosamente). Mismo problema en `@keydown.space.prevent` y `@keydown.enter.prevent`.

**Verificado:** `json_encode(Team::with('members.reclutado.pokemon')->get())` es válido (3.3KB) — el problema NO es `@json($teams)` en sí, sino el `->filter`. `$equiposEnExploracion` es Collection (controller lo mapea) ✓. `@stack('scripts')` existe en el layout ✓.

**Fix:**
1. Inicializar `teams: @json($teams)` como propiedad (JSON verificado válido).
2. Getter seguro:
```js
get availableTeams() {
    return (this.teams || []).filter(t => !this.equiposEnExploracion.includes(t.id));
},
```
3. Patrón data-attribute para clic y teclado:
```blade
@click="selectTeam({{ $team->id }}, $el.dataset.teamName)"
@keydown.space.prevent="selectTeam({{ $team->id }}, $el.dataset.teamName)"
@keydown.enter.prevent="selectTeam({{ $team->id }}, $el.dataset.teamName)"
data-team-name="{{ $team->name }}"
```
(`{{ }}` escapa HTML; `dataset` devuelve el valor decodificado; `$el` es la card en Alpine).

## DTOs consumidos
- Ninguno nuevo. `$teams` (array de Team Eloquent), `$equiposEnExploracion` (Collection), `$sightedPokemonIds` (array ints).

## Tests
- Sin Dusk en el proyecto. Verificación: render real de la vista con datos (tinker) + `php artisan view:cache` + `vendor/bin/pint --dirty --format agent`.

## Estados UI
- Reclutamiento: imagen 128px transparente; badge cantidad intacto; empty state intacto.
- Equipos: disponible/asignado 128px; asignado con `grayscale` + `opacity-60`; hover overlay intacto; tooltip 128px; placeholder vacío 128px dashed.
- Habitat detail: clic en card de equipo y fila de nivel abre el modal de exploración; teclado (Enter/Space) funciona; equipos en exploración siguen bloqueados.

## Riesgos accesibilidad/UX
- Menos densidad en grids (necesario para mínimo 128px).
- Cards de equipo en lg recortan ligeramente imágenes laterales (limitación aceptada, instrucción literal del usuario).
- `data-team-name` con `{{ }}` escapa `"`/`&`/`<` → dataset seguro.
