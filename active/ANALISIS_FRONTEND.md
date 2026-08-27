# Análisis Frontend - Vistas Pokedex, Habitat Show, Reclutamiento, Equipos

## Vistas a crear/modificar

### 1. `resources/views/pokedex/index.blade.php` (NUEVA)
- Vista standalone con grid responsivo de pokemon (3/6/9 columnas)
- Estados: no vistos (grayscale + "???"), vistos (nombre visible), atrapados (borde verde + badge)
- Filtros: Todos / Vistos / Atrapados + búsqueda por nombre
- Modal detalle: stats, tipo, cadena evolutiva, hábitat
- DTO consumido: `$pokemons` collection con `visto`, `atrapado`, `habitat_name` appendados
- Modal con Alpine.js x-data para estado del modal

### 2. `resources/views/habitats/show.blade.php` (REESCRIBIR)
- Sección superior: 3 columnas (botón volver + nombre | imagen | botones placeholder)
- Sección inferior: 1/3 teams panel + 2/3 pokemon grid
- Modal exploración Alpine.js con opciones de duración
- Se reutilizan partials `_level-preview.blade.php` y `_family-modal.blade.php`
- DTO: `$habitat`, `$teams`, `$exploracionActiva`

### 3. `resources/views/reclutamiento/index.blade.php` (NUEVA)
- Header con título + "Descartar todos" con confirmación
- Grid pokemon 9 columnas, cada celda con imagen + cantidad + botón Reclutar
- Alpine.js para gestión de cantidad y diálogos de confirmación
- Empty state: "No hay Pokémon reclutables"
- DTO: `$reclutables` collection de Reclutable con pokemon relationship

### 4. `resources/views/equipos/index.blade.php` (NUEVA)
- Layout 2 columnas: 1/3 teams + 2/3 pokemon disponibles
- Teams: lista con 3 slots, botón eliminar, botón nuevo equipo
- Pokemon disponibles: grid con botón [+], asignados en grayscale con [→]
- Filtros: búsqueda + tipo
- Tooltip en hover para stats
- Alpine.js para todas las interacciones
- DTO: `$teams`, `$reclutados`, `$teamIds`

## Patrones Tailwind a seguir (del proyecto existente)
- `bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700` para cards
- `min-h-screen bg-gray-50 dark:bg-gray-900` para fondo de página
- `max-w-6xl mx-auto px-4 py-8` para contenedor principal
- `text-gray-900 dark:text-white` para texto principal
- `text-gray-500 dark:text-gray-400` para texto secundario
- `text-blue-600 dark:text-blue-400` para enlaces
- Imágenes: `src="/images/iconos/{id}.png"` con `onerror="this.style.display='none'"`
- Botones: `px-4 py-2 rounded-lg text-sm font-medium transition-colors`

## Estados UI a cubrir
- **Pokedex**: vacío (no hay pokemon), sin resultados de búsqueda, loading modal
- **Habitat show**: sin equipos, equipo vacío, exploración activa (botón deshabilitado)
- **Reclutamiento**: vacío (no hay reclutables), cantidad > 1, confirmación descarte
- **Equipos**: sin equipos, sin pokemon disponibles, equipo inválido (<3 miembros)

## Accesibilidad
- `role="button"` + `tabindex="0"` en elementos clickeables
- `aria-label` en botones e interacciones
- Soporte `@keydown.space.prevent` y `@keydown.enter.prevent` para navegación por teclado
- Contraste de colores en dark mode verificado

## Riesgos
- Nuevos directorios `pokedex/`, `reclutamiento/`, `equipos/` — requieren controlador (el backend los creará)
- El campo `habitat_name` en pokedex debe ser appendado por el controlador
- Reclutamiento necesita modelo `Reclutable` (o se usa `Reclutado` con cantidad)

---

# Tarea actual: Restructurar `resources/views/habitats/show.blade.php`

## Vistas/componentes a tocar
- `resources/views/habitats/show.blade.php` (único archivo; sin partials nuevos)

## DTOs consumidos (sin cambios)
- `$habitat` (array: name, image, id, levels[1..3][] => {id|species_id, name, icon})
- `$teams`, `$equiposEnExploracion`, `$exploracionesActivas`, `$sightedPokemonIds`

## Cambios estructurales
1. **Top**: quitar grid 3 columnas → columna única: back link + título + botones construcción (inline, flex-wrap) → imagen full-width (aspect-[16/9]) debajo.
2. **Niveles + Pokémon**: fusionar panel "Nivel de exploración" + "Pokémon del hábitat" en UN panel "Niveles" (2/3 derecho) con 3 filas clickeables (selectLevel). Equipos queda 1/3 izquierdo. Grid `lg:grid-cols-3`.
3. **Modal automático**: `selectTeam()`/`selectLevel()` llaman `checkAndOpenModal()`; si ambos seleccionados → `openExplorationModal()`. Botón "Iniciar Exploración" queda como fallback llamando `checkAndOpenModal()`.
4. Mantener: modal duración (3 opciones), lista exploraciones activas, botones construcción con disabled, grayscale no vistos (`isSighted` + overlay "?" en filas de nivel).

## Estados UI a cubrir
- Nivel sin pokémon → "0 pokémon" + hint vacío
- Equipo en exploración → bloqueado (existing)
- Sin equipos → empty state (existing)
- Modal: auto-apertura con team+nivel, cancelar cierra sin limpiar selección

## Tests
- No existen tests para esta vista. Verificación: `php artisan view:cache` (compila Blade) + revisión manual de bindings Alpine.

## Riesgos
- Overlay `x-show="!isSighted(...)"` requiere `isSighted` existente (sí, se mantiene).
- `@keydown` en filas nivel: usar `<button>` nativo (accesible por defecto, sin role/tabindex manual).
