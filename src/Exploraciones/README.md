# Exploraciones

Simula y resuelve expediciones: valida requisitos, genera eventos, estima riesgo y
recompensas, ejecuta combates y persiste el resultado.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `App/ProcesarExploracionCommand.php` + `ProcesarExploracionHandler.php` | Caso de uso principal: eventos, combates, capturas, paradas |
| `App/FinalizarExploracionCommand.php` + `FinalizarExploracionHandler.php` | Cierre: recompensas, derrotados, avistados, log |
| `App/CombateExploracion.php` | Adaptador de combate 1v1 para eventos |
| `App/Calculador*` / `Domain/Calculador*` | Capacidad, peligro, riesgo, matchups, recompensas, vuelta |
| `Domain/EvaluadorExploracion.php` | Reglas de elegibilidad, contratiempos y avance |
| `Domain/SimuladorEncuentros.php` | Generación de eventos y pool ponderado |
| `Domain/SinergiaEquipo.php` | Sinergias por combinación de roles |
| `Domain/Recompensas/` | `RecompensaCaptura`, `RecompensaFamilia`, `RecompensaEv`, `RecompensaTipo`, `ResultadoRecompensas` |
| `Domain/ValueObjects/` | `PoolHabitat`, `PokemonDelPool`, `ColeccionPoolPonderado`, `EventoExploracion`, `BitacoraExploracion`, `EstadoExplorador` |
| `Domain/Collections/` | `RolesExploracionCollection`, `RecompensaCapturaCollection` |
| `Presentation/` | `PresentadorExploraciones`, `TransformadorResultadoExploracion`, DTOs de incidentes/objetos |

## Puntos de entrada

- `ProcesarExploracionHandler` / `FinalizarExploracionHandler` (invocados por comandos del bus).
- `SimuladorEncuentros::generarEventosDesdePool(PoolHabitat, …)` → `ColeccionEventosExploracion`.
- `CalculadorRiesgo::evaluar()` y `CalculadorMatchups::calcular()` → preview de riesgo.
- `SinergiaEquipo::sinergiaPara(RolesExploracionCollection)` → `?ModificadoresSinergia`.
- `CapacidadesStats::todas()` → `MapaFactores` (combate, detección, recolección…).

## Reglas y convenciones

- El pool se pondera por `capture_rate / hatch`.
- Los cálculos son deterministas; el azar se inyecta como `callable(): float`.
- `ResultadoRecompensas` y el presentador son la frontera hacia la vista.
- Los wrappers con arrays de `SimuladorEncuentros` están `@deprecated` y solo existen
  por compatibilidad con tests; usa las variantes `*De(PoolHabitat|Coleccion…)`.

## Tests

`tests/Unit/Exploraciones/` (calculadores, simulador, sinergias, matchups) y
`tests/Feature/Exploraciones*` (endpoints, auto-resolución, presentador).

## Deuda conocida

`CalculadorRiesgo::evaluar()`, `CalculadorMatchups::calcular()`,
`CalculadorRecompensas::calcularHallazgos*()` y
`EvaluadorExploracion::resolverContratiempo()` todavía devuelven shapes de preview
(`array`). Los `PresentadorExploraciones::toActiva/toTerminada` son payloads de vista.
