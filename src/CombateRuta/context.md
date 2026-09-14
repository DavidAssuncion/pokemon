# Contexto del módulo `CombateRuta`

## Propósito

Combate de Ruta 5v5: el jugador se enfrenta a 5 pokémon salvajes generados del pool de un
hábitat (por nivel 1-3). Es el **modo por defecto** de `/habitats/{id}` (junto a Entrenadores).
Reutiliza el motor de batalla (`src/Battle/Domain/`) y el sistema de recompensas de
`Exploraciones` (misma fórmula de exploración con multiplicador 1.0).

## Estructura (convención DDD por módulos, `docs/ddd.md`)

| Carpeta / clase | Responsabilidad |
|---|---|
| `Domain/DataTransferObjects/ResultadoRuta` | DTO de presentación del modal de victoria (`victoria`, `expTotal`, `expMiembro`, `caramelos`, `capturas`). `aArray()` define el contrato snake_case de la vista. |
| `App/GeneradorEquipoRuta` | Genera los 5 salvajes del pool del hábitat por nivel. Selección ponderada **CON reemplazo** por slot (`capture_rate/hatch`, reutiliza `PoolHabitat::ponderado`); aleatorio inyectable (tests) o azar real (`Randomizer`); clasifica cada miembro con `ClasificadorOfensivaDefensiva`. |
| `App/IniciarCombateRuta` | Valida equipo del jugador de exactamente 5 miembros, construye ambos `EquipoBatalla` (jugador con `ConstruirEquipoJugador`, rival con `GeneradorEquipoRuta`) y crea la batalla en sesión vía `CreadorBatallaSesion` (meta `tipo=ruta`). |
| `App/RegistrarResultadoRuta` | Procesa el resultado: derrota → `null` (sin modal); victoria → delega en `OtorgarRecompensasRuta`. Sin log ni bloqueo diario. |
| `App/OtorgarRecompensasRuta` | Recompensas con `MULTIPLICADOR_RUTA = 1.0`: EXP a cuenta y por miembro, caramelos familia/EV/tipo, **capturas** de salvajes (`ProbabilidadCaptura` cap-25 con roll inyectable) y `ActualizarPokedexJob` (AVISTADO) por rival. |
| `Infra/Controllers/CombateRutaController` | HTTP: `GET rivales` (genera equipo para el panel, no persiste), `POST iniciar` (valida `team_id` del usuario, `formacion`, `nivel`; devuelve `battle_id` + redirect). |

## Dependencias

- `src/Battle/Domain/` — `AgregadoBatalla`, `EquipoBatalla`, `DatosPokemonBatalla`, `Posicion` (motor reutilizado, sin duplicación).
- `src/CombateEntrenadores/App/` — `MapeadorPokemonBatalla` (Eloquent `Pokemon` → `DatosPokemonBatalla`), `ConstruirEquipoJugador` (Team + formación).
- `src/Exploraciones/` — `CalculadorRecompensas`, `PersistirRecompensas`, `NormalizadorPokemonDerrotado`, `PokemonDerrotado`, `ResultadoRecompensas`, `ColeccionPoolPonderado`/`PoolHabitat`.
- `src/Shared/Domain/` — `ClasificadorOfensivaDefensiva`, `ProbabilidadCaptura` (dominio puro compartido, hardening).
- `app/Support/` — `CreadorBatallaSesion` (DRY de creación 5v5), `CadenasEvolutivas`, `ItemCatalogo`, `BattleSessionService`.
- `app/Jobs/ActualizarPokedexJob.php` — avistados al ganar.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` — despacha el fin de batalla por `meta['tipo'] === 'ruta'`.
- Modelos Eloquent: `App\Models\Team`, `App\Models\Habitat`, `App\Models\Pokemon`, `App\Models\User`.

## Decisiones de diseño

- **Selección ponderada CON reemplazo (Opción A)**: cada slot se tira de forma independiente
  con peso `capture_rate / hatch` (igual fórmula que `PoolHabitat::ponderado`; `hatch` nulo/cero
  → divisor 1; `capture_rate <= 0` → excluido). Consecuencia: el rival **varía en cada combate**
  (sin semilla), a diferencia del entrenador (determinista por día). Pool sin especies con peso →
  array vacío → `ViolacionReglaNegocio` "No hay Pokémon salvajes…".
- **Clasificador determinista** (`ClasificadorOfensivaDefensiva`): ofensiva = `atk + speed` >
  defensiva = `def + spDef + hp` → retaguardia; empate → vanguardia. Formación determinista con
  *mixed guarantee*: si todos caen en el mismo bando, se mueve al de mayor stat contraria al otro
  (primer índice, desempate estable).
- **Sin límite diario ni log**: el combate de ruta es siempre repetible (no usa
  `trainer_combat_log`). Consta como test `repetible_sin_limite_diario`.
- **Capturas habilitadas**: al ganar, cada salvaje derrotado hace un roll de captura con la regla
  compartida cap-25 (`ProbabilidadCaptura::intentar`, máximo 25/255 ≈ 9.8 %), con aleatorio
  inyectable para tests. Las capturas se devuelven al modal (`capturas[].pokemon_id`).
- **Recompensas ×1.0**: misma fórmula de exploración (`CalculadorRecompensas`) con multiplicador
  1.0 (el entrenador usa ×2.0, gimnasio × mayor).
- **Formación del jugador**: prioridad popup del modal > `teams.formacion` persistida >
  clasificación automática por stats.
- **Nivel del rival**: pasa `nivelRival` (nivel del jugador) a los `DatosPokemonBatalla`; el
  pool del hábitat se filtra por nivel (1-3).

## Reglas de negocio

1. El equipo del jugador debe tener **exactamente 5 miembros** (`ViolacionReglaNegocio` en caso contrario).
2. Repetible sin límite diario; no hay bloqueo por victoria ni tabla de log.
3. El rival es siempre salvaje (team2 nombre "Pokémon salvajes") y varía entre combates.
4. La derrota no otorga nada (sin modal de recompensas).
5. Solo se procesan `speciesIdsRival > 0` y existentes en BD para recompensar/avistar.

## Endpoints API

Rutas en `routes/ruta.php` (requeridas desde `routes/web.php` dentro de `auth`):

```
GET  /api/habitats/{habitat}/ruta/rivales?nivel=1-3 → { rivales: [{id, nombre, posicion, species_id, nivel}] }
POST /api/habitats/{habitat}/ruta/iniciar
     body: { team_id (del usuario), formacion?[slot => vanguardia|retaguardia], nivel? (1-3) }
     → { battle_id, redirect: /combate?battle_id=... }
```

## Casos especiales

- `GeneradorEquipoRuta` retorna `[]` si el pool del nivel está vacío o no tiene especies con peso
  → `IniciarCombateRuta` lanza `ViolacionReglaNegocio` claro.
- El panel de rivales (`GET rivales`) genera equipo sin guardarlo; el POST `iniciar` vuelve a
  generar (los rivales del combate pueden diferir de los vistos en el panel).
- `PresentadorResultadoBatalla::procesarRuta` no genera log; la victoria devuelve `rewards` con
  el shape de `ResultadoRuta::aArray()` (contrato del modal) y limpiar meta de sesión.
- `SESSION_VERSION` vive en `app/Support/BattleSessionService.php` (9 al cierre de la tarea) y su
  migración de versiones es best-effort.

## Decisiones de hardening (Shared/Domain)

Endurecimiento de la arquitectura al escalar a 5v5 (commit f81c2339d0):

- **`src/Shared/Domain/ClasificadorOfensivaDefensiva`** — clasificador + formación extraídos a
  dominio puro compartido (lo usan CombateEntrenadores y CombateRuta; antes duplicado/variante).
- **`src/Shared/Domain/ProbabilidadCaptura`** — regla unificada de captura cap-25, compartida
  entre Reclutamiento, Exploraciones y CombateRuta (un solo lugar para la fórmula).
- **`src/Shared/Domain/EscaladorNivelRival`** — escalado `nivel_base + floor((jugador - base)/2)`
  en dominio puro, compartido entre módulos (Exploraciones, Gimnasios).
- **`app/Support/CreadorBatallaSesion`** — centraliza el "tail" de creación de batallas 5v5
  (construir `EquipoBatalla`, `triggerBattleStartEffects`, guardar batalla + meta) y elimina el
  duplicado entre Ruta/Entrenador/Gimnasio/Mazmorra (DRY).
- Cleaner: módulos relacionados reutilizan estos servicios compartidos en lugar de lógica propia.

## Tests

`tests/Feature/CombateRuta/`:
- `GeneradorEquipoRutaTest` — ponderación CON reemplazo, formación determinista, ids `ruta_{habitat}_{nivel}_{i}`, pool sin peso/vacío, hatch nulo/cero.
- `IniciarCombateRutaTest` — batalla 5v5 + meta `ruta`, error con <5 miembros, pool vacío, repetible sin límite.
- `OtorgarRecompensasRutaTest` — victoria con capturas y avistados, derrota sin recompensas, filtrado de ids.
- `CombateRutaControllerTest` — endpoints HTTP (valida team del usuario, nivel 1-3, redirect).
- `PresentadorRutaTest` — contrato `aArray()` del modal.

## Referencias

- `docs/context.md` — Resumen funcional del proyecto
- `docs/architecture.md` — Sección del módulo en la arquitectura
- `docs/ddd.md` — Convención canónica DDD por módulos
- `routes/ruta.php` — Definición de rutas
- `database/migrations/2026_09_13_195001_add_formacion_to_teams_table.php` — columna `teams.formacion`
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` — despacho por tipo de batalla

## Commits de la tarea "combate-ruta-5v5"

- Backend: `0195b049cf`
- Frontend: `68bf20c63b`
- Cleaner (escalado/limpieza): `5b802bc`, `67577fa`, `b8b5b9d`, `e577b7e`, `b1c44dd`
- Hardener (Endurecimiento Shared/Domain): `f81c2339d0`