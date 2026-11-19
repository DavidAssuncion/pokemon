# TASK-065 — Revisión

- **ID:** TASK-065
- **Estado:** APROBADO (incluye confirmación del delta posterior)
- **Revisor:** `reviewer`
- **Fecha:** 2026-11-19

## Veredicto

**APROBADO.** La implementación respeta el plan v1 y la arquitectura: `AUSENTE`
se anuncia en `onRoundStart`, `puedeActuar` la consume y deniega, el enfado
cancela la ausencia y aplica +1 Ataque temporal (×1.30), la curación de 1/16
ocurre en `alPerderTurno` (no en `onRoundEnd`), el boost se retira en
`onTurnEnd`, no hay regla duplicada en `app/Livewire/**` y `SESSION_VERSION`
sigue en 21. Análisis estático dirigido y Pint en verde.

## Hallazgos

### LOW — corregidos tras la revisión

1. **`EfectoTruant::onFainted()` no retiraba el boost temporal** si el portador
   moría enfadado. Corregido: `retirarEnfadoSiProcede($portador)` antes de limpiar
   flags.
2. **`onFainted()` no retiraba la condición `AUSENTE`** si moría anunciado, lo que
   podía mostrar `AUSENTE` junto a `K.O.`. Corregido:
   `quitarCondicion(ClaveCondicion::AUSENTE)`.

El revisor confirmó el delta: sin regresiones, orden correcto, idempotente y sin
doble división del stack temporal.

### MEDIO — justificado y acotado

- `tieneCargasPendientes()` comprueba “alguna carga > 0” de forma global, no solo
  la carga del movimiento en curso. Si un portador de Truant conociera un
  movimiento con `cargaMaxima > 0`, el descanso podría no programarse. Se verificó
  que **el moveset de Slaking no tiene movimientos con carga** (`pokemon_moves_pokeapi.json`
  + `moves.json`), por lo que el riesgo no afecta al caso solicitado. Queda como
  residual si la habilidad se copia/intercambia a otro Pokémon con cargas.

### NIT — sin acción

- Tooltip del badge solo con `title` (patrón existente en la tarjeta); se podría
  añadir `aria-label`, fuera del alcance.
- `retirarTemporal()` acepta cualquier factor y divide el stack; correcto para el
  uso actual.
- `'taunt'` duplicado en `ClaveCondicion::desdePs` es preexistente y ajeno a esta
  tarea.

## Fortalezas

- Condición `AUSENTE` primera en `veredictoCondicionBloqueante`; no cancela cargas
  (solo `INTERRUPCION` lo hace) y rompe combo.
- `alPerderTurno` idempotente: consume el flag antes de curar; la segunda invocación
  no cura.
- Tres puntos de despacho genéricos y bien colocados: `MotorRondas:150`,
  `GestorCicloTurnosBatalla:97` (antes del snapshot `status_block`) y
  `GestorPrioridadesBatalla:146` (antes de log/consumeAction). En denegaciones
  ajenas a Ausente son no-op.
- No hay doble denegación real: prioridades consume `consumeAction` y `getNextActor`
  no vuelve a seleccionar al actor.
- `retirarTemporal` divide el stack, elimina la clave si ≈1.0, deja
  `turnosTemporales = 0` sin residual y preserva otros stacks; la serialización no
  cambia.
- `CombatienteVista` expone `ausente`/`ausenteTooltip` y excluye `'ausente'` de
  `condicionesBadge`; Blade sin lógica.
- Compatibilidad de sesión: `SESSION_VERSION` 21 / mínimo 9 intactos; propiedades
  tipadas con default.
- Sin tests, ADR, migraciones ni dependencias.

## Verificación ejecutada

- `php -l` de los 10 PHP modificados: OK.
- PHPStan dirigido: `[OK] No errors`.
- Pint dirigido y global: `pass`.
- `composer analyse` global: 35 errores preexistentes ajenos; ninguno en archivos de
  TASK-065.
- `deptrac`: 105 violaciones preexistentes; ninguna en archivos revisados.
- `grep "dispararTurnoPerdido"`: 1 definición + 3 llamadas.
- `grep "AUSENTE|ausente"`: regla solo en dominio, vista y descripción; sin lógica
  en `app/Livewire`.
- `grep "onTurnStart"`: `EfectoTruant` ya no lo declara.
- JSON válido; `descripciones.ausente` presente y fiel (244 descripciones).
- Sin ejecutar tests.

## Riesgos residuales

- Guarda global de cargas (ver MEDIO); no afecta a Slaking, sí a una hipotética
  copia de la habilidad.
- `AUSENTE` huérfana si la habilidad se anula a mitad de ronda (Gas Reactivo):
  residual aceptado, un turno bloqueado sin curación.
- El ciclo actúa/descansa puede desplazarse si, tras el enfado, otra condición
  (parálisis/confusión) deniega la acción; el drawback no desaparece.
- Rollback: revertir el código sin limpiar sesiones con `AUSENTE` rompería la
  deserialización del enum.
- Validación manual en partida pendiente del desarrollador.
