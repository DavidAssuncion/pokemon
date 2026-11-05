# TASK-012 — Informe final

- **ID:** TASK-012
- **Estado:** `COMPLETED`
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon`
- **Rama:** `task-011`
- **Alcance:** F2 (plantación/cosecha), F3 (bonus de hábitat y plantaciones), F4 (forja) y F5 (objetos en combate).
- **Aprobación:** plan v1 (F2–F5), registrada en `approval.md`.
- **Cambios:** en el working tree, **sin commit y sin push**.

---

## 1. Resumen

Se implementaron F2–F5 sobre la F1 existente. El sistema permite:

- Plantar y cosechar bayas en los huecos de los nidos, con crecimiento diario y stock máximo 3.
- Obtener más y mejores recompensas al explorar con un Pokémon nativo del hábitat y con plantaciones de la misma granja.
- Fabricar 46 recetas (pociones, restauradores, revivir, recipiente, mejoras, reductores y resistencias) desde bayas, bonguris y recipientes.
- Usar 44 objetos en combate (6 bayas básicas + 38 fabricados), consumiendo la acción y no el movimiento, con objetivos aliado vivo/debilitado/rival vivo.

La página `/forja` agrupa las recetas en cuatro categorías colapsables: **Medicinas**, **Mejoras**, **Reductores** y **Resistencias**. El recipiente, material base de medicina, aparece dentro de Medicinas.

---

## 2. Entregables por fase

| Fase | Entregable |
|---|---|
| **Bloque 0** | Puerto `InventarioJugador`, `StockInsuficiente`, adaptador `InventarioJugadorEloquent`, binding y delegación desde `InventarioGranja`. |
| **F2** | Migración `nest_plantings` (batch 21), modelo `PlantacionNido`, entidad `Plantacion`, `ReglasPlantacion`, DTOs/colecciones, `PlantarBaya`, `CosecharPlantacion`, endpoints y UI de huecos. |
| **F3** | Puerto `ConsultorPlantaciones`, `EsDelHabitat`, factores de plantación, bonus +25 % tiradas / +10 pp bonguri, flag persistido y hito “Conoce el terreno”. |
| **F4** | 39 claves nuevas, 82 definiciones totales, 46 recetas, 39 resultados, módulo `src/Forja`, endpoints y página `/forja` con 4 categorías colapsables. |
| **F5** | `AccionObjeto`, 44 efectos, `ServicioUsoObjetos`, `UsarObjetoEnCombate`, resistencias temporales, `restaurarUsos`, actor server-side, fases Livewire y panel de objetos. |

### Archivos clave

- **Inventario:** `src/Shared/Domain/Ports/InventarioJugador.php`, `src/Shared/Infra/Inventario/InventarioJugadorEloquent.php`, `src/Shared/Domain/Exceptions/StockInsuficiente.php`.
- **F2:** `database/migrations/2026_11_04_000003_create_nest_plantings_table.php`, `src/Granjas/App/PlantarBaya.php`, `src/Granjas/App/CosecharPlantacion.php`, `src/Granjas/Domain/ReglasPlantacion.php`, `src/Granjas/App/ObtenerGranja.php`, `resources/views/habitats/show.blade.php`.
- **F3:** `src/Exploraciones/Domain/Ports/ConsultorPlantaciones.php`, `src/Exploraciones/App/EsDelHabitat.php`, `src/Granjas/Infra/Plantaciones/ConsultorPlantacionesEloquent.php`, `src/Exploraciones/Domain/CalculadorBotinExploracion.php`, `src/Exploraciones/App/FinalizarExploracionHandler.php`.
- **F4:** `src/Shared/Domain/Items/Clave{Pocion,Material,Mejora,Reductor,Resistencia}.php`, `src/Shared/Domain/Items/CatalogoItems.php`, `src/Forja/Domain/CatalogoRecetas.php`, `src/Forja/App/Fabricar.php`, `resources/views/forja/index.blade.php`.
- **F5:** `src/Battle/Domain/UsoObjetos/*`, `src/Battle/Domain/ValueObjects/ResistenciasTemporales.php`, `src/Battle/App/UsarObjetoEnCombate.php`, `src/Battle/Domain/AgregadoBatalla.php`, `app/Livewire/Combate.php`, `resources/views/livewire/partials/items-panel.blade.php`.
- **Documentación:** `docs/adr/0001-inventario-compartido-f2-f5.md`, `docs/architecture.md`.

---

## 3. Decisiones y ADR

- **ADR:** `docs/adr/0001-inventario-compartido-f2-f5.md` — estado Aceptado.
- **Arquitectura:** `docs/architecture.md` actualizado con inventario compartido, F2–F5, `src/Forja`, esquema `farms → nests → nest_plantings`, sesión de batalla 21/9 y flujo de objetos.
- Decisiones principales:
  - Inventario compartido con validación de catálogo y carve-out legacy `familia:`/`ev:`.
  - `nest_plantings` con stock calculado sin cron y unique `(nest_id, slot_index)`.
  - `ConsultorPlantaciones` como puerto de Exploraciones implementado en Granjas.
  - 46 recetas estáticas y 39 resultados; variantes agrupadas por `resultado_clave`.
  - `actorActualId` server-side para autorizar objetos en combate.
  - `SESSION_VERSION` 21 con mínimo compatible 9.

---

## 4. Verificación ejecutada

Sin tests. Resultados:

| Comprobación | Resultado |
|---|---|
| `php -l` de archivos nuevos/modificados | Sin errores. |
| `phpstan analyse` | 34 errores preexistentes; **ninguno nuevo** en F2–F5. |
| `pint --test --dirty` | `pass`. |
| `deptrac analyse` | 113 violaciones preexistentes; **ninguna nueva**. |
| `php artisan view:cache` / `view:clear` | OK, también tras la corrección del modal. |
| `npm run build` | OK; assets con `w-[90vw]`, `max-w-[90vw]`, `max-h-[94vh]`, `min-h-0`, `max-h-40` y `lg:grid-cols-2`. |
| `route:list --path=forja` | 3 rutas. |
| `route:list --path=farm` | 7 rutas, incluidas plantar/cosechar. |
| Catálogo | 82 definiciones, 44 objetos de combate, 46 recetas, 39 resultados, 44 efectos. |
| Migración F2 | `2026_11_04_000003_create_nest_plantings_table` aplicada en local, batch 21. |
| `tests/` | Sin cambios. No se ejecutaron tests. |

Comprobaciones manuales/estáticas adicionales:

- `ItemCatalogo::resolve()` resuelve correctamente `pocion:*`, `material:*`, `mejora:*`, `reductor:*` y `resistencia:*`.
- `CatalogoItems::objetosDeCombate()` devuelve 44 claves (6 bayas + 38 fabricados).
- `CatalogoRecetas::paraResultado('pocion:pocion')` = 2; `paraResultado('material:recipiente')` = 7.
- `SESSION_VERSION = 21`, mínimo compatible 9.
- `actorActualId` con default para payloads v20.
- `ObtenerGranja` filtra plantaciones por `nests.id`.
- `ForjaController` sin dependencia de `App\Http\Controllers\Controller`.

---

## 5. Revisión y seguridad

### Revisión independiente

- **Veredicto:** `APPROVED`.
- Hallazgos residuales:
  - MEDIUM: `PersistirRecompensas` conserva una segunda vía de escritura de inventario (atómica, sin saldos negativos). Deuda.
  - MINOR: el botín de `FinalizarExploracionHandler` no comparte la fuente de aleatoriedad inyectada.
  - MINOR: `refrescarTemporal()` reinicia la duración compartida de todos los temporales del combatiente.
  - NIT: `refrescarObjetosCombate()` usa `resolveActor()` como fallback de presentación.
- Checklist de aceptación: verificado por lectura y análisis estático; sin ejecución runtime.

### Auditoría de seguridad

- **Veredicto:** `APPROVED`.
- Sin hallazgos CRITICAL/HIGH introducidos por F2–F5.
- Correcciones verificadas:
  - Actor de objetos server-side (`actorActualId`), sin confiar en `actingRefId`/`phase`/`processing`.
  - `resolverAccionObjeto` revalida actor/team1/acción pendiente antes de consumir.
  - `incrementar` atómico (`insertOrIgnore` + `increment`).
  - Consumo + aplicación de objeto en la misma transacción.
- Residual MEDIUM preexistente: `resolveActor()` en `selectMove`/`previewTarget`/`pasarTurno`/`retrasarTurno` sigue usando `actingRefId` público. No es un bypass de objetos ni acceso entre usuarios; se recomienda hardening posterior.
- Residual LOW: pérdida de objeto si falla el guardado de sesión tras consumir; sin idempotencia en `commitAction`/`nextActor`.

---

## 6. Riesgos residuales

- **Sin verificación runtime:** no se ejecutaron tests ni una prueba completa en navegador de plantación/cosecha, forja y uso de objetos. La verificación fue estática y por lectura.
- **`resolveActor()` preexistente:** permite manipular la acción del rival en la propia batalla en acciones de movimiento; no afecta al uso de objetos (ya hardened) ni a datos de otros usuarios.
- **Atomicidad sesión/inventario:** si falla el guardado de sesión tras consumir un objeto, se pierde el objeto.
- **Sin idempotencia:** doble clic/concurrencia extrema en `commitAction`/`nextActor` puede consumir dos objetos sin ganancia neta; la UI deshabilita doble clic en forja.
- **Iconos:** los 39 iconos nuevos no existen; se usa `unknown.webp`, iconos de tipo o de baya. `roseli-berry.webp` sigue ausente. Queda para F6.
- **Bayas directas:** solo 6 bayas básicas (`oran`, `sitrus`, `cheri`, `pecha`, `rawst`, `aspear`) se usan directamente; `chesto`, `persim`, `lum` y `leppa` no existen en `ClaveBaya` y quedan diferidas.
- **Doble vía de inventario:** `PersistirRecompensas` mantiene su propio incremento atómico.
- **Duración compartida de temporales:** `refrescarTemporal()` reinicia la duración de todos los temporales del combatiente, no solo de la stat del objeto.
- **Dependencias:** avisos preexistentes de Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17 y CommonMark 2.10.0; no se actualizaron.
- **Cambios locales preexistentes:** `.githooks/pre-commit` y `MVP.txt` ya estaban modificados antes de TASK-012; quedan fuera de alcance.

---

## 6.1 Corrección posterior — modal de granja

- **Reporte:** al seleccionar/plantar una baya el modal se rompía; además debía ser más grande, como el detalle de Pokémon de `/equipos`.
- **Diagnóstico:** el backend de plantación/cosecha funciona en local. El recorte se debía al patrón flex `overflow-hidden` + cuerpo `flex-1` sin `min-h-0`; el selector usaba `x-model` con una clave dinámica calculada por función.
- **Corrección:** panel `w-[90vw] max-w-[90vw] max-h-[94vh]`, cabecera `shrink-0`, cuerpo `flex-1 min-h-0 overflow-y-auto`; selector con `:checked` + `@change`; helper `puedePlantar()` y limpieza de selección tras plantar.
- **Verificación:** `view:cache`/`view:clear` OK; `npm run build` OK; clases presentes en el CSS compilado; sin `x-model` dinámico; revisión focalizada `APPROVED`.
- **Estado:** corregido en el working tree, sin commit ni push.

## 7. Próximos pasos

1. **Prueba manual en navegador:** plantar/cosechar en un nido, comprobar stock a 24/48/72 h, fabricar una poción y usar objetos en combate (curar aliado, revivir debilitado, debuff a rival, `swapTurno` + objeto).
2. **Commit:** los cambios están sin commitear; el desarrollador decide el mensaje y el alcance del commit. No se hizo `git push`.
3. **Hardening posterior:** migrar `selectMove`, `previewTarget`, `pasarTurno`, `retrasarTurno` y `refrescarObjetosCombate` a `actorActualId()` para cerrar el residual MEDIUM preexistente.
4. **Iconos F6:** incorporar los 39 iconos nuevos y `roseli-berry.webp`, y sustituir fallbacks.
5. **Dependencias:** actualizar Laravel/Livewire/Symfony/CommonMark en una tarea separada con aprobación.
6. **Deuda:** unificar la escritura de inventario de `PersistirRecompensas` en `InventarioJugador` y decidir si se añade idempotencia a `commitAction`.

---

## 8. Estado final

`COMPLETED` — F2–F5 implementados, revisados y auditados. Cambios en el working tree, sin commit ni push, sin tests ejecutados y sin modificaciones en `tests/`.
