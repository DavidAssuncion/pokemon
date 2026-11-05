# TASK-012 — Revisión independiente

- **Fecha:** 2026-11-04
- **Revisor:** `reviewer`
- **Alcance:** F2–F5 y correcciones de verificación/seguridad.
- **Veredicto:** `APPROVED`

## Resumen

La revisión final confirma que las seis correcciones aplicadas resuelven los hallazgos previos sin regresiones funcionales detectables por lectura:

1. `DTOAccionObjeto` en `src/Battle/Domain/DataTransferObjects`, sin `Wireable`.
2. `ForjaController` sin `extends Controller`.
3. `ObtenerGranja` filtra plantaciones por `nests.id`.
4. Actor de objetos resuelto en servidor con `AgregadoBatalla::$actorActualId` y `Combate::actorJugadorActual()`.
5. Incrementos de inventario atómicos (`insertOrIgnore` + `increment`).
6. `UsarObjetoEnCombate` consume y aplica dentro de la misma transacción.

## Hallazgos

### MEDIUM

- `src/Exploraciones/App/PersistirRecompensas.php:112-130`: duplica la secuencia atómica de `InventarioJugadorEloquent::incrementar()`. No hay riesgo de saldo negativo; es deuda de una segunda vía de escritura. Se acepta para esta entrega y se registra como deuda.

### MINOR

- `src/Exploraciones/App/FinalizarExploracionHandler.php` (`botin()`): el botín no comparte la fuente de aleatoriedad inyectada del handler. No afecta al comportamiento normal; se registra como deuda.
- `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:188-205`: `refrescarTemporal()` reinicia la duración compartida de todos los temporales del combatiente. El criterio “sin stack” se cumple para la stat del objeto; la interacción con temporales de otras stats queda documentada.
- `resources/views/forja/index.blade.php`: `material:recipiente` aparece bajo “Medicinas”, según la instrucción de producto de cuatro categorías. Se acepta.

### NIT

- `app/Livewire/Combate.php`: `refrescarObjetosCombate()` usa `resolveActor()` como fallback de presentación; la autorización real usa `actorJugadorActual()`.
- `GranjaController` mantiene `extends Controller` mientras `ForjaController` no; inconsistencia cosmética.

## Checklist de aceptación

- Bloque 0: verificado por lectura y análisis estático.
- F2: huecos, rango, consumo, unique/locks, stock 24/48/72 con tope 3, doble cosecha, replantación, autorización y DTOs; verificado por lectura. No ejecutado en runtime.
- F3: nativo vs no nativo, flag persistido, factores solo bayas, tope ×2.0, no bonguris, hitos; verificado por lectura.
- F4: 46 recetas, 39 resultados, R1–R9, 7 recipientes, mejoras/reductores/resistencias, transacción, 404/422, UI de 4 categorías; verificado por lectura.
- F5: 44 objetos, consume acción y no movimiento, stock, objetivos, revivir, buffs/debuffs, resistencia, crítico, restaurar usos, log, sesión v20; verificado por lectura. No ejecutado en runtime.
- Documentación: ADR y `docs/architecture.md` actualizados; verificado por el `documenter`.

## Riesgos residuales

- Sin verificación runtime de cosecha 24/48/72 h, concurrencia, payloads v20 ni UI en navegador.
- `swapTurno` + objeto no se ha ejercitado en runtime.
- `selectMove()` no valida `phase` (preexistente); no permite duplicar inventario.
- Duración compartida de temporales y doble vía de escritura de inventario quedan como deuda.
- `ItemCatalogo::resolve()` fue verificado por el orchestrator para las claves nuevas.

## Addendum — Corrección posterior del modal de granja

- **Motivo:** el modal se recortaba al seleccionar/plantar una baya; además se solicitó ampliarlo como el detalle de Pokémon de `/equipos`.
- **Cambios:** panel `w-[90vw] max-w-[90vw] max-h-[94vh]`, cabecera `shrink-0`, cuerpo `flex-1 min-h-0 overflow-y-auto`; selector de baya con `:checked` + `@change`; helper `puedePlantar()` y limpieza de selección tras plantar.
- **Revisión focalizada:** `APPROVED`.
- **Hallazgo LOW corregido:** el botón “Plantar” se deshabilita si la baya seleccionada ya no tiene stock; `plantarBaya()` borra la selección tras éxito.
- **Verificación:** `view:cache`/`view:clear` OK; assets compilados con `w-[90vw]`, `max-w-[90vw]`, `max-h-[94vh]`, `min-h-0`, `max-h-40` y `lg:grid-cols-2`; sin `x-model` dinámico.

## Archivos inspeccionados

Los detallados en el informe del `reviewer`: plan, arquitectura, aprobación, ejecución, módulos F2–F5, Blade de granja/forja/combate, migraciones, rutas y providers.
