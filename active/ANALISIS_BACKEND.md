# ANÁLISIS BACKEND — IVs, Shiny, Huevos, EVs, Rival IVs

Fecha: 2026-09-15
Rol: Backend (Laravel / PHP 8.4). Contrato: `active/ANALISIS_FRONTEND.md` (sección IVs/EVs/huevos/shiny).

## Objetivo

Implementar per-stat IVs (0–15 ×4 into base stats/CP), shiny wild encounters (1/2048, 15/15/15 IVs, guaranteed capture), módulo huevos (2 same species, min 10/10/10 IVs, 1/512 shiny, capture-rate-based times), EV candies vía `POST /reclutado/{id}/dar-evs`, e IVs de rivales por dificultad.

## Archivos afectados

### Nuevos
- `src/Shared/Domain/ProbabilidadesShiny.php`
- `src/Shared/Domain/DistribuidorStatsValor.php`
- `src/Shared/Domain/IvsRangoEntrenador.php`
- `src/Pokemon/Domain/Stats/GeneradorIvs.php`
- `app/Models/Casts/StatsReclutado.php`
- `app/Models/Huevo.php`
- `app/Support/CrearReclutado.php`
- `src/Huevos/Domain/EstadoHuevo.php`
- `src/Huevos/Domain/CalculadorTiemposHuevo.php`
- `src/Huevos/Domain/CalculadorEstadoHuevo.php`
- `src/Huevos/App/CrearHuevo.php`
- `src/Huevos/App/EclosionarHuevo.php`
- `src/Huevos/App/ListarHuevos.php`
- `src/Huevos/Infra/Controllers/HuevoController.php`
- `routes/huevos.php`
- `database/migrations/2026_09_15_000001_add_ivs_evs_to_reclutados_table.php`
- `database/migrations/2026_09_15_000002_create_huevos_table.php`

### Modificados
- `src/Pokemon/Domain/Stats/StatsValue.php` — add `desdeArray()`, `toArray()`, `conStat()`
- `src/Pokemon/Domain/Stats/BattleStats.php` — 4th ctor param `?StatsValue $ivs`, per-stat IVs
- `src/Battle/Domain/DatosPokemonBatalla.php` — add promoted `?StatsValue $ivs = null`
- `src/Battle/Domain/EquipoBatalla.php` — pass `$member->ivs` to BattleStats
- `src/Battle/Domain/Combatiente.php` — shiny icon `/shiny/{id}_s.webp`, add `es_shiny` to aArrayVista
- `src/CombateEntrenadores/App/MapeadorPokemonBatalla.php` — add `?StatsValue $ivs = null`
- `src/CombateEntrenadores/App/ConstruirEquipoJugador.php` — pass evs + ivs
- `src/CombateEntrenadores/App/GeneradorEquipoEntrenador.php` — add ivPrincipal/ivResto params
- `src/CombateEntrenadores/App/IniciarCombateEntrenador.php` — compute IVs by nivel
- `src/Gimnasios/Domain/EvsRangoEntrenador.php` — delegate to DistribuidorStatsValor
- `src/Gimnasios/App/GeneradorPokemonGimnasio.php` — add ivPrincipal/ivResto params
- `src/Gimnasios/App/IniciarCombateGimnasio.php` — pass IVs by stage
- `src/CombateRuta/App/GeneradorEquipoRuta.php` — shiny roll + random IVs per slot
- `src/CombateRuta/App/OtorgarRecompensasRuta.php` — shiny partition + direct recruit
- `src/CombateRuta/App/RegistrarResultadoRuta.php` — shinySlots param
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` — pass shinySlots
- `app/Models/Reclutado.php` — add ivs/evs to fillable + casts
- `app/Support/ReclutadoSerializer.php` — add ivs/evs/habilidades + fix cpDe
- `app/Http/Controllers/ReclutamientoController.php` — use CrearReclutado
- `app/Http/Controllers/ReclutadoController.php` — add darEvs
- `routes/player.php` — add dar-evs route
- `routes/web.php` — require huevos.php

## Comportamientos a cubrir en el testing futuro

### Unit
- `ProbabilidadesShiny::esShiny()` — callable 1/max → true; callable 2/max → false.
- `GeneradorIvs::aleatorios()` — 6 stats in [0,15]; callable override.
- `GeneradorIvs::maximos()` — all 15.
- `GeneradorIvs::huevo()` — all in [10,15]; callable override.
- `BattleStats` — per-stat IVs affect formulas; no IVs → same as legacy.
- `StatsValue::desdeArray()` — short keys, missing keys → 0, null → zeros.
- `StatsValue::toArray()` — canonical short keys.
- `StatsValue::conStat()` — returns new instance with one stat changed.
- `DistribuidorStatsValor::distribuir()` — top2 → principal, rest → resto, tie-break.
- `EvsRangoEntrenador::distribuir()` — same behavior (delegates).
- `IvsRangoEntrenador::rangoPorNivelEntrenador()` — nivel 1→[8,6], 2→[10,8], 3→[12,10].
- `IvsRangoEntrenador::distribuir()` — same as EvsRangoEntrenador.
- `CalculadorTiemposHuevo::gestacionMinutos()` / `eclosionMinutos()` — CR45→160min/320min.
- `CalculadorEstadoHuevo::estadoDe()` — GESTANDO→INCUBANDO→LISTO transitions.
- `CrearReclutado::crear()` — creates with random IVs, dispatches PokedexJob.
- `CrearHuevo::crear()` — validates ≥2 same species, no active eggs, rolls shiny/IVs.
- `EclosionarHuevo::eclosionar()` — validates LISTO, marks eclosionado_en, creates reclutado.
- `ListarHuevos::listar()` — builds huevos, disponibles, limites.
- `CrearReclutado::crear()` for shiny — passes maximos IVs.
- `CrearReclutado::crear()` for hatch — passes egg IVs.

### Feature
- `POST /reclutado/{id}/dar-evs` — happy path (+10 EV), 422 sin caramelos, 422 tope 255, 422 en exploración.
- `GET /api/huevos` — huevos vacíos, disponibles, limites, estados.
- `POST /api/huevos` — crear huevo válido, 422 sin 2 ejemplares, 422 con huevo activo.
- `POST /api/huevos/{id}/eclosionar` — eclosionar listo, 422 no listo, 422 ajeno (404).
- Shiny capture en ruta — partition, direct recruit, capturas combined.
- Rival IVs — entrenador nivel 1-3, gimnasio etapa 1-3 vs 4, líder.

## Diseño

### IVs flow
- `StatsValue` (6 float props) → `BattleStats` 4th param → `DatosPokemonBatalla::$ivs` → `EquipoBatalla::fromData` → `PokemonEntity::battleStats`.
- Player IVs: `Reclutado->ivs` (StatsReclutado cast) → `ConstruirEquipoJugador` → `MapeadorPokemonBatalla::desdePokemon`.
- Rival IVs: `IvsRangoEntrenador::distribuir()` → `GeneradorEquipoEntrenador`/`GeneradorPokemonGimnasio` → `DatosPokemonBatalla::$ivs`.

### Shiny flow
- `ProbabilidadesShiny::esShiny()` with callable `fn(int $max): int`.
- `GeneradorEquipoRuta::generar()` → per-slot shiny roll → shinySlots list.
- `OtorgarRecompensasRuta::otorgar()` → partition derrotados by shiny → normals via CalculadorRecompensas + PersistirRecompensas; shinies via `CrearReclutado::crear(esShiny: true, ivs: GeneradorIvs::maximos())`.
- Combined capturas = normal captures + shiny RecompensaCaptura entries.

### Huevos
- States computed on READ from `created_at` + `capture_rate` (no stored state column).
- `gestacionMinutos = round(7200/cr)` clamped [15,240]; `eclosionMinutos = gestacion × 2`.
- Single active egg limit (gestando/incubando/listo unhatched).
- Egg creation is USER-INITIATED via `POST /api/huevos` (no auto-trigger).
- Route-model binding with BelongsToUser → anti-IDOR (404 on others).

### Dar EVs
- `POST /reclutado/{reclutado}/dar-evs` body `{stat}` validated `in:hp,atk,def,spAtk,spDef,speed`.
- Stat slug → StatEnum::value (1-6) for item_key; slug → StatsValue property for read/write.
- +10 EV per candy, per-stat cap 255 (no 510 total cap — per frontend UI).
- Response: `{success, actual, caramelos_disponibles, evs}`.

### DRY
- `DistribuidorStatsValor` shared by EvsRangoEntrenador + IvsRangoEntrenador.
- `CrearReclutado` shared by recruit, shiny capture, egg hatch.

## Riesgos

- **`MapadorPokemonBatalla` does not exist**: the file is `MapeadorPokemonBatalla` (typo in plan). Actual file is correct.
- **DefenseHp/SpDefenseHp IV source**: These pseudo-HP stats use their respective stat's IV (defense IV for defenseHp, spDef IV for spDefenseHp) — changed from uniform `$iv`.
- **`CombatientesCollection::map()` returns `array`** (not Collection) — verified via `Src\Shared\Domain\Collection::map()` signature.
- **Shiny icon convention**: Real files are `{id}_s.webp` (1137 files), NOT `{id}.webp`. Backend must emit `_s` URL.
- **`StatEnum::slug()`** uses `atksp|defsp|spd` — dar-evs maps frontend slugs to StatEnum internally.
- **Auto-egg trigger DROPPED** — egg creation is user-initiated via POST /api/huevos only.
- **Tests deferred** per backend.md convention.
