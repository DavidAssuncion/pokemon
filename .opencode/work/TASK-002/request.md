# TASK-002 — Tests de batalla IA vs IA y cobertura de efectos

## Petición literal

> Haz test de batallas y de los diferentes efectos, que se realicen IA vs IA en busca de bugs

## Contexto

- Proyecto: `/home/david/pokemon` (Laravel 12, PHP 8.4). Repositorio git en `master`.
- Motor de batalla propio en `src/Battle/Domain` (343 archivos): `AgregadoBatalla`, `GestorTurnos`,
  `ServicioEjecucionBatalla`, cadena de daño, efectos de movimiento/habilidad/objeto/clima.
- IA en `src/Battle/Domain/AI`: `SelectorAccionIA`, `EvaluadorAccionIAImpl`, `SimuladorAccionIA`,
  `MemoriaCombateIA`, `EvaluadorPosicionIA`, `NivelDificultad`.
- Tests existentes: `tests/Unit/Battle/` (incluye `AI/Fase2Test.php`, `AI/Fase3Test.php`,
  `AI/SelectorAccionIATest.php`) y `tests/Feature/CombateLivewireTest.php`, `PokemonBattleTest.php`.
- El clon upstream `/home/david/pokemon-showdown` es solo referencia; el objeto de esta tarea es el
  motor del proyecto Laravel.
- Existe WIP local extenso (123 entradas en `git status`). No se debe sobrescribir ni revertir.

## Objetivo

Investigar y preparar la ejecución de batallas completas **IA vs IA** que ejerciten el catálogo de
efectos del motor, con el fin de **encontrar bugs reales** y dejar evidencia reproducible. El
resultado esperado es un informe de hallazgos con casos mínimos reproducibles, no una afirmación
genérica de que «los tests pasan».

## Preguntas a resolver en el análisis

1. ¿Existe ya un arnés/camino soportado para ejecutar una batalla completa IA vs IA sin UI
   (test, comando artisan, servicio)? ¿Cómo se instancia y se hace avanzar una batalla hasta el
   final?
2. ¿Qué efectos del catálogo (`ClaveEfecto`, `ClaveEfectoMovimiento`, habilidades, objetos, clima,
   terreno, estados) se pueden ejercitar de forma determinista en ese arnés y cuáles no?
3. ¿Qué cobertura real aportan los tests actuales de `tests/Unit/Battle` y `tests/Unit/Battle/AI`?
   ¿Qué efectos están sin cubrir?
4. ¿Qué invariantes globales se pueden comprobar en cada batalla simulada (HP nunca negativo,
   batalla termina, no hay bucles, un solo actor por ronda, estados coherentes, etc.)?
5. ¿Qué estrategia de generación de casos maximiza hallazgos: seeds deterministas, combinaciones de
   efectos, property-based testing, barrido del catálogo, mutaciones de estado?
6. ¿Qué artefactos habría que crear (test de integración, test unitario parametrizado, comando de
   simulación, informe) y con qué coste/riesgo?

## Bug reportado por el desarrollador (2026-10-31)

> Con dragoaliento a veces salta que sufre paralisis pero no se paraliza a veces, no deberia
> mostarlo entonces

Síntoma: al usar **Dragoaliento** (Dragon Breath, 30 % de parálisis), el log/animación muestra que
el objetivo «sufre parálisis» aunque el estado no se haya aplicado.

Evidencia preliminar (a confirmar por `analyst`):
- `app/Livewire/Combate.php:695-698` y `:931-936` registran el mensaje de estado de forma
  incondicional si el movimiento tiene `statusEffect` y el objetivo sigue vivo.
- `GestorEfectosMovimiento::aplicarEstado()` (`:41-44`) hace el sorteo del 30 % y retorna `void`
  sin comunicar si aplicó el estado.
- `registrarEvento('status', ...)` fuerza el evento con `'forzar' => true`, por lo que la UI
  también puede animar un estado que no se aplicó.
- `AgregadoBatalla::ejecutarAccion()` (IA vs IA) no registra el estado, pero tampoco propaga si se
  aplicó.

El resultado esperado es que el mensaje/animación solo aparezca cuando el estado se aplica
realmente. Debe cubrirse con test de regresión determinista (éxito, fallo del sorteo, inmunidad y
objetivo ya afectado).

## Criterio de calidad exigido por el desarrollador (2026-11-01)

> A estos casos me refiero, lo descubro tras pruebas y no entender el motivo, por eso necesitamos
> una bateria consistente de test, por un lado a los efectos, y por otro asserts de movimientos y
> estados deterministas, que no deba cambiar nada mas
>
> Pero que termine siendo un test de calidad y no solamente ruido

Interpretación operativa:
- Dos baterías deterministas separadas: efectos y movimientos/estados.
- La IA vs IA se usa para explorar y encontrar bugs, pero cada hallazgo se convierte en un test
  determinista y reproducible.
- Sin aleatoriedad no controlada, sin aserciones vacías y sin efectos secundarios sobre el proyecto.

## Restricciones

- El análisis no modifica código ni tests; solo lee e informa.
- No tocar `.env`, secretos ni credenciales.
- No ejecutar `git push`, `git reset --hard`, `git clean` ni `git checkout --`.
- No instalar/actualizar dependencias.
- No revertir ni sobrescribir el WIP preexistente.
- La convención del proyecto difiere testing durante el desarrollo, pero **esta tarea es
  precisamente de testing**, así que ejecutar tests y simulaciones está dentro de su naturaleza.
- Cualquier cambio de código (arnés, tests, correcciones) requiere plan aprobado antes de ejecutarse.
