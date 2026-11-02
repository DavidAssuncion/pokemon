# Contexto del módulo `Mazmorras`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Mazmorras por hábitat: una secuencia de pisos, cada uno con un jefe configurado. Cada piso es un
combate **3v1** (equipo del jugador de 3 vs un jefe con stats multiplicados). El progreso es
secuencial y la derrota impone un cooldown.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/ConfiguracionMazmorra.php` | Configuración desde `habitats.mazmorra` (pisos + `species_id`). |
| `Domain/ReglasMazmorra.php` | Constantes: vida jefe ×7, resto de stats ×10, bonus inicial +30 %, recompensa 7× gimnasio, cooldown 1 h, piso mínimo 1. |
| `Domain/Collections/PisoConfiguracionCollection.php` | Pisos tipados (`PisoConfiguracion`). |
| `Domain/DataTransferObjects/` | `PisoMazmorra`, `ResultadoPisosMazmorra`, `ResultadoMazmorra`, `PisoConfiguracion`. |
| `Domain/Collections/PisoMazmorraCollection.php` | Pisos con estado para la API. |
| `Domain/Repositories/DungeonProgresoRepositoryInterface.php` | Progreso/cooldown por usuario. |
| `Domain/Exceptions/PisoNoDisponible.php` | Piso bloqueado, inexistente o en cooldown. |
| `App/ObtenerPisosMazmorra.php` | Lista de pisos alcanzables con estado. |
| `App/IniciarCombateMazmorra.php` | Inicia el combate del piso y guarda la batalla en sesión. |
| `App/GeneradorJefeMazmorra.php` | Genera el jefe (vida ×7, resto ×10, +30 % inicial, IVs 0 deterministas). |
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
3. **Jefe: vida ×7 y resto de stats ×10** (`MULTIPLICADOR_HP_JEFE` y `MULTIPLICADOR_STATS_JEFE`),
   nivel = nivel del jugador al iniciar, **+30 % inicial a todas las estadísticas**
   (`BONUS_INICIAL_STATS_JEFE`) y **IVs 0 (determinista)**. El bonus se aplica en la capa base de
   `MultiplicadoresStats` al construir el combate y persiste en sesión. El jefe lleva `esJefe=true`,
   fases y puntos débiles para la UI de combate.
4. **Progresión secuencial**: `current_floor` empieza en 1; solo se puede combatir el piso actual.
   Pisos futuros no se muestran; pisos ganados no se repiten.
5. **Derrota → cooldown de 1 hora** (`ReglasMazmorra::HORAS_COOLDOWN`) para ese piso; la derrota se
   registra en `dungeon_log` (`won=false`). Una derrota antigua (>1 h) no bloquea.
6. **Victoria → avance** al siguiente piso; al superar el último, `completed_at` y mazmorra
   completada.
7. **Anti-IDOR**: `RegistrarResultadoMazmorra` solo persiste si `userId === authUserId`.
8. **Recompensas (decisión 2026-10-31)**: la victoria otorga **7× la recompensa de un gimnasio**
   (multiplicador combinado 70.0 = 10.0 × 7) vía `OtorgarRecompensasMazmorra`, que reutiliza
   `OtorgarRecompensasEntrenador`; la derrota no otorga nada. El presentador devuelve el modal con
   `rewards` en la victoria.
9. **Estado de piso** devuelto como strings `'ganado' | 'disponible' | 'cooldown'`; candidato a
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
- **[Media] Arrays inline de fases/puntos débiles** en `GeneradorJefeMazmorra` (umbrales 0.75/0.50/
  0.25, bonificadores y puntos débiles). Extraer constantes/DTOs (`FaseJefe`, `PuntoDebil`) y
  mantener una sola fuente.
- **[Media] Estados de piso como strings** (`'ganado'`, `'disponible'`, `'cooldown'`) en
  `ObtenerPisosMazmorra`/DTO. Introducir un enum y eliminar comparaciones mágicas.
- **[Resuelto 2026-10-31] La lectura de configuración se movió a `ObtenerConfiguracionMazmorra`**
  (usado por `IniciarCombateMazmorra`, `ObtenerPisosMazmorra` y el presentador de resultado).
- **[Resuelto 2026-10-31] Excepciones importadas con nombre corto** en `IniciarCombateMazmorra`.
- **Constantes ya centralizadas**: `ReglasMazmorra` (×10, 1 h, piso mínimo 1); no reintroducir
  literales.

## Guía de búsqueda (grep)

- Clases: `ConfiguracionMazmorra`, `ReglasMazmorra`, `GeneradorJefeMazmorra`,
  `ObtenerConfiguracionMazmorra`, `OtorgarRecompensasMazmorra`,
  `IniciarCombateMazmorra`, `ObtenerPisosMazmorra`, `RegistrarResultadoMazmorra`,
  `DungeonProgresoRepositoryInterface`, `PisoNoDisponible`, `PisoMazmorra`,
  `ResultadoPisosMazmorra`.
- Constantes: `ReglasMazmorra::MULTIPLICADOR_STATS_JEFE`, `MULTIPLICADOR_HP_JEFE`,
  `BONUS_INICIAL_STATS_JEFE`, `FACTOR_RECOMPENSA_RESPECTO_GIMNASIO`, `HORAS_COOLDOWN`, `PISO_MIN`.
- Rutas/términos: `/api/habitats/{habitat}/mazmorra`, `combatir`, `piso`, `floor`, `boss`,
  `current_floor`, `cooldown`, `completed_at`.
- Tablas: `habitats.mazmorra`, `dungeon_progress`, `dungeon_log`.
- Tests: `tests/Feature/Mazmorras/MazmorraApiTest.php` y tests del generador de jefe.
