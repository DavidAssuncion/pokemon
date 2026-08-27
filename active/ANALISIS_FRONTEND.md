# Análisis Frontend — Imágenes Pokémon 96px en Equipos + Placeholder no visto en Pokédex/Hábitat + Iconos construcción

## Vistas a tocar

### 1. `resources/views/equipos/index.blade.php` — imágenes Pokémon a 96px (w-24)
- Revertir TODAS las imágenes de Pokémon de `w-32 h-32` → `w-24 h-24` (5 ubicaciones):
  1. Ícono de miembro en card de equipo (`class="w-32 h-32 object-contain mx-auto"`).
  2. Placeholder de slot vacío (`w-32 h-32 mx-auto rounded border-2 border-dashed ...`).
  3. Wrapper de grid "Reclutados Disponibles" (`<div class="w-32 h-32 mx-auto">`).
  4. Wrapper de grid "Asignados" (idéntico al anterior).
  5. Imagen del tooltip de stats (`class="w-32 h-32 object-contain mx-auto"`).
- Mantener: transparente, sin bg/borde en imágenes, `grayscale` en asignados, bindings Alpine, `onerror` display none.
- `resources/views/reclutamiento/index.blade.php` NO se toca (se queda en 128px).

### 2. `resources/views/pokedex/index.blade.php` — placeholder para no visto
- Grid card: reemplazar `<img>` con `:class="{ 'grayscale opacity-40': !pokemon.visto }"` por dos `<template x-if>`:
  - `!pokemon.visto` → `<img src="/images/reward/pokemon_encounter/0.png" alt="Pokemon desconocido">`.
  - `pokemon.visto` → img normal con `transition-transform group-hover:scale-110` y `onerror` (snippet del Arquitecto).
- Modal detalle: misma lógica con `selectedPokemon`; mantener `w-24 h-24 object-contain mx-auto mb-3` en ambos.
- Imagen verificada: `public/images/reward/pokemon_encounter/0.png` existe.

### 3. `resources/views/habitats/show.blade.php` — Niveles: placeholder en no avistado
- Reemplazar el overlay oscuro con "?" (`x-show="!isSighted(...)"`) por dos `<template x-if>` con placeholder `0.png` vs ícono real; mantener `w-24 h-24` y `relative`.
- `isSighted()` ya existe en `habitatShow()`; `{{ $pokemon['id'] ?? $pokemon['species_id'] ?? 0 }}` se interpola en PHP.

### 4. `resources/views/habitats/show.blade.php` — botones construcción con `<img>` en vez de SVG
- Granjas → `/images/reward/item/303.png`; Entrenadores → `/images/reward/item/1503.png`; Mazmorras → `/images/misc/raid.png`.
- Tamaño uniforme `w-8 h-8 object-contain` en los tres; mantener texto, clases, disabled, title.
- Imágenes verificadas en `public/images/` (303.png, 1503.png, raid.png existen).

## DTOs consumidos
- Ninguno nuevo. `$teams`, `$reclutados`, `$teamIds` (equipos); `$pokemons` con `visto/atrapado` (pokédex); `$habitat['levels']`, `$sightedPokemonIds`, `$exploracionesActivas` (hábitat).

## Tests
- Sin Dusk en el proyecto (verificado en análisis previo). Verificación: `php artisan view:cache` compila sin errores, `vendor/bin/pint --dirty --format agent`, greps de confirmación.

## Estados UI cubiertos
- Equipos: miembro real, slot vacío, grid disponible, grid asignado (grayscale), tooltip.
- Pokédex: tarjeta vista/no vista, modal vista/no vista, `onerror` oculta imágenes faltantes.
- Hábitat: nivel con pokémon avistado, nivel con no avistado, nivel vacío ("Sin Pokémon en este nivel"), botones construcción habilitados/disabled (opacity-50).

## Riesgos accesibilidad/UX
- Placeholder con `alt="Pokemon desconocido"` / `alt="?"`: informativo, sin duplicar nombres reales para no spoilear especies no vistas.
- `<template x-if>` con condición interpolada en PHP en hábitat: ambos `<template>` evalúan en Alpine; solo uno se renderiza.
- Imágenes de botones construcción llevan `alt` descriptivo (accesibilidad, reemplaza SVG que era decorativo con `aria-hidden` implícito).
- `w-8 h-8` en botones con `gap-2 flex items-center justify-center`: alineación vertical correcta.
