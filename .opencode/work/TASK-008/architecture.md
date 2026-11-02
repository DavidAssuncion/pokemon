# TASK-008 — Arquitectura

- **Arquitecto:** `architect`
- **Fecha:** 2026-11-02
- **Estado:** aprobada (plan v1)
- **Nota:** reconstrucción fiel del diseño de TASK-007, renumerado a TASK-008. Las decisiones no cambian.

## Decisiones

1. **Familia evolutiva:** resolvedor puro en `src/Pokemon/Domain/Evolution/` con cierre conectado por `pokemon_evolution`, multi-raíz, `visited`, `stage`, `parent_id`, `minimum_level`, `branch_index`. `ResolvedorCadenasEvolutivas` (Habitats) delega en modo compatibilidad (`multiRaiz=false`, `maxEtapas=3`), conservando API y DTO.
2. **Lectura:** `App\Support\FamiliaPokemon` carga cadena (1 consulta), aristas globales (1) y endpoints cross-chain faltantes (1); traduce `species_id → pokemon.id`.
3. **Presentación:** `App\Support\PresentadorDetallePokemon` construye el contrato aditivo; la closure `detail` queda como composición y guard.
4. **Contrato:** conserva `id`, `name`, `visto`, `atrapado`, `types`, `stats`, `habitat_name`, `habitat_id`, `habitats`; añade `type_details`, `location`, `family`.
5. **Badges:** `Src\Shared\UI\TipoBadges` es la fuente única por slug ASCII, con clases Tailwind literales, `label` de `TipoPokemon`, icono `/images/type/{slug}.webp`, componente Blade `<x-tipo-badge>` y helper JS global. Se migran solo los pills Tailwind de Pokédex, equipos, exploraciones y hábitats; gimnasios y Bootstrap no cambian visualmente.
6. **Guard:** en la closure `detail` del registro `pokemon`: `DesbloqueoPokedex::activo(request()) || visto`; si no, `null` → 404 en `DatagridController`.
7. **Sin migraciones, dependencias ni cambios en `TipoPokemon::color()`.**

## Contrato de `GET /datagrid/pokemon/{id}/detalle`

```json
{
  "id": 133,
  "name": "eevee",
  "visto": true,
  "atrapado": false,
  "types": ["Normal"],
  "type_details": [
    { "id": 1, "label": "Normal", "slug": "normal", "icon": "/images/type/normal.webp" }
  ],
  "stats": [{ "name": "PS (HP)", "value": 55 }],
  "habitat_name": "Bosque",
  "habitat_id": 1,
  "habitats": ["Bosque"],
  "location": {
    "habitat_id": 1,
    "habitat_name": "Bosque",
    "habitat_image": "/habitats-img/1.webp?v=1",
    "province_id": 3,
    "province_name": "Kanto",
    "url": "/habitats/1"
  },
  "family": {
    "evolution_chain_id": 67,
    "members": [
      {
        "id": 133, "name": "eevee", "species_id": 133,
        "stage": 1, "parent_id": null, "minimum_level": null,
        "branch_index": 0, "icon": "/images/iconos_webp/133.webp",
        "is_current": true
      },
      {
        "id": 134, "name": "vaporeon", "species_id": 134,
        "stage": 2, "parent_id": 133, "minimum_level": 25,
        "branch_index": 0, "icon": "/images/iconos_webp/134.webp",
        "is_current": false
      }
    ]
  }
}
```

Reglas:

- `types` se conserva como labels.
- `type_details` ordenado por `slot`.
- `location` null si no hay `pokemon_habitat`.
- `family.members` siempre ≥ 1, plano y ordenado por `stage`, `parent_id`, `branch_index`, `species_id`.
- `minimum_level` de la arista; `is_current` derivado del id actual.

## Estrategia de badge

- `TipoBadges`:
  - `porId`, `porSlug`, `porLabel`, `resolver`, `clases`, `icono`, `etiquetasPorId`, `paraJs`, `idsParaJs`, `DEFAULT`.
  - 18 tipos; unknown → `DEFAULT`.
  - Paleta canónica: `tipoClase` de equipos (la del span de movimientos).
  - Clases literales para que Tailwind las genere; `@source "../../src";`.
- Componente `resources/views/components/tipo-badge.blade.php`:
  - Pill `inline-flex shrink-0 items-center gap-1 rounded-full py-0.5 pl-0 pr-2 text-[11px] font-semibold`.
  - Icono 30×30 por defecto; tamaños `xs` (16), `sm` (20), `md` (30).
  - Icono `alt=""`, `loading="lazy"`, `decoding="async"`; se omite si no hay icono.
- Helper `resources/views/partials/tipos-badge-script.blade.php`:
  - `window.TIPOS_BADGE`, `window.TIPOS_BADGE_POR_ID`, `window.TIPOS_BADGE_DEFAULT`.
  - `window.tipoBadge(valor)` acepta slug/id/label; `window.tipoBadgePorId(id)`.
  - Sin `x-html` ni clases dinámicas.
- Vistas migradas:
  - `pokedex/index.blade.php`: tipos con `type_details` y helper; se elimina `getTypeClass`.
  - `equipos/index.blade.php`: filtro server-side, cabecera del modal y filtro de tipo de movimiento.
  - `equipos/partials/movimiento-card.blade.php`: helper.
  - `exploraciones/_explorador.blade.php`: `<x-tipo-badge>`.
  - `habitats/show.blade.php`: dos badges por id.
- No se migran: gimnasios (solo `etiquetasPorId()` para el texto), Bootstrap `moves-panel`/`combate`, iconos de sendas, caramelos/eventos, badges de estado.

## Guard

```php
detail: function (Model $model): ?array {
    $pokemon = $this->requirePokemon($model);

    if (! DesbloqueoPokedex::activo(request()) && ! (bool) $pokemon->getAttribute('visto')) {
        return null; // DatagridController → 404
    }

    return PresentadorDetallePokemon::para($pokemon);
},
```

- `$pokemon->visto` procede del leftJoin de `pokedex` del usuario autenticado.
- 404 para no visto sin unlock; 200 con `?unlock=1` en local/testing.
- La vista debe propagar `?unlock=1` en `loadDetail()` cuando `unlockAll`.

## Capas

- **Dominio:** `src/Pokemon/Domain/Evolution/` sin Laravel/Blade/Tailwind.
- **Aplicación/frontera:** `app/Support/FamiliaPokemon` y `PresentadorDetallePokemon`.
- **Infraestructura:** `DatagridServiceProvider`, `DesbloqueoPokedex`.
- **Presentación:** `TipoBadges`, componente, partial JS y vistas.

## Archivos

### Crear

- `src/Pokemon/Domain/Evolution/PokemonFamilia.php`
- `src/Pokemon/Domain/Evolution/AristaEvolutiva.php`
- `src/Pokemon/Domain/Evolution/MiembroFamiliaEvolutiva.php`
- `src/Pokemon/Domain/Evolution/FamiliaEvolutiva.php`
- `src/Pokemon/Domain/Evolution/ResolvedorFamiliaEvolutiva.php`
- `app/Support/FamiliaPokemon.php`
- `app/Support/PresentadorDetallePokemon.php`
- `resources/views/components/tipo-badge.blade.php`
- `resources/views/partials/tipos-badge-script.blade.php`
- Tests unitarios/feature correspondientes.

### Modificar

- `app/Providers/DatagridServiceProvider.php`
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php`
- `src/Shared/UI/TipoBadges.php`
- `resources/css/app.css`
- `resources/views/layouts/app.blade.php`
- `resources/views/pokedex/index.blade.php`
- `resources/views/equipos/index.blade.php`
- `resources/views/equipos/partials/movimiento-card.blade.php`
- `resources/views/exploraciones/_explorador.blade.php`
- `resources/views/habitats/show.blade.php`
- `resources/views/gimnasios/{index,show,admin}.blade.php` (solo etiquetas)
- `tests/Feature/DatagridTest.php`
- `tests/Feature/PokedexViewTest.php`
- `src/Pokemon/context.md`, `src/Pokemon/README.md`, `src/Shared/context.md` (documentación)

## Pruebas

- Unitarias: `ResolvedorFamiliaEvolutivaTest`, `TipoBadgesTest`, `FaseEvolutivaTest`.
- Feature: `DatagridTest`, `PokedexViewTest`, `TipoBadgeBladeTest`.
- Regresión: hábitats, gimnasios, equipos, exploraciones.
- Build: `npm run build` y verificación de clases.

## Riesgos

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Refactor de `ResolvedorCadenasEvolutivas` altera etapas | Alto | Modo compat, API intacta, `FaseEvolutivaTest` + hábitats. |
| Cambio visual de badges | Medio | Paleta aprobada; gimnasios no cambian. |
| Clases Tailwind no generadas | Medio | Clases literales + `@source` + build. |
| Guard 404 rompe vista/tests | Medio | Vista no fetch para no vistos; propagar unlock; test. |
| Familia cross-chain amplia | Medio | Solo endpoints alcanzados por aristas; test. |
| N+1 | Bajo | ≤8 consultas; familia 2-3. |
| WIP local | Medio | `git diff` por archivo; no revertir. |

## Criterios de aceptación del diseño

- [x] Contrato aditivo concreto.
- [x] Capas y dirección de dependencias.
- [x] Badge único viable en Blade y Alpine.
- [x] Guard definido.
- [x] Archivos y responsables.
- [x] Pruebas y riesgos.
- [x] Sin migraciones ni dependencias.
