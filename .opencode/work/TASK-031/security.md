# TASK-031 — Auditoría de seguridad

- **Estado:** COMPLETADA
- **Veredicto:** `APPROVED` — sin hallazgos críticos ni altos.
- **Fecha:** 2026-11-07
- **Auditor:** `security` (independiente)
- **Alcance:** modo aliado ampliado + IA de soporte; selección y autorización de objetivos, integridad de la acción pendiente, sesión, datos y superficie general.
- **Tests:** no ejecutados, no modificados.

## 1. Veredicto

`APPROVED`. No hay hallazgos CRITICAL ni HIGH. Las observaciones LOW/INFO no bloquean la entrega; varias son preexistentes y ajenas a este cambio.

## 2. Superficie analizada

- Dominio: catálogo de apoyo, `EjecutorEfectoAliado`, `TipoEfectoAliado`, `Combatiente::curarEstadoMayor()`.
- IA: `ClasificadorAccionIA`, `EvaluadorSoporteIA`, `SelectorAccionIA`, `EvaluadorAccionIAImpl`, `PesosAmenaza`, `EvaluacionAccion`, `AgregadoBatalla`.
- Presentación: `GestorAccionBatalla::prepararAnimacionIa`, `MovesPreviewPresenter`.
- Contratos sin cambios revisados: `GestorSeleccionBatalla`, `ResolutorAccionBatalla`, `ResolutorActorBatalla`, `DTOAccionBatalla`, `BattleSessionService`, `Combate`, `BatallaVistaPresenter`.
- Datos: `storage/data/moves.json` (solo lectura).

## 3. Hallazgos

### Críticos

Ninguno.

### Altos

Ninguno.

### Medios

Ninguno demostrable.

### Bajos

**L-SEC-1 — El objetivo aliado puede morir entre selección y resolución**
- Ubicación: `GestorAccionBatalla.php:158-238`; `EjecutorEfectoAliado.php:35-40`.
- Vector: acción pendiente de apoyo sobre un aliado vivo que muere por otra acción/efecto antes de resolverse.
- Impacto: integridad de juego limitada (PP/turno consumidos, mensaje redundante). No revive al aliado: `VitalidadCombatiente::curarHpCantidad()` rechaza no vivos.
- Remediación: guarda de vida del objetivo en `resolver()` antes de `aplicarMovimientoAliado()`. No bloqueante.

**L-SEC-2 — Coste evitable en la evaluación de soporte**
- Ubicación: `EvaluadorSoporteIA.php:56,94-111`.
- Vector: `golpeMaximo()` se calculaba para todos los tipos, incluidos los que no lo usan.
- Impacto: solo rendimiento; acotado.
- Remediación aplicada tras la revisión: `golpeMaximo()` solo se calcula para `CURAR_PS`, `ESCUDO_FISICO`, `ESCUDO_ESPECIAL` y `AQUA_RING`.

**L-SEC-3 — Inconsistencia documental de aprobación**
- `plan.md` indicaba “PENDIENTE DE APROBACIÓN” frente a `approval.md` “APROBADO”.
- Remediación aplicada: `plan.md` actualizado a “APROBADO v1”.

### INFO

- `resolver()` no revalida que `pending->actorId` sea el actor autorizado, a diferencia de `resolverObjeto`. Mitigado: `pendingAction` vive en la sesión server-side y `actorId`/`defenderId` los fija el servidor. Preexistente.
- `selectMove()`/`previewTarget()` no fuerzan `phase` server-side. Un cliente podría pre-fijar acción fuera de fase, pero sigue exigiendo actor autorizado, movimiento disponible y objetivo válido. Preexistente.
- `BattleSessionService::cargar()` usa `unserialize($payload)` sin `allowed_classes`. Preexistente; no introducido por este cambio. Sin campos nuevos persistidos.
- `SelectorAccionIA::elegirObjetivoPara()` legacy pasa `incluirSoporte:false` sin `$dificultad`, por lo que usa `PERFECTA` por defecto. Comportamiento previo.

## 4. Controles correctos

1. **Autorización de objetivos del jugador:** `previewTarget` exige objetivo vivo, catálogo para aliado y retaguardia alcanzable; `selectMove` revalida vivo, catálogo, retaguardia y autoobjetivo; `resolver()` revalida catálogo y enruta a `aplicarMovimientoAliado`, sin daño/precisión/crítico.
2. **Catálogo como allowlist:** los 37 slugs nuevos son movimientos de estado con `heal_percent=50` o boosts positivos; ninguno es dañino ni debuff. Los compuestos excluidos no están en el catálogo.
3. **IA no daña al aliado:** `ClasificadorAccionIA::esApoyo()` exige misma escuadra, distinto id y slug admitido; `evaluarApoyo()` no llama a `estimar()` y fija KO/daño/amenaza a 0; lookahead desactivado para apoyo.
4. **IA no se autoaplica soporte:** `agregarAccionesSoporte()` excluye actor, muertos y retaguardia no alcanzable; la ruta de soporte no fuerza `defenderId = actor`.
5. **Compuerta de KO:** `hayKOEn()` solo considera evaluaciones no-apoyo con `koValue > 0`; si existe, se descarta todo el apoyo.
6. **APIs legacy aisladas:** `incluirSoporte:false` en `AgregadoBatalla`, `EjecutorAccion` y `SelectorAccionIA::elegirObjetivoPara`.
7. **Integridad de la acción pendiente:** `pendingAction` es privada de `AgregadoBatalla`, serializada en sesión; no es propiedad pública de `Combate` ni viaja como propiedad Livewire.
8. **Sesión:** `SESSION_VERSION=21` sin cambios; `supportValue` y pesos nuevos no se persisten; `EfectoAliado` se calcula en runtime.
9. **XSS:** el log se escapa con `e()`; los nuevos textos usan nombres y turnos numéricos; sin `{!! !!}` sobre datos sin escapar.
10. **Datos/secretos/dependencias:** `moves.json`, `.env`, Blade, JS, migraciones y `composer.json/lock` sin cambios en el diff. Sin logs con secretos.

## 5. Dependencias

`composer audit` reporta 10 paquetes con avisos, **todos preexistentes** y no introducidos por TASK-031:

- `laravel/framework v12.67.0`: PKSA-b41t-wbzk-9q51, PKSA-4hy6-m98j-dz9f, PKSA-d5tc-s1qs-h781.
- `livewire/livewire v4.4.2`: PKSA-xhv3-nr94-h8db; no aplicable (el proyecto no usa `#[Authorize]`; `/combate` está bajo `auth`).
- `league/commonmark`, `symfony/config|filesystem|http-foundation|http-kernel|mime|routing|yaml`: avisos varios.

Recomendación: planificar actualización de dependencias fuera de esta tarea.

## 6. Riesgos residuales / aceptados

- Integridad de juego (no seguridad): la IA puede gastar turno en un aliado que muere antes de resolver; buffs/curas con pesos que pueden dominar la selección si se descalibran.
- `refresh` deja la confusión a 0 turnos y se elimina en la siguiente acción (decisión de producto).
- Divergencia `CRITICO_MEJORADO` propio (`agregar`) vs aliado (`reemplazar`).
- Preexistentes: `unserialize` sin `allowed_classes`, ausencia de revalidación de actor en `resolver()` de ataque, fase no validada server-side.
- Advisories de dependencias preexistentes.

## 7. Limitaciones

- Sin tests ni smoke manual (política de la tarea).
- Sin análisis dinámico ni sesión real en navegador.
- No se leyó `.env` ni credenciales.
- No se ejecutó la suite completa de PHPStan/deptrac en esta pasada; F5 reporta 34/100 preexistentes, 0 nuevos.
- No se revisaron todos los caminos de `ServicioEjecucionBatalla`/`SimuladorAccionIA`; sí se verificó que el soporte no los invoca.
