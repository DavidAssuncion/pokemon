# Contexto del módulo `CombateEntrenadores`

## Propósito

Permite al jugador enfrentarse a entrenadores procedurales desde la vista de un hábitat. Reutiliza el motor de batalla (`src/Battle/Domain/`) para el combate real y el sistema de recompensas de `Exploraciones` para otorgar EXP y caramelos dobles al ganar.

## Dependencias

- `src/Battle/Domain/` — `AgregadoBatalla`, `EquipoBatalla`, `DatosPokemonBatalla`, `Posicion`, `MovimientoBatalla`, `CategoriaMovimiento`
- `src/Exploraciones/` — `CalculadorRecompensas`, `PersistirRecompensas`, `NormalizadorPokemonDerrotado`, `PokemonDerrotado`, `ResultadoRecompensas`
- `src/Shared/` — `TipoPokemon`, `ViolacionReglaNegocio`, `DominioExcepcion`
- `app/Livewire/Combate.php` — llama a `RegistrarResultadoEntrenador` y `OtorgarRecompensasEntrenador` al finalizar la batalla
- `app/Models/TrainerCombatLog.php` — modelo Eloquent usado por `EloquentEntrenadorLogRepository`
- `app/Jobs/ActualizarPokedexJob.php` — dispara avistados al ganar

## Decisiones de diseño

- **Movimientos sintéticos**: no hay ataques reales en BD, se generan por tipo (físico 60, especial 80 por tipo no-Normal; Normal puro 80/100; Normal mixto 40/60).
- **Rival determinista y de exactamente 3**: semilla `crc32(habitat|nivel|entrenador|fecha)` con `Mt19937` → mismo equipo todo el día; 3 especies únicas si el pool las tiene y repetición ponderada hasta 3 si es corto.
- **Equipo del jugador de exactamente 3**: `ConstruirEquipoJugador::desdeEquipo` es el punto único de validación (422 «exactamente 3 miembros») para ruta, entrenadores, gimnasios y mazmorra.
- **Formación rival**: clasificación defensivo→vanguardia + aleatorio 1/2 o 2/1.
- **Límite diario**: 9 combates por hábitat (3×3). `won=true` bloquea. `won=false` es repetible.
- **Recompensas dobles**: multiplicador 2.0 sobre la fórmula de exploración. Sin captura.
- **Persistencia en sesión**: batalla serializada con prefijo `v{version}|` + metadatos bajo `battleId._meta`.

## Reglas de negocio

1. Un entrenador bloqueado (`won=true` hoy) no puede ser desafiado hasta el día siguiente.
2. Si se pierde, no se registra victoria → se puede repetir.
3. La generación del rival es determinista por fecha: todos los jugadores ven el mismo equipo cada día.
4. Los movimientos se cargan del catálogo (`pokemon_moves`); si la especie no tiene movimientos ofensivos asignados se añade el generador sintético por tipos como fallback.
5. No hay captura de pokémon rivales al ganar.

## Casos especiales

- `EntrenadorDerrotadoHoy` (excepción 422) se lanza si se intenta combatir contra un entrenador ya derrotado hoy.
- Si el pool del hábitat está vacío para un nivel, `GeneradorEquipoEntrenador` retorna array vacío y
  `IniciarCombateEntrenador` lanza `ViolacionReglaNegocio` 422 («No hay Pokémon disponibles para este
  entrenador.») **sin persistir sesión** (guard post-Arquitecto).
- `OtorgarRecompensasEntrenador` filtra `speciesIdsRival <= 0` y solo procesa los que existen en BD.
- `SESSION_VERSION` vive en `app/Support/BattleSessionService.php` (**20**, mínimo compatible 9);
  `IniciarCombateEntrenador` no la duplica: usa `CreadorBatallaSesion`/`BattleSessionService`.

## Referencias

- `docs/combate_entrenadores.md` — Documentación técnica completa del módulo
- `routes/entrenadores.php` — Definición de rutas
- `app/Models/TrainerCombatLog.php` — Modelo Eloquent
- `database/migrations/2026_08_31_000001_create_trainer_combat_log_table.php` — Migración
---

## Guía de búsqueda (grep)

- Clases: `GeneradorEquipoEntrenador`, `ConstruirEquipoJugador`, `IniciarCombateEntrenador`,
  `RegistrarResultadoEntrenador`, `OtorgarRecompensasEntrenador`, `MapeadorPokemonBatalla`,
  `EntrenadorLogRepositoryInterface`, `EntrenadorDerrotadoHoy`.
- Constantes: `OtorgarRecompensasEntrenador::MULTIPLICADOR_ENTRENADOR` (2.0),
  `ReglasEquipo::MIEMBROS` (3), `MapeadorPokemonBatalla::MAX_MOVIMIENTOS_RESPALDO`.
- Términos: `trainer_combat_log`, `nivel`, `trainer_index`, `fought_at`, `formacion`,
  `crc32`, `Mt19937`, `tipo='entrenador'`.
- Tablas: `trainer_combat_log`, `habitats`, `teams`, `reclutados`, `pokemon`.
- Tests: `tests/Feature/CombateEntrenadores/`, `tests/Unit/CombateEntrenadores/`.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Alta] Duplicación `GeneradorEquipoRuta` ↔ `GeneradorEquipoEntrenador`** (pool + ponderación,
  ~9-15 líneas; pendiente 17 de `docs/context.md`). Extraer un generador de pool compartido.
- **[Media] `OtorgarRecompensasEntrenador` usa `array<int, Collection<Pokemon>>`** de
  `CadenasEvolutivas`; tipar con un VO/colección (ver `src/Shared/context.md`).
- **[Media] `RegistrarResultadoEntrenador`/`RegistrarResultadoGimnasio` devuelven arrays** de
  resultado; convertirlos a DTOs readonly.
- **[Baja] `ConstruirEquipoJugador` ya usa `ReglasEquipo::MIEMBROS`**; no reintroducir el literal 3
  en validaciones/mensajes.
- **[Baja] `MapeadorPokemonBatalla` normaliza movimientos desde Eloquent**; mantener el mapper como
  frontera y no filtrar modelos al dominio.
