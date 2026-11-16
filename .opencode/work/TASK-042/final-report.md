# TASK-042 — Informe final

- **ID:** TASK-042
- **Título:** Navbar de expediciones y reclutamiento con caché cliente
- **Estado:** COMPLETED
- **Fecha:** 2026-11-11
- **Rama:** `task-011`
- **Plan aprobado:** v1 (`approval.md`, texto literal «Aprobar plan v1»)
- **Revisión:** PASS (`review.md`)
- **Seguridad:** PASS con hallazgos (`security.md`)

---

## 1. Resumen

Se ha reformulado la navbar para que Reclutamiento y Exploraciones sean iconos FontAwesome con `aria-label`, sustituyendo los enlaces de texto:

- **Exploraciones** abre un panel de tres columnas (Pokémon, progreso y tiempo restante), con flecha de detalle y opción de archivar a la derecha en las completadas. «Ver historial», abajo a la derecha, abre un modal con activas, pendientes y archivadas.
- **Reclutamiento** abre directamente un modal funcional con grid, reclutar, descartar y descartar todos.
- Ambas colecciones se cachean en memoria mediante stores Alpine, se cargan bajo demanda, se revalidan al recuperar el foco (TTL 15 s) y se reemplazan por la colección canónica del backend tras cada acción, sin recargas completas ni polling.
- El archivado se persiste con `archived_at`; una expedición archivada desaparece del panel y de la página, pero permanece en el historial. «Cerrar resultados» sigue borrando la fila, también si estaba archivada.
- Se extrajeron parciales Blade reutilizables y JS-free para las tarjetas, el detalle, el historial y el grid de reclutamiento.

## 2. Archivos creados y modificados

### Base de datos
- `database/migrations/2026_11_10_000001_add_archived_at_to_exploraciones_activas.php` (nuevo): columna `archived_at` nullable + índice `(user_id, archived_at)`.
- `app/Models/ExploracionActiva.php`: `archived_at` en `$fillable` y cast `datetime`.

### Backend — dominio y aplicación
Nuevos:
- `src/Exploraciones/Domain/EstadoExpedicion.php`
- `src/Exploraciones/Domain/CalculadorEstadoExpedicion.php`
- `src/Exploraciones/Domain/CalculadorProgresoExpedicion.php`
- `src/Exploraciones/Domain/DataTransferObjects/ExpedicionResumen.php`
- `src/Exploraciones/Domain/Collections/ExpedicionesResumenCollection.php`
- `src/Exploraciones/App/ConsultorEstadoExpedicion.php`
- `src/Exploraciones/App/FormateadorTiempoRestante.php`
- `src/Exploraciones/App/ExpedicionesAgrupadas.php`
- `src/Exploraciones/App/ConsultarExpedicionesUsuario.php`
- `src/Exploraciones/App/ConstructorExpedicionesResumen.php`
- `src/Exploraciones/App/ArchivarExpedicion.php`
- `src/Reclutamiento/Domain/DataTransferObjects/ReclutableResumen.php`
- `src/Reclutamiento/Domain/Collections/ReclutablesResumenCollection.php`
- `src/Reclutamiento/App/ConstructorReclutablesResumen.php`
- `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php`
- `src/Reclutamiento/Infra/Controllers/ReclutamientoApiController.php`

Modificados:
- `src/Exploraciones/Presentation/PresentadorExploraciones.php` (`toView` con archivadas, `toResumen`, estados/flags/fechas, `restanteResumen`).
- `app/Http/Controllers/ExploracionActivaController.php` (respuestas incrementales, ownership, `no-store` en GET del panel).
- `app/Http/Controllers/ReclutamientoController.php` (ownership y colección canónica en respuestas).
- `app/Http/Controllers/PlayerController.php` (`reclutamiento()` usa `ConstructorReclutablesResumen` y el usuario autenticado).
- `routes/exploraciones.php`, `routes/player.php`.

### Frontend
Nuevos:
- `resources/js/stores/comun.js` (fetch/CSRF, store de modales y trampa de foco).
- `resources/js/stores/expediciones.js`, `resources/js/stores/reclutamiento.js`.
- `resources/views/components/ui/modal.blade.php`.
- `resources/views/layouts/partials/navbar-acciones.blade.php`, `panel-expediciones.blade.php`, `modal-historial-expediciones.blade.php`, `modal-detalle-expedicion.blade.php`, `modal-reclutamiento.blade.php`.
- `resources/views/exploraciones/partials/_contenido.blade.php`, `_detalle.blade.php`, `_tarjeta-activa.blade.php`, `_tarjeta-terminada.blade.php`.
- `resources/views/reclutamiento/partials/_grid.blade.php`, `_modal-descartar.blade.php`, `_modal-descartar-todos.blade.php`.

Modificados:
- `resources/js/bootstrap.js` (registro de stores antes de `Livewire.start()`).
- `resources/views/layouts/app.blade.php` (navbar, modales, `x-init`, scroll lock).
- `resources/views/exploraciones/index.blade.php` (contenedor compartido, sin JS de página).
- `resources/views/reclutamiento/index.blade.php` (store + grid compartido).
- `resources/views/exploraciones/_evento.blade.php`, `_caramelo.blade.php` (JS-free; commit local `90c7af9`).
- `resources/views/habitats/show.blade.php` (solo hunk de `confirmExploration`: sin `location.reload`, evento `expediciones:actualizada`).

### Tooling
- `phpstan-baseline.php`: solo eliminación de 2 bloques obsoletos de `PlayerController` (12 líneas). Sin entradas nuevas.

## 3. Decisiones y ADR

- No se creó ADR nuevo; las decisiones están en `architecture.md`.
- Una expedición es una fila de `exploraciones_activas`; no se creó entidad ni tabla nueva.
- JSON compacto para la colección cacheable (`GET /api/exploraciones`, `GET /api/reclutamiento`); HTML de servidor JS-free para detalle e historial.
- GET nuevos sin efectos secundarios: no ejecutan `ProcesarExploracionesPendientes`.
- Archivado con `archived_at`, solo completadas no canceladas, idempotente, 422 para activa/cancelada y 404 para otro usuario.
- Caché cliente en memoria, sin `localStorage`/`sessionStorage`, sin polling; TTL de revalidación 15 s.
- El backend es autoritativo: cada mutación devuelve la colección completa y el cliente la reemplaza; no ordena ni recalcula.
- Pila de modales con foco, Escape solo en el tope, trampa de Tab y scroll lock; panel con foco y retorno al trigger.
- Confirmaciones anidadas con `z-[60]`; panel `z-40`, modales `z-50`, toast `z-[70]`.
- `cerrar` sigue borrando también las archivadas; no se implementó «desarchivar»; canceladas fuera de panel e historial; sin paginación; iconos provisionales `fa-user-plus` y `fa-compass`.

## 4. Verificación ejecutada

Sin tests (testing diferido, según plan y reglas del proyecto).

| Comprobación | Resultado |
|---|---|
| `php -l` sobre 24 archivos PHP de la tarea | Sin errores |
| `vendor/bin/pint --format agent` (archivos de la tarea, sin baseline) | `pass` |
| `composer run analyse` / PHPStan L6 | 34 errores, **0 en archivos de TASK-042**; todos preexistentes/ajenos |
| `php artisan route:list --path=exploraciones` | 11 rutas correctas, bajo `auth`; `whereNumber` en detalle/archivar/recoger/cancelar/cerrar |
| `php artisan route:list --path=api/reclutamiento` | `GET api/reclutamiento` presente |
| `php artisan view:cache` / `view:clear` | Correcto |
| `npm run build` | Correcto (70 módulos; `z-[60]` y `overflow-hidden` generados) |
| `git diff --check` (archivos de la tarea) | Sin errores; único aviso ajeno en `bugs.txt` |
| Greps de alcance | Sin `exploracionesPage`, `reclutamientoApp`, `postAction`, `location.reload` en hábitats, Alpine en parciales de exploraciones, `localStorage`/`sessionStorage`/`setInterval`, `withoutUserScope` |
| Tests / migraciones | No ejecutados |

Errores PHPStan restantes (preexistentes/ajenos): `BuildShowdownMovesets` 17, `SendasController` 8, `PreferenciasShowdown` 2, `ReclutamientoController` 2 (falso positivo de `lockForUpdate`), `ReclutadoController` 2, `CombateExploracion` 1.

## 5. Hallazgos de revisión y seguridad

### Revisión (`review.md`) — PASS
- Criterios 4 y 16 verificados: Escape, foco al abrir/cerrar, trampa de Tab, scroll lock, `aria-live` y responsive.
- Dos MENOR cerrados antes del cierre: foco robado al cerrar el panel por click fuera y texto «Quedan Pendiente/Indefinida» en la tarjeta activa.
- Observaciones no bloqueantes: parámetro `contexto` muerto en `ejecutarAccion`, icono sin toggle, closures sin `: void` en la migración, hunks ajenos en `habitats/show.blade.php`.

### Seguridad (`security.md`) — PASS con hallazgos
- Sin CRITICAL ni HIGH. Autenticación, CSRF, autorización/IDOR, mass assignment y XSS resisten.
- **MEDIO aceptado:** historial sin paginación y colección completa en cada mutación (auto-DoS acotado al propio usuario; aceptado en plan v1).
- **BAJO:** respuestas POST sin `no-store` (sin fuga demostrada); `onerror` con `$candySrc` de slug persistido (sin entrada de usuario; sink latente).
- **INFORMATIVO:** expresiones Alpine crudas en `x-ui.modal` (llamadores con literales), `archived_at` en `$fillable` (sin request que lo acepte), `discardAll` solo con global scope, advisories de dependencias preexistentes.

## 6. Riesgos residuales

1. El comportamiento dinámico de foco, trampa de Tab y orden `$watch`/`x-show`/`focus()` no se ejecutó; requiere verificación manual en navegador.
2. Tras `refrescarPagina()` con `x-html`, un nodo enfocado puede quedar desconectado y el foco caer al `body`; aceptado.
3. Caché de 15 s sin polling: un panel abierto puede mostrar datos obsoletos hasta reabrir, recuperar foco o pulsar «Actualizar»; aceptado.
4. Historial sin paginación y `cerrar` borrando archivadas: decisiones asumidas en el plan.
5. `habitats/show.blade.php` contiene hunks de otra tarea (comparación de PC); al commitear TASK-042 debe aislarse solo el hunk de `confirmExploration`.
6. PHPStan mantiene 34 errores preexistentes/ajenos; no se añadió deuda al baseline.

## 7. Próximos pasos

1. Verificación manual en navegador de los criterios de aceptación, especialmente foco/Escape/Tab/scroll y los flujos de archivar, cerrar y recargar.
2. Al preparar el commit, separar los cambios de TASK-042 del WIP ajeno (`habitats/show.blade.php`, `bugs.txt`, TASK-032..041, archivos de comparación de PC). No se ha hecho `push`; existe un commit local `90c7af9` del subagente frontend con solo `_evento.blade.php` y `_caramelo.blade.php`.
3. Si el desarrollador lo autoriza, actualizar `src/Exploraciones/context.md` y `docs/context.md` con `archived_at`, los endpoints del panel y las reglas de archivado (el plan v1 excluyó `documenter`).
4. Endurecimientos opcionales fuera de alcance: `Cache-Control: no-store` en POST, `Js::from()` en `onerror`, `where('user_id')` explícito en `discardAll`, paginación/retención del historial.

## 8. Estado de los artefactos

- `request.md`: petición literal y aclaraciones.
- `analysis.md`: análisis del `analyst` (COMPLETED).
- `architecture.md`: diseño del `architect` (COMPLETED).
- `plan.md`: plan v1 aprobado.
- `approval.md`: aprobación explícita registrada.
- `tasks.md`: seguimiento completado.
- `execution.md`: preflight, fases y verificación.
- `review.md`: revisión independiente PASS.
- `security.md`: auditoría PASS con hallazgos.
- `final-report.md`: este informe.
