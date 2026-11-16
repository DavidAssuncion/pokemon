# TASK-042 — Revisión independiente

- **Estado:** PASS
- **Revisor:** `reviewer` (sesión `ses_e362b6a82ffewa1PpckO05hjWc`)
- **Fecha:** 2026-11-11
- **Plan revisado:** v1 aprobado
- **Nota:** informe final del revisor, sin alterar sus conclusiones. No se ejecutaron tests (testing diferido).

---

# REVISIÓN TASK-042 — informe final

- **Tarea:** TASK-042 · **Plan:** v1 (aprobado) · **Rama:** `task-011`
- **Revisor:** independiente · **Fecha:** 2026-11-11
- **Alcance revisado:** correcciones posteriores a la primera revisión (panel Escape/foco, store `modales`, `contexto` del fragmento, paridad de `restante_label`, `whereNumber`).
- **Testing:** diferido. No se ha ejecutado la suite.

---

## 1. Veredicto final

**PASS**

No quedan BLOCKER, HIGH ni MEDIUM abiertos. Las dos correcciones mayores solicitadas están implementadas y verificadas por lectura; los hallazgos restantes son MENOR/OBSERVACIÓN sin impacto en datos, seguridad ni criterios de aceptación.

---

## 2. Estado de los hallazgos previos

| # | Hallazgo previo | Estado | Evidencia |
|---|---|---|---|
| 1 | **MAYOR** — Criterio 4: el panel no cerraba con Escape ni gestionaba foco | **Corregido** | `panel-expediciones.blade.php:17` (`x-effect` + `$nextTick(...focus())`), `:22` (`tabindex="-1"`), `:24` (`@keydown.escape.window` con guardas `panelAbierto && !historialAbierto && !detalleAbierto && !reclutamientoNavbar.abierto`); `expediciones.js:140-148` (`cerrarPanel()` devuelve foco a `[data-expediciones-trigger]` si estaba abierto) |
| 2 | **MAYOR** — Criterio 16: los modales no movían/restauraban foco, no atrapaban Tab ni bloqueaban scroll | **Corregido** | `comun.js:100-137` (store `modales`: `pila`, `focoPrevio`, `abrir` con `queueMicrotask(() => elemento.focus())`, `cerrar` restaura si `isConnected`, `esTope`); `comun.js:145-171` (`trampaDeFoco`); `modal.blade.php:34-36` (`x-data` + `$watch` de `abierto`), `:42` (Escape solo en tope), `:43` (Tab), `:47-50` (`x-ref="dialogo"`, `tabindex="-1"`); `app.blade.php:20` (`overflow-hidden` cuando `pila.length > 0`) |
| 3 | **MENOR** — Escape cerraba toda la pila de modales | **Corregido** | `modal.blade.php:42` (`if ($store.modales.esTope('{{ $id }}'))`); `comun.js:134-136` |
| 4 | **MENOR** — Confirmaciones anidadas a `z-50` en lugar de `z-[60]` | **Corregido** | `_modal-descartar.blade.php:13` y `_modal-descartar-todos.blade.php:13` (`capa="z-[60]"`); prop `capa` en `modal.blade.php:30,38` |
| 5 | **OBSERVACIÓN** — `contexto` fijo `historial` en el fragmento de página (IDs duplicados) | **Corregido** | `ExpedicionesPanelController.php:62-63` (`contexto=pagina\|historial`, default `historial`) y `:74`; `expediciones.js:192` (`contexto=historial`) y `:323` (`contexto=pagina`) |
| 6 | **OBSERVACIÓN** — `contexto` muerto en `ejecutarAccion(id, accion, contexto)` | **Pendiente (aceptado, no bloquea)** | `expediciones.js:249`: el parámetro sigue declarado y no se usa; `:78` lo pasa. Sin efecto funcional; deuda de limpieza |
| 7 | **OBSERVACIÓN** — `toActiva` no aplicaba la regla de `PENDIENTE_RESOLUCION` al tiempo restante | **Corregido** | `PresentadorExploraciones.php:233-234` (estado + `restanteResumen`) y `:254-255`; `restanteResumen()` en `:325-342` (PENDIENTE/TERMINADA/ARCHIVADA → `[0, 'Pendiente']`; indefinida → `[null, 'Indefinida']`) |
| 8 | **NIT** — Migración sin `: void` en el closure | **Pendiente (aceptado)** | `2026_11_10_000001_...php:19,30`: closure sin tipo de retorno. No altera comportamiento ni análisis |
| 9 | **OBSERVACIÓN de alcance** — `habitats/show.blade.php` mezcla hunks ajenos (comparación de PC, TASK-043) | **Aceptado / fuera de alcance** | El hunk de `confirmExploration` (`:1336-1351`) es el autorizado y es correcto; el resto del diff pertenece a otra tarea concurrente y no se atribuye a TASK-042 |

---

## 3. Verificación de los criterios 4 y 16

### Criterio 4 — panel: sin recorte, apertura/cierre, foco

| Requisito | Estado | Evidencia |
|---|---|---|
| No recortado por `overflow-x-auto` | Cumple | `navbar-acciones.blade.php:15` (grupo `relative shrink-0`) es hermano de `app.blade.php` (`overflow-x-auto` en el contenedor de enlaces); panel `z-40` en `panel-expediciones.blade.php:23` |
| Abre con click | Cumple | `navbar-acciones.blade.php:30` (`@click="...abrirPanel()"`) |
| Cierra con click | Cumple | `expediciones.js:83-96` (click fuera) y botón cerrar en `panel-expediciones.blade.php:39-46` |
| Cierra con Escape | **Cumple** | `panel-expediciones.blade.php:24`, con guardas para no cerrar si hay modal por encima |
| Foco al abrir | **Cumple** | `panel-expediciones.blade.php:17` (`x-effect` → `$nextTick` → `focus()` sobre `x-ref="panelExpediciones"`, `tabindex="-1"`) |
| Foco vuelve al trigger | **Cumple** | `expediciones.js:140-148` |

### Criterio 16 — teclado, Escape, foco, `aria-live`, móvil

| Requisito | Estado | Evidencia |
|---|---|---|
| Navegación por teclado en iconos/panel/modales | Cumple | Botones reales con `aria-label` (`navbar-acciones.blade.php:16-38`), panel enfocable (`:22`), diálogo `tabindex="-1"` (`modal.blade.php:49`) |
| Escape cierra (solo el superior) | **Cumple** | `modal.blade.php:42` + `comun.js:134-136`; panel con guardas (`panel-expediciones.blade.php:24`) |
| Retorno de foco | **Cumple** | `comun.js:117-132` (restaura `focoPrevio` si `isConnected`, incluido anidado) |
| Trampa de foco | **Cumple** | `modal.blade.php:43` + `comun.js:145-171` (ciclo primero/último, fallback al contenedor sin enfocables) |
| Bloqueo de scroll de fondo | **Cumple** | `app.blade.php:20` |
| `aria-live` de mensajes | Cumple | `panel-expediciones.blade.php:44-50` (`role="status"` + `aria-live="polite"`), `modal-reclutamiento.blade.php:47-53` |
| Móvil sin desbordar | Cumple | Panel `fixed inset-x-2 ... max-h-[70vh] overflow-y-auto` (`panel-expediciones.blade.php:23`); modales `p-4` + `max-h-[85vh] overflow-y-auto` (`modal.blade.php:38,50`) |

**Nota:** los criterios se verifican por lectura del markup/JS. El comportamiento dinámico real (orden de eventos foco/`x-show`, ciclo de Tab) no se ha ejecutado; queda como verificación manual del desarrollador (ver §5).

---

## 4. Hallazgos nuevos

### MENOR

- **`resources/js/stores/expediciones.js:140-148` — `cerrarPanel()` roba el foco al cerrar por click fuera.**
  Evidencia: `cerrarPanel()` enfoca incondicionalmente `[data-expediciones-trigger]` cuando `estabaAbierto`, y el listener `document.click` (`:83-96`) lo invoca en cualquier click externo. El foco del elemento clicado (p. ej. un `<input>`) ya se produjo en `mousedown` y se sobrescribe con el del icono.
  Impacto: al cerrar el panel haciendo click en un campo de la página, el usuario debe volver a hacer click para escribir. UX, sin impacto en datos.
  Recomendación: restaurar foco solo cuando el cierre no venga de un click fuera (p. ej. `cerrarPanel({ restaurarFoco = true })` y pasar `false` desde el listener delegado), o condicionar a `document.activeElement === document.body`.
  **Cierre posterior:** corregido con `cerrarPanel(restaurarFoco = true)` y `store.cerrarPanel(false)` en el listener de click fuera.

- **`resources/views/exploraciones/partials/_tarjeta-activa.blade.php:62-66` — concordancia textual con las nuevas etiquetas.**
  Evidencia: la tarjeta imprime `Quedan <span>{{ $exp['restante_label'] }}</span>`; con la paridad aplicada (`PresentadorExploraciones.php:325-342`) una vencida produce «Quedan Pendiente» y una indefinida «Quedan Indefinida».
  Impacto: solo presentación en la página `/exploraciones`; el panel (criterio 2/14) muestra `Indefinida`/`Pendiente` correctamente y el progreso 100.
  Recomendación: cambiar el texto a condicional (`@if($exp['restante_min'] > 0)` → «Quedan …», si no mostrar la etiqueta sola) o pasar la frase desde el presentador.
  **Cierre posterior:** corregido con `$restanteEsEstado` y ramas `@if/@else`; solo presentación, sin lógica de negocio.

### OBSERVACIÓN

- **`resources/js/stores/expediciones.js:249` — parámetro `contexto` sin uso** (heredado del hallazgo 6). Eliminarlo o usarlo; no bloquea.
- **`resources/views/layouts/partials/navbar-acciones.blade.php:27-38` — el icono no alterna (toggle).** `abrirPanel()` solo abre; el mismo botón no cierra el panel ya abierto (`expediciones.js:131-138`). El cierre está cubierto por X, Escape y click fuera, por lo que el criterio 4 se cumple; valorar `panelAbierto ? cerrarPanel() : abrirPanel()`.
- **`database/migrations/2026_11_10_000001_add_archived_at_to_exploraciones_activas.php:19,30` — closures sin `: void`** (NIT previo, aceptado).
- **`habitats/show.blade.php`** sigue arrastrando hunks de comparación de PC ajenos a TASK-042; deben excluirse del commit de esta tarea.

### Sin hallazgos nuevos en backend

- `ExpedicionesPanelController@index|contenido|detalle` siguen sin ejecutar `ProcesarExploracionesPendientes` ni mutar (`:43-52`, `:60-76`, `:82-105`). El nuevo parámetro `contexto` es puramente de presentación y **no puede exponer archivadas en la página**: `archivadas` solo se carga con `incluir_archivadas` (`:62,70`) y `_contenido.blade.php:70` exige además `$contexto === 'historial'` (defensa en profundidad).
- `toActiva` conserva el contrato previo: `restante_min`/`restante_label` (`:254-255`) más el bloque `camposEstado` (`:260`), con paridad exacta con `toResumen` (`:276`, `:304-305`) al compartir `restanteResumen()`.
- `->whereNumber('exploracion')` (`routes/exploraciones.php:18-28`) solo restringe a dígitos: no afecta a rutas legítimas (los ids son enteros; `preview` y `panel/contenido` están declaradas antes y son literales). Un id no numérico ahora da 404 de ruta en lugar de 404 de binding: mismo resultado observable.

---

## 5. Riesgos residuales

1. **Comportamiento dinámico de foco/trampa no ejecutado.** La corrección es correcta por lectura, pero el orden real (`$watch` → `x-show` → `queueMicrotask` → `focus()`, y el ciclo de Tab con contenido inyectado por `x-html`) requiere verificación manual en navegador. Es el único punto que no puedo cerrar sin ejecutar.
2. **`x-html` + foco.** Los fragmentos de historial/detalle se inyectan con `x-html` sin inicializar Alpine: si un botón del fragmento es el elemento enfocado al cerrar un modal, el foco se restaura solo si `isConnected` (`comun.js:129`); tras un `refrescarPagina()` el nodo puede quedar desconectado y el foco cae al `body`. Aceptable.
3. **Caché de 15 s sin polling.** Un panel abierto y sin cambio de foco puede mostrar datos obsoletos; mitigado con botón «Actualizar» y revalidación en foco (diseño §4.2).
4. **Historial sin paginación** y **`cerrar` borra también archivadas**: decisiones asumidas y aprobadas en el plan v1.
5. **Mezcla de cambios en `habitats/show.blade.php`** con TASK-043: riesgo de commit contaminado, no de comportamiento.
6. **PHPStan 2 errores en `ReclutamientoController.php:88,116`** (`staticMethod.dynamicCall` sobre `lockForUpdate`): patrón preexistente, no introducido por TASK-042; el resto de archivos de la tarea dan 0 errores (verificado: `src/Exploraciones/Presentation/PresentadorExploraciones.php` y `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php` → `[OK] No errors`).

---

## 6. Confirmación de alcance

- Los cambios revisados se limitan a los archivos de TASK-042 listados en `plan.md` §4: migración y modelo, dominio/aplicación/presentación de `src/Exploraciones`, `src/Reclutamiento` (DTO, colección, constructor, controlador API), `ExploracionActivaController`, `ReclutamientoController`, `PlayerController`, `routes/exploraciones.php`, `routes/player.php`, stores JS, `bootstrap.js`, `components/ui/modal.blade.php`, parciales de layout y de páginas, y el hunk autorizado de `habitats/show.blade.php`.
- **No se ha modificado ningún archivo durante esta revisión.**
- **No se han ejecutado tests ni migraciones.** Verificación realizada: lectura de código, `git diff`/`git status`, `php -l` y PHPStan focalizado sobre los archivos de la tarea.
- Cambios ajenos detectados y **no revisados**: `BuildShowdownMovesets`, `SendasController`, `PreferenciasShowdown`, `ReclutadoController`, `app/Support/Pc/`, `src/Shared/Domain/Pc/`, `partials/comparacion-pc*`, `bugs.txt`, `TASK-032..041` y los hunks de comparación de PC de `habitats/show.blade.php`.

---

### Conclusión

Las correcciones 1-5 están implementadas correctamente y cubren los criterios 4 y 16 del plan. Los dos MENOR nuevos son de presentación/UX y no afectan a datos, seguridad, contratos ni a los criterios de aceptación. **Veredicto: PASS**, con recomendación de atender los dos MENOR antes del cierre del módulo y de separar el commit de los cambios ajenos de `habitats/show.blade.php`.
