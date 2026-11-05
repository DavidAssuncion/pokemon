# TASK-012 — Auditoría de seguridad

- **Fecha:** 2026-11-04
- **Auditor:** `security`
- **Alcance:** F2–F5 y correcciones de hardening.
- **Veredicto:** `APPROVED`

## Resumen

No se identificaron hallazgos `CRITICAL` ni `HIGH` introducidos por F2–F5. Las correcciones cierran los vectores que motivaron el `CHANGES_REQUESTED` anterior:

- El uso de objetos ya no confía en `actingRefId`/`phase`/`processing` públicos: el actor se lee de `AgregadoBatalla::$actorActualId`, fijado en servidor por `nextActor()`, y se valida team1 + vivo + acción pendiente.
- `resolverAccionObjeto()` revalida el actor y descarta acciones pendientes obsoletas antes de consumir inventario.
- `incrementar` usa `insertOrIgnore` + `increment`; no hay 500 por unique, saldos negativos ni duplicación.
- `UsarObjetoEnCombate` consume y aplica dentro de la misma transacción; un fallo de `aplicar()` revierte el consumo.
- F2/F4 mantienen autorización por usuario/granja, validación de claves, CSRF y escape de salida.

## Hallazgos residuales

### MEDIUM (preexistente, no introducido por F2–F5)

- `app/Livewire/Combate.php::resolveActor()` sigue usando `actingRefId` público en `selectMove`, `previewTarget`, `pasarTurno`, `retrasarTurno` y `refrescarObjetosCombate`. Un cliente podría manipular la acción del rival en su propia batalla. No hay acceso entre usuarios ni duplicación de inventario. Se recomienda migrar esas acciones a `actorActualId()` en una tarea de hardening posterior.

### LOW

- Atomicidad consumo ↔ guardado de sesión: si `session()->put()` falla después de consumir, se pierde el objeto; no hay ganancia ni efecto persistido sin consumo.
- `UsarObjetoEnCombate` podría confirmar el consumo si `aplicar()` devolviera un fallo sin lanzar; en el flujo actual es inalcanzable porque `validar()` comprueba lo mismo antes.
- `insertOrIgnore` silencioso en fallos no-unique (p. ej. FK inválida); no afecta a los flujos actuales.

### INFO

- `commitAction()`/`nextActor()` sin idempotencia de petición; concurrencia extrema puede consumir dos objetos sin ganancia neta.
- Avisos de dependencias preexistentes (Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17, CommonMark 2.10.0); no se actualizaron en esta tarea.

## Controles verificados

- Rutas F2/F4 dentro de `auth` + `web` (CSRF).
- `AsegurarGranja` y consultas filtradas por `user_id`/`farm_id`; sin IDOR en plantar/cosechar.
- Claves de baya/objeto/receta validadas contra catálogo; `cantidad` de forja fijada a 1.
- Inventario sin saldos negativos; locks ordenados y unique `(user_id, item_key)`.
- Combate: validación server-side de actor, objetivo, stock y tipo de objeto; objetivos aliado vivo/debilitado/rival vivo.
- Sesión de batalla v21, mínimo 9; `resistencias` con default; `actorActualId` con default.
- XSS: vistas nuevas usan `{{ }}`/`x-text`; log de combate escapa antes de insertar iconos.
- Sin secretos leídos, sin dependencias añadidas, sin rutas públicas nuevas.

## Riesgos residuales

- `resolveActor()` en acciones de movimiento (preexistente).
- Pérdida de objeto si falla el guardado de sesión tras consumir.
- Sin idempotencia en `commitAction`/`nextActor`.
- Avisos de dependencias preexistentes.

## Archivos inspeccionados

Los detallados en el informe del `security`: `Combate.php`, `AgregadoBatalla.php`, DTOs/efectos, `UsarObjetoEnCombate`, `ObtenerOpcionesObjeto`, inventario, F2/F3/F4, rutas, vistas y configuración de sesión.
