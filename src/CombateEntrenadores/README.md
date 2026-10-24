# CombateEntrenadores

Combate contra los entrenadores de un hábitat (3v3). Genera el equipo rival de forma
determinista por fecha/hábitat/nivel/entrenador, construye el equipo del jugador y
registra el resultado (recompensas + log diario).

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `App/IniciarCombateEntrenador.php` | Caso de uso de inicio: valida derrota diaria, forma equipos, crea batalla |
| `App/ConstruirEquipoJugador.php` | Equipo del jugador desde `Team` + `FormacionEquipo` + clasificación por stats |
| `App/GeneradorEquipoEntrenador.php` | Equipo rival determinista (semilla por fecha) |
| `App/MapeadorPokemonBatalla.php` | Modelo Eloquent → `DatosPokemonBatalla` (stats, tipos, movimientos, efectos) |
| `App/OtorgarRecompensasEntrenador.php` | Recompensas tras victoria |
| `App/RegistrarResultadoEntrenador.php` | Persistencia del resultado |
| `App/ObtenerEntrenadoresHabitat.php` | Listado de entrenadores disponibles |
| `Domain/ValueObjects/FormacionEquipo.php` | Formación por slot (`vanguardia`/`retaguardia`) |
| `Domain/DataTransferObjects/` | `EntrenadoresNivel`, `EstadoEntrenador`, `MovimientoSintetico`, `DatosModalVictoria` |
| `Domain/Collections/` | `EntrenadoresNivelCollection`, `EstadoEntrenadorCollection`, `MovimientosSinteticosCollection` |
| `Domain/GeneradorMovimientosTipo.php` | Fallback de movimientos por tipo |
| `Domain/Repositories/` + `Infra/` | `EntrenadorLogRepositoryInterface` y su implementación Eloquent |
| `Infra/Controllers/EntrenadorController.php` + `Infra/routes.php` | Endpoints `/api/entrenadores…` |

## Flujo

```
POST /api/entrenadores/{habitat}/{nivel}/{indice}/combatir
  → IniciarCombateEntrenador
      → ConstruirEquipoJugador::desdeEquipo(Team, FormacionEquipo, ?nivel)
      → GeneradorEquipoEntrenador::generar(...)  // DatosPokemonBatallaCollection
      → CreadorBatallaSesion::crearYGuardar(...)
```

## Contratos tipados

- `FormacionEquipo::desde(array)` reconstruye la formación validada del request.
- `MapeadorPokemonBatalla`: `movimientosDe*()` → `MovimientoBatallaCollection`,
  `efectosDe()` → `StringCollection`, `statsDe()` → `DatosStats`.
- `GeneradorEquipoEntrenador::generar()` → `DatosPokemonBatallaCollection`.
- `ConstruirEquipoJugador::desdeEquipo()` → `DatosPokemonBatallaCollection`.

## Tests

`tests/Feature/CombateEntrenadores/` (generador, construir equipo, flujo completo) y
`tests/Unit/…` para el mapeador/movimientos sintéticos.

## Notas

- Un entrenador solo puede derrotarse una vez al día (`EntrenadorDerrotadoHoy`).
- La posición se decide por `FormacionEquipo` del popup, luego la persistida en el
  `Team`, y si no hay, por stats (`ClasificadorOfensivaDefensiva`).
