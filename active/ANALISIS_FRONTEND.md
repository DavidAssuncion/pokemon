# Análisis Frontend — Grid hábitats 8/row + reestructura show.blade.php (teams+niveles en 2/3)

## Vistas a tocar

### 1. `resources/views/habitats/index.blade.php`
- Grid de hábitats: `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5` → `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8` (8 por fila en xl).
- Nombre del hábitat centrado: añadir `text-center` al `<p>` del nombre.
- Resto intacto: tabs Alpine, cards `aspect-square` 300x300, dark mode, onerror.

### 2. `resources/views/habitats/show.blade.php`
- El grid superior (`lg:grid-cols-3` con back/título/imagen + botones construcción en fila) y el grid inferior (Equipos 1/3 + Niveles 2/3) se fusionan en UN solo `grid lg:grid-cols-3 gap-6`:
  - **Izquierda 1/3** (`space-y-4`): back link, título, card imagen `w-fit` (sin cambios), y los 3 botones de construcción (Granjas/Entrenadores/Mazmorras) **apilados verticalmente** (`w-full`, `space-y-3`), conservando estado disabled + `title` + aviso naranja cuando `$bloqueadoConstruccion`.
  - **Derecha 2/3** (`lg:col-span-2 space-y-6`): lista de exploraciones activas (se mantiene visible, encima del panel de equipos), panel **Equipos** con grid de cards `grid sm:grid-cols-2 gap-3` (misma card, iconos miembros `w-10 h-10` → `w-8 h-8` para compactar), empty state "No hay equipos creados" con `sm:col-span-2`, y debajo panel **Niveles** con las 3 filas clickeables (comportamiento intacto: selectLevel, highlight azul, overlay "?" no avistado, counts).
  - Se añade título de panel ("Equipos" / "Niveles") al header de cada panel según el diagrama del Arquitecto; se conserva el subtítulo existente.
  - El botón fallback "Iniciar Exploración" (usa `checkAndOpenModal()` + `canStartExploration`) se conserva al final de la columna 2/3 para no perder funcionalidad.
- El modal de exploración y el script `habitatShow()` con TODOS sus métodos quedan **intactos** (`selectTeam`, `selectLevel`, `checkAndOpenModal`, `isSighted`, `confirmExploration`, getters, etc.).

## DTOs consumidos
- `$habitat` (id, name, image, levels), `$exploracionesActivas`, `$teams`, `$equiposEnExploracion`, `$sightedPokemonIds` — mismo shape actual, **sin cambios backend**.

## Tests
- No hay Dusk en el proyecto (solo `tests/Feature` + `tests/Unit`); ningún test referencia las vistas de hábitats ni las clases del grid.
- Verificación: `php artisan view:cache`, `vendor/bin/pint --dirty --format agent`, grep de bindings Alpine (`selectTeam`, `selectLevel`, `checkAndOpenModal`, `canStartExploration`, `isSighted`) presentes en la vista.

## Estados UI a cubrir
- **Grid 8/row**: 8 cards por fila en xl, 6 en lg, 4 md, 3 sm, 2 base.
- **Nombre centrado**: `text-center` en el `<p>` del card.
- **Show — columna 1/3**: botones apilados `w-full`; disabled con `opacity-50 cursor-not-allowed` + tooltip lock + aviso naranja; imagen `w-auto` sin romper layout.
- **Show — columna 2/3**: 2 columnas de teams en sm+, empty state centrado a todo el ancho, filas de nivel clickeables con overlay "?" para no avistados, botón fallback habilitado solo con equipo+nivel seleccionados.
- **Modal auto-open**: seleccionar equipo + nivel abre el modal con las 3 opciones de duración.

## Riesgos accesibilidad/UX
- Las cards de equipo en 2 columnas son más estrechas: se compactan iconos (w-8) y se mantiene `truncate` en nombres; `min-w` no aplica (grid colapsa correctamente).
- El botón fallback queda al final de la columna derecha; su estado `:disabled` depende de `canStartExploration` (getter existente, sin cambios).
- El overlay "?" conserva `rounded` sobre img sin `rounded` (fuera de alcance, ya anotado).
- Sin `aria-selected` dinámico en tabs de index (ya anotado en análisis previo para QA).
