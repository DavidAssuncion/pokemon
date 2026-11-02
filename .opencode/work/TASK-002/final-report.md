# TASK-002 — Informe final

## Resumen

Se ha corregido el bug reportado de **Dragoaliento** (mensaje/animación de parálisis sin estado
aplicado) y se ha añadido una infraestructura de pruebas determinista para batallas:

- Contrato `aplicarEstado(): bool` en dominio.
- Log y evento `status` derivados del delta real de estado, no del movimiento declarado.
- Arnés IA vs IA determinista con tope de rondas y observador de invariantes.
- Dos baterías de calidad: efectos y movimientos/estados, con casos deterministas.
- Campaña de diagnóstico priorizada (61 escenarios) y barrido completo de claves (295 escenarios).
- Política de ejecución eficiente de tests documentada en `docs/testing.md`.

## Estado final

`COMPLETED`

- Plan v1 aprobado con el texto literal «Aprobar plan v1».
- Revisión independiente: `APPROVED`.
- QA final: `PASS`, sin regresiones de TASK-002.
- Los 2 fallos de `tests/Unit/Battle` son baseline preexistente y quedan documentados.

## Causa raíz y corrección

`app/Livewire/Combate.php` registraba «sufre X» de forma incondicional si el movimiento tenía
`statusEffect` y el objetivo seguía vivo, y forzaba el evento `status`. `aplicarEstado()` devolvía
`void`, así que la UI no podía saber si el sorteo del 30 % de Dragoaliento había fallado.

Corrección:
- `GestorEfectosMovimiento::aplicarEstado()` y `ServicioEjecucionBatalla::aplicarEstado()` devuelven
  `bool` (`true` solo si el estado se escribió en el objetivo declarado).
- `Combate.php` captura el estado antes/después, escribe el log solo si cambió `status` y registra
  el evento `status` sin `forzar`.
- Espejo Mágico se refleja correctamente: el atacante recibe el estado y el evento contiene su
  delta, sin mensaje falso al objetivo.

## Archivos

### Producción

- `app/Livewire/Combate.php`
- `src/Battle/Domain/AgregadoBatalla.php`
- `src/Battle/Domain/Danio/GestorEfectosMovimiento.php`
- `src/Battle/Domain/ServicioEjecucionBatalla.php`
- `src/Battle/Domain/Exceptions/LimiteRondasAlcanzado.php` (nuevo)
- `src/Battle/Domain/Observer/ObservadorEjecucionBatalla.php` (nuevo)

### Tests

- `tests/Unit/Battle/ServicioEjecucionBatallaTest.php`
- `tests/Unit/Battle/MovimientoEfectosTest.php`
- `tests/Support/Battle/EscenarioBatalla.php`
- `tests/Support/Battle/ConstructorBatallaHarness.php`
- `tests/Support/Battle/VerificadorInvariantes.php`
- `tests/Support/Battle/ObservadorInvariantes.php`
- `tests/Support/Battle/EjecutorBatallaHarness.php`
- `tests/Support/Battle/ResultadoBatallaHarness.php`
- `tests/Support/Battle/CatalogoEscenarios.php`
- `tests/Feature/Battle/EstadoAplicadoLivewireTest.php`
- `tests/Feature/Battle/HarnessIaVsIaTest.php`
- `tests/Feature/Battle/EfectosDeterministasTest.php`
- `tests/Feature/Battle/MovimientosEstadosDeterministasTest.php`
- `tests/Feature/Battle/CampanaDiagnosticoTest.php`

### Documentación

- `docs/testing.md` — sección 14 «Ejecución eficiente de tests (corrección y QA)».

### No tocado

- `src/Battle/Domain/{Combatiente,DatosPokemonBatalla,EquipoBatalla}.php` (WIP).
- WIP de Sendas/Equipos, Blade, rutas, configuración, dependencias, `.env`, migraciones.
- `SESSION_VERSION` no cambia.

## Decisiones y contratos

1. `aplicarEstado(): bool` en lugar de DTO de motivos: mínimo, suficiente y verificable.
2. La UI se guía por el delta real de `capturarEstado()`, no por `tieneStatus()` ni por el retorno
   del dominio. Cubre Espejo Mágico y Sincronía.
3. `ejecutarBatalla(?int $maxRondas = null, ?ObservadorEjecucionBatalla $observador = null)`:
   aditivo y retrocompatible. Sin argumentos conserva el comportamiento histórico.
4. `LimiteRondasAlcanzado` trunca la batalla sin declarar ganador.
5. El observador va por parámetro, no como propiedad: no altera la serialización ni la sesión.
6. Arnés IA vs IA como exploración; las baterías deterministas son la suite de calidad. Cada bug
   encontrado debe reducirse a un test determinista antes de corregirse.
7. Se excluyó la guarda de estado previo en `setEstado()` y la corrección de Velo Pastel; quedan
   como tareas separadas.

## Pruebas

| Comando | Resultado |
|---|---|
| `phpunit tests/Feature/Battle` | OK: 39 tests, 427 assertions, 2 skipped (campaña/barrido autogateados), 1,09 s |
| `phpunit tests/Unit/Battle tests/Feature/PokemonBattleTest.php tests/Feature/CombateLivewireTest.php` | 280 tests, 702 assertions, 2 fallos de baseline |
| `BATTLE_HARNESS_CAMPAIGN=1 phpunit --filter CampanaDiagnostico` | OK: 61 escenarios, 122 batallas, 9 estancadas, 0 violaciones seguras, 36 diagnósticas |
| `BATTLE_HARNESS_CAMPAIGN=1 BATTLE_HARNESS_FULL=1 phpunit --filter CampanaDiagnostico` | OK: priorizada 61/122; barrido completo 295 escenarios, 590 batallas, 0 excepciones, 0 seguras, 18 diagnósticas |
| `phpunit tests/Feature/Battle/HarnessIaVsIaTest.php` | OK: 6 tests, 101 assertions, 0,4 s |
| `pint --test <archivos TASK-002>` | pass |

Fallos de baseline:
- `CombatienteAvanzadoTest.php:176` — expected 100.0, actual 205.0.
- `ManejadorObjetosEquipadosTest.php:137` — expected 150.0, actual 195.00000000000003.

No son regresiones de TASK-002.

## Revisión y seguridad

- `reviewer`: `APPROVED` tras resolver los dos MEDIUM (campaña incompleta y 14 vs 20 batallas).
- `security`: `no aplica`; no se tocan autenticación, autorización, entradas, SQL, secretos ni
  endpoints.
- QA final: `PASS`; criterios 1-10 cumplidos.

## Hallazgos abiertos (fuera de alcance)

1. **Velo Pastel en secundarios**: `GestorEfectosMovimiento::aplicarEfectoSecundario()` no comprueba
   `EfectoEquipoVeloPastel`, aunque `aplicarEstado()` sí. Un secundario de veneno puede ignorarlo.
   Preexistente; requiere tarea y test determinista.
2. **Diagnósticos `#4`/`#12`**: estados (`paralysis`, `sleep`) y condiciones volátiles persisten tras
   debilitarse. El barrido completo reporta 18 casos. Es deuda de semántica del motor; decidir si es
   intencional o debe limpiarse.
3. **Sobrescritura de estados**: `Combatiente::setEstado()` no impide sustituir un estado mayor
   previo. Requiere regla de producto y tests.
4. **LOW de Livewire**: doble `capturarEstado()` por acción y posible evento `status` con texto vacío
   si solo cambian `statusTurns`.
5. **Baseline**: los 2 tests de `tests/Unit/Battle` siguen en rojo; no se corrigen aquí.

## Riesgos residuales

- La campaña genérica detecta excepciones e invariantes, pero no garantiza la semántica exacta de
  cada efecto; eso corresponde a las baterías deterministas.
- El arnés usa una clase anónima marcadora que depende de que `DatosPokemonBatalla` siga siendo
  extensible (WIP ajeno).
- El barrido completo es manual; no está en CI.
- El motor sigue sin un tope de rondas por defecto en producción; solo se aplica si el llamador lo
  pasa.

## Próximos pasos recomendados

1. Tarea para Velo Pastel + test determinista en `EfectosDeterministasTest`.
2. Tarea para decidir y aplicar la guarda de estados previos en `setEstado()`.
3. Tarea para la limpieza de estados/condiciones tras debilitarse (`#4`/`#12`) o documentar que es
   intencional.
4. Tarea para los 2 tests de baseline y para los LOW de Livewire.
5. Mantener la política de testing eficiente: solo el test que falla, trazas temporales y una única
   pasada de la suite relevante al cierre.

## Aprobación

- ID: TASK-002.
- Versión: plan v1.
- Texto literal: «Aprobar plan v1».
- Fecha: 2026-11-01.
- Alcance: bugfix Dragoaliento, tests de regresión, arnés IA vs IA, campaña de diagnóstico,
  revisión y QA.
