# TASK-065 — Informe final

- **ID:** TASK-065
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`
- **Estado final:** COMPLETADO (código y revisión). Validación manual en partida
  pendiente del desarrollador.

## Resumen

Se ha rediseñado la habilidad **Ausente** de Slaking:

1. Al inicio de la ronda en que descansará, se anuncia con la condición `AUSENTE`
   y un badge visible en la tarjeta.
2. Si no recibe daño directo antes de su turno, pierde la acción y recupera 1/16
   (6,25 %) de sus barreras física y especial en el momento de perder el turno,
   con tope en cada máximo.
3. Si recibe daño directo de un movimiento antes de su turno, se enfada: cancela
   la ausencia, actúa ese turno y gana 1 etapa de Ataque temporal (×1.30) que se
   retira al terminar su turno.
4. El daño recibido después del descanso no le devuelve la acción; el daño
   indirecto (clima, estado, retroceso) no provoca enfado.
5. Tras el enfado, vuelve a descansar la ronda siguiente (ciclo normal de Ausente).

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/Enums/ClaveCondicion.php` | `AUSENTE` + label |
| `src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php` | Rama `AUSENTE` primera: consume, rompe combo y deniega |
| `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php` | `retirarTemporal()` |
| `src/Battle/Domain/Effects/Capacidades/ReaccionaAlPerderTurno.php` | Nueva capacidad |
| `src/Battle/Domain/Effects/ColeccionEfectos.php` | `dispararTurnoPerdido()` |
| `src/Battle/Domain/Effects/EfectoTruant.php` | Máquina de estados completa |
| `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` | Notificación genérica de turno perdido |
| `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Ídem |
| `src/Battle/Domain/Componentes/Agregado/MotorRondas.php` | Ídem |
| `src/Battle/Domain/CombatienteVista.php` | `ausente`/`ausenteTooltip`; exclusión de `condicionesBadge` |
| `resources/views/livewire/_pokemon-card.blade.php` | Badge `AUSENTE` con tooltip |
| `storage/data/abilities_descripciones.json` | Descripción de `ausente` |

Sin cambios en `SESSION_VERSION`, base de datos, dependencias, tests ni ADR.

## Decisiones y arquitectura

- El anuncio se hace en `onRoundStart`, antes de elegir acción, lo que corrige de
  paso el bloqueo poco fiable que tenía Ausente en Livewire.
- La condición `AUSENTE` la consume `puedeActuar`; el enfado la retira antes.
- La curación se ejecuta en `alPerderTurno` (capacidad
  `ReaccionaAlPerderTurno` despachada desde los 3 caminos), no en `onRoundEnd`,
  para que un rival más lento no golpee antes de la curación.
- El boost usa `CambioStat::desdeEtapas(ATAQUE, 1)->aplicadoTemporalEn()` y se
  retira en `onTurnEnd` con `MultiplicadoresStats::retirarTemporal()`.
- Serialización compatible: propiedades nuevas con default; `SESSION_VERSION` 21.

## Verificación ejecutada

Análisis estático (sin tests):

- `php -l` de los 10 PHP modificados: OK.
- PHPStan dirigido: `[OK] No errors`.
- Pint dirigido y global: `pass`.
- `composer analyse`: 35 errores preexistentes ajenos; ninguno en TASK-065.
- `composer arch`/deptrac: 105 violaciones preexistentes ajenas; ninguna en TASK-065.
- `grep "dispararTurnoPerdido"`: 1 definición + 3 llamadas.
- `grep "AUSENTE|ausente"`: regla solo en dominio, vista y descripción; sin lógica
  en `app/Livewire`.
- JSON válido; 244 descripciones; `descripciones.ausente` actualizada.
- Revisión independiente: **APROBADO**, incluida la confirmación del delta de
  `onFainted`.

Comprobación manual: no ejecutada por el orchestrator. Pendiente de validar en
partida (ver próximos pasos).

## Revisión y seguridad

- `reviewer`: APROBADO. Dos LOW corregidos (`onFainted` retira boost y condición);
  un MEDIO justificado y acotado (guarda global de cargas; Slaking no tiene
  movimientos con carga).
- `security`: no aplica; el cambio no toca superficie de seguridad.

## Riesgos residuales

- Si la habilidad se copia/intercambia a un Pokémon con movimientos de carga, la
  guarda global `alguna carga > 0` podría impedir el descanso. No afecta a Slaking.
- `AUSENTE` huérfana si la habilidad se anula a mitad de ronda (Gas Reactivo):
  residual aceptado, un turno bloqueado sin curación.
- Si tras el enfado otra condición (parálisis/confusión) deniega la acción, el
  ciclo actúa/descansa puede desplazarse un turno; el drawback no desaparece.
- Rollback: revertir el código sin limpiar sesiones con `AUSENTE` rompería la
  deserialización del enum.
- `abilities_descripciones.json` está sin commitear por TASK-061; el cambio se
  aplicó solo sobre la clave `ausente`.

## Próximos pasos

1. Validar en partida: anuncio/badge, enfado con rival más rápido, descanso con
   curación, daño posterior sin acción, daño indirecto, multigolpe y tope de
   escudos.
2. Cargar una batalla guardada antes del cambio para comprobar la compatibilidad.
3. Si se quiere que Truant copiado ignore movimientos de carga, acotar
   `tieneCargasPendientes` al movimiento en curso en una tarea posterior.
