# Mazmorras

Mazmorras por hábitat: pisos, jefe por piso, progreso y cooldown. Cada piso es un
combate 3v1 contra el jefe configurado.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/ConfiguracionMazmorra.php` | Configuración desde `habitats.mazmorra` (pisos + species_id) |
| `Domain/Collections/PisoConfiguracionCollection.php` | Pisos tipados (`PisoConfiguracion`) |
| `Domain/DataTransferObjects/` | `PisoMazmorra`, `ResultadoPisosMazmorra`, `ResultadoMazmorra`, `PisoConfiguracion` |
| `Domain/Collections/PisoMazmorraCollection.php` | Pisos con estado para la API |
| `Domain/Repositories/DungeonProgresoRepositoryInterface.php` | Progreso/cooldown por usuario |
| `Domain/Exceptions/PisoNoDisponible.php` | Piso bloqueado o no alcanzado |
| `App/ObtenerPisosMazmorra.php` | Lista de pisos alcanzables con estado |
| `App/IniciarCombateMazmorra.php` | Inicia el combate del piso |
| `App/GeneradorJefeMazmorra.php` | Genera el jefe (stats ×10, nivel del jugador) |
| `App/RegistrarResultadoMazmorra.php` | Avance de piso y recompensas |
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

- Pisos futuros no se muestran hasta alcanzarlos.
- Piso actual en cooldown (<1 h desde la derrota) → estado `cooldown`.
- Piso ganado no se repite.
- Sin configuración de mazmorra → lista vacía.

## Tests

`tests/Feature/Mazmorras/` (`MazmorraApiTest`, progreso y combate) y tests del
generador de jefe.
