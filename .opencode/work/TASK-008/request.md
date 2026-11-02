# TASK-008 — Detalle de Pokémon en la Pokédex: familia evolutiva, ubicación y badge de tipo genérico

- **Fecha de recepción:** 2026-11-02
- **Estado:** `RECEIVED`
- **Proyecto:** `/home/david/pokemon` (Laravel 12 / PHP 8.4, rama `master`)
- **Nota:** esta tarea se renumeró desde `TASK-007` porque otra sesión concurrente ocupó ese ID con una tarea distinta (fase evolutiva de caramelos). El contenido del plan y la aprobación se conservan.

## Petición literal

> Vamos a modificar el detalle del pokemon en la pokedex. Debemos mostrar la familia del pokemon,en  quien evoluciona o de donde viene, tambien poner la ubicacion, y un boton para dirigirnos al habitat. Para los estilos de los tipos, debemos usar los span de los movimientos. A partir de ahora se usara de forma generica para reprentar todos los tipos de la aplicacion.

## Interpretación operativa inicial

1. **Familia evolutiva en el modal de detalle:** mostrar de dónde viene (pre-evolución) y en quién evoluciona, dentro de la misma familia (`evolution_chain_id`). El Pokémon actual debe quedar identificado.
2. **Ubicación:** mostrar la ubicación del Pokémon (provincia y hábitat) en el detalle. Cada Pokémon tiene como máximo un hábitat en los datos actuales.
3. **Botón al hábitat:** acción explícita que navegue a `/habitats/{id}`.
4. **Tipos con el span de los movimientos:** sustituir el badge actual del detalle de la Pokédex por el mismo patrón visual de `resources/views/equipos/partials/movimiento-card.blade.php` (pill con icono `/images/type/{slug}.webp` y clases por tipo).
5. **Uso genérico:** el patrón de badge de tipo debe quedar como representación reutilizable para el resto de la aplicación.

## Contexto mínimo conocido

- La vista es `resources/views/pokedex/index.blade.php`; el detalle se carga por `fetch` a `/datagrid/pokemon/{id}/detalle` y se pinta con Alpine (`pokedexApp()`).
- El contrato del detalle se construye en `app/Providers/DatagridServiceProvider.php` (registro `pokemon`, closure `detail`).
- Datos disponibles:
  - `pokemon.evolution_chain_id` (579 familias; 0 Pokémon sin cadena; 223 familias de tamaño 1).
  - `pokemon_evolution` (514 filas; `evolves_from_species_id` → `evolved_species_id`, `minimum_level`; sin huérfanos).
  - `pokemon.species_id` relaciona el Pokémon con su especie; `PokemonEvolution` usa species IDs.
  - `pokemon_habitat` + `habitats` + `provinces`: cada Pokémon tiene 0 o 1 hábitat; 78 Pokémon sin hábitat.
  - `habitats` tiene `name`, `province_id`; `provinces` tiene `name`.
- Badge actual de tipos en el detalle: `getTypeClass(type)` (mapa JS en la propia vista) sin icono.
- Badge de referencia en movimientos: `resources/views/equipos/partials/movimiento-card.blade.php:4-6`:
  - `span` base `shrink-0 inline-flex items-center gap-1 pl-0 pr-2 py-0.5 text-[11px] font-semibold rounded-full`
  - `tipoClase(...)` con el mapa de `resources/views/equipos/index.blade.php:1909-1928`
  - `<img>` de `public/images/type/{slug}.webp` a `30×30`.
- Ya existe `Src\Shared\UI\TipoBadges` (mapa `id => [label, clases]`) usado por gimnasios; sus colores no coinciden exactamente con el mapa de movimientos.

## Restricciones

- No revertir ni sobrescribir el WIP local preexistente.
- No tocar migraciones ni dependencias.
- Mantener Blade/Alpine/Tailwind existentes.
- El detalle no debe revelar información de Pokémon no avistados (el fetch solo se lanza para vistos o `unlockAll`), y se añade guard backend.
- No introducir consultas N+1 en el listado ni en el detalle.

## Decisiones de alcance (2026-11-02)

1. **Familia:** familia completa. Todos los miembros de la cadena (`evolution_chain_id`), incluidas ramas, con el Pokémon actual destacado y el nivel de evolución cuando exista. Se muestran nombres e iconos aunque el Pokémon no esté avistado.
2. **Formas regionales:** se incluyen las 14 aristas que cruzan `evolution_chain_id` (formas regionales enlazadas).
3. **Badge genérico:** crear el componente/patrón reutilizable y migrar en esta tarea los pills de tipo existentes de Pokédex, equipos, exploraciones y hábitats. NO se migran los badges especiales de gimnasio (fondo hex sólido + texto blanco) ni el panel Bootstrap de combate.
4. **Ubicación:** mostrar la provincia, la imagen y el nombre del hábitat, y un botón para ir a `/habitats/{id}`.
5. **Seguridad:** añadir guard backend: el detalle solo devuelve datos si el Pokémon está visto o si `unlockAll` está activo.
