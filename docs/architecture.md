# Arquitectura del proyecto

## Visión general

Arquitectura híbrida: Laravel estándar (`app/`) + módulos DDD en `src/`.

**Convención canónica de módulos: `docs/ddd.md`** (adoptada 2026-08-30). El acoplamiento a Laravel
está aceptado y ordenado por capas dentro de cada módulo `src/{{Modulo}}/`: `Domain/` **pura** (sin
Eloquent, sin HTTP, sin facades, sin `Request`) y `App/` + `Infra/` **usando Laravel libremente**
(Eloquent, FormRequests, controllers, rutas, jobs, container). Sustituye a la antigua regla "los
módulos `src/` no dependen de Laravel": la infraestructura del módulo (controllers, modelos
Eloquent, FormRequests, rutas, repos Eloquent, factories) vive en `src/{{Modulo}}/Infra/`. Los
DTOs de presentación viven en `Domain/DataTransferObjects` — la capa `Presentation/`
desaparece como destino y migra a DTOs en Domain.

> **Estado actual vs destino**: los módulos existentes siguen con su estructura actual (algunos con
> `Presentation/`, controllers en `app/Http/Controllers`, rutas en `routes/`, repos sin contrato
> unificado). La migración es **gradual, por módulo, al tocarlo** (estrategia strangler); nada está
> migrado todavía. Este documento describe tanto el destino (convención) como el estado actual
> (secciones de módulos abajo).

```
┌─────────────────────────────────────────────┐
│                  app/                        │
│  Infraestructura TRANSVERSAL compartida     │
│  Datagrid · Support · Console · Providers   │
│  base Controller — permanece en app/        │
├─────────────────────────────────────────────┤
│                  src/                        │
│  {{Modulo}}/  Domain · App · Infra  (destino)│
│  Infra/ = Controllers · Models · Livewire    │
│  Repos · Factories · Requests · routes.php  │
│  (destino: NADA de esto vive en app/)       │
│  Battle · Pokemon · Habitats · Equipos      │
│  Reclutamiento · Exploraciones · Granjas    │
│  Shared · CombateEntrenadores · CombateRuta │
│  Huevos · Forja · Gimnasios · Mazmorras     │
├─────────────────────────────────────────────┤
│  Estado actual: Controllers/Models/Livewire │
│  aún en app/; migran por módulo (strangler)│
└─────────────────────────────────────────────┘
```

## Módulos (src/)

### Battle (`src/Battle/`)

Núcleo del sistema. Organizado en 3 subcarpetas (tras el refactor Cleaner de 2026-08-30 se eliminaron `App/`, `BattleAggregate` y `BattleSrv`; el refactor de diseño posterior extrajo `CalculadorDañoClima` y `SelectorAccionIA` como servicios y sustituyó `ManejadorOrbeVida` por `ManejadorObjetosEquipados`):

| Carpeta | Propósito | Archivos clave |
|---|---|---|
| `Domain/` | Lógica de dominio pura | `AgregadoBatalla`, `Combatiente`, `EquipoBatalla`, `GestorTurnos`, `ServicioEjecucionBatalla`, `CalculadorDañoClima` (daño por clima fin de ronda), `SelectorAccionIA` (objetivo/movimiento IA), `MovimientoBatalla`, `AccionBatalla`, `DatosPokemonBatalla`, `Posicion`, `FabricaBatallaInterface` |
| `Domain/Chain/` | Chain of Responsibility para daño | `CadenaDanio` (cadena de manejadores: base, protección, inmunidad/efectividad de tipo, STAB, combo, crítico, posición, clima, terreno, habilidades de ataque/defensa, **resistencia temporal de objetos** (`ManejadorResistenciaTemporal`), marcado, equipo, objetos equipados, bonus, aguante, etc.) + `ManejadorDanio` (interface) + `ManejadorDanioAbstracto` |
| `Domain/Effects/` | Strategy pattern para efectos | `InterfazEfecto`, `ComportamientosPorDefecto` (trait), `ColeccionEfectos`, `FabricaEfectos` (instancia inyectable), `EfectoPerforacionArmadura`, `EfectoRegeneracionDefensa`, `EfectoInvocadorClima` (parametrizado, sustituye a `EfectoInvocadorTormentaArena`), `EfectoRestos`, `EfectoOrbeVida` |
| `Domain/Enums/` | Enums de dominio | `TipoClima` (7 valores con `label()` en español), `EstadoPokemon` (8 valores, `causaDanoPorRonda()`), `CategoriaMovimiento` |
| `Domain/Observer/` | Observer pattern para eventos | `SujetoBatalla`, `ObservadorBatalla` |
| `Domain/ValueObjects/` | Value Objects | `EtapasStats` (clamp -6..+6), `ColeccionMovimientos` (sin dependencia de Illuminate), `MultiplicadoresStats` (incluye `refrescarTemporal()`), `ResistenciasTemporales` |
| `Domain/DataTransferObjects/` | DTOs internos del agregado | `DTOAccionObjeto` (acción de objeto; **no** es `Wireable`), `OpcionObjeto` |
| `Infrastructure/` | Persistencia/servicios externos | `FabricaBatallaMock` (6 pokémon mock, recibe `FabricaEfectos` inyectable) |
| `Presentation/` | DTOs para la vista (Livewire Wireable) | `DTOMovimientoBatalla`, `DTOAccionBatalla` (con `move: DTOMovimientoBatalla` tipado), `DTOResultadoDanio`. `DTOEquipoBatalla` NO se usa (deuda: eliminar). Los DTOs de objeto viven en `Domain/DataTransferObjects` (`DTOAccionObjeto`, `OpcionObjeto`) |

Documentación exhaustiva del módulo (clases, mecánicas, contrato de vista, flujos, deuda): `src/Battle/context.md`.

#### Deuda de arquitectura Battle (Arquitecto APROBADO, 2026-08-30)

- Ciclo de dependencia Pokemon↔Battle: `MovimientoBatalla` debería moverse a `src/Pokemon/Domain/Movement/` (y `PokemonEntity` importa `MovimientoBatalla`/`ColeccionMovimientos` de Battle).
- `AgregadoBatalla` importa `DTOAccionBatalla` de Presentation (inversión Domain→Presentation).
- `DTOEquipoBatalla` muerto (eliminar).
- 26 strings mágicos de stats sin enum tipado.
- `max(1, floor(...))` en `CadenaDanio::calculate()` anula inmunidad de tipos.
- God-classes: `Combate.php` (653 líneas), `Combatiente` (606), `AgregadoBatalla` (~255 tras extraer `CalculadorDañoClima`/`SelectorAccionIA`).

#### Motor de turnos e invocaciones (2026-10-15)

- **Una acción por combatiente vivo y ronda.** `GestorTurnos` mantiene `round` y el contador
  privado `vecesActuadoEstaRonda` de cada `Combatiente` (`vecesActuadoEstaRonda()` /
  `tieneAccionPendienteEstaRonda()`); `getNextActor()` devuelve el vivo con contador 0 de mayor
  **velocidad efectiva** (`Combatiente::velocidadEfectiva()` = `obtenerStatEfectivo(VELOCIDAD)`, que
  aplica etapas y parálisis). `consumeAction()` solo incrementa el contador; `startNewRound()`
  incrementa la ronda y resetea contadores. Empates: orden de inserción (team1 antes que team2).
  Invariante: con acciones pendientes, `getNextActor()` nunca es null. Ya no existe velocidad
  acumulada (`velocidadAcumulada`, `menorVelocidadEntreVivos`).
- **Contrato de vista**: `Combatiente::aArrayVista()` expone `effectiveSpeed` (no
  `accumulatedSpeed`); `Combate::buildTurnQueue()` ordena por velocidad efectiva descendente con
  orden estable, misma métrica que `getNextActor()`.
- **Invocaciones**: `AgregadoBatalla::incorporarCombatiente(EquipoBatalla, Combatiente, Posicion)`
  añade combatientes sin tope, con contador 0 en la ronda en curso, sin efectos de entrada ni log y
  sobre la misma colección que consume `GestorTurnos`. Es la única vía por la que un bando puede
  superar los 3 combatientes.
- **Sesión**: `SESSION_VERSION = 21`, mínimo compatible 9; los payloads v9/v10 con
  `velocidadAcumulada` se cargan ignorando la clave (best-effort). Los payloads v20 siguen
  cargando con defaults: `resistencias` se restaura vacía y `pendingAction` nunca contiene
  `DTOAccionObjeto` en v20. Las acciones de objeto usan `AgregadoBatalla::$actorActualId` como
  fuente de verdad server-side; `resolveActor()` de movimientos sigue leyendo `actingRefId`
  (deuda preexistente, ver `docs/adr/0001-inventario-compartido-f2-f5.md`).

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
| `Infra/` | `HabitatLecturaRepository` + `HabitatGestionRepository` (divididos 2026-10-31; consultas compartidas en `Concerns/CadenasFamiliaHabitatsTrait`) |
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

#### Tabs por familia y detalle de especie (TASK-013)

La UI del modal de granja usa **tabs por familia**: una tab por familia (`familiaActiva`) y, al
pulsarla, se presenta el panel con **nido, refugio, complementos y plantaciones** de esa familia.
No hay tablist interno por integrante ni panel de especie visible. Decisión registrada en el
addendum v1.1 del plan y en [`ADR-0002`](adr/0002-catalogo-bayas-y-detalle-especie.md).

```
GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}   (bajo auth)
```

El endpoint de detalle de especie sigue disponible, pero **no está conectado a la UI actual**;
queda para una fase posterior.

- **Autorización por pertenencia:** `DetallePokemonFamiliaQuery` exige que la cadena esté
  representada en `pokemon_habitat` para ese hábitat y que el Pokémon cumpla
  `pokemon.evolution_chain_id = chain`; cualquier combinación inválida → 404
  (`RecursoNoExiste`) sin revelar si el id existe en otro hábitat.
- **Orden de miembros (contrato del detalle):** `MiembroCadena` incorpora `parentId` y
  `DTOPokemonFamilia` añade `stage` y `parentId` (defaults compatibles). `toArray()` expone además
  `stage`, `parent_id` y `species_id` (claves aditivas). La lista `[base, ...evolutions]` se ordena
  por `stage`, `species_id`, `id`; `base` sigue siendo el de menor `species_id`. Este orden no se
  renderiza hoy: la UI solo muestra una tab por familia.
- **Detalle opt-in:** `PresentadorDetalleEspecie` (`app/Support/`) compone el shape de
  `PresentadorDetallePokemon::para()` y añade `abilities`, `moves` y `species`. La Pokédex
  (`GET /datagrid/pokemon/{id}/detalle`) no cambia: `PresentadorDetallePokemon` solo acepta
  overrides opcionales de `visto`/`atrapado`.
- **Pokédex informativa:** una especie no vista devuelve el detalle completo con `visto=false`;
  no se bloquea la respuesta.
- **Limitación:** `ability_pokemon` no guarda `hidden`; se exponen `slot` y `source` sin inventar
  la bandera. Las formas regionales con cadena propia requieren que su cadena esté asignada al
  hábitat.

La vista mantiene una caché JS por `chainId/pokemonId` (`detallePokemonCache`) preparada para ese
detalle, pero **no se invoca desde la UI actual**; tampoco se incrustan estas claves en
`GET /api/habitats/{id}/families` ni en `GET /farm`. Diseño y criterios de aceptación:
[`TASK-013/architecture.md`](../.opencode/work/TASK-013/architecture.md) y addendum v1.1 en
[`TASK-013/plan.md`](../.opencode/work/TASK-013/plan.md#addendum-v11--ajuste-de-ui-aprobado-2026-11-04).

### Equipos (`src/Equipos/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `TeamAggregate`, `TeamSrv` |
| `App/` | `ObtenerEquipos` |

Los equipos estándar tienen **exactamente 3 miembros** (slots 1-3; `TeamController` valida
`max:3` y la migración `2026_10_15_000001` recorta slots > 3). El tamaño se exige al combatir en
`ConstruirEquipoJugador::desdeEquipo` (422), no al guardar el equipo (la UI permite equipos
incompletos marcados como inválidos).

### Reclutamiento (`src/Reclutamiento/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `ReclutamientoSrv` |
| `App/` | `ObtenerPokemonsReclutados` |

### Exploraciones (`src/Exploraciones/`)

| Carpeta | Contenido |
|---|---|
| `Domain/` | `EconomiaRecompensas`, `CalculadorFinExploracion`, `CalculadorRecompensas`, `SimuladorEncuentros`, `EstimadorRecompensasExploracion`, `Recompensas/` (`RecompensaFamilia`, `RecompensaEv`, `RecompensaCaptura`, `RecompensaTipo`, `PokemonDerrotado`, `ResultadoRecompensas`) |
| `App/` | `ProcesarExploracionHandler`/`ProcesarExploracionCommand`, `FinalizarExploracionHandler`/`FinalizarExploracionCommand`, `ProcesarExploracionesPendientes`/`ResultadoProcesamientoPendiente`, `PersistirRecompensas`, `NormalizadorPokemonDerrotado` |
| `Presentation/` | `PresentadorExploraciones`, `TransformadorResultadoExploracion`, `ExploradorVista`, `DiarioExploracion`, `EventoExploracionVista` (shape de resultados para `/exploraciones`) |

La exploración es **individual** (`reclutado_id`, combate 1v1). Economía Fase 1
(`EconomiaRecompensas::individual()`): factor 0.5 sobre EXP/caramelos/probabilidad de captura y
reparto `expCuenta = intdiv(pool, 2)` / `expReclutado = pool - expCuenta`; `EconomiaRecompensas::legado()`
(1.0, 80 %/3) queda para `OtorgarRecompensasRuta`. El procesamiento pendiente usa lock
`exploracion:procesar:{id}` (TTL 60 s, no bloqueante) y throttle de 30 s.
Detalle y deudas en `src/Exploraciones/context.md`.

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

### Granjas (`src/Granjas/`)

Sistema de granjas por hábitat: nidos, refugios, complementos y —desde F2— plantaciones de
bayas. Depende de `Shared` (catálogo e inventario) y su adaptador de plantaciones implementa el
puerto que consume `Exploraciones`.

| Carpeta | Contenido |
|---|---|
| `Domain/` | `CalculadoraBonosGranja`, `ReglasPlantacion`, `Entities/Plantacion`, `Enums/EstadoPlantacion`, DTOs (`GranjaDetalle`, `NidoGranja`, `PlantacionGranja`, `BayaGranja`, `BonguriGranja`) y colecciones tipadas. |
| `App/` | `AsegurarGranja`, `FamiliaEnHabitat`, `ObtenerGranja`, `MejorarNido`, `ResolverBonosFamilia`, `InventarioGranja`, `ObtenerCatalogoBayas`, `PlantarBaya`, `CosecharPlantacion`, `CosecharTodo`. |
| `Infra/` | `Controllers/GranjaController`, `Requests/PlantarBayaRequest`, `Plantaciones/ConsultorPlantacionesEloquent` (adaptador de `Exploraciones\Domain\Ports\ConsultorPlantaciones`). |

Reglas de plantación (F2):

- Huecos = `nivel * 2`; un nido de nivel 0 no permite plantar.
- Plantar consume 1 baya: la clave debe ser `baya:{slug}` y validarse contra `ClaveBaya`; nunca
  se acepta un `item_key` libre.
- Crecimiento: 1 cosecha cada 24 h, stock máximo 3 y planta persistente. **No hay cron**: el
  stock se calcula al leer con `ReglasPlantacion::stockDe()` a partir de `planted_at` y
  `harvest_count`.
- Replantar sustituye la baya, reinicia `planted_at`/`harvest_count` y pierde el stock
  pendiente; la UI lo advierte.
- `nest_plantings` guarda una fila por hueco ocupado con unique `(nest_id, slot_index)`. Los
  locks del nido y del hueco, más el recálculo del stock tras el lock, evitan dobles
  plantaciones y dobles cosechas.

Endpoints (`routes/granjas.php`, bajo `auth`; salvo el catálogo de bayas, cada acción devuelve el
`GranjaDetalle` completo):

```
GET  /api/habitats/{habitat}/farm/berries            → catálogo de las 36 bayas con stock (incluye 0)
POST /api/habitats/{habitat}/farm/nests/{chain}/plantings
POST /api/habitats/{habitat}/farm/nests/{chain}/plantings/{slot}/harvest
POST /api/farm/harvest-all                           → cosecha global (TASK-014)
```

`GranjaDetalle` conserva `id`, `habitat`, `familias`, `inventario` y añade `bayas` y `bonguri`;
`NidoGranja` añade `huecos_totales`, `huecos_libres` y `plantaciones`.

#### Catálogo de bayas y selector de plantación (TASK-013)

`resumenBayas()` no cambia: sigue devolviendo solo existencias y alimenta `GranjaDetalle.bayas`.
El catálogo completo se sirve aparte para no inflar el payload de granja
([`ADR-0002`](adr/0002-catalogo-bayas-y-detalle-especie.md)):

- `InventarioGranja::catalogoBayas(int $userId)` recorre las 36 `ClaveBaya::cases()`, lee los
  saldos en una sola llamada y devuelve cantidad 0 cuando no hay fila.
- `ObtenerCatalogoBayas::handle(int $userId, int $habitatId)` valida el hábitat con
  `AsegurarGranja::para()` antes de leer el inventario.
- `GranjaController::bayas()` responde `{ "bayas": [...] }` con el shape de `BayaGranja`, que
  incorpora `GrupoBaya $grupo` y expone `grupo` y `grupo_nombre` (aditivo).
- `GrupoBaya` (`src/Shared/Domain/Items/GrupoBaya.php`) clasifica las 36 bayas con una única
  fuente de verdad, `CatalogoItems::grupoDeBaya(ClaveBaya)`: Medicinas = `BAYAS_BASE` (6),
  Mejoras = `BAYAS_RARAS` (7), Reductores = `BAYAS_ESTADISTICA` (5) y Resistencias = bayas de
  tipo (18).

En la vista, el selector de baya es un modal hermano del de granja (`z-[60]`): busca por nombre,
filtra por grupo, muestra primero las disponibles y planta con un clic reutilizando el endpoint
existente. `cerrarCapaSuperior()` cierra solo la capa superior (bayas o granja), de modo que
plantar refresca la granja sin cerrarla.

Diseño y criterios de aceptación: [`TASK-013/plan.md`](../.opencode/work/TASK-013/plan.md) y
[`TASK-013/architecture.md`](../.opencode/work/TASK-013/architecture.md).

#### Cosecha global por nido (TASK-014)

`CosecharTodo::handle(int $userId)` cosecha todas las plantaciones listas del jugador en todos sus
hábitats y nidos. Se expone en `POST /api/farm/harvest-all` (bajo `auth`, sin body) y
`GranjaController::cosecharTodo()` devuelve `ResultadoCosechaGlobal::toArray()`.

Flujo:

1. Tres consultas de selección: granjas del usuario, nidos con `level >= 1` y plantaciones
   candidatas. El stock se calcula con `ReglasPlantacion::stockDe()`.
2. Por cada nido candidato, `DB::transaction(..., 3)`:
   - lock del nido (`whereKey` + `where farm_id`);
   - lock de sus plantaciones por `id` ascendente;
   - recálculo del stock tras el lock; si es `<= 0`, se omite;
   - `increment('harvest_count', stock)` y acumulación por `berry_key`;
   - incremento del inventario agregado por baya, ordenado por `item_key`.
3. Un fallo en un nido se captura como `ErrorCosecha` y no detiene el resto. Las excepciones de
   dominio conservan su mensaje; el resto se registra con `report()`.
4. Se agregan totales y bayas (cantidad descendente, nombre ascendente).

Contrato `200`:

```json
{
  "total_bayas": 12,
  "total_plantaciones": 5,
  "nidos": 3,
  "bayas": [
    {"clave": "baya:liechi", "nombre": "Lichi", "icono": "/images/berry/liechi-berry.webp", "cantidad": 6}
  ],
  "errores": [
    {"farm_id": 4, "habitat_id": 2, "nido_id": 42, "evolution_chain_id": 7, "mensaje": "..."}
  ]
}
```

`nidos` cuenta los nidos que aportaron stock y `total_plantaciones` las filas cosechadas. Sin
candidatos devuelve ceros y listas vacías. La operación es idempotente: el stock se recalcula bajo
lock y `harvest_count` se actualiza en la misma transacción, por lo que una segunda petición
inmediata devuelve 0.

El botón del menú emite `inventario:actualizado` al terminar. `habitats/show.blade.php` recarga la
granja de forma silenciosa si el modal está abierto y no hay una acción en curso; la forja relee
recetas con `cargar(false)`. Decisión completa en
[`ADR-0003`](adr/0003-cosecha-global-e-iconos-items.md).

### Forja (`src/Forja/`)

Módulo de fabricación de objetos de combate a partir de bayas, bonguris y recipientes (F4).
Sin migración: el catálogo de 46 recetas es código estático.

| Carpeta | Contenido |
|---|---|
| `Domain/` | `RecetaFabricacion`, `RecetaFabricacionCollection`, `CatalogoRecetas` (46 recetas / 39 resultados), `Exceptions/RecetaNoExiste`. |
| `App/` | `ListarRecetas` (saldos en una sola lectura), `Fabricar` (1 unidad por acción), DTOs `RecetaDetalle`, `IngredienteDetalle`, `ResultadoFabricacion` y colecciones. |
| `Infra/` | `Controllers/ForjaController`, `Requests/FabricarRequest`. |

- Recetas con id `receta:*`. Varias variantes pueden compartir `resultado_clave`
  (`pocion:pocion` desde Aranja o Zidra; `material:recipiente` desde los 7 bonguris).
  `CatalogoRecetas::paraResultado()` devuelve las variantes y la UI las agrupa con un selector.
- `Fabricar` valida la receta, exige `cantidad = 1`, consume todos los ingredientes con lock
  ordenado (`consumirVarios`) e incrementa el resultado en la misma transacción. Si falta un
  ingrediente devuelve 422 y no descuenta nada.
- 39 claves nuevas en `Shared\Domain\Items` (8 pociones, 1 recipiente, 7 mejoras, 5 reductores
  y 18 resistencias): `CatalogoItems` pasa a 82 definiciones y `objetosDeCombate()` a 44.

Endpoints (`routes/forja.php`, requerido desde `routes/web.php` bajo `auth`):

```
GET  /api/forja/recetas
POST /api/forja/fabricar   body: {receta, cantidad?: 1}
GET  /forja
```

UI `/forja`: 4 categorías colapsables — **Medicinas** (pociones + recipiente), **Mejoras**,
**Reductores** y **Resistencias**. Cada receta muestra ingredientes `disponible/requerido`,
`puede_fabricar` y el botón de fabricar; los cálculos vienen del backend.

#### Recipiente por bonguri y origen (TASK-014)

`ClaveBonguri::origen(): string` es la fuente única del mapa color → provincia (Galicia, Euskadi,
Barcelona, Extremadura, Nova Alacant, Mutxamel, Andalucía). `IngredienteDetalle` añade
`?string $origen = null` y lo expone en `toArray()`; `ListarRecetas` lo rellena solo para claves
`bonguri:*`, de modo que el resto de ingredientes mantiene `origen: null`.

La UI de `/forja` trata `material:recipiente` de forma especial: en lugar del `<select>` de
variantes muestra una lista con las **7 variantes** (una por bonguri), con icono, nombre,
`disponible/requerido`, `Origen: …` y botón **Fabricar**. `variantesRecipiente()` ordena primero
las que tienen existencias y mantiene el orden estable del catálogo; el botón se deshabilita si
`puede_fabricar` es false o mientras se fabrica otra variante. El resto de grupos conserva el
selector actual.

### UI global: menú de cosecha y assets de items (TASK-014)

#### Menú y Font Awesome

El botón `?` y su popup de ayuda se eliminaron de `resources/views/layouts/app.blade.php`; el
`x-data` del `<nav>` ahora gestiona la cosecha global. El botón usa
`<i class="fa-solid" :class="cosechando ? 'fa-spinner fa-spin' : 'fa-seedling'">`, se deshabilita
mientras hay una petición y se oculta en `/combate*` para no alterar el inventario en batalla.

- La petición es `fetch POST /api/farm/harvest-all` con `X-CSRF-TOKEN` y `AbortController` de 15 s.
- El resultado se anuncia con `role="status"` y `aria-live="polite"`: `+N bayas`,
  `Nada que cosechar` o `Error` (con el número de errores parciales).
- Al terminar se emite `inventario:actualizado`; los listeners de granja y forja refrescan sus
  datos (ver [Cosecha global por nido](#cosecha-global-por-nido-task-014)).
- Font Awesome 6 Free se instala localmente (`@fortawesome/fontawesome-free@^6.7.2`) y se importa
  en `resources/css/app.css` (`fontawesome.css` + `solid.css`). `npm run build` emite las fuentes
  `fa-solid-900.woff2`; no hay CDN.

`config/ayuda.php` queda sin referencias tras eliminar el popup (ver deuda en
[`ADR-0003`](adr/0003-cosecha-global-e-iconos-items.md)).

#### Assets WebP y Recipiente/Éter

Los 16 PNG de `public/images/items` se convirtieron a WebP con
`cwebp -q 85 -m 6 -alpha_q 100 -metadata none` y se reorganizaron por categoría:

| Grupo | Ruta | Nº | Enum |
|---|---|---|---|
| Pociones | `public/images/items/pocion/*.webp` | 8 | `ClavePocion::archivoIcono()` |
| Mejoras | `public/images/items/mejora/*.webp` | 7 | `ClaveMejora::archivoIcono()` |
| Material | `public/images/items/material/recipiente.webp` | 1 | `ClaveMaterial::archivoIcono()` |

Los enums construyen la ruta con `{$this->value}.webp`; ya no se devuelve `unknown.webp` para
pociones ni para el material. Los 16 PNG originales se movieron a
`storage/app/imports/items-png/` (fuera de `public`) y no quedan PNG en `public/images/items`.

`material:recipiente` conserva el nombre visible **Recipiente** y la descripción “Recipiente para
fabricar objetos”, pero su icono es el artwork de **Éter** (`material/recipiente.webp`). La
divergencia con la propuesta “Éter” del plan D7 queda documentada como deuda de producto.

### CombateEntrenadores (`src/CombateEntrenadores/`)

Módulo que integra el combate PvE contra entrenadores dentro de los hábitats (2026-09-01). Sigue la convención DDD por módulos (Domain pura; App/Infra con Laravel) y **reutiliza el motor de batalla** (`src/Battle/Domain/`) en lugar de duplicarlo.

| Carpeta | Propósito | Archivos clave |
|---|---|---|
| `Domain/` | Lógica pura | `GeneradorMovimientosTipo` (movimientos sintéticos por tipo: no-Normal → físico 60 + especial 80 por tipo; Normal puro → 80/100; Normal mixto → 40/60), `ClasificadorPosicion` (defensivo → vanguardia si `def+spDef >= atk+spAtk`), `GeneradorFormacion` (formación aleatoria 1/2 o 2/1 respetando clasificación), `Repositories/EntrenadorLogRepositoryInterface`, `Exceptions/EntrenadorDerrotadoHoy` (422) |
| `App/` | Casos de uso | `MapeadorPokemonBatalla` (Eloquent `Pokemon` → `DatosPokemonBatalla`), `GeneradorEquipoEntrenador` (equipo rival determinista por semilla `crc32(habitat\|nivel\|entrenador\|fecha)` desde el pool del hábitat), `ConstruirEquipoJugador` (`Team` + formación), `ObtenerEntrenadoresHabitat`, `IniciarCombateEntrenador` (crea `AgregadoBatalla` y lo guarda en sesión con prefijo `v{version}\|` + metadatos `battleId._meta`), `RegistrarResultadoEntrenador`, `OtorgarRecompensasEntrenador` (recompensas dobles vía `CalculadorRecompensas` ×2.0 + `ActualizarPokedexJob` AVISTADO) |
| `Infra/` | Persistencia/HTTP | `EloquentEntrenadorLogRepository` (upsert en `trainer_combat_log`), `Controllers/EntrenadorController` (GET listado, POST combatir), `routes.php` (placeholder; rutas reales en `routes/entrenadores.php`) |

Reglas de negocio clave: límite diario 3×3=9 combates por hábitat; `won=true` bloquea al entrenador ese día (upsert con unique `(user_id, habitat_id, level, trainer_index, fought_at)`); perder es repetible; rival determinista por día y de **exactamente 3 combatientes** (3 especies únicas; repetición ponderada si el pool es corto); rival vacío → `ViolacionReglaNegocio` 422 sin persistir sesión; sin capturas (no recluta rivales). Documentación exhaustiva: `docs/combate_entrenadores.md` y `src/CombateEntrenadores/context.md`.

### CombateRuta (`src/CombateRuta/`)

Módulo del **combate de ruta 3v3** (2026-09-13; equipos y rivales de 3 desde 2026-10-15): PvE contra 3 pokémon salvajes del pool de un
hábitat desde `/habitats/{id}` (modo "Ruta", el modo por defecto del hábitat). Sigue la convención
DDD por módulos y **reutiliza el motor de batalla** (`src/Battle/Domain/`) y las recompensas de
`Exploraciones`.

| Carpeta | Propósito | Archivos clave |
|---|---|---|
| `Domain/DataTransferObjects/` | DTO de presentación | `ResultadoRuta` (victoria/exp/caramelos/capturas; `aArray()` = contrato snake_case del modal) |
| `App/` | Casos de uso | `GeneradorEquipoRuta` (3 salvajes por selección ponderada CON reemplazo `capture_rate/hatch` desde el pool del hábitat y nivel; aleatorio inyectable), `IniciarCombateRuta` (delega la validación del equipo en `ConstruirEquipoJugador`: exactamente 3, crea batalla y meta `tipo=ruta`), `RegistrarResultadoRuta`, `OtorgarRecompensasRuta` (multiplicador 1.0 + capturas cap-25 + avistados) |
| `Infra/Controllers/` | HTTP | `CombateRutaController` (GET rivales del panel, POST iniciar → `battle_id` + redirect) |

Reglas de negocio clave: el equipo del jugador debe tener **exactamente 3 miembros**
(`ViolacionReglaNegocio` 422 en `ConstruirEquipoJugador::desdeEquipo`, punto único de los 4
iniciadores); **sin límite diario ni log** (`trainer_combat_log` no aplica);
la victoria otorga recompensas ×1.0 y **capturas** (`ProbabilidadCaptura` cap-25) + avistados;
el rival **varía en cada combate** (sin semilla). Endpoints en `routes/ruta.php`:

```
GET  /api/habitats/{habitat}/ruta/rivales?nivel=1-3
POST /api/habitats/{habitat}/ruta/iniciar   body: {team_id, formacion?, nivel?}
```

Hardening compartido: `ClasificadorOfensivaDefensiva`, `ProbabilidadCaptura`,
`EscaladorNivelRival` en `src/Shared/Domain/`; `CreadorBatallaSesion` (`app/Support`) unifica la
creación de batallas 3v3 de Ruta/Entrenador/Gimnasio/Mazmorra (DRY). Documentación exhaustiva:
`src/CombateRuta/context.md`.

### Huevos (`src/Huevos/`)

Módulo del **sistema de huevos** (2026-09-16): el jugador pone a gestar un huevo de una familia
evolutiva de la que ya tiene **≥2 reclutados distintos** (misma `evolution_chain_id`; especies
distintas de la misma cadena valen), espera gestación + incubación y eclosiona para obtener un
reclutado con IVs mejores que los salvajes. Sigue la convención DDD por módulos (Domain puro;
App/Infra con Laravel).

| Carpeta | Propósito | Archivos clave |
|---|---|---|
| `Domain/` | Lógica pura de tiempos y estados | `CalculadorTiemposHuevo` (puesta = `255/capture_rate` horas con suelo 1; eclosión = `ceil(hatch/15)` horas, `DIVISOR_HATCH=15`; guards `max(rate,1)`/`max(hatch,1)`; reducción por nivel de nido), `CalculadorEstadoHuevo` (estado **on-read** desde `created_at` + snapshots + `nido_nivel`; GESTANDO → INCUBANDO → LISTO), `EstadoHuevo` (enum), `EstadoCalculadoHuevo` (VO inmutable con `restanteHoras`) |
| `App/` | Casos de uso | `ListarHuevos` (serializa en horas float + `progreso` 0-100 + `limites` + nido/rango IV; `disponibles` por familia: ≥2 individuos, 1 entrada por cadena, sin huevo activo de esa familia, hatch no-nulo, representante de menor `species_id`), `CrearHuevo` (pareja por familia, 1 gestando + 1 incubando + 1 activo por familia, roll shiny 1/512 e IVs por nivel de nido), `EclosionarHuevo` (idempotente + defensa en profundidad `userId`; crea el reclutado vía `CrearReclutado` con EVs de complementos) |
| `Infra/Controllers/` | HTTP | `HuevoController` (GET listado, POST crear 201, POST eclosionar → reclutado serializado con CP al nivel del usuario) |

Reglas de negocio clave:

1. **Tiempos (contrato B1 del cliente)**: puesta `255 / capture_rate` horas; eclosión
   `ceil(hatch / 15)` horas; total = suma. La respuesta trabaja en horas (float) con `progreso`
   0-100.
2. **Estados derivados por timestamps**: GESTANDO/INCUBANDO/LISTO se calculan on-read
   (`CalculadorEstadoHuevo::estadoDe`); no se almacenan (solo `huevo.eclosionado_en` marca el final).
3. **Límites**: máx. 1 huevo en GESTANDO y máx. 1 en INCUBANDO simultáneos (2 huevos activos de
   distinto estado pueden convivir) + máx. 1 huevo activo (sin eclosionar) por familia
   (`huevos.evolution_chain_id`, índice, sin FK estricta).
4. **Pareja por familia**: ≥2 reclutados DISTINTOS de la misma `evolution_chain_id`; la especie del
   huevo es el miembro de menor `species_id` (regla "primer integrante" del proyecto).
5. **Shiny de huevo**: 1/512 (`ProbabilidadesShiny::HUEVO`); shiny → IVs máximos (15); no-shiny →
   `GeneradorIvs::huevo` con rango según el nivel de nido (0–4, 5–8, 9–10, 11–12, 13–14, 15).
6. **Eclosión idempotente**: eclosionar un huevo ya eclosionado → 422; huevo ajeno → 404 (ruta con
   scope `BelongsToUser` + check interno de cinturón).

Endpoints (`routes/huevos.php`, requerido desde `routes/web.php` bajo `auth`):

```
GET  /api/huevos                       → {huevos[], disponibles[], limites}
POST /api/huevos                       body: {evolution_chain_id} → 201 {huevo}
POST /api/huevos/{huevo}/eclosionar   → {reclutado} (ReclutadoSerializer con CP al nivel del usuario)
```

Deudas conocidas (ver `src/Huevos/context.md`):

- `habilidades=[]` en `ReclutadoSerializer` pendiente de Ability model (pendiente 15 de `docs/context.md`).
- Dedupe por familia sin FK estricta: `huevos.evolution_chain_id` es índice plano (misma estrategia
  que `pokemon.evolution_chain_id`); filas legacy sin `hatch`/cadena se toleran con `?? 1` / whereNotNull.

### Shared (`src/Shared/`)

- `Domain/Collection.php` — Colección base genérica
- `Tipos/` — `TipoPokemon` (enum), `TypeChart` (efectividades), `TypeEffectService`, `TiposCollection`, `TipoEntity`
- `Domain/Items/` — catálogo estático de items: 36 bayas, 7 bonguris, 8 pociones, 1 recipiente, 7 mejoras, 5 reductores y 18 resistencias (82 definiciones). `CatalogoItems` es la única fuente de claves, nombres, categorías, iconos y banderas de uso; `ItemCatalogo` (en `app/Support`) resuelve las claves de inventario y delega en él.
- `Domain/Ports/InventarioJugador.php` — puerto de inventario compartido; adaptador en `Infra/Inventario/InventarioJugadorEloquent.php`.

## Inventario compartido y fases F2–F5

Las decisiones de diseño, alternativas y deuda están en
[ADR-0001](adr/0001-inventario-compartido-f2-f5.md). Diseño detallado y criterios de aceptación
en [`TASK-012/architecture.md`](../.opencode/work/TASK-012/architecture.md) y
[`TASK-012/plan.md`](../.opencode/work/TASK-012/plan.md).

### Inventario compartido

`InventarioJugador` + `InventarioJugadorEloquent` son la vía común de F2, F4 y F5:

- `saldo`, `saldos`, `consumir`, `consumirVarios`, `incrementar`.
- Toda clave se valida contra `CatalogoItems`; hay un carve-out estrecho para las claves legacy
  de caramelos `familia:{id}` y `ev:{stat}`.
- `consumirVarios` bloquea las filas en orden de `item_key` y valida todos los saldos antes de
  decrementar: nunca deja cantidades negativas.
- Binding en `app/Providers/AppServiceProvider.php`.
- `InventarioGranja` sigue siendo la fachada de caramelos (`resumen()`, `consumirFamilia`,
  `consumirEv`) y añade `resumenBayas()`.

### Flujo F2 — plantar y cosechar

```
POST /api/habitats/{h}/farm/nests/{chain}/plantings
  → PlantarBaya: validar ClaveBaya → asegurar granja/familia
    → tx: lock nido → validar hueco (nivel*2) → InventarioJugador.consumir(1)
      → upsert nest_plantings (unique nest_id+slot)
        · existe → replantar: berry_key, planted_at, harvest_count = 0
        · no existe → crear fila

GET granja → ObtenerGranja
  → plantaciones en una consulta → ReglasPlantacion.stockDe/progresoPct/estadoDe (on-read)
  → GranjaDetalle { bayas, bonguri, familias[].nido { huecos_totales, plantaciones } }

POST .../plantings/{slot}/harvest
  → CosecharPlantacion: tx → lock nido + hueco → stock = f(ahora - planted_at, harvest_count)
    → stock <= 0 → 422 · stock > 0 → InventarioJugador.incrementar(stock)
      → harvest_count += stock
```

### Flujo F3 — bonus de hábitat y plantaciones

```
ProcesarExploracion (tick)
  → EsDelHabitat(especie del explorador, habitat)
  → eventos['bonos']['del_habitat'] (se calcula y persiste una sola vez)

FinalizarExploracion
  → ConsultorPlantaciones.factoresPara(user_id, habitat_id)
  → ContextoBotin { delHabitat, factoresPlantacion }
    → CalculadorBotinExploracion
      · pesosBayas *= factor por baya (solo bayas salvajes)
      · tiradas de baya ×1.25 (tope 4) si delHabitat
      · bonguri +10 pp (tope 85 %), sin factores de plantación
  → resultado persistido { bonos: { del_habitat } } → hito “Conoce el terreno”
```

### Flujo F4 — forjar

```
GET /api/forja/recetas
  → ListarRecetas: CatalogoRecetas.todas() (46) + InventarioJugador.saldos() (1 consulta)
  → RecetaDetalle[] con ingredientes, disponible/requerido, puede_fabricar

POST /api/forja/fabricar { receta }
  → Fabricar: CatalogoRecetas.receta() (404 si no existe) → cantidad = 1
    → tx: InventarioJugador.consumirVarios (lock ordenado) → incrementar(resultado)
    → ResultadoFabricacion { recetaClave, resultadoClave, cantidad, saldos }
```

### Flujo F5 — objeto en combate

```
player_target → seleccionarObjeto(clave)
  → valida catálogo, efecto y saldo → phase = player_item_target

player_item_target → previewTarget(team, idx) → confirmarObjetivoItem()
  → AccionObjeto { actorId, objetivoId, clave }
  → setPendingAction(DTOAccionObjeto) → setAnimStateObjeto()
  → commitAction() → resolverAccionObjeto()
    → actorJugadorActual() (AgregadoBatalla.actorActualId, server-side)
    → UsarObjetoEnCombate: validar → tx consumir(1) → aplicar efecto
    → turnManager.consumeAction()  (NO consumirMovimiento)
    → log “X usó Item en Y” → syncViewData → saveBattle → nextActor
```

### Decisiones y dependencias

| Decisión | Implementación | Depende de | Detalle |
|---|---|---|---|
| **D1** Inventario compartido | `Shared\Domain\Ports\InventarioJugador` + `Shared\Infra\Inventario\InventarioJugadorEloquent` | F1 (`player_inventory`) | Bloque 0; base de F2/F4/F5 |
| **D2** Plantaciones | `nest_plantings`, `ReglasPlantacion`, `PlantarBaya`, `CosecharPlantacion` | D1 | F2; stock on-read y unique por hueco |
| **D3** Bonus de hábitat | `ConsultorPlantaciones` (puerto) + `ConsultorPlantacionesEloquent` + `EsDelHabitat` | D1, D2 | F3; `Exploraciones.Domain` no importa `Granjas` |
| **D4** Forja | `src/Forja` + 39 claves en `Shared\Domain\Items` | D1 | F4; 46 recetas, sin migración |
| **D5** Objetos en combate | `AccionObjeto`, `DTOAccionObjeto`, `CatalogoEfectosItem`, `ResistenciasTemporales` | D1, D4 | F5; consume acción, no movimiento |
| **D6** Sesión | `BattleSessionService` v21 / mínimo 9 | D5 | v20 carga con defaults |
| **D7** Cosecha global | `CosecharTodo` + `POST /api/farm/harvest-all`; transacción por nido, errores parciales y evento `inventario:actualizado` | D1, D2 | TASK-014; no cambia `ReglasPlantacion` ni el bonus |

```
Shared.Domain.Items ──────────────► Granjas (F2), Forja (F4), Battle (F5)
Shared.Domain.Ports.InventarioJugador ─► Granjas, Forja, Battle
Granjas.Infra ──implementa──► Exploraciones.Domain.Ports.ConsultorPlantaciones
Exploraciones.Domain ──(no importa Granjas)──► CalculadorBotinExploracion
Forja.Domain ──(no importa Battle)──► CatalogoRecetas
Battle.Domain ──(consume claves de Shared, no importa Forja)──► CatalogoEfectosItem
```

Deuda conocida: `PersistirRecompensas` conserva una segunda vía de escritura directa de
`player_inventory`; `resolveActor()` de movimientos sigue usando `actingRefId`; las claves
`tipo:{slug}` no están en el carve-out del puerto; los iconos nuevos usan fallback. Detalle y
motivos en el [ADR-0001](adr/0001-inventario-compartido-f2-f5.md).

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
| **Aggregate** | Entidades raíz de batalla y equipos | `AgregadoBatalla`, `TeamAggregate` |
| **DTO** | Transferencia dominio → vista | `DTOMovimientoBatalla`, `DTOAccionBatalla`, `DTOResultadoDanio` — estado actual: viven en `Presentation/`; la convención destino los ubica en `Domain/DataTransferObjects` (ver `docs/ddd.md`) |
| **Service (Domain)** | Lógica de dominio orquestada | `ServicioEjecucionBatalla`, `CalculadorDañoClima`, `SelectorAccionIA`, `ReclutamientoSrv`, `TeamSrv` |
| **Repository** | Acceso a datos de hábitats | `HabitatRepository` |
| **Factory** | Creación de efectos y datos mock | `FabricaEfectos` (instancia inyectable), `FabricaBatallaMock` |
| **Registry** | Registro explícito de modelos expuestos | `DatagridRegistry` |
| **Whitelist declarativa** | Campos consultables por modelo (anti inyección) | `DatagridDefinition` |
| **Composition Root** | Registro de definiciones del datagrid | `DatagridServiceProvider` |
| **Strategy (constraint)** | whereHas custom por filtro de relación | `RelationFilter::$constraint` |
| **Ports & Adapters** | Inventario y consulta de plantaciones desacoplados de Eloquent/otros módulos | `Shared\Domain\Ports\InventarioJugador`, `Exploraciones\Domain\Ports\ConsultorPlantaciones` |
| **Cálculo on-read** | Stock de plantaciones sin cron (derivado de timestamps) | `Granjas\Domain\ReglasPlantacion` |
| **Estado local con fetch API (Alpine)** | Modal de gestión sin recarga: query inicial al abrir, mutaciones locales tras 200 OK | `habitats/show.blade.php` (`habitatShow()`) |

## Flujo de datos — Batalla manual (Livewire)

```
Usuario click → Combate (Livewire) → mount() → FabricaBatallaMock::createBattle()
  → triggerBattleStartEffects() → syncViewData() → saveBattle() → nextActor()

nextActor() → getBattle() → turnManager.getNextActor()
  → Si es jugador: phase='player_target', espera selección
      → (movimiento) previewTarget(team, idx) → daño preview → phase='player_move'
        → selectMove(index) → setPendingAction(DTOAccionBatalla) → setAnimState()
      → (objeto) seleccionarObjeto(clave) → phase='player_item_target'
        → previewTarget(team, idx) → confirmarObjetivoItem()
        → setPendingAction(DTOAccionObjeto) → setAnimStateObjeto()
      → Alpine.js timeout 700ms → commitAction()
  → Si es IA: prepareAiAnimation() → setPendingAction() → setAnimState()
    → Alpine.js timeout 700ms → commitAction()

commitAction()
  → DTOAccionBatalla → ServicioEjecucionBatalla.calcularYAplicarDaño()
      → CadenaDanio.calculate() (21 manejadores; incluye ManejadorResistenciaTemporal)
      → Combatiente.recibirDaño()
      → aplicarEstado() / aplicarStatChanges()
      → observer.notifyDamaged() / notifyFainted()
      → turnManager.consumeAction() + consumirMovimiento()
  → DTOAccionObjeto → resolverAccionObjeto()
      → actorJugadorActual() (AgregadoBatalla.actorActualId, server-side)
      → UsarObjetoEnCombate → ServicioUsoObjetos.validar() → consumir inventario (tx)
        → ServicioUsoObjetos.aplicar() (efecto + log)
      → turnManager.consumeAction()  (consume acción, NO movimiento)
  → syncViewData() → saveBattle() → nextActor()
```

## Flujo de datos — Batalla automática

```
AgregadoBatalla.ejecutarBatalla()
  → loop rondas → turnManager.startNewRound() (resetea contadores de acción)
    → loop turnos → getNextActor() (mayor velocidad efectiva) → SelectorAccionIA.elegirObjetivoPara() → SelectorAccionIA.elegirMejorMovimiento()
      → ServicioEjecucionBatalla.calcularYAplicarDaño()
      → aplicarEstado() / aplicarStatChanges()
      → observer.notifyDamaged() / notifyFainted()
      → turnManager.consumeAction()
    → triggerRoundEndEffects() (efectos de fin de ronda + daño por estado + daño por clima vía CalculadorDañoClima)
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

## Infraestructura transversal compartida (permanece en `app/`)

Infraestructura Laravel **transversal** que no pertenece a ningún módulo y permanece en `app/`:

| Capa | Archivos | Responsabilidad |
|---|---|---|
| **Datagrid** | `Datagrid/{DatagridService,DatagridRegistry,DatagridDefinition,RelationFilter}.php` | Consulta JSON de solo lectura con whitelist por modelo |
| **Providers** | 4 providers | Registro de servicios: App, BattleEffect, Datagrid |
| **Support** | `WebpConverterInterface.php`, `WebpConverter.php` | Conversión PNG→WebP (GD/Imagick/CLI cwebp) |
| **Console** | `Commands/OptimizeIconsToWebp.php` | `iconos:optimize-webp` (`--dir`, `--out`, idempotente) |
| **Enums** | 2 enums | `TipoEnum` (tipos pokémon), `StatEnum` |
| **base Controller** | `Http/Controllers/Controller.php` | Base de controladores HTTP; los controladores del módulo lo extienden |

### Pendiente de migrar al módulo (estado actual)

En el **destino** (ver `docs/ddd.md`), Controllers, Models Eloquent y Livewire viven en
`src/{{Modulo}}/Infra/` (`Controllers/`, `Models/`, `Livewire/`). Hoy están en `app/` y migran
**por módulo** al tocarlos (estrategia strangler); todavía nada migrado.

| Componente | Estado actual | Destino |
|---|---|---|
| **Controllers** | 10 controladores en `app/Http/Controllers` (hasta migrar cada módulo) | `src/{{Modulo}}/Infra/Controllers/` |
| **Livewire** | `Combate.php` en `app/Livewire` (módulo Battle, hasta migrar) | `src/Battle/Infra/Livewire/` |
| **Models** | 14 modelos en `app/Models` (hasta migrar cada módulo) | `src/{{Modulo}}/Infra/Models/` |

> Nota: los hábitats migraron de Livewire a Alpine + fetch API (modal "Gestión", ver sección
> Habitats); no quedan componentes Livewire fuera de `Combate.php`.

## Base de datos

PostgreSQL en el entorno de ejecución (Docker); los tests usan SQLite en memoria (`:memory:`).
76 migraciones. Esquema relacional:

```
provinces ──→ habitats ──→ pokemon_habitat ──→ pokemon ──→ pokemon_stats
                                                          ──→ pokemon_types
                                                          ──→ pokemon_evolution
                                                          ──→ abilities
                                                          ──→ pokedex (1:1)

farms ──→ nests ──→ nest_plantings (slot_index, berry_key, planted_at,
                                    harvest_count; unique nest_id+slot_index)

pokemon ──→ reclutados ──→ team_members ──→ teams
                       ──→ exploraciones_activas
                       ──→ huevos (snapshot capture_rate/hatch, es_shiny,
                                   ivs, evolution_chain_id índice, eclosionado_en)

player_inventory (unique user_id + item_key)
  · claves de catálogo: baya:*, bonguri:*, pocion:*, material:*,
    mejora:*, reductor:*, resistencia:*
  · claves legacy de caramelos: familia:{id}, ev:{stat}, tipo:{slug}
  · InventarioJugador valida catálogo + familia:/ev: (carve-out);
    tipo:{slug} queda fuera del puerto y lo escribe PersistirRecompensas

reclutados: columnas `ivs`/`evs` JSON nullable (cast `StatsReclutado`) desde
2026-09-15; legacy rows quedan en ceros vía cast.

teams: equipos de exactamente 3 miembros (slots 1-3; migración
2026_10_15_000001 borra `team_members` con `slot > 3`, `down()` no-op); columna `formacion`
(JSON nullable, migración 2026_09_13_195001) con la posición por slot (vanguardia/retaguardia)
para los combates 3v3 (prioridad: popup > persistida > clasificación automática).

users ──→ trainer_combat_log ──→ habitats
                                  (level, trainer_index, won, fought_at)

Familias evolutivas: grupo de filas de `pokemon` con el MISMO valor en la columna
`pokemon.evolution_chain_id` (entero, sin FK ni tabla). `caramelos.evolution_chain_id`
también es entero simple SIN FK (columna + unique conservadas). La tabla
`evolution_chains` fue eliminada (bug 23503).
```
