# Mazmorras

Mazmorras por hábitat: pisos, jefe por piso, progreso y cooldown. Cada piso es un
combate 3v1 contra el jefe configurado.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/ConfiguracionMazmorra.php` | Configuración desde `habitats.mazmorra` (pisos + species_id) |
| `Domain/ReglasMazmorra.php` | Vida jefe ×10, multiplicador permanente ×3 (+200 %) no-etapa, recompensa 7× gimnasio, cooldown 1 h |
| `Domain/Collections/PisoConfiguracionCollection.php` | Pisos tipados (`PisoConfiguracion`) |
| `Domain/DataTransferObjects/` | `PisoMazmorra`, `ResultadoPisosMazmorra`, `ResultadoMazmorra`, `PisoConfiguracion` |
| `Domain/Collections/PisoMazmorraCollection.php` | Pisos con estado para la API |
| `Domain/Repositories/DungeonProgresoRepositoryInterface.php` | Progreso/cooldown por usuario |
| `Domain/Exceptions/PisoNoDisponible.php` | Piso bloqueado o no alcanzado |
| `App/ObtenerPisosMazmorra.php` | Lista de pisos alcanzables con estado |
| `App/IniciarCombateMazmorra.php` | Inicia el combate del piso |
| `App/GeneradorJefeMazmorra.php` | Genera el jefe (vida ×10, stats base ×1, `multiplicadorStatsJefe` ×3, sin `puntosDebiles`, IVs 0) |
| `App/ObtenerConfiguracionMazmorra.php` | Punto único de lectura de `habitats.mazmorra` |
| `App/RegistrarResultadoMazmorra.php` | Avance de piso |
| `App/OtorgarRecompensasMazmorra.php` | Recompensas de victoria (7× gimnasio) |
| `Infra/` | Repositorio Eloquent, `MazmorraController`, rutas |

## Flujo

```
GET  /api/mazmorras/{habitat}/pisos
POST /api/mazmorras/{habitat}/pisos/{piso}/combatir
```

`IniciarCombateMazmorra` construye el equipo del jugador con
`ConstruirEquipoJugador` y un rival de un solo combatiente
(`DatosPokemonBatallaCollection([$jefe])`).

## Reglas

- Jefe: vida ×10 sobre la vida base de especie y stats base ×1.
- Efecto permanente ×3 (+200 %) no-etapa en ataque, defensa, ataque/defensa especial y velocidad
  (`multiplicadorStatsJefe` → `BattleStats::multiplicadorStats`); las barreras también escalan ×3.
- Sin partes Núcleo/Coraza ni fallback implícito, y sin `bonusInicialStats` (sigue disponible para
  otros modos).
- Pisos futuros no se muestran hasta alcanzarlos.
- Piso actual en cooldown (<1 h desde la derrota) → estado `cooldown`.
- Piso ganado no se repite.
- Sin configuración de mazmorra → lista vacía.

## Tests

`tests/Feature/Mazmorras/` (`MazmorraApiTest`, progreso y combate) y tests del
generador de jefe. `MazmorraApiTest` conserva el balance anterior (×7/×10/+30 %) y
queda obsoleto (ver `docs/testing.md`); la suite no se ejecuta en este cambio.
