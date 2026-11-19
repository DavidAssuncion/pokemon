# TASK-063 — Informe final

- **ID:** TASK-063
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`
- **Estado final:** COMPLETADO (código y revisión). Validación manual en juego
  pendiente del desarrollador.

## Resumen

Se ha eliminado la concesión global e incondicional de un turno extra tras un KO.
El motor ya no reparte acciones extra por el mero hecho de debilitar a un rival en
combate manual, uso de objetos ni simulación/IA. La capacidad
`otorgarAccionExtra()` se conserva para una futura habilidad, que deberá invocarla
desde el efecto de dominio `ReaccionaAlDebilitarRival` y solo con un KO causado por
el actor.

Decisión del desarrollador (2026-11-19): ninguna habilidad conserva el turno extra
por ahora; cuando exista, solo contará el KO causado por el propio actor, no
muertes de aliados ni efectos ajenos.

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php` | En `procesarConsecuencias()` se conserva `procesarDebilitamientos()` y se retira la concesión de acción extra. |
| `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | Mismo cambio en `aplicarMovimiento()` y `resolverObjeto()`. |

Diff neto: 2 archivos, 3 inserciones, 12 eliminaciones. Sin cambios en serialización,
`SESSION_VERSION`, UI, BD, dependencias ni tests.

## Artefactos de la tarea

- `request.md`, `analysis.md`, `architecture.md`, `plan.md` (v1), `approval.md`
  (aprobado), `tasks.md`, `execution.md`, `review.md`, `security.md`, este informe.

## Decisiones y arquitectura

- No se añaden abstracciones ni servicios nuevos: la regla se retira, no se
  sustituye.
- Se conserva `EstadoTurno::accionesExtra`/`extraOtorgada`,
  `otorgarAccionExtra()`, el pool de `GestorTurnos::getNextActor()`, el consumo en
  `consumeAction()` y la serialización actual.
- Contrato futuro documentado en `architecture.md`: efecto de dominio que implemente
  `ReaccionaAlDebilitarRival`; el despacho compartido ya existe en
  `AplicadorEfectosPostImpacto::dispararReacciones`.
- No se crea ADR: el AGENTS.md del proyecto solo permite documentación si el
  desarrollador la solicita explícitamente.

## Verificación ejecutada

Análisis estático (sin tests):

- `php -l` de ambos archivos: OK.
- PHPStan dirigido a ambos archivos: `[OK] No errors`.
- Pint dirigido: `pass`.
- `grep -rn "otorgarAccionExtra" src app`: solo definiciones delegadas; cero
  invocaciones.
- `grep -rn "nuevosDebilitados" src app`: sin referencias.
- `procesarDebilitamientos()` presente en los tres puntos originales y en
  `aplicarMovimientoAliado()`.
- `composer analyse` global: 35 errores preexistentes ajenos; ninguno en los
  archivos de TASK-063.
- `composer arch`: 105 violaciones preexistentes ajenas; ninguna en los archivos de
  TASK-063.
- Revisión independiente: `APROBADO` (ver `review.md`).

Comprobación manual: no ejecutada por el orchestrator. Requiere que el desarrollador
pruebe en juego una batalla 3v3 y un objeto que debilite.

## Revisión y seguridad

- `reviewer`: APROBADO. Solo NITs de trazabilidad y de código muerto intencional.
- `security`: no aplica; el cambio no toca superficie de seguridad.

## Riesgos residuales

- Batallas ya serializadas con `accionesExtra = 1` conservan un extra hasta
  consumirlo o abrir ronda. Aceptado; no se migra ni se invalida la sesión.
- `otorgarAccionExtra()` queda sin llamadores de producción hasta que exista la
  habilidad futura.
- Cambio de balance esperado: menos acciones por ronda en batallas multijugador.
- Posible reintroducción accidental de la regla en Livewire; el contrato y el grep
  de cierre lo previenen.

## Próximos pasos

1. Validar manualmente: batalla 3v3 (un KO no debe repetir turno) y objeto que
   debilite (no debe conceder turno).
2. Cuando se defina la habilidad de turno extra, implementarla como efecto
   `ReaccionaAlDebilitarRival`; decidir entonces si la repetición es inmediata o al
   final de la ronda y si se anuncia en el log.
3. Resolver en su propia tarea los errores preexistentes de `composer analyse` y
   `composer arch` para poder exigir la verificación global en verde.
