# TASK-055 — Petición

- **ID:** TASK-055
- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** ajuste de balance del jefe de mazmorra y retirada de puntos débiles.
- **Estado:** RECEIVED → ANALYZING.

## Texto literal

Mensaje 1:

> Todos los stats x3 y la vida x10

Mensaje 2 (corrección):

> Los stats x0 pero recibe el aumento de estadisticas x3 indefinidamente, asi es mas visual. las barreras se calculan sobre estos valores

## Decisiones aclaradas por el desarrollador (2026-11-17)

1. **Vida del jefe:** ×10 sobre la vida base de la especie.
2. **Stats base distintos de HP:** ×1 (sin multiplicador directo). El «x0» significa
   «sin multiplicador extra», no stats a cero.
3. **Aumento permanente:** +200 % (= ×3 efectivo) a ataque, defensa, ataque especial,
   defensa especial y velocidad. No afecta a vida, precisión ni evasión. Sustituye al
   +30 % inicial actual. Debe mostrarse como **indicador visual +200 %**, **sin etapas
   reales**: no debe contar como `bonificacionesPositivas()`, no debe poder invertirse,
   copiarse ni intercambiarse como una etapa, y no debe disparar interacciones de
   Castigo/Poder Reserva/Canto Encantador.
4. **Barreras:** las barreras de defensa física y especial del jefe se calculan sobre la
   defensa efectiva con el ×3 (base ×1 × 3), no sobre la base ×1 ni sobre el ×10 actual.
5. **Núcleo/Coraza:** se eliminan de los jefes de mazmorra. No se configurarán puntos
   débiles en `GeneradorJefeMazmorra`. Se elimina el fallback de `JefeEstado` que crea un
   Núcleo implícito. La infraestructura de partes (`JefeEstado`/`JefeCombatiente`) se
   mantiene para una futura habilidad de otro Pokémon; esa habilidad queda **fuera del
   alcance** de TASK-055.
6. **Sin tests:** no crear, modificar ni ejecutar la suite.
7. **Sin dependencias nuevas** y sin tocar cambios locales de otras tareas.

## Fuera de alcance

- Implementar la habilidad que dará Núcleo/Coraza a un Pokémon concreto.
- Rebalancear gimnasios, exploraciones u otros modos.
- Rediseñar la UI más allá de la visualización de etapas ya existente.
- Tests.

## Criterios de aceptación preliminares

- La vida del jefe de mazmorra se genera con ×10.
- Los stats de combate distintos de HP se generan sin multiplicador directo (×1).
- El jefe recibe +200 % permanente solo en ataque, defensa, ataque especial, defensa
  especial y velocidad; la vida no se ve afectada por ese bonus.
- El +200 % se muestra como indicador visual en la tarjeta del jefe, sin crear etapas
  reales ni disparar interacciones de movimientos/IA.
- Las barreras de defensa física y especial se calculan sobre la defensa efectiva ×3.
- Los jefes de mazmorra ya no muestran ni generan Núcleo/Coraza; no queda una parte
  Núcleo implícita por el fallback de `JefeEstado`.
- La infraestructura de partes sigue disponible para la futura habilidad.
- Documentación del módulo actualizada.
- Sin cambios en `tests/` ni en archivos ajenos a la tarea.

## Preguntas para `analyst`

- ¿Qué archivos y líneas exactas cambian?
- ¿Cómo se aplica hoy `bonusInicialStats` y cómo limitarlo a los cinco stats de combate?
- ¿El fallback de `JefeEstado::normalizarPartes` crea un Núcleo aunque no se configuren
  partes? ¿Cuál es la forma segura de permitir cero partes sin romper la futura habilidad?
- ¿La UI (`CombatienteVista`, `_pokemon-card.blade.php`) muestra correctamente +4 etapas /
  +200 % para un factor 3.0? ¿Hay algún tope o redondeo que lo impida?
- ¿Qué documentación (`context.md`, README) queda desactualizada?
- ¿Hay tests existentes que referencien estos valores? Identificarlos sin modificarlos.
