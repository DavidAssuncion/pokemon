# Battle

Motor de combate por turnos 3v3. Es el módulo central del juego: resuelve turnos,
daño, efectos de habilidades/objetos, clima, posiciones (vanguardia/retaguardia),
jefes y la IA rival.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/AgregadoBatalla.php` | Aggregate root: equipos, clima, terreno, log, turnos, ejecución de acciones |
| `Domain/EquipoBatalla.php` | Equipo de combatientes + formación; `fromData(DatosPokemonBatallaCollection)` |
| `Domain/Combatiente.php` | Entidad de combate: HP, stats, condiciones, efectos, objeto, jefe |
| `Domain/ServicioEjecucionBatalla.php` | Orquesta cálculo + aplicación de daño y estados |
| `Domain/GestorTurnos.php` | Orden de turnos, prioridad, velocidad efectiva, acciones pendientes |
| `Domain/Chain/` | Chain of Responsibility del daño (STAB, tipos, clima, crítico, objetos, habilidades…) |
| `Domain/Effects/` | Catálogo de efectos: `Capacidades/` (interfaces), `Movimiento/`, `Equipo/`, `FabricaEfectos` |
| `Domain/Danio/` | Aplicación de daño, objetivos múltiples, postura, vínculo, efectos de movimiento |
| `Domain/AI/` | Analizador de amenazas, evaluador de acciones, simulador, selector y memoria |
| `Domain/ValueObjects/` | `BattleLog`, `ResultadoDanio`, `EstadoMovimientos`, `JefeEstado`, `AjusteGolpes`, etc. |
| `Domain/Collections/` | `CombatientesCollection`, `MovimientoBatallaCollection`, `DatosPokemonBatallaCollection`, `PartesJefeCollection` |
| `Domain/Catalog/` | `CatalogoHabilidades`, `CatalogoObjetos`, `NormalizadorNombre` |
| `Domain/Enums/` | `ClaveCondicion`, `ClaveEfecto`, `ClaveItem`, `StatClave`, `TipoClima`, `Posicion`… |
| `Domain/Observer/` | Observer de eventos de batalla |
| `Domain/Repositories/` | Contrato `MovimientoRepository` |
| `Infra/` | `MovimientoFactory`, `EloquentMovimientoRepository` |
| `Infrastructure/FabricaBatallaMock.php` | Mock de fábrica para tests |
| `Presentation/` | DTOs de acción/movimiento/resultado para Livewire |

## Puntos de entrada

- `AgregadoBatalla::ejecutarTurno()` / `turnManager()` / `log()`.
- `ServicioEjecucionBatalla::calcularYAplicarDano()` y `aplicarEstadoYStatChanges()`.
- `FabricaEfectos::crearEfectos()` / `crearItem()` para instanciar efectos por clave.
- `FabricaBatallaInterface` (`createBattle`) implementada por `FabricaBatallaMock`.

## Cómo añadir un efecto

1. Crea la clase en `Domain/Effects/` (o subcarpeta) implementando `InterfazEfecto` y,
   si aplica, una capacidad de `Effects/Capacidades/`.
2. Regístrala en `FabricaEfectos` (`registrarEfecto`/`registrarItem`).
3. Añade su clave en el enum correspondiente (`ClaveEfecto`/`ClaveItem`).
4. Cubre el comportamiento con un test en `tests/Unit/Battle/` o `tests/Feature/`.

## Contratos tipados clave

- `DatosPokemonBatallaCollection` → entrada de equipos.
- `CombatientesCollection` → vivos, vanguardia, retaguardia, búsqueda por índice.
- `AjusteGolpes` / `ResultadoSustituto` / `ResultadoDanio` → resultados de reglas.
- `MapaConteo` y `MapaFactores` → mapas de cargas/usos y multiplicadores.
- `EvaluacionAmenazaCollection` → salida del analizador de IA.

## Tests

`tests/Unit/Battle/` (reglas, cadena, efectos, IA) y `tests/Feature/PokemonBattleTest.php`,
`tests/Feature/CombateLivewireTest.php`. La serialización de sesión se prueba en
`CombatienteAvanzadoTest`.

## Frontera tolerada

`Combatiente::aArrayVista()`, `CombatienteVista::para()` y `BattleLog::toArray()` son
payloads de vista/serialización. `DatosPokemonBatalla::tipos`/`moves` y
`DTOResultadoDanio` conservan arrays por compatibilidad con el motor y Livewire.
