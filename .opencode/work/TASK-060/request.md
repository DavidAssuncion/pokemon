# TASK-060 — Petición

- **ID:** TASK-060
- **Fecha:** 2026-11-18
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección de UI de combate + regla de daño para boosts en mazmorra.
- **Estado:** RECEIVED → ANALYZING.

## Texto literal

> Fase 1
> +200 %
> BUFF 4  tampoco, deberia mostrarse en todos el chip normal. Que se muestre tal cual, los movimientos que hagan daño en mazmorra sobre las caracteristicas aumentas dejan de tener efecto, se considear a partir del limite

## Decisiones aclaradas por el desarrollador

1. **Chips innecesarios:** quitar «Fase 1»/«ENRAGE» y «BUFF» de **todas las tarjetas de
   combate**. No se pidieron; no deben mostrarse.
2. **Chips de +200 %:** mostrar **cinco chips, uno por stat**: ATK, DEF, SpA, SpD y SPE,
   cada uno con `+200 %` (o el porcentaje que corresponda al multiplicador permanente del
   jefe).
3. **Movimientos que escalan con boosts:** en mazmorra, contar **solo el exceso sobre el
   tope de +4 etapas / ×3**. El multiplicador permanente ×3 no debe sumar potencia; solo
   cuenta lo que supere ese límite.
4. **Alcance:** la regla del límite se aplica **solo a mazmorra** (jefes con multiplicador
   permanente). No cambia otros modos.

## Contexto conocido

- Los chips están en `resources/views/livewire/_pokemon-card.blade.php`: `JEFE`, fase,
  `ENRAGE`, partes, `BUFF`, etapas, condiciones, etc.
- `CombatienteVista` expone `bonusStatsJefe` (un único porcentaje), `buffTurns`, `stages` y
  `stagesDetalle`.
- `bonificacionesPositivas()` (`StatsCombatiente`) suma las etapas positivas y se usa en
  `ManejadorDanioBase` para `objetivo_con_boost`/`objetivo_boost`/`atacante_boost`, y en
  `EjecutorCantoEncantador`.
- El jefe de mazmorra tiene `BattleStats::multiplicadorStats = 3.0`; su
  `MultiplicadoresStats` es neutro (el ×3 no es etapa).
- `TASK-055` implementó el multiplicador y el badge único `+200 %`; está completada y sin
  commit. TASK-060 ajusta esa UI y las reglas de daño.

## Ambigüedades para `analyst`

- ¿Cómo exponer los cinco porcentajes por stat sin duplicar lógica en Blade?
- ¿Qué labels usar (ATK/DEF/SpA/SpD/SPE o nombres completos)?
- ¿La regla “solo el exceso sobre +4 etapas” se calcula por stat o sobre el total de
  `bonificacionesPositivas()`?
- ¿Cómo identificar que un combatiente es jefe de mazmorra para aplicar la regla sin
  afectar a otros modos? ¿Por `BattleStats::multiplicadorStats()`, por una bandera en
  `DatosPokemonBatalla` o por el contexto de batalla?
- ¿La regla aplica al jefe como atacante, como defensor o a ambos?
- ¿`EjecutorCantoEncantador` debe seguir contando boosts normales o también aplicar el
  límite?
- ¿Hay tests que referencien los chips o `bonificacionesPositivas()`? Identificarlos.

## Restricciones

- Sin tests: no crear, modificar ni ejecutar la suite.
- Sin dependencias nuevas.
- No revertir ni sobrescribir cambios ajenos.
- Mantener accesibilidad y el comportamiento de otros modos.
- No tocar `.env` ni secretos.
