# Contexto del módulo `Gimnasios`

## Propósito

Sistema de 5 gimnasios (bug/poison/normal/grass/flying) con progresión secuencial: 3 entrenadores
+ 1 líder por gimnasio, medalla al completar, nivel mínimo y escalado de rivales según el nivel
del jugador. Reutiliza el motor de batalla (`src/Battle/Domain/`), el mapeo de pokémon a combate
y las recompensas dobles de `CombateEntrenadores`.

## Dependencias

- `src/Battle/Domain/` — `AgregadoBatalla`, `EquipoBatalla`, `DatosPokemonBatalla`, `Posicion`
- `src/CombateEntrenadores/App/` — `MapeadorPokemonBatalla`, `ConstruirEquipoJugador`, `OtorgarRecompensasEntrenador`
- `src/CombateEntrenadores/Domain/` — `GeneradorMovimientosTipo`, `ClasificadorPosicion`
- `src/Shared/` — `TipoPokemon`, `NivelHelper`, excepciones de dominio (`ViolacionReglaNegocio`, `RecursoNoExiste`)
- `app/Livewire/Combate.php` — llama a `RegistrarResultadoGimnasio` + `OtorgarRecompensasEntrenador` al finalizar
- `app/Models/GymProgress.php` — modelo Eloquent usado por `EloquentGymProgressRepository`

## Decisiones de diseño

- **Catálogo en código**: los 5 gimnasios (slug, medalla, tipo, nivel mínimo, equipos por etapa
  con species_id) viven en `CatalogoGimnasios` (Domain), no en BD.
- **Escalado de stats**: el motor de batalla NO escala por nivel (BattleStats calcula a nivel
  fijo 100 y ManejadorDanioBase usa 50), por lo que `GeneradorPokemonGimnasio` escala los stats
  base de la BD al `nivel_rival` (HP = floor((2*base*L/100)+L+10); resto = floor((2*base*L/100)+5);
  clamp a 1) antes de construir los `DatosPokemonBatalla` del rival.
- **Nivel rival congelado**: `nivel_rival = nivel_min + floor((jugador - nivel_min)/2)` se calcula
  al INICIAR cada combate y se guarda en metadatos de sesión.
- **Progresión secuencial**: `gym_progress.current_stage` (1-5). Solo se puede combatir la etapa
  actual; ganar → +1; perder → repetible; 5 = completado para siempre.
- **Anti-IDOR**: `RegistrarResultadoGimnasio` solo persiste si `won` y `userId === authUserId`.
- **Recompensas**: reutiliza `OtorgarRecompensasEntrenador` (×2.0 + `ActualizarPokedexJob`
  AVISTADO por rival). Si se derrota al líder (etapa 4), añade la medalla al modal.
- **Persistencia en sesión**: batalla serializada con prefijo `v{version}|` + metadatos
  (`tipo='gimnasio'`, `gym_id`, `stage`, `nivel_rival`, `user_id`, `team_id`) bajo `battleId._meta`.
  `SESSION_VERSION` = 5 (coincide con `Combate.php` y `IniciarCombateEntrenador`).

## Reglas de negocio

1. Un gimnasio requiere nivel mínimo (`nivel_min`); por debajo queda bloqueado.
2. Solo se puede combatir la etapa ACTUAL (`current_stage`). No hay marcha atrás ni repetición.
3. Gimnasio completado (`current_stage = 5` + `completed_at`) → cerrado para siempre.
4. Si se pierde, la etapa no avanza → repetible.
5. El nivel rival se congela al iniciar el combate (metadatos de sesión).
6. Las etapas son: 1=Entrenador 1, 2=Entrenador 2, 3=Entrenador 3, 4=Líder, 5=Completado.

## Casos especiales

- `GimnasioBloqueado` (422) si nivel_jugador < nivel_min; `GimnasioCompletado` (422) si ya se
  completó; `GimnasioNoExiste` (404) si el slug no está en el catálogo.
- `RegistrarResultadoGimnasio` devuelve `['avance', 'completado', 'medalla']`: si `won` y
  `userId === authUserId`, avanza; si llega a 5, `completado` y `medalla` = nombre de la medalla.
- `GeneradorPokemonGimnasio` carga pokémon por `species_id` (no por `id`) y, si una especie no
  existe en BD, se omite del equipo.
- Los pokémon rivales se clasifican por stats (defensivo → vanguardia, ofensivo → retaguardia)
  con `ClasificadorPosicion`.

## Referencias

- `docs/gimnasios.md` — Documentación técnica completa del módulo
- `routes/gimnasios.php` — Definición de rutas
- `app/Models/GymProgress.php` — Modelo Eloquent
- `database/migrations/2026_09_01_000001_create_gym_progress_table.php` — Migración
