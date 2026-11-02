# TASK-002 — QA

## Estado

`PENDIENTE` — tarea en pausa temporal solicitada por el desarrollador (2026-11-01).

## Política de ejecución eficiente de tests (directriz del desarrollador)

> «lanzar unicamente los test que fallen, hay que añadir trazas para entender el problema y luego
> quitarlas tras solucionarlo, no podemos quemar tokens»

Reglas operativas para la fase de corrección y QA:

1. **Durante la corrección, ejecutar solo el test que falla**, no la suite completa:
   ```bash
   vendor/bin/phpunit --filter 'nombre_del_test' ruta/al/Test.php --no-coverage
   ```
   Si hay varios fallos, empezar por uno y usar `--stop-on-failure` cuando aplique.
2. **Añadir trazas temporales** en el punto exacto del fallo (log de dominio, `fwrite(STDERR, ...)`
   o `Log::debug`) para entender el estado real. Las trazas deben ser mínimas y no incluir secretos,
   datos personales ni volcados masivos.
3. **Reproducir con un solo caso**: una semilla, un escenario, un movimiento. No lanzar la campaña
   completa ni barridos de 289 claves para depurar un fallo concreto.
4. **Quitar las trazas tras corregir** y verificar que no quedan restos:
   ```bash
   git diff -- <archivos tocados> | grep -nE 'var_dump|dump\(|fwrite\(STDERR|Log::debug|error_log'
   ```
5. **Validación mínima tras el fix**: ejecutar el test que fallaba y los tests directamente
   relacionados. Solo al cerrar la tarea, una única pasada de la suite relevante:
   ```bash
   vendor/bin/phpunit tests/Feature/Battle --no-coverage
   vendor/bin/phpunit tests/Unit/Battle tests/Feature/PokemonBattleTest.php --no-coverage
   ```
6. **No repetir suites verdes** ni lanzar la suite completa del proyecto por cada cambio. La campaña
   `BATTLE_HARNESS_CAMPAIGN=1` y el barrido completo `BATTLE_HARNESS_FULL=1` son manuales y solo se
   ejecutan cuando se piden o al cierre de una fase de diagnóstico.
7. **Salida mínima**: usar `tail`, `--display-errors`, `--no-coverage` y filtros; evitar `-v`, dumps
   de arrays completos y ejecuciones concurrentes innecesarias.
8. **Criterio de parada**: si un fallo no se reproduce con el test aislado, no encadenar suites;
   primero aislar el caso con una traza y un escenario mínimo.

## Comandos de referencia

```bash
# Un solo test
vendor/bin/phpunit --filter 'test_dragoaliento_que_falla_probabilidad' \
  tests/Feature/Battle/EstadoAplicadoLivewireTest.php --no-coverage

# Un archivo concreto, parando en el primer fallo
vendor/bin/phpunit tests/Feature/Battle/HarnessIaVsIaTest.php --no-coverage --stop-on-failure

# Suite relevante al cierre (no en cada iteración)
vendor/bin/phpunit tests/Feature/Battle --no-coverage
vendor/bin/phpunit tests/Unit/Battle tests/Feature/PokemonBattleTest.php --no-coverage

# Diagnóstico manual, solo si se solicita
BATTLE_HARNESS_CAMPAIGN=1 vendor/bin/phpunit --filter CampanaDiagnostico --no-coverage
BATTLE_HARNESS_CAMPAIGN=1 BATTLE_HARNESS_FULL=1 \
  vendor/bin/phpunit --filter CampanaDiagnostico --no-coverage
```

## Registro de ejecuciones

| Fecha | Comando | Resultado | Notas |
|---|---|---|---|
| 2026-11-01 | `tests/Feature/Battle` | 37 tests OK, 1 skipped (campaña) | Suite nueva del arnés |
| 2026-11-01 | `tests/Unit/Battle` + `PokemonBattleTest` + `CombateLivewireTest` | 280 tests, 2 fallos de baseline | Sin regresiones de TASK-002 |
| 2026-11-01 | Campaña `BATTLE_HARNESS_CAMPAIGN=1` | 14 batallas, 0 violaciones seguras | Pendiente ampliar campaña |

## Resultados QA final (2026-11-01)

- **Veredicto**: `PASS`.
- `vendor/bin/phpunit tests/Feature/Battle --no-coverage` → **OK**, 39 tests, 427 assertions,
  2 skipped (campaña y barrido autogateados), 1,09 s.
- `vendor/bin/phpunit tests/Unit/Battle tests/Feature/PokemonBattleTest.php
  tests/Feature/CombateLivewireTest.php --no-coverage` → **280 tests, 702 assertions, 2 fallos de
  baseline**:
  - `CombatienteAvanzadoTest.php:176` — expected 100.0, actual 205.0.
  - `ManejadorObjetosEquipadosTest.php:137` — expected 150.0, actual 195.00000000000003.
  - Clasificación: preexistentes, no regresiones de TASK-002.
- `BATTLE_HARNESS_CAMPAIGN=1 ... CampanaDiagnostico` → **OK**, 61 escenarios, 122 batallas,
  9 estancadas, 0 violaciones seguras, 36 diagnósticas (`#4`), 0,8 s.
- `BATTLE_HARNESS_CAMPAIGN=1 BATTLE_HARNESS_FULL=1 ... CampanaDiagnostico` → **OK**, priorizada
  61/122; barrido completo 295 escenarios, 590 batallas, 0 estancadas, 0 excepciones,
  0 violaciones seguras, 18 diagnósticas (`#12` ×12, `#4` ×6), 3,7 s.
- `vendor/bin/pint --test <archivos TASK-002>` → **pass**.
- Criterio 6: el test automatizado se ajustó para usar literalmente `maxRondas = 20`; el corte se
  produce en 0,19 s y se aseveran 20 rondas.
- Criterio 7: 20 batallas exactas en CI (`assertSame`), misma semilla ⇒ mismo log/HP, < 10 s.
- Criterio 8: campaña priorizada y barrido completo con informe y aserciones de 0 seguras /
  0 excepciones.

### Cobertura observada

- Contrato `bool`: éxito, fallo de probabilidad, muerto, protegido, cuerpo áureo, habilidad, flag y
  Espejo Mágico.
- Dragoaliento: estado, log y evento en dominio y Livewire (prob. 0 y 1), incluido «sin evento sin
  cambio».
- Arnés: determinismo, tope de rondas, IVs explícitas obligatorias, `maxRondas < 1`, matriz de 20.
- Campaña: 61 escenarios priorizados y 295 de barrido completo.

### Riesgos residuales

- Diagnósticos abiertos `#4`/`#12` (estados/condiciones que persisten tras debilitarse).
- Bug preexistente de Velo Pastel en `aplicarSecundarios()`.
- Deuda de `setEstado()` (sobrescritura de estados).
- LOW de Livewire: doble captura de estado y evento `status` con texto vacío si solo cambian turnos.
- 2 tests de baseline en rojo.

## Traslado a `docs/testing.md`

Realizado por `documenter` el 2026-11-01: se añadió la sección
`## 14 · Ejecución eficiente de tests (corrección y QA)` al final de `docs/testing.md`, preservando
el contenido previo y el WIP local. Incluye la directriz de ejecutar solo el test que falla, trazas
temporales, retirada tras el fix, validación única al cierre y campañas manuales.
