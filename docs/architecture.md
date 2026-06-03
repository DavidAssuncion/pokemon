# Arquitectura del proyecto

## Visión general

Arquitectura híbrida: Laravel estándar (`app/`) + DDD/Hexagonal (`src/`). El código de dominio vive en `src/` sin dependencias del framework. La infraestructura (Livewire, Eloquent, HTTP) vive en `app/`.

```
┌─────────────────────────────────────────────┐
│                  app/                        │
│  Livewire · Controllers · Models · Providers │
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

## Capas de `app/`

| Capa | Archivos | Responsabilidad |
|---|---|---|
| **Livewire** | `Combate.php` | Componente interactivo de batalla |
| **Controllers** | 4 controladores | HTTP: Hábitats, Reclutados, Teams |
| **Models** | 13 modelos | Eloquent ORM (tablas del sistema) |
| **Providers** | 3 providers | Registro de servicios y efectos |
| **Enums** | 2 enums | `TipoEnum` (tipos pokémon), `StatEnum` |

## Base de datos

SQLite. 18 migraciones. Esquema relacional:

```
provinces ──→ habitats ──→ pokemon_habitat ──→ pokemon ──→ pokemon_stats
                                                          ──→ pokemon_types
                                                          ──→ pokemon_evolution
                                                          ──→ evolution_chains
                                                          ──→ abilities

pokemon ──→ reclutados ──→ team_members ──→ teams
                       ──→ exploraciones_activas
```
