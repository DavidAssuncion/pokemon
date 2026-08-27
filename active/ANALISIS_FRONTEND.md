# Análisis Frontend — Fix imágenes pokémon en equipos/index.blade.php

## Vistas a tocar

### `resources/views/equipos/index.blade.php` (única vista afectada)

4 `<img>` con `:src="'/images/iconos/' + ..."`:

1. **Icono miembro de equipo** (columna izquierda, dentro de card de equipo, ~línea 108):
   - Actual: `class="w-12 h-12 object-contain mx-auto rounded bg-gray-100 dark:bg-gray-900"` → 48px + fondo + rounded.
   - Fix: `class="w-24 h-24 object-contain mx-auto"` (patrón exacto de habitats/show.blade.php línea 168).
   - Acompañante: placeholder de slot vacío `w-12 h-12` → `w-24 h-24` (mismo patrón que habitats/show línea 177).

2. **Pokémon disponible** (columna derecha, grid "Reclutados Disponibles", ~línea 214):
   - `<img class="w-full h-full object-contain">` dentro de `<div class="aspect-square p-1.5">`.
   - La imagen se renderiza al tamaño de la celda del grid (58px en lg con 10 columnas) → por debajo de 96px.
   - Fix: wrapper → `<div class="w-24 h-24 mx-auto">` (mismo patrón que habitats/show líneas 217-224), img se mantiene `w-full h-full object-contain` → exactamente 96px.
   - Grid: `grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10` → celdas < 96px en todos los breakpoints. Nueva cadena con celdas ≥ 96px (verificado por cálculo de anchos):
     `grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-9`
     - base 343px: 3 cols → 109px ✓ | sm 608: 5 → 115 ✓ | md 736: 6 → 116 ✓
     - lg (col 2/3 ≈ 653px): 6 → 102 ✓ | xl (≈813px): 7 → 109 ✓ | 2xl (≈984px): 9 → 102 ✓

3. **Pokémon asignado** (columna derecha, grid "Asignados", ~línea 266):
   - Mismo fix que #2 (wrapper `w-24 h-24 mx-auto` + misma cadena de columnas).
   - CONSERVAR `grayscale` (filtro gris de asignados) y `opacity-60` de la card.

4. **Tooltip hover** (modal tooltip de stats, ~línea 340):
   - `class="w-16 h-16 object-contain mx-auto"` (64px) → `class="w-24 h-24 object-contain mx-auto"` (96px, cabe en card w-56).

## DTOs consumidos
- Ninguno nuevo; se conservan todos los bindings Alpine (`:src`, `x-for`, `getMember`, `onerror`, etc.).

## Tests
- Sin Dusk en el proyecto. Verificación: `php artisan view:cache`, grep de clases eliminadas, `vendor/bin/pint --dirty --format agent`.

## Estados UI a cubrir
- Imagen disponible/asignada: 96px transparente sin borde; hover overlay intacto.
- Asignados: 96px + `grayscale` + card `opacity-60`.
- Miembro de equipo: 96px sin fondo; placeholder vacío 96px con borde dashed.
- Tooltip: 96px centrado.
- `onerror` (ocultar imagen si no existe) intacto en todas.

## Riesgos accesibilidad/UX
- Celdas < 96px solo en pantallas < 336px (celda 90px, recorte ~5px del box de 96px, tolerado; pokedex ya tiene iconos < 96px en 320px).
- La cadena de columnas del grid cambia densidad (lg: 10 → 6), necesario para cumplir mínimo 96px.
- No tocar cards (`bg-white dark:bg-gray-800`, bordes) ni botones ni modales: son elementos NO-imagen y deben conservar sus fondos.
