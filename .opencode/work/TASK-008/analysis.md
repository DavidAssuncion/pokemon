# TASK-008 — Análisis

- **Analista:** `analyst`
- **Fecha:** 2026-11-02
- **Estado:** completado
- **Nota:** reconstrucción fiel del análisis de TASK-007, renumerado a TASK-008 por colisión de artefactos. Las conclusiones no cambian.

## Resumen

El detalle actual de la Pokédex devuelve tipos (etiquetas), stats y un hábitat plano; no devuelve familia evolutiva, provincia ni imagen/nombre estructurados de hábitat. La familia puede resolverse con 2-3 consultas indexadas a partir de `pokemon.evolution_chain_id` y `pokemon_evolution`, pero el resolvedor existente (`ResolvedorCadenasEvolutivas`) aplana ramas y descarta `minimum_level`, por lo que no sirve tal cual. Los badges de tipo están duplicados en al menos cuatro mapas Tailwind distintos más un mapa de colores hex para gimnasios; el patrón visual de `movimiento-card.blade.php:4-6` debe convertirse en el canónico.

Hechos verificados en BD: `pokemon.id == pokemon.species_id` en los 1083 registros, todas las filas de `pokemon_evolution` resuelven a `pokemon.id`, 0 Pokémon sin `evolution_chain_id`, 579 cadenas, 223 de tamaño 1, 33 padres ramificados y 14 aristas que cruzan cadena (formas regionales). Un Pokémon tiene como máximo un hábitat; 78 no tienen hábitat.

## Requisitos

- **Funcionales:**
  - Mostrar en el modal de detalle la familia evolutiva completa, incluidas ramas, niveles de evolución y formas regionales enlazadas.
  - Mostrar provincia, imagen y nombre del hábitat, con enlace a `/habitats/{id}`.
  - Migrar los pills de tipo de Pokédex, equipos, exploraciones y hábitats al patrón visual del span de movimientos.
  - Mantener el comportamiento actual de tipos, stats y hábitat.
- **No funcionales:**
  - Sin N+1 en el detalle (presupuesto constante, ≈8 consultas).
  - Contrato aditivo y compatible.
  - Dominio puro: sin Blade/Tailwind/Eloquent en `src/**/Domain`.
  - Accesibilidad: icono decorativo `alt=""`, texto visible, no depender solo del color.
  - Sin migraciones ni dependencias nuevas.
- **Criterios de aceptación:** contrato actual conservado; familia y ubicación verificables; singleton sin ramas; ramas y niveles conservados; badges con un único mapa e icono.

## Evidencia principal

- `app/Providers/DatagridServiceProvider.php:41-120` — registro `pokemon`; `detail` devuelve `id`, `name`, `visto`, `atrapado`, `types`, `stats`, `habitat_name`, `habitat_id`, `habitats`.
- `app/Providers/DatagridServiceProvider.php:106,218-225` — `types` son labels (`typeNames`), sin id ni slug.
- `app/Datagrid/DatagridService.php:78-94` — `detail()` resuelve por `find($id)` y delega en la closure.
- `app/Http/Controllers/DatagridController.php:28-39` — 404 si `detail()` es null; sin comprobación de visibilidad.
- `routes/datagrid.php:9-10`, `routes/web.php:16,44` — rutas dentro del grupo `auth`.
- `resources/views/pokedex/index.blade.php:348-407` — tipos con `getTypeClass`, stats y hábitat; `:711` fetch del detalle; `:740-762` mapa `getTypeClass`.
- `app/Support/CadenasEvolutivas.php:30-48` — agrupa por `evolution_chain_id`; no conserva niveles.
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php:82-98` — BFS limitado a 3 etapas; pierde ramas y `minimum_level`.
- `src/Habitats/Domain/DataTransferObjects/MiembroCadena.php:13-38` — DTO `{id,nombre,icono,stage,speciesId}`, sin nivel ni padre.
- `app/Models/Pokemon.php:20-30` — `species_id` y `evolution_chain_id`.
- `app/Models/PokemonEvolution.php:13-24` — tabla y `evolvedSpecies()`; sin relación inversa.
- `app/Models/PokemonType.php:20-35` — cast `type => TipoEnum`; `tipo_nombre`.
- `app/Models/Habitat.php:37-49` — `province()` y `pokemon()`.
- `src/Shared/UI/TipoBadges.php:20-42` — mapa int→[label, clases]; usado por gimnasios.
- `src/Shared/Tipos/TipoPokemon.php:61-64` — `slug()` ASCII; `:67-89` `color()` hex.
- `resources/views/equipos/partials/movimiento-card.blade.php:4-6` — patrón de referencia.
- `resources/views/equipos/index.blade.php:1909-1941` — `tipoClase()` con paleta canónica.
- `resources/views/gimnasios/index.blade.php:55-57`, `show.blade.php:46-48`, `admin.blade.php:85-87` — badge hex de gimnasio.
- `resources/views/exploraciones/_explorador.blade.php:11-31,85-89` — `$claseTipo` propio, sin icono.
- `resources/views/habitats/show.blade.php:867-874,1155-1162` — badges azules genéricos.
- `resources/views/livewire/partials/moves-panel.blade.php:29,36` — panel Bootstrap (fuera de alcance).
- `tests/Feature/DatagridTest.php:253-321` — shape, 404 y no visto.
- `tests/Feature/PokedexViewTest.php:31-151` — markup y contrato de la vista; no aserta clases de color.

## Inventario de badges

**Pills de tipo a migrar:**

- `resources/views/equipos/index.blade.php:296-311` — filtro server-side; `tipoClase` + icono 20px.
- `resources/views/equipos/index.blade.php:884-893` — cabecera del modal, Alpine.
- `resources/views/equipos/index.blade.php:1034-1036` — filtro de tipo de movimiento.
- `resources/views/equipos/partials/movimiento-card.blade.php:4-6` — referencia.
- `resources/views/pokedex/index.blade.php:354-360` — modal, Alpine, sin icono.
- `resources/views/exploraciones/_explorador.blade.php:85-89` — ficha de explorador, sin icono.
- `resources/views/habitats/show.blade.php:867-874,1155-1162` — familias, badge azul genérico.

**Fuera de alcance:**

- Badges de gimnasio (`gimnasios/index|show|admin`): hex sólido + texto blanco.
- Panel Bootstrap `livewire/partials/moves-panel` y `livewire/combate`.
- Iconos sueltos de sendas (`sendas/index|show`), caramelos/eventos y badges de estado.

**Mapas de color duplicados:**

1. `pokedex/index.blade.php:741-760` (`getTypeClass`).
2. `equipos/index.blade.php:1910-1930` (`tipoClase`).
3. `src/Shared/UI/TipoBadges.php:20-39` (`MAP`).
4. `exploraciones/_explorador.blade.php:11-31` (`$claseTipo`).
5. `TipoPokemon::color()` hex, usado por gimnasios y sendas.

## Estrategia recomendada

- **Familia:** resolvedor puro nuevo en `src/Pokemon/Domain/Evolution/` con cierre conectado, multi-raíz, `visited`, `stage`, `parent_id`, `minimum_level` y `branch_index`; Hábitats delega en modo compatibilidad (`multiRaiz=false`, `maxEtapas=3`).
- **Contrato aditivo:** conservar `types`, `stats`, `habitat_name`, `habitat_id`, `habitats`; añadir `type_details`, `location` y `family`.
- **Badges:** `TipoBadges` como fuente única por slug ASCII con clases literales, componente Blade y helper JS; migrar solo los pills Tailwind.
- **Guard:** en la closure `detail` del registro `pokemon`: `visto || unlockAll`; si no, 404.

## Riesgos

- **[alto] Fuga de información de no avistados:** el endpoint no comprobaba `visto`. Mitigación: guard backend 404.
- **[alto] La familia revela especies no avistadas:** decisión de producto: mostrar siempre.
- **[medio] Formas regionales fuera de la cadena:** 14 aristas cross-chain; decisión: incluirlas.
- **[medio] Duplicación de mapas de color:** mitigación: `TipoBadges` único.
- **[medio] Clases Tailwind dinámicas:** mitigación: clases literales + `@source`.
- **[medio] Regresión visual en gimnasios:** mitigación: no cambiar su badge, solo la fuente de la etiqueta.
- **[bajo] N+1, ciclos, accesibilidad, WIP local:** mitigaciones en el plan.

## Verificaciones cerradas por el arquitecto

- `habitats/index.blade.php` no tiene badges de tipo.
- Ningún test aserta clases de color de tipo; el riesgo de regresión es bajo.
- `/habitats/{id}` está dentro de `auth`; cualquier usuario autenticado puede verlo.
- `DesbloqueoPokedex::activo()` usa config o `?unlock=1` en local/testing/debug.
- Tailwind autodetecta `src/**`; se añade `@source` como garantía.
- `src/Pokemon` existe con `Domain/`; es el lugar del resolvedor.

## Recomendación

Implementar el plan v1 con las decisiones de producto ya cerradas: familia completa con formas regionales, badges migrados solo en pills Tailwind, ubicación provincia + hábitat + botón y guard backend 404.
