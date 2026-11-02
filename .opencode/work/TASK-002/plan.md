# TASK-002 — Plan v1

- **ID**: TASK-002
- **Versión**: v1
- **Estado**: `AWAITING_APPROVAL`
- **Fecha**: 2026-11-01
- **Proyecto**: `/home/david/pokemon`

## Objetivo

1. Corregir el bug reportado de **Dragoaliento**: el mensaje/animación de parálisis se muestra
   aunque el estado no se aplique.
2. Añadir un **arnés determinista IA vs IA** que ejecute batallas completas con efectos reales,
   compruebe invariantes y permita una campaña de diagnóstico para encontrar más bugs.
3. Dejar pruebas de regresión y evidencia reproducible.

## Alcance

### A. Bugfix Dragoaliento (obligatorio)

- `GestorEfectosMovimiento::aplicarEstado()` y `ServicioEjecucionBatalla::aplicarEstado()` pasan de
  `void` a `bool`, con `true` solo si el estado se escribió en el objetivo declarado.
- `app/Livewire/Combate.php` deja de mostrar el mensaje por `tieneStatus()` y deja de forzar el
  evento `status`; ambos se derivan del **delta real** de `capturarEstado()`.
- Tests unitarios y feature: éxito, fallo de probabilidad, inmunidad, protegido, cuerpo áureo,
  Espejo Mágico y evento sin cambio.

### B. Arnés IA vs IA (obligatorio)

- `AgregadoBatalla::ejecutarBatalla(?int $maxRondas = null, ?ObservadorEjecucionBatalla $observador = null)`.
- Excepción `LimiteRondasAlcanzado`; sin argumentos se conserva el comportamiento actual.
- Puerto `ObservadorEjecucionBatalla` para comprobar invariantes por turno/ronda.
- Helpers de test en `tests/Support/Battle/`: escenario, constructor determinista, verificador de
  invariantes, ejecutor y catálogo de escenarios.
- `tests/Feature/Battle/HarnessEfectosTest.php` (CI, 20 batallas, < 10 s) y
  `tests/Feature/Battle/CampanaDiagnosticoTest.php` (autogateado por `BATTLE_HARNESS_CAMPAIGN=1`).
- Campaña priorizada: barrido 1×1 de efectos de alto riesgo, pares de riesgo y seeds; el barrido
  completo de 289 claves queda como ejecución manual.

### C. Validación

- `reviewer` revisa el diff completo.
- `qa` ejecuta la suite y la campaña, clasifica fallos y adjunta evidencia.
- `security` no aplica: no se tocan autenticación, autorización, entradas, SQL, secretos ni
  endpoints.
- `documenter` no aplica: no se solicita documentación de proyecto; el ADR queda como borrador en
  `architecture.md`.

## Fuera de alcance

- Guardar la regla de «no sobrescribir un estado mayor previo» en `setEstado()`/servicio. Se
  registra como hallazgo y se abrirá tarea separada si el desarrollador lo aprueba.
- Corregir los defectos preexistentes de Espejo Mágico (log de reflejo aunque el atacante sea
  inmune; `cuerpo_aureo` solo en movimientos de estado).
- Corregir automáticamente los bugs que encuentre la campaña IA vs IA. La campaña produce un
  informe; cada corrección requiere su propio plan.
- Comando artisan de simulación masiva. La campaña vive como test autogateado.
- Tope de rondas en producción/`CombateExploracion`.
- Cambios en Blade, migraciones, base de datos, dependencias o `.env`.
- Tocar el WIP local en `src/Battle/Domain/{Combatiente,DatosPokemonBatalla,EquipoBatalla}.php`.

## Arquitectura y contratos

Se sigue `architecture.md` (TASK-002). Decisiones que se aprueban con este plan:

1. `aplicarEstado(): bool` en dominio, no un DTO de motivos.
2. Log y evento `status` derivados de `capturarEstado()`, no del `bool` ni de `forzar`.
3. Puerto `ObservadorEjecucionBatalla` inyectado por parámetro; no se serializa ni cambia
   `SESSION_VERSION`.
4. `maxRondas` opcional y `LimiteRondasAlcanzado`; sin argumentos, comportamiento actual.
5. Arnés como test de integración + campaña autogateada; sin comando artisan.
6. La guarda de estado previo queda fuera de esta tarea.

## Archivos

### Crear — `backend`

- `src/Battle/Domain/Exceptions/LimiteRondasAlcanzado.php`
- `src/Battle/Domain/Observer/ObservadorEjecucionBatalla.php`
- `tests/Support/Battle/EscenarioBatalla.php`
- `tests/Support/Battle/ConstructorBatallaHarness.php`
- `tests/Support/Battle/VerificadorInvariantes.php`
- `tests/Support/Battle/ObservadorInvariantes.php`
- `tests/Support/Battle/EjecutorBatallaHarness.php`
- `tests/Support/Battle/ResultadoBatallaHarness.php`
- `tests/Support/Battle/CatalogoEscenarios.php`
- `tests/Feature/Battle/HarnessEfectosTest.php`
- `tests/Feature/Battle/CampanaDiagnosticoTest.php`
- `tests/Feature/Battle/EstadoAplicadoLivewireTest.php`

### Modificar — `backend`

- `src/Battle/Domain/Danio/GestorEfectosMovimiento.php`
- `src/Battle/Domain/ServicioEjecucionBatalla.php`
- `src/Battle/Domain/AgregadoBatalla.php`
- `app/Livewire/Combate.php`
- `tests/Unit/Battle/ServicioEjecucionBatallaTest.php`
- `tests/Unit/Battle/MovimientoEfectosTest.php`

### No tocar

- `src/Battle/Domain/Combatiente.php` (WIP)
- `src/Battle/Domain/DatosPokemonBatalla.php` (WIP)
- `src/Battle/Domain/EquipoBatalla.php` (WIP)
- `resources/views/**`, `routes/**`, `config/**`, `composer.json`, `phpunit.xml`, `.env*`
- Cualquier archivo de `TASK-001` o WIP de Sendas/Equipos

## Orden de ejecución

1. **Backend A — bugfix**: contratos `bool`, call sites de Livewire, helper de delta, tests
   unitarios y feature de Dragoaliento.
2. **Backend B — arnés**: `maxRondas` + observador + excepción, helpers de test, escenarios y tests
   del arnés.
3. **Reviewer**: revisión independiente de A+B con criterios de aceptación.
4. **QA**: suite completa, `tests/Feature/Battle`, campaña priorizada y evidencia.
5. **Correcciones**: solo dentro del alcance aprobado si review/QA detectan defectos de esta tarea.
6. **Informe final**: hallazgos de la campaña, bugs abiertos y próximos pasos.

Dependencias: A y B comparten `AgregadoBatalla` solo en B; A no depende de B. La campaña de QA
depende de B. La revisión depende de A+B. No hay trabajo paralelo sobre archivos compartidos.

## Criterios de aceptación

1. `ServicioEjecucionBatalla::aplicarEstado()` y `GestorEfectosMovimiento::aplicarEstado()` declaran
   `: bool`; todas las ramas de no aplicación devuelven `false`.
2. Dragoaliento con probabilidad 0: sin línea «sufre parálisis», sin evento `status`, estado
   `NONE`.
3. Dragoaliento con probabilidad 1: línea «sufre parálisis», evento `status` con el delta del
   objetivo, estado `PARALYSIS`.
4. Espejo Mágico: el atacante queda paralizado, no hay mensaje «objetivo sufre», existe log de
   reflejo y el evento `status` incluye el delta del atacante.
5. `ejecutarBatalla()` sin argumentos mantiene el comportamiento actual.
6. `ejecutarBatalla(20)` sobre un 1v1 de Paz Mental lanza `LimiteRondasAlcanzado` en < 2 s.
7. El arnés CI ejecuta 20 batallas en < 10 s, sin fallos, y la misma semilla produce log y HP
   finales idénticos.
8. La campaña priorizada termina dentro de `timeout 300` y produce un informe con batallas
   ejecutadas, estancadas y violaciones agrupadas.
9. `vendor/bin/pint --dirty --format agent` sin cambios pendientes.
10. Los 2 fallos preexistentes (`CombatienteAvanzadoTest:176`, `ManejadorObjetosEquipadosTest:137`)
    se documentan como baseline; no se corrigen en esta tarea.

## Addendum de calidad (2026-11-01)

El desarrollador ha precisado el objetivo: no quiere una batería ruidosa de batallas aleatorias, sino
pruebas **consistentes y deterministas** que expliquen los fallos y no cambien nada más.

Se refuerza el plan con estas reglas, sin ampliar el alcance aprobado:

1. **Dos baterías principales, separadas por responsabilidad**:
   - `tests/Feature/Battle/EfectosDeterministasTest.php`: efectos concretos (objetos, habilidades,
     clima, terreno, pantallas, velos, inmunidades), cada caso con estado inicial explícito y
     resultado esperado exacto.
   - `tests/Feature/Battle/MovimientosEstadosDeterministasTest.php`: movimientos y estados
     (Dragoaliento, Onda Trueno, Fuego Fatuo, Tóxico, Espora, Confusión, congelación, sueño,
     parálisis, veneno), con `PerfilMovimiento(probabilidadEstado: 0.0|1.0)` y aserciones exactas
     de estado, log y evento.
2. **IA vs IA es exploración, no la suite de calidad**: `HarnessIaVsIaTest.php` solo comprueba
   determinismo, tope de rondas e invariantes seguras; `CampanaDiagnosticoTest.php` es autogateado y
   no corre en CI.
3. **Cada bug encontrado por la campaña debe reducirse a un test determinista** en una de las dos
   baterías antes de considerarse corregido. La campaña no sustituye a la regresión.
4. **Prohibido el ruido**: nada de `assertTrue(true)`, nada de depender de una tirada aleatoria sin
   fijar probabilidad o semilla, nada de tests que solo comprueben «no lanza excepción».
5. **Aislamiento**: cada test construye su propia batalla, fija IVs y semilla, y restaura
   `mt_srand()`; no escribe en BD, sesión, storage ni archivos del proyecto.
6. **Presupuesto CI**: la suite determinista debe ser rápida (< 15 s); la campaña queda fuera de CI.
7. **Criterio de aceptación adicional**: los tests nuevos deben fallar si se reintroduce el bug
   (verificado al menos para Dragoaliento) y pasar con la corrección.

## Pruebas requeridas

```bash
vendor/bin/phpunit tests/Unit/Battle/ServicioEjecucionBatallaTest.php --no-coverage
vendor/bin/phpunit tests/Unit/Battle/MovimientoEfectosTest.php --no-coverage
vendor/bin/phpunit tests/Feature/Battle/EstadoAplicadoLivewireTest.php --no-coverage
vendor/bin/phpunit tests/Feature/Battle/HarnessEfectosTest.php --no-coverage
vendor/bin/phpunit tests/Feature/CombateLivewireTest.php --no-coverage
vendor/bin/phpunit tests/Unit/Battle tests/Feature/PokemonBattleTest.php --no-coverage
vendor/bin/pint --dirty --format agent

# Campaña de diagnóstico (manual, no CI)
BATTLE_HARNESS_CAMPAIGN=1 BATTLE_HARNESS_SEEDS=2 timeout 300 \
  vendor/bin/phpunit --filter CampanaDiagnostico --no-coverage
```

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| `void → bool` rompe subclases/mocks | Verificado que no hay subclases; revisar antes de implementar |
| Arnés flaky por IVs `random_int` | IVs obligatorias en el constructor; test que lo verifica |
| `CalculadoraDanioIA` re-siembra MT | Test de doble ejecución con la misma semilla |
| Batalla infinita | `maxRondas` + `LimiteRondasAlcanzado` + timeout externo |
| Tocar WIP | Lista de archivos autorizados y exclusión explícita |
| Campaña lenta | Presupuesto por capas; barrido completo solo manual |
| Falsos positivos de invariantes | Invariantes 4, 8b, 10, 11 y 12 son diagnósticos, no rompen CI |
| Dos tests preexistentes en rojo | Baseline documentado; no confundir con regresiones |

## Rollback

Tres unidades independientes:
1. Contrato `bool` + Livewire: revertir restaura el mensaje actual sin afectar al arnés.
2. `maxRondas` + observador: revertir deja el bugfix intacto.
3. Arnés y tests: revertir no afecta producción.

## Aprobación requerida

Para pasar a `IMPLEMENTING` se necesita una respuesta explícita que incluya la versión, por ejemplo:

> APROBADO plan v1

o

> Implementa el plan v1

Si el desarrollador quiere un alcance distinto (solo el fix de Dragoaliento, sin arnés, o incluir
la guarda de estados), se responde «Solicitar cambios» y se genera `plan v2` con nueva aprobación.
