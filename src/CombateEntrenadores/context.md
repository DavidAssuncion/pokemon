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
- **Rival determinista**: semilla `crc32(habitat|nivel|entrenador|fecha)` con `Mt19937` → mismo equipo todo el día.
- **Formación rival**: clasificación defensivo→vanguardia + aleatorio 1/2 o 2/1.
- **Límite diario**: 9 combates por hábitat (3×3). `won=true` bloquea. `won=false` es repetible.
- **Recompensas dobles**: multiplicador 2.0 sobre la fórmula de exploración. Sin captura.
- **Persistencia en sesión**: batalla serializada con prefijo `v{version}|` + metadatos bajo `battleId._meta`.

## Reglas de negocio

1. Un entrenador bloqueado (`won=true` hoy) no puede ser desafiado hasta el día siguiente.
2. Si se pierde, no se registra victoria → se puede repetir.
3. La generación del rival es determinista por fecha: todos los jugadores ven el mismo equipo cada día.
4. Los movimientos son temporales (sintéticos) — no persisten en BD.
5. No hay captura de pokémon rivales al ganar.

## Casos especiales

- `EntrenadorDerrotadoHoy` (excepción 422) se lanza si se intenta combatir contra un entrenador ya derrotado hoy.
- Si el pool del hábitat está vacío para un nivel, `GeneradorEquipoEntrenador` retorna array vacío.
- `OtorgarRecompensasEntrenador` filtra `speciesIdsRival <= 0` y solo procesa los que existen en BD.
- `SESSION_VERSION` en `IniciarCombateEntrenador` debe coincidir con la de `Combate.php` (actualmente 5).

## Referencias

- `docs/combate_entrenadores.md` — Documentación técnica completa del módulo
- `routes/entrenadores.php` — Definición de rutas
- `app/Models/TrainerCombatLog.php` — Modelo Eloquent
- `database/migrations/2026_08_31_000001_create_trainer_combat_log_table.php` — Migración