# Análisis Frontend — Layout full-width con nav centrado + grid hábitats 5/row + restructura show.blade.php

## Vistas a tocar

### 1. `resources/views/layouts/app.blade.php`
- Quitar el link logo "🎮 Pokemon" (no existe en el markup actual: el logo ya no está; confirmar solo nav links).
- Contenedor header: `max-w-7xl mx-auto px-4` → `px-4 sm:px-6` (full width, sin boxed).
- `<main>`: `max-w-7xl mx-auto px-4 py-6` → `px-4 sm:px-6 py-6`.
- Nav: `justify-between` → `justify-center`; el wrapper de links queda centrado.
- Toggle dark: sale del wrapper de links y pasa a `absolute right-0 top-1/2 -translate-y-1/2` dentro del nav `relative` (el nav ya vive dentro del contenedor con `px-4 sm:px-6`, así `right-0` lo alinea con el borde del contenido).
- Se conservan: flash messages, `@vite`, script anti-flash dark mode, `[x-cloak]`, `@stack('scripts')`.

### 2. `resources/views/habitats/index.blade.php`
- Grid de hábitats: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` → `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5` (5 por fila en desktop).
- Resto intacto: tabs Alpine, cards `aspect-square` 300x300, dark mode, onerror.

### 3. `resources/views/habitats/show.blade.php`
- Sección superior apilada → grid `lg:grid-cols-3`:
  - Izquierda 1/3: back link, título, imagen auto-sized (`w-fit` + `w-auto h-auto max-w-full` en un card con `p-3`).
  - Derecha 2/3: 3 botones construcción (Granjas/Entrenadores/Mazmorras) en fila `flex flex-wrap` con `flex-1 min-w-[200px]`, estado disabled + `title` lock cuando `$bloqueadoConstruccion`, y aviso naranja `text-xs`.
- Abajo se conserva: lista exploraciones activas, grid `lg:grid-cols-3` (Equipos 1/3 + Niveles 2/3), modal auto-open, script `habitatShow()`.
- Iconos pokémon (Niveles y Equipos): quitar fondo/borde → `w-full h-full object-contain` / `w-10 h-10 object-contain mx-auto` (PNGs transparentes). Mantener overlay "?" de no avistado.

## DTOs consumidos
- `$habitat` (array: id, name, image, levels), `$provincias`, `$exploracionesActivas`, `$teams`, `$equiposEnExploracion`, `$sightedPokemonIds` — mismo shape actual, **sin cambios backend**.

## Tests
- No hay Dusk en el proyecto. Cambio puramente de presentación.
- Verificación: `php artisan view:cache`, grep de `max-w-7xl` en layout y `bg-gray-100 dark:bg-gray-800` en imgs de show.blade.php, `vendor/bin/pint --dirty`.

## Estados UI a cubrir
- **Nav**: links centrados, active azul, `whitespace-nowrap` + `overflow-x-auto` móvil, toggle visible a la derecha (absolute) en cualquier ancho.
- **Grid 5/row**: 5 cards por fila en lg+, 2/3/4 en pantallas menores.
- **Show top**: imagen auto-sized sin romper layout (max-w-full evita overflow), botones disabled con `opacity-50` + tooltip lock, aviso naranja.
- **Iconos**: sin fondo/borde, overlay "?" para no avistados se mantiene.

## Riesgos accesibilidad/UX
- El toggle absolute puede solaparse con links en móvil muy estrecho (mitigado por `overflow-x-auto` en el wrapper).
- Imagen de hábitat en show puede ser grande: `max-w-full` la limita al ancho del card `w-fit`.
- El overlay "?" conserva `rounded` sobre img sin `rounded` (solo visible sobre PNG transparente; sin impacto visual, fuera de alcance).
- Sin `aria-selected` dinámico en tabs de index (ya anotado en análisis previo para QA).
