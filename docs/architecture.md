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
| `App/` | 8 casos de uso: `ObtenerHabitatsPorProvincia`, `ObtenerHabitatDetalle`, `ObtenerPokemonsPorHabitat`, `ObtenerFamiliasDisponibles`, `ObtenerFamiliasSinHabitat`, `AsignarFamiliaAHabitat`, `EliminarFamiliaDeHabitat`, `MoverPokemonDeNivel` |
| `Infra/` | `HabitatRepository` (god-class ~477 líneas, pendiente de dividir — ver deuda en `docs/context.md`) |
| `Presentation/` | `DTOHabitatDetalle`, `DTOFamiliasDisponibles`, `DTOFamiliasSinHabitat`, `DTOFamiliaDisponible`, `DTOFamiliaSinHabitat`, `DTOFamiliaEliminada`, `DTOPokemonNivelActualizado` |

#### API de familias de hábitats (admin "Gestión")

Endpoints (ver `routes/habitats.php` y `HabitatsController`):

```
GET    /api/habitats/{id}/families          → familias asignadas con level real por miembro
POST   /api/habitats/{id}/families          → 201 familia COMPLETA asignada (body: {evolution_chain_id})
DELETE /api/habitats/{id}/families/{chainId}→ quita TODA la familia del hábitat
PATCH  /api/habitats/{habitat}/pokemon/{pokemon} → mueve un pokémon de nivel (body: {level: 1|2|3})
GET    /api/habitats/unassigned-families    → familias sin hábitat (solo base, con types[])
```

Contrato de familia (aditivo, compartido por `DTOFamiliaDisponible` y `DTOFamiliaSinHabitat`):

```
{
  evolution_chain_id: int,
  base:      { id: int, name: string, icon: string, level: int },
  evolutions: [{ id: int, name: string, icon: string, level: int }],
  types:     [{ id: int, name: string }]   // unión dedup de tipos de TODOS los miembros, ordenada por id
}
```

Reglas de negocio del reparto de niveles (`levelForStage` en `HabitatRepository`):

- Reparto por fases: base → nivel 1, 2ª evolución → 2, 3ª → 3 (`min(stage, 3)`).
- Familias unicetapa → nivel 2 (`totalStages === 1 → 2`).
- Familias ramificadas → todas las evoluciones al mismo nivel real (Eevee: base 1, Vaporeon/Jolteon 2).
- El POST de asignar devuelve la familia completa con niveles reales (sin inferencia client-side).
- El DELETE quita TODA la familia (decisión de negocio; la X del modal solo existe en la tarjeta base).
- El PATCH mueve UN pokémon (reordenamiento manual por pokémon, no por familia).
- El **"primer integrante" de la familia es el de menor `species_id`** (decisión de negocio
  confirmada por el cliente): `getFamilyMembersByChain` ordena por `species_id` asc (desempate
  id) y la "base" de display del DTO = `$members[0]` (min species_id); GET families y
  unassigned-families se ordenan por el species_id mínimo de la cadena
  (`sortChainIdsByMinSpeciesId`). El reparto de niveles (BFS evolutivo) NO cambia: en
  Happiny(440)/Chansey(113)/Blissey(242) la base de display es Chansey 113, pero los niveles
  siguen siendo 440→1, 113→2, 242→3 (quirk: la X está en la base de display, nivel 2).

UI: modal Alpine `habitatShow()` en `resources/views/habitats/show.blade.php`. Sin refresco pesado:
la query inicial solo al abrir el modal; asignar/quitar/mover mutan estado local tras 200 OK.
Sustituye al componente Livewire `FamilyModal` (eliminado; hábitats ya no usa Livewire).

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

### Exploraciones (`src/Exploraciones/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `CalculadorTiempos`, `CalculadorRecompensas`, `SimuladorEncuentros`, `Recompensas/` (`RecompensaFamilia`, `RecompensaEv`, `RecompensaCaptura`, `RecompensaTipo`, `PokemonDerrotado`, `ResultadoRecompensas`) |
| `App/` | `ProcesarExploracionHandler`/`ProcesarExploracionCommand`, `FinalizarExploracionHandler`/`FinalizarExploracionCommand`, `PersistirRecompensas`, `NormalizadorPokemonDerrotado` |
| `Presentation/` | `TransformadorResultadoExploracion` (shape de resultados para `/exploraciones`) |

Contrato aditivo de resultados (`TransformadorResultadoExploracion` + `ExploracionActivaController`):

- `caramelos_familia[].pokemon_id` — id del miembro de menor `species_id` (misma regla que
  Habitats; `null` si la cadena no tiene pokémon).
- `caramelos_ev[].stat_slug` — `hp|atk|def|atksp|defsp|spd`, resuelto desde la const `STATS`
  unificada del controlador (`[1=>PS/hp, 2=>Ataque/atk, 3=>Defensa/def, 4=>Ataque Especial/atksp,
  5=>Defensa Especial/defsp, 6=>Velocidad/spd]`).

#### Familias por columna `pokemon.evolution_chain_id` (bug 23503 — tabla `evolution_chains` eliminada)

- La agrupación de familias es por la COLUMNA `pokemon.evolution_chain_id` (entero, sin FK). El
  mapa `array<int, Collection<int, Pokemon>>` (`chainId => miembros ligeros: id, name,
  species_id, evolution_chain_id`) se construye con
  `whereIn('evolution_chain_id', $ids)->get([...])->groupBy('evolution_chain_id')->all()` en:
  - `FinalizarExploracionHandler::cargarMiembrosDeCadenas()` → lo pasa a `normalizar()` y `desde()`.
  - `ReclutamientoController::miembrosDeLasCadenas()` (desvío: este controlador SÍ usaba la
    relación `evolutionChain` en eager load y fase; corregido con la columna).
- `NormalizadorPokemonDerrotado::fase()` = nº de miembros del mapa con `species_id <= actual`
  (mismo criterio que el `hasMany` anterior); sin cadena en el mapa → fase 1 (antes: Error
  fatal con la relación null).
- `TransformadorResultadoExploracion::pokemonBaseDeCadena()` = min `species_id` del mapa;
  fallback determinista sobre los derrotados de esa cadena con `sortBy('species_id')`
  (estable); `null` si no hay miembros (comportamiento intencional documentado por test).
- `caramelos.evolution_chain_id` conserva columna + unique, ahora SIN FK (entero simple). El
  `down()` de la migración que recrea la FK es best-effort (asume filas válidas; el bug 23503
  dejaba cadenas huérfanas).

### Crud (`src/Crud/`)

12 submódulos: `Abilities`, `ExploracionesActivas`, `Habitats`, `Pokemon`, `PokemonEvolution`, `PokemonHabitat`, `PokemonStats`, `PokemonTypes`, `Provinces`, `Reclutados`, `TeamMembers`, `Teams`.

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
| **Estado local con fetch API (Alpine)** | Modal de gestión sin recarga: query inicial al abrir, mutaciones locales tras 200 OK | `habitats/show.blade.php` (`habitatShow()`) |

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
| **Livewire** | `Combate.php` | Componente interactivo de batalla. Los hábitats migraron de Livewire a Alpine + fetch API (modal "Gestión", ver sección Habitats) |
| **Controllers** | 9 controladores + base `Controller` | HTTP: Hábitats, Reclutados, Teams, Player (pokédex/reclutamiento/equipos), Datagrid, Exploraciones, Iconos, Dashboard |
| **Models** | 17 modelos | Eloquent ORM (tablas del sistema) |
| **Providers** | 4 providers | Registro de servicios: App, BattleEffect, Datagrid |
| **Support** | `WebpConverterInterface.php`, `WebpConverter.php` | Conversión PNG→WebP (GD/Imagick/CLI cwebp) |
| **Console** | `Commands/OptimizeIconsToWebp.php` | `iconos:optimize-webp` (`--dir`, `--out`, idempotente) |
| **Enums** | 2 enums | `TipoEnum` (tipos pokémon), `StatEnum` |

## Base de datos

PostgreSQL en el entorno de ejecución (Docker); los tests usan SQLite en memoria (`:memory:`).
31 migraciones. Esquema relacional:

```
provinces ──→ habitats ──→ pokemon_habitat ──→ pokemon ──→ pokemon_stats
                                                          ──→ pokemon_types
                                                          ──→ pokemon_evolution
                                                          ──→ abilities
                                                          ──→ pokedex (1:1)

pokemon ──→ reclutados ──→ team_members ──→ teams
                       ──→ exploraciones_activas

Familias evolutivas: grupo de filas de `pokemon` con el MISMO valor en la columna
`pokemon.evolution_chain_id` (entero, sin FK ni tabla). `caramelos.evolution_chain_id`
también es entero simple SIN FK (columna + unique conservadas). La tabla
`evolution_chains` fue eliminada (bug 23503).
```
