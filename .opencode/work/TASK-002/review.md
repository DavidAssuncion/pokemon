# TASK-002 — Revisión

- **Revisor**: `reviewer`
- **Fecha**: 2026-11-01
- **Veredicto final**: `APPROVED` (re-revisión tras correcciones)
- **Veredicto inicial**: `CHANGES_REQUESTED`

## Resumen

El núcleo técnico está bien resuelto: el contrato `aplicarEstado(): bool` es correcto en todas las
ramas, Livewire deriva log y evento del delta real, y `maxRondas`/observador son retrocompatibles y
no alteran la sesión. Las baterías deterministas son de alta señal y no hay regresiones (solo los 2
fallos de baseline).

No se aprueba todavía porque la campaña de diagnóstico no implementa el alcance aprobado (solo
repite el catálogo de humo) y el arnés CI ejecuta 14 batallas frente a las 20 del criterio 7, con una
aserción que permite que el catálogo encoja sin fallar.

## Hallazgos

### MEDIUM

1. `tests/Feature/Battle/CampanaDiagnosticoTest.php:48-60` — La campaña no es la «priorizada» del
   plan. Itera únicamente `CatalogoEscenarios::humo()` (7 escenarios) por semillas. No existe el
   barrido 1×1 de efectos de alto riesgo, ni los 12 pares de riesgo de B.4, ni
   `test_barrido_completo_de_claves` prometido en C.5. Con `BATTLE_HARNESS_SEEDS=2` ejecuta 14
   batallas, las mismas que CI. Impacto: el objetivo de encontrar más bugs queda mermado.
2. `tests/Feature/Battle/HarnessIaVsIaTest.php:100` — El arnés CI ejecuta 14 batallas, no 20, y la
   aserción `assertGreaterThanOrEqual(14, $batallas)` no detecta una reducción futura. Criterio 7
   incumplido.

### LOW

- `app/Livewire/Combate.php:944` y `:1337` — Doble captura de estado por acción (tres recorridos
  completos de combatientes). Coste O(n) redundante y dos fuentes potenciales de divergencia.
- `app/Livewire/Combate.php:1422-1423` — El evento `status` puede emitirse con `texto` vacío si solo
  cambian `statusTurns`; la Blade no anima sin cambio de `status`, así que no hay mensaje falso.
- `tests/Support/Battle/VerificadorInvariantes.php:18,50` — Dos clases en un archivo
  (`ViolacionInvariante` y `VerificadorInvariantes`); frágil para autoload PSR-4.
- `tests/Support/Battle/ConstructorBatallaHarness.php:52` — Marcador de IVs mediante clase anónima
  que extiende `DatosPokemonBatalla`; acopla el arnés a que la clase siga siendo no-final y con
  constructor compatible.
- `src/Battle/Domain/AgregadoBatalla.php:737-739` — Sin test del contrato `maxRondas < 1`.
- Duplicación de helpers de batalla en tests (tres archivos con ~50 líneas casi idénticas).
- NIT: rama `default` de log puede producir «sufre ninguno» si el estado previo no mapea (hoy
  imposible); umbrales de tiempo de pared; `mt_srand(7)` innecesario en probabilidad 0.0;
  `HarnessIaVsIaTest` no coincide con el nombre `HarnessEfectosTest` del plan (aceptado por el
  addendum).

## Verificación de contratos

- **`aplicarEstado(): bool` — correcto.** `GestorEfectosMovimiento.php:39-74` devuelve `false` en
  todas las ramas de no aplicación y `true` solo tras escribir el estado. `ServicioEjecucionBatalla`
  añade `false` en cuerpo áureo, flags y Espejo Mágico; delega el resto. Ningún call site asume
  `void`.
- **Livewire — correcto en lo esencial.** La ruta normal usa `$previo` tras el evento de daño, aplica
  estado, captura `$actual`, escribe log por delta y emite `status` sin `forzar`. La ruta de
  prioridad no emite eventos y registra log por delta. El helper compara solo `status` y cubre
  NONE→X, X→NONE y X→Y. No hay duplicados ni mensajes forzados.
- **`maxRondas`/observador — correcto.** Firma con parámetros opcionales, validación, corte antes de
  `startNewRound`, `null` conserva el comportamiento histórico. Observador por parámetro, no
  serializado; `SESSION_VERSION` sigue en 20. `CombateExploracion` y `Fase3Test` siguen funcionando.

## Calidad de tests

- Deterministas: probabilidad 0.0/1.0, IVs explícitas, semillas fijas, `tearDown` con `mt_srand()` y
  reseed/restore en `finally`. Sin BD/storage; sesión solo en el feature Livewire.
- Alta señal: aserciones exactas de estado, HP, stat, cargas, PP y log. Sin `assertTrue(true)` ni
  tests que solo comprueben «no lanza excepción».
- El test de Dragoaliento fallaría con el bug reintroducido.
- Campaña autogateada correctamente.

## Hallazgo fuera de alcance: Velo Pastel

`GestorEfectosMovimiento::aplicarEfectoSecundario()` (`:232-244`) comprueba Velo Flor, Velo Dulce y
Safeguard, pero no `EfectoEquipoVeloPastel`, mientras `aplicarEstado()` sí lo hace (`:62-65`). Un
secundario que aplica veneno puede ignorar Velo Pastel. Es preexistente, no lo introduce TASK-002 y
el plan excluye corregir bugs hallados por la campaña. **No bloqueante; tarea separada.**

## Pruebas ejecutadas

- `vendor/bin/phpunit tests/Feature/Battle --no-coverage` → OK, 37 tests, 403 assertions, 1 skipped
  (campaña autogateada), ~1,0 s.
- `vendor/bin/phpunit tests/Unit/Battle tests/Feature/PokemonBattleTest.php
  tests/Feature/CombateLivewireTest.php --no-coverage` → 280 tests, 702 assertions, 2 fallos de
  baseline: `CombatienteAvanzadoTest.php:176` (205 vs 100) y
  `ManejadorObjetosEquipadosTest.php:137` (195 vs 150). Sin regresiones.
- `vendor/bin/pint --test <archivos TASK-002> --format agent` → pass.
- `BATTLE_HARNESS_CAMPAIGN=1 BATTLE_HARNESS_SEEDS=2 ... CampanaDiagnostico` → OK, 14 batallas, 2
  estancadas, 0,143 s, 0 violaciones seguras, 6 diagnósticas agrupadas en `#4` (parálisis conservada
  tras debilitarse).

## Criterios de aceptación

1. ✅ `: bool` y `false` en no aplicación.
2. ✅ Dragoaliento prob. 0: sin mensaje, sin evento, `NONE`.
3. ✅ Dragoaliento prob. 1: mensaje, evento con delta, `PARALYSIS`.
4. ✅ Espejo Mágico: atacante paralizado, sin mensaje al objetivo, log de reflejo, evento con delta.
5. ✅ `ejecutarBatalla()` sin argumentos mantiene comportamiento.
6. ✅ Tope de rondas en < 2 s.
7. ⚠️ Parcial: determinismo y < 10 s OK; 14-17 batallas frente a 20.
8. ⚠️ Parcial: informe correcto y rápido; no es el barrido priorizado ni el completo prometidos.
9. ✅ Pint sin cambios.
10. ✅ Baseline documentado.

## Riesgos residuales

- Cobertura diagnóstica menor de la aprobada.
- Defectos preexistentes de Espejo Mágico documentados (log de reflejo aunque el atacante sea inmune;
  `cuerpo_aureo` solo en movimientos de estado).
- Sobrescritura de estados en `setEstado()` diferida.
- Acoplamiento del arnés a la extensibilidad de `DatosPokemonBatalla`.

## Correcciones solicitadas

1. Ampliar `CatalogoEscenarios` con barrido priorizado, pares de riesgo y barrido completo
   autogateado; hacer que `CampanaDiagnosticoTest` los ejecute.
2. Añadir 3 escenarios de humo (10 total) y aseverar exactamente 20 batallas en CI; añadir test de
   `maxRondas < 1`.
3. Revisar de nuevo tras las correcciones.

## Re-revisión (2026-11-01) — APPROVED

Los dos MEDIUM quedaron resueltos:

- `CatalogoEscenarios`: 10 humo, 39 priorizados, 12 pares de riesgo y 295 escenarios de barrido
  completo (289 claves + 6 items).
- `CampanaDiagnosticoTest`: ejecuta humo + priorizados + pares; barrido completo autogateado por
  `BATTLE_HARNESS_FULL=1`.
- `HarnessIaVsIaTest`: 20 batallas exactas (`assertSame`) y test de `maxRondas < 1`.

Evidencia verificada: harness 6 tests/101 assertions; campaña priorizada 61 escenarios/122 batallas,
0 violaciones seguras, 36 diagnósticas; barrido completo 295 escenarios/590 batallas, 0 excepciones,
0 violaciones seguras, 18 diagnósticas. Pint pass.

### LOW recomendados y aplicados después de la aprobación (solo tests)

- `niebla` → `niebla_summoner` en el escenario de clima.
- Aserción exacta de 39 escenarios priorizados.
- El barrido completo ahora falla si hay violaciones seguras o excepciones.

Verificado con la campaña priorizada y el barrido completo; sin cambios de producción.

### Deuda abierta (fuera de alcance)

- `#4`/`#12`: estados/condiciones que persisten tras debilitarse (diagnóstico, 18 casos).
- `aplicarSecundarios()` no comprueba `EfectoEquipoVeloPastel` (bug preexistente).
- Sobrescritura de estados en `setEstado()`.
- LOW de Livewire (doble captura, evento `status` con texto vacío) y de infraestructura de tests.
