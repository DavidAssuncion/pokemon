# Exploraciones

Simula y resuelve **expediciones individuales**: valida requisitos, genera eventos, estima riesgo y
recompensas, ejecuta combates 1v1 y persiste el resultado. Cada expedición la realiza **un único
reclutado** (`reclutado_id`); no hay equipo de expedición.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `App/ProcesarExploracionCommand.php` + `ProcesarExploracionHandler.php` | Tick principal: eventos, combates, capturas, paradas, descanso |
| `App/FinalizarExploracionCommand.php` + `FinalizarExploracionHandler.php` | Cierre: recompensas, derrotados, avistados, objetos perdidos, bitácora |
| `App/ProcesarExploracionesPendientes.php` | Procesamiento bajo demanda (`paraUsuario`, `paraTodas`, `una`) con throttle y lock |
| `App/ResultadoProcesamientoPendiente.php` | Resumen tipado del procesamiento |
| `App/CombateExploracion.php` | Adaptador de combate 1v1 para eventos |
| `App/PersistirRecompensas.php` | Persistencia de caramelos, capturas y EXP en la cuenta |
| `App/NormalizadorPokemonDerrotado.php` | Normalización de derrotados por fase evolutiva |
| `App/FabricaCapacidadesStats.php` | Construcción de capacidades del explorador |
| `Domain/EconomiaRecompensas.php` | Política económica (`individual()` 0.5 y reparto 50/50; `legado()` 1.0 y 80 %/3) |
| `Domain/CalculadorFinExploracion.php` | Fin por `hora_limite` (siguiente ocurrencia) o `duracion_horas` |
| `Domain/Calculador*` | Capacidad, peligro, riesgo, matchups, recompensas, vuelta |
| `Domain/EstimadorRecompensasExploracion.php` | Estimación del preview; aplica el factor 0.5 |
| `Domain/EvaluadorExploracion.php` | Dificultad, resultados de combate, contratiempos, categoría final |
| `Domain/SimuladorEncuentros.php` | Generación de eventos y pool ponderado |
| `Domain/SinergiaEquipo.php` | Sinergias por roles (legado de exploración por equipo) |
| `Domain/ReglasExploracion.php` | Constantes: `MINUTOS_POR_ENCUENTRO`, `HP_POR_MINUTO_DESCANSO`, `FRACCION_EXP_REPARTO`, `FACTOR_RECOMPENSAS`, `FRACCION_EXP_INDIVIDUAL` |
| `Domain/Recompensas/` | `RecompensaCaptura`, `RecompensaFamilia`, `RecompensaEv`, `RecompensaTipo`, `ResultadoRecompensas`, `PokemonDerrotado` |
| `Domain/ValueObjects/` | `PoolHabitat`, `PokemonDelPool`, `ColeccionPoolPonderado`, `EventoExploracion`, `BitacoraExploracion`, `EstadoExplorador`, etc. |
| `Domain/DataTransferObjects/` | `ResolucionContratiempo`, `ResolucionEncuentro`, `ResolucionEmboscada`, `Matchup`, `EvaluacionRiesgo`, `HallazgosCalculados` |
| `Domain/Collections/` | `MatchupCollection`, `RecompensaCapturaCollection`, `RolesExploracionCollection` |
| `Presentation/PresentadorExploraciones.php` | Payloads de vista (`toActiva`/`toTerminada`), mapa de nombres y enriquecimiento de `objetos_perdidos` |
| `Presentation/TransformadorResultadoExploracion.php` + `ResultadoExploracionVista.php` | Shape de resultados para `/exploraciones` |
| `Presentation/ExploradorVista.php` | Ficha del explorador (nivel, EXP, rol, tipos, HP, ánimo, progreso); nombre con fallback al Pokémon |
| `Presentation/DiarioExploracion.php` | Bitácora + raíces (`retirada`, `derrota`, `cancelada`), agrupada por fecha; filtra `oculto` |
| `Presentation/EventoExploracionVista.php` | Contrato del diario: narrativa, caramelos, PV de derrota y `reason_texto` |
| `Presentation/HitoExploracion.php`, `FraseAnimo.php` | Hitos y textos de ánimo |
| `Presentation/DTOIncidentesExploracion.php`, `DTOObjetoPerdido.php` | DTOs de incidentes y objetos perdidos |
| `resources/views/exploraciones/_perdidas.blade.php` | Partial «Recompensas perdidas» (solo tarjeta de resultados) |

## Puntos de entrada

- **HTTP** (`routes/exploraciones.php`, bajo `auth`):
  - `GET /exploraciones` — procesa las activas del usuario (`paraUsuario`) antes de renderizar.
  - `GET /exploraciones/preview` — estimación con `economia` y `aviso_economia`.
  - `POST /exploraciones` — alta clásica.
  - `POST /api/exploraciones/store-individual` — alta JSON, **201** `{ok, id, inicio, fin, estado}`.
  - `POST /exploraciones/{exploracion}/recoger` — `una(..., forzarRegreso: true)`; **409** o redirect
    si el lock está ocupado.
  - `POST /exploraciones/{exploracion}/cancelar`, `POST /exploraciones/{exploracion}/cerrar`.
- **Consola**: `php artisan exploraciones:procesar` → `ProcesarExploracionesPendientes::paraTodas(500)`,
  programado cada 5 minutos en `routes/console.php`.
- **Dominio**: `SimuladorEncuentros::generarEventosDesdePool(...)`,
  `CalculadorFinExploracion::calcular(...)`, `EconomiaRecompensas::individual()`.

## Reglas y convenciones

- Economía individual: factor **0.5** sobre EXP, caramelos y probabilidad de captura; EXP
  `expCuenta = intdiv(pool, 2)` / `expReclutado = pool - expCuenta` (invariante).
- Caramelos y capturas van a la cuenta; los reclutados no tienen inventario.
- El factor se aplica **una sola vez** en dominio; persistencia y presentación no descuentan otra vez.
- Solo se bloquea al reclutado que explora; el equipo sigue editable.
- El pool se pondera por `capture_rate / hatch`.
- Los cálculos son deterministas; el azar se inyecta como `callable(): float`.
- Los wrappers con arrays de `SimuladorEncuentros` están `@deprecated`; usa las variantes
  `*De(PoolHabitat|Coleccion…)`.
- El diario oculta los eventos irresolubles (`oculto=true`). El detalle del contrato (caramelos,
  PV de combate, derrota y objetos perdidos) vive en
  [`context.md#contrato-del-diario-task-005`](./context.md#contrato-del-diario-task-005).

## Tests

`tests/Unit/Exploraciones/` (economía, calculadores, simulador, sinergias, matchups, evaluador) y
`tests/Feature/Exploraciones*` (endpoints, auto-resolución, presentador).

Constantes canónicas: `MINUTOS_POR_ENCUENTRO = 30` y `HP_POR_MINUTO_DESCANSO = 1`. Suite relevante
en verde: **416 tests / 2555 aserciones** (TASK-005; última corrida, incluye cambios concurrentes
ajenos a TASK-005). PHPStan sin errores en los archivos de TASK-005 (quedan 8 errores
preexistentes en `SendasController`, WIP ajeno); Pint pass.

## Deuda conocida

- `ProcesarExploracionHandler` es un god-handler.
- Preview duplicado: falta un caso de uso `PreviewExploracion` único.
- Idempotencia débil fuera del lock de 60 s; falta `withoutOverlapping()` en consola.
- Alias deprecados: `equipo` en payloads y `equiposEnExploracion` en `PlayerController`/
  `HabitatsController` (usar `reclutadosEnExploracion`).
- Columna `llegada_destino` deprecada, sin escritores.
- `hora_limite` se lee con `getRawOriginal`; falta cast.
- `Presentation/` sigue devolviendo arrays en la frontera de vista.
