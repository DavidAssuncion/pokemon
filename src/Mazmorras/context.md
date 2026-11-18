# Contexto del módulo `Mazmorras`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Mazmorras por hábitat: una secuencia de pisos, cada uno con un jefe configurado. Cada piso es un
combate **3v1** (equipo del jugador de 3 vs un jefe con vida ×10 y un multiplicador permanente ×3
no-etapa sobre sus stats de combate). El progreso es secuencial y la derrota impone un cooldown.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/ConfiguracionMazmorra.php` | Configuración desde `habitats.mazmorra` (pisos + `species_id`). |
| `Domain/ReglasMazmorra.php` | Constantes: vida jefe ×10, multiplicador permanente ×3 (+200 %) no-etapa, recompensa 7× gimnasio, cooldown 1 h, piso mínimo 1. |
| `Domain/Collections/PisoConfiguracionCollection.php` | Pisos tipados (`PisoConfiguracion`). |
| `Domain/DataTransferObjects/` | `PisoMazmorra`, `ResultadoPisosMazmorra`, `ResultadoMazmorra`, `PisoConfiguracion`. |
| `Domain/Collections/PisoMazmorraCollection.php` | Pisos con estado para la API. |
| `Domain/Repositories/DungeonProgresoRepositoryInterface.php` | Progreso/cooldown por usuario. |
| `Domain/Exceptions/PisoNoDisponible.php` | Piso bloqueado, inexistente o en cooldown. |
| `App/ObtenerPisosMazmorra.php` | Lista de pisos alcanzables con estado. |
| `App/IniciarCombateMazmorra.php` | Inicia el combate del piso y guarda la batalla en sesión. |
| `App/GeneradorJefeMazmorra.php` | Genera el jefe (vida ×10, stats base ×1, `multiplicadorStatsJefe` ×3, sin `bonusInicialStats` ni `puntosDebiles`, IVs 0 deterministas). |
| `App/ObtenerConfiguracionMazmorra.php` | Lee `habitats.mazmorra`; punto único de lectura. |
| `App/RegistrarResultadoMazmorra.php` | Avance de piso (victoria/derrota). |
| `App/OtorgarRecompensasMazmorra.php` | Recompensas de victoria: 7× la de un gimnasio (multiplicador 70.0). |
| `Infra/EloquentDungeonProgresoRepository.php` | `DungeonProgress`/`DungeonLog`. |
| `Infra/Controllers/MazmorraController.php` | HTTP JSON. |

## Decisiones de producto y funcionamiento

1. **Configuración en `habitats.mazmorra`** (JSON): lista de `{piso, species_id}`. Sin
   configuración válida → lista vacía; el módulo no crea mazmorras por defecto.
2. **Un jefe por piso**, un solo combatiente. El jugador combate con su equipo de exactamente 3
   (`ConstruirEquipoJugador`, 422 si no).
3. **Jefe: vida ×10 y stats base ×1** (`MULTIPLICADOR_HP_JEFE = 10`), nivel = nivel del jugador al
   iniciar e **IVs 0 (determinista)**. El poder lo aporta un **efecto permanente ×3 (+200 %) no-etapa**
   (`MULTIPLICADOR_STATS_EFECTIVO_JEFE`) que viaja como `DatosPokemonBatalla::$multiplicadorStatsJefe`
   y se aplica en `BattleStats::multiplicadorStats` a ataque, defensa, ataque/defensa especial,
   velocidad y a las barreras `defenseHp`/`spDefenseHp`; la vida queda excluida y
   `MultiplicadoresStats` permanece neutro (no es una etapa). El jefe lleva `esJefe=true` y fases;
   **no envía `puntosDebiles` ni `bonusInicialStats`**. Se eliminaron las partes Núcleo/Coraza y el
   fallback de `JefeEstado` que creaba un Núcleo implícito; la infraestructura de partes se conserva
   para una habilidad futura (fuera de alcance). `bonusInicialStats` sigue disponible para otros modos.
   La tarjeta de combate muestra **cinco chips, uno por stat** (ATK, DEF, SpA, SpD y SPE), con el
   porcentaje derivado del multiplicador (`+200 %` con ×3) y sin chip único; tampoco muestra chips
   de fase/ENRAGE ni `BUFF`.
4. **Boosts limitados en mazmorra (2026-11-18)**: los movimientos que escalan con etapas
   (`objetivo_con_boost`, `objetivo_boost`, `atacante_boost`) **solo cuentan el exceso sobre
   +4 etapas / ×3**. El multiplicador permanente del jefe no suma potencia por sí mismo: con
   `BattleStats::multiplicadorStats() > 1.0`, `Combatiente::bonificacionesPositivasParaDanio()`
   delega en `StatsCombatiente::bonificacionesPositivasSobreLimite(ReglasBatalla::LIMITE_ETAPAS_BOOST_EXTRA)`,
   que suma `max(0, etapas − 4)` por stat. Sin multiplicador se mantiene
   `bonificacionesPositivas()` y `EjecutorCantoEncantador` no cambia; otros modos no se ven afectados.

   | Etapas del stat | Aporta al movimiento |
   |---|---|
   | +1 | 0 |
   | +4 | 0 |
   | +5 | 1 |
   | +6 | 2 |

5. **Progresión secuencial**: `current_floor` empieza en 1; solo se puede combatir el piso actual.
   Pisos futuros no se muestran; pisos ganados no se repiten.
6. **Derrota → cooldown de 1 hora** (`ReglasMazmorra::HORAS_COOLDOWN`) para ese piso; la derrota se
   registra en `dungeon_log` (`won=false`). Una derrota antigua (>1 h) no bloquea.
7. **Victoria → avance** al siguiente piso; al superar el último, `completed_at` y mazmorra
   completada.
8. **Anti-IDOR**: `RegistrarResultadoMazmorra` solo persiste si `userId === authUserId`.
9. **Recompensas (decisión 2026-10-31)**: la victoria otorga **7× la recompensa de un gimnasio**
   (multiplicador combinado 70.0 = 10.0 base de gimnasio × 7 factor de mazmorra) vía
   `OtorgarRecompensasMazmorra`, que reutiliza
   `OtorgarRecompensasEntrenador`; la derrota no otorga nada. El presentador devuelve el modal con
   `rewards` en la victoria.
10. **Estado de piso** devuelto como strings `'ganado' | 'disponible' | 'cooldown'`; candidato a
    enum (`EstadoPisoMazmorra`).

## Contratos y puntos de entrada

Rutas (`routes/mazmorras.php`, bajo `auth`):

```text
GET  /api/habitats/{habitat}/mazmorra
POST /api/habitats/{habitat}/mazmorra/{piso}/combatir
```

- `MazmorraController::pisos()` → `ObtenerPisosMazmorra`.
- `MazmorraController::combatir()` → `IniciarCombateMazmorra`.
- El fin de batalla se resuelve en `App\Livewire\Presenters\PresentadorResultadoBatalla`
  (`tipo='mazmorra'`) → `RegistrarResultadoMazmorra` y, en victoria,
  `OtorgarRecompensasMazmorra` (7× gimnasio).
- Tablas: `dungeon_progress` (`current_floor`, `completed_at`), `dungeon_log` (`floor`, `won`,
  `fought_at`), `habitats.mazmorra`.
- Metadatos de sesión: `tipo='mazmorra'`, `habitat_id`, `floor`, `boss_species_id`,
  `nivel_rival`, `user_id`, `team_id`.

## Dependencias

- `Src\Battle\Domain` — motor 3v1.
- `Src\CombateEntrenadores\App\ConstruirEquipoJugador` — equipo del jugador.
- `Src\CombateEntrenadores\App\MapeadorPokemonBatalla` — stats/tipos/movimientos del jefe.
- `Src\CombateEntrenadores\App\OtorgarRecompensasEntrenador` — reutilizado para las recompensas
  (multiplicador base de gimnasio × factor 7 de mazmorra).
- `App\Support\CreadorBatallaSesion` — creación/serialización de la batalla.
- `App\Models\Habitat`, `DungeonProgress`, `DungeonLog`, `Pokemon`, `Team`.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Resuelto 2026-10-31] Recompensas implementadas**: `OtorgarRecompensasMazmorra` aplica 7× la
  recompensa de gimnasio; cubierto por `PresentadorMazmorraTest` (victoria/derrota y factor 7).
- **[Media] Arrays inline de fases en `GeneradorJefeMazmorra`** (umbrales 0.75/0.50/0.25 y
  bonificadores). Extraer constantes/DTOs (`FaseJefe`) y mantener una sola fuente. La infraestructura
  de partes (`JefeEstado`/`ParteJefe`) se conserva sin uso para una habilidad futura, fuera de alcance.
- **[Media] Estados de piso como strings** (`'ganado'`, `'disponible'`, `'cooldown'`) en
  `ObtenerPisosMazmorra`/DTO. Introducir un enum y eliminar comparaciones mágicas.
- **[Resuelto 2026-10-31] La lectura de configuración se movió a `ObtenerConfiguracionMazmorra`**
  (usado por `IniciarCombateMazmorra`, `ObtenerPisosMazmorra` y el presentador de resultado).
- **[Resuelto 2026-10-31] Excepciones importadas con nombre corto** en `IniciarCombateMazmorra`.
- **Constantes ya centralizadas**: `ReglasMazmorra` (vida ×10, multiplicador ×3, 1 h, piso mínimo 1);
  no reintroducir literales.

## Guía de búsqueda (grep)

- Clases: `ConfiguracionMazmorra`, `ReglasMazmorra`, `GeneradorJefeMazmorra`,
  `ObtenerConfiguracionMazmorra`, `OtorgarRecompensasMazmorra`,
  `IniciarCombateMazmorra`, `ObtenerPisosMazmorra`, `RegistrarResultadoMazmorra`,
  `DungeonProgresoRepositoryInterface`, `PisoNoDisponible`, `PisoMazmorra`,
  `ResultadoPisosMazmorra`.
- Constantes: `ReglasMazmorra::MULTIPLICADOR_HP_JEFE`, `MULTIPLICADOR_STATS_EFECTIVO_JEFE`,
  `FACTOR_RECOMPENSA_RESPECTO_GIMNASIO`, `HORAS_COOLDOWN`, `PISO_MIN`.
- Términos: `multiplicadorStatsJefe`, `multiplicadorStats`, `statsJefe`,
  `bonificacionesPositivasParaDanio`, `bonificacionesPositivasSobreLimite`,
  `LIMITE_ETAPAS_BOOST_EXTRA`, `puntosDebiles`.
- Rutas/términos: `/api/habitats/{habitat}/mazmorra`, `combatir`, `piso`, `floor`, `boss`,
  `current_floor`, `cooldown`, `completed_at`.
- Tablas: `habitats.mazmorra`, `dungeon_progress`, `dungeon_log`.
- Tests: `tests/Feature/Mazmorras/MazmorraApiTest.php` (obsoleto: conserva el balance ×7/×10/+30 %;
  ver `docs/testing.md`) y tests del generador de jefe.
