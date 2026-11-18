# TASK-060 — Informe final

- **ID:** TASK-060
- **Estado:** `COMPLETED`
- **Plan aprobado:** v1 («Aprobar plan v1», 2026-11-18)
- **Proyecto:** `/home/david/pokemon`

## Resumen

Se eliminaron de las tarjetas de combate los chips no solicitados («Fase», «ENRAGE» y
«BUFF»), se sustituyó el chip único `+200 %` por cinco chips por stat (ATK, DEF, SpA, SpD,
SPE) y se aplicó en mazmorra la regla de que los movimientos que escalan con boosts cuentan
solo el exceso real sobre +4 etapas / ×3.

## Archivos modificados

| Archivo | Cambio | Responsable |
|---|---|---|
| `src/Battle/Domain/ReglasBatalla.php` | `LIMITE_ETAPAS_BOOST_EXTRA = 4` | `backend` |
| `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php` | `bonificacionesPositivasSobreLimite(int $limite)` | `backend` |
| `src/Battle/Domain/Combatiente.php` | `bonificacionesPositivasParaDanio()` | `backend` |
| `src/Battle/Domain/Chain/ManejadorDanioBase.php` | Tres fórmulas usan el método limitado | `backend` |
| `src/Battle/Domain/CombatienteVista.php` | `statsJefe` (5 entradas) sustituye a `bonusStatsJefe` | `backend` |
| `resources/views/livewire/_pokemon-card.blade.php` | Quita Fase/ENRAGE/BUFF; pinta cinco chips | `frontend` |
| `src/Battle/context.md` | Métodos, regla y contrato de vista | `documenter` |
| `src/Mazmorras/context.md` | Regla de daño y chips por stat | `documenter` |

Sin cambios en `tests/`, dependencias, migraciones, `MultiplicadoresStats`,
`BattleStats` ni `EjecutorCantoEncantador`.

## Decisiones

- **Opción C confirmada por el desarrollador:** el ×3 permanente no aporta potencia; solo
  cuenta el exceso sobre +4 etapas por stat. Con el jefe a ≤ +4 etapas, Castigo, Poder
  Reserva, Chulería y Envidia Ardiente no reciben bonus.
- **Gate por `BattleStats::multiplicadorStats() > 1.0`** en lugar de propagar contexto de
  batalla a `AccionBatalla`; hoy solo `GeneradorJefeMazmorra` produce multiplicador.
- `bonificacionesPositivas()` intacto para no alterar Canto Encantador ni otros modos.
- `buffTurns`, `jefe.nombreFase` y `jefe.enrage` se conservan en el contrato aunque dejen
  de pintarse.

## Verificación

- `php -l` de los 5 PHP modificados: sin errores.
- `vendor/bin/phpstan analyse` de los 5 PHP: «No errors».
- `vendor/bin/pint --test` de los 5 PHP: `pass`.
- Comprobación manual por lectura: etapas +1/+4 → 0, +5 → 1, +6 → 2 con multiplicador 3.0;
  sin multiplicador devuelve el total de etapas positivas.
- **No se ejecutó ningún test.** `tests/` permanece sin modificar.

## Revisión y seguridad

- `reviewer`: **APPROVED**, con dos NIT (duplicación de bucle; datos de contrato sin
  consumidor visual). Ninguno bloquea.
- `security`: **no aplica** (UI y cálculo de daño; el multiplicador se fija en servidor).

## Riesgos residuales

- Nerf intencional: el jefe ×3 pierde hasta +120 de potencia en movimientos de boost si no
  supera +4 etapas. Es la regla aprobada.
- Si otra fuente futura asigna `multiplicadorStats > 1.0`, el límite se aplicaría también,
  porque el gate no distingue contexto de mazmorra.
- `statsJefe` da por hecho que el multiplicador escalar afecta por igual a las cinco stats.
- El árbol de trabajo sigue sucio con TASK-049..059 y TASK-055; este cambio no se ha
  commiteado (no autorizado).

## Próximos pasos

1. Revisar visualmente en navegador la tarjeta del jefe de mazmorra (cinco chips, sin
   Fase/ENRAGE/BUFF) y una tarjeta normal.
2. Confirmar el nerf de los movimientos de boost en un combate real de mazmorra.
3. TASK-059 (formación y unificación de modales) sigue pendiente de aprobación; no se tocó
   en esta tarea.
