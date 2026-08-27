# Arquitectura del proyecto

## Visión general

Arquitectura híbrida: Laravel estándar (`app/`) + DDD/Hexagonal (`src/`). El código de dominio vive en `src/` sin dependencias del framework. La infraestructura (Livewire, Eloquent, HTTP) vive en `app/`.

```
┌─────────────────────────────────────────────┐
│                  app/                        │
│  Livewire · Controllers · Models · Providers │
│  Datagrid · Support · Console (comandos)     │
├─────────────────────────────────────────────┤
│                  src/                        │
│  Battle · Pokemon · Habitats · Equipos      │
│  Reclutamiento · Crud · Shared              │
└─────────────────────────────────────────────┘
```

## Módulos (src/)

### Battle (`src/Battle/`)

Núcleo del sistema. Organizado en 5 subcarpetas:

| Carpeta | Propósito | Archivos clave |
|---|---|---|
| `Domain/` | Lógica de dominio pura | `AgregadoBatalla`, `Combatiente`, `EquipoBatalla`, `GestorTurnos`, `MovimientoBatalla`, `AccionBatalla`, `ServicioEjecucionBatalla`, `Posicion`, `DatosPokemonBatalla`, `BattleAggregate`, `BattleSrv` |
| `Domain/Chain/` | Chain of Responsibility para daño | `CadenaDanio`, `ManejadorDanioBase`, `ManejadorEfectividadTipo`, `ManejadorSTAB`, `ManejadorCritico`, `ManejadorPosicion`, `ManejadorClima`, `ManejadorOrbeVida` |
| `Domain/Effects/` | Strategy pattern para efectos | `InterfazEfecto`, `FabricaEfectos`, `EfectoPerforacionArmadura`, `EfectoRegeneracionDefensa`, `EfectoInvocadorClima`, `EfectoRestos`, `EfectoOrbeVida` |
| `Domain/Observer/` | Observer pattern para eventos | `SujetoBatalla`, `ObservadorBatalla` |
| `App/` | Casos de uso | `IniciarBatalla` |
| `Infrastructure/` | Persistencia/servicios externos | `FabricaBatallaMock` |
| `Presentation/` | DTOs para la vista | `DTOMovimientoBatalla` |

### Pokemon (`src/Pokemon/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `PokemonEntity`, `Stats/PokemonStats`, `Movement/PokemonMovements` |

Entidad de dominio compartida entre batalla automática y manual.

### Habitats (`src/Habitats/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `HabitatEntity`, `ProvinceEntity`, `HabitatsCollection`, `ProvinciasCollection`, `Repositories/` |
| `App/` | 3 casos de uso: `ObtenerHabitatsPorProvincia`, `ObtenerHabitatDetalle`, `ObtenerPokemonsPorHabitat` |
| `Infra/` | `HabitatRepository` |

### Equipos (`src/Equipos/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `TeamAggregate`, `TeamSrv` |
| `App/` | `ObtenerEquipos` |

### Reclutamiento (`src/Reclutamiento/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `ReclutamientoSrv` |
| `App/` | `ObtenerPokemonsReclutados` |

### Crud (`src/Crud/`)

13 submódulos: `Abilities`, `EvolutionChains`, `ExploracionesActivas`, `Habitats`, `Pokemon`, `PokemonEvolution`, `PokemonHabitat`, `PokemonStats`, `PokemonTypes`, `Provinces`, `Reclutados`, `TeamMembers`, `Teams`.

### Shared (`src/Shared/`)

- `Domain/Collection.php` — Colección base genérica
- `Tipos/` — `TipoPokemon` (enum), `TypeChart` (efectividades), `TypeEffectService`, `TiposCollection`, `TipoEntity`

## Datagrid (`app/Datagrid/`)

Subsistema de consulta JSON de **solo lectura** para modelos Eloquent. Vive en `app/` (no en `src/`) porque es infraestructura Laravel (consulta directa a Eloquent), no lógica de dominio.

| Clase | Responsabilidad |
|---|---|
| `DatagridDefinition` | Whitelist inmutable por modelo: `searchable`/`filterable`/`sortable` (clave pública → columna SQL), `relationFilters`, `with`, `visible`, `boolFields`, `itemFields` (closures por item), `baseQuery`, `counts`, `detail`. |
| `DatagridRegistry` | Registro slug → definición (slugs en minúscula). `has()/get()/register()`. |
| `DatagridService` | `list(slug, params): {data, meta}` + `detail(slug, id): ?array` + `registered(slug): bool`. Aplica search/filtros/sort/paginación restringidos a la whitelist. |
| `RelationFilter` | Filtro de relación (whereHas): `{relation, column, map?, constraint?}`. `constraint` (Closure opcional) permite whereHas custom (ej. `effort > 0`); por defecto `whereIn(column, mapped)`. |
| `DatagridController` | `index()`/`show()` → JsonResponse; modelo no registrado → `abort(404)`. |
| `DatagridServiceProvider` | Composition root: registra el registry como singleton con las 6 definiciones (pokemon, pokedex, reclutado, team, habitat, province). Añadido a `bootstrap/providers.php`. |

Rutas (`routes/datagrid.php`, requiere desde `routes/web.php`):

```
GET /datagrid/{model}            → listado (search, filter[], sort, order, page, per_page)
GET /datagrid/{model}/{id}/detalle → detalle (whereNumber('id'))
```

### Contrato de la API

```
GET /datagrid/{model}?search=&filter[field]=&sort=&order=&page=&per_page=
→ { "data": [{ id, name, visto, atrapado, types[], icon }],
    "meta": { total, page, per_page, last_page,
              counts: { total, vistos, atrapados, no_vistos } | null } }
```

Reglas del contrato (ver `tests/Feature/DatagridTest.php`):

- **Whitelist**: filtro/sort no registrado en la definición → ignorado silenciosamente. Modelo no registrado → 404 (nunca se revela la clase).
- **Paginación**: `per_page` clamp 1-200 (default 100); `page` mínimo 1.
- **`filter[field]=a,b`** → `whereIn`. **`filter[types]=Eléctrico`** → whereHas con map label→id (`TipoEnum`); labels inválidos → filtro ignorado.
- **`filter[effort]=Ataque|2`** → whereHas('stats', stat = id AND effort > 0) vía `RelationFilter::$constraint`. `effort=0` → ignorado.
- **Booleans sobre leftJoin**: NULL ≡ false (`filter[visto]=0` añade `orWhereNull`).
- **`meta.counts`**: contadores globales (independientes de filtros/paginación), solo para `pokemon`; `null` para el resto.
- **`icon`** → `/images/iconos_webp/{id}.webp` (contrato compartido con `HabitatRepository`).
- **`detail`**: `{id, name, visto, atrapado, types[], stats[{name,value}], habitat_name}` (primer hábitat de la relación; stats ordenadas por stat 1-6).

### Definición de pokemon (registro de referencia)

`baseQuery` hace `leftJoin('pokedex', pokedex.pokemon_id = pokemon.id)` + `select('pokemon.*', 'pokedex.visto', 'pokedex.atrapado')` — join 1:1 (unique `pokemon_id`), columnas SQL siempre cualificadas, `visto`/`atrapado` normalizados a bool. El listado usa `with: ['types']` y `itemFields` (icon, types labels en español); el detalle usa `loadMissing(['stats','types','habitats'])`. `PlayerController::pokedex()` delega en `DatagridService::list('pokemon', ...)` (sin N+1).

## Patrones utilizados

| Patrón | Uso | Ubicación |
|---|---|---|
| **Chain of Responsibility** | Cálculo de daño | `src/Battle/Domain/Chain/` |
| **Observer** | Eventos de batalla (daño, debilitamiento, fin turno) | `src/Battle/Domain/Observer/` |
| **Strategy** | Efectos de habilidad y objetos | `src/Battle/Domain/Effects/` |
| **Aggregate** | Entidades raíz de batalla y equipos | `AgregadoBatalla`, `TeamAggregate`, `BattleAggregate` |
| **DTO** | Transferencia dominio → vista | `DTOMovimientoBatalla` |
| **Service (Domain)** | Lógica de dominio orquestada | `ServicioEjecucionBatalla`, `ReclutamientoSrv`, `TeamSrv`, `BattleSrv` |
| **Repository** | Acceso a datos de hábitats | `HabitatRepository` |
| **Factory** | Creación de efectos y datos mock | `FabricaEfectos`, `FabricaBatallaMock` |
| **Registry** | Registro explícito de modelos expuestos | `DatagridRegistry` |
| **Whitelist declarativa** | Campos consultables por modelo (anti inyección) | `DatagridDefinition` |
| **Composition Root** | Registro de definiciones del datagrid | `DatagridServiceProvider` |
| **Strategy (constraint)** | whereHas custom por filtro de relación | `RelationFilter::$constraint` |

## Flujo de datos — Batalla manual (Livewire)

```
Usuario click → Combate (Livewire)
  → nextActor() → turnManager.getNextActor()
    → Si es jugador: muestra movimientos, espera selección
      → previewTarget() → previewMove() → selectMove()
        → commitAction()
          → ServicioEjecucionBatalla.calcularYAplicarDaño()
            → CadenaDanio.calculate() (7 manejadores)
          → aplicarEstado() / aplicarStatChanges()
          → observer.notifyDamaged() / notifyFainted()
          → turnManager.consumeAction()
          → nextActor()
    → Si es IA: prepara animación → Alpine.js timeout 700ms → commitAction()
```

## Flujo de datos — Batalla automática

```
BattleAggregate.ejecutarBatalla()
  → loop rondas → turnManager.startNewRound()
    → loop turnos → elegirObjetivo() → elegirMejorMovimiento()
      → ServicioEjecucionBatalla.calcularYAplicarDaño()
      → aplicarEstado() / aplicarStatChanges()
      → observer.notifyDamaged()
      → turnManager.consumeAction()
    → triggerRoundEndEffects() (daño estado, daño clima)
```

## Flujo de datos — Pokédex asíncrona

```
GET /pokedex (PlayerController::pokedex)
  → DatagridService::list('pokemon', per_page=100, sort=id asc)
    → DatagridDefinition (whitelist) → baseQuery (leftJoin pokedex)
    → search/filtros/sort → paginate → toVisibleArray (+ itemFields icon/types)
  → viewData: pokemons {data, meta}, counts, tipos, stats

Blade → Alpine pokedexApp()
  → pestañas: filter[visto]=1 / filter[visto]=0 / filter[atrapado]=1 (reset + refetch)
  → scroll infinito: IntersectionObserver (sentinel, rootMargin 300px)
      → fetch GET /datagrid/pokemon?page=N&... (AbortController + dedupe Set)
  → modal detalle: fetch GET /datagrid/pokemon/{id}/detalle (solo si pokemon.visto)
      → DatagridService::detail → closure detail (stats 1-6 + types + habitat)
  → iconos: img :src=icon (webp) → @error onIconError → png → ocultar (solo aquí hay red)
```

## Capas de `app/`

| Capa | Archivos | Responsabilidad |
|---|---|---|
| **Datagrid** | `Datagrid/{DatagridService,DatagridRegistry,DatagridDefinition,RelationFilter}.php` | Consulta JSON de solo lectura con whitelist por modelo |
| **Livewire** | `Combate.php` | Componente interactivo de batalla |
| **Controllers** | 9 controladores + base `Controller` | HTTP: Hábitats, Reclutados, Teams, Player (pokédex/reclutamiento/equipos), Datagrid, Exploraciones, Iconos, Dashboard |
| **Models** | 13 modelos | Eloquent ORM (tablas del sistema) |
| **Providers** | 4 providers | Registro de servicios: App, BattleEffect, Datagrid |
| **Support** | `WebpConverterInterface.php`, `WebpConverter.php` | Conversión PNG→WebP (GD/Imagick/CLI cwebp) |
| **Console** | `Commands/OptimizeIconsToWebp.php` | `iconos:optimize-webp` (`--dir`, `--out`, idempotente) |
| **Enums** | 2 enums | `TipoEnum` (tipos pokémon), `StatEnum` |

## Base de datos

SQLite. 18 migraciones. Esquema relacional:

```
provinces ──→ habitats ──→ pokemon_habitat ──→ pokemon ──→ pokemon_stats
                                                          ──→ pokemon_types
                                                          ──→ pokemon_evolution
                                                          ──→ evolution_chains
                                                          ──→ abilities
                                                          ──→ pokedex (1:1)

pokemon ──→ reclutados ──→ team_members ──→ teams
                       ──→ exploraciones_activas
```
