# TASK-042 — Plan v1

- **ID:** TASK-042
- **Versión:** v1
- **Fecha:** 2026-11-10
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Reformular la navbar para que Reclutamiento y Exploraciones sean iconos FontAwesome, sin ocupar espacio permanente con listados. Al pulsar:

- **Exploraciones:** panel de tres columnas (Pokémon, progreso y tiempo restante), con flecha para ver el detalle y opción de archivar a la derecha en las completadas. En la parte inferior derecha, «Ver historial», que abre un modal con activas, terminadas pendientes y archivadas.
- **Reclutamiento:** modal funcional con reclutar, descartar y descartar todos, equivalente a la página actual.

Los datos se mantienen en una colección cliente cacheada en memoria, se cargan bajo demanda, se refrescan al recuperar el foco y se actualizan al instante tras las acciones, sin recargas completas. El backend devuelve la colección canónica ya ordenada; el cliente no calcula orden, progreso ni permisos.

## 2. Alcance

### 2.1. Incluido

- Reestructurar la navbar para sacar los iconos de acciones del contenedor con `overflow-x-auto`.
- Iconos provisionales `fa-user-plus` (Reclutamiento) y `fa-compass` (Exploraciones), con `aria-label`.
- Panel de expediciones con tres columnas y botones de detalle/archivar.
- Modal de detalle con la tarjeta completa reutilizada de la página.
- Modal de historial con activas, pendientes y archivadas; acciones funcionales.
- Modal de reclutamiento con grid, reclutar, descartar y descartar todos.
- Componente reutilizable `x-ui.modal` y confirmaciones anidadas.
- Persistencia de archivado mediante `archived_at` nullable en `exploraciones_activas`.
- Endpoints de lectura compacta y fragmentos HTML, sin efectos secundarios en GET.
- Endpoints de acción que devuelven la colección canónica para reemplazar el estado cliente.
- Stores Alpine singleton en memoria, registrados antes de `Livewire.start()`.
- Extracción de parciales Blade reutilizables para panel, historial, detalle, página y reclutamiento.
- Actualización de `/exploraciones` y `/reclutamiento` para compartir el store y evitar recargas completas.
- Actualización del flujo de creación de expedición desde hábitats: sin `location.reload()`, con evento `expediciones:actualizada`.
- Verificación estática, revisión independiente y auditoría de seguridad.

### 2.2. Fuera de alcance

- Tabla de histórico separada o borrado lógico con `SoftDeletes`. El archivo se representa con `archived_at`.
- Desarchivar una expedición.
- Paginación o retención del historial.
- Polling, SSE, WebSocket o notificaciones push.
- `localStorage`/`sessionStorage` para estas colecciones.
- Cambios en el proceso programado `exploraciones:procesar`.
- Rediseño del sistema de combate, hábitats, reclutados o equipos.
- Iconos definitivos: se ajustarán después.
- Badges/contadores en los iconos de la navbar.
- Tests nuevos, modificados o ejecutados.
- Dependencias nuevas.
- Cambios de comportamiento en `cerrar` (seguirá borrando), salvo la nueva acción de archivar.
- Historial de expediciones canceladas.

## 3. Arquitectura y contratos

Se sigue el diseño completo de `architecture.md`. Resumen de decisiones vinculantes:

1. **Dos módulos existentes**, sin entidad `Expedicion`: una expedición es una fila de `exploraciones_activas`.
2. **JSON compacto** para la colección cacheable: `GET /api/exploraciones` y `GET /api/reclutamiento`.
3. **HTML de servidor** para detalle e historial: `GET /exploraciones/panel/contenido` y `GET /exploraciones/{exploracion}/panel/detalle`, reutilizando parciales Blade sin Alpine.
4. **GET sin efectos secundarios:** los endpoints nuevos no ejecutan `ProcesarExploracionesPendientes`.
5. **Archivado persistido** con `archived_at`; solo completadas no canceladas; idempotente; oculta del panel y de la página; permanece en el historial.
6. **Caché cliente en memoria** con `Alpine.store`; sin persistencia; TTL de revalidación de 15 s; refresco al abrir, al recuperar foco/visibilidad y tras acciones.
7. **Backend autoritativo:** cada mutación devuelve la colección canónica completa; el cliente la reemplaza. «Reindexar» = reemplazar por la colección ordenada del servidor.
8. **Páginas existentes** comparten store y parciales; `/exploraciones` refresca su fragmento HTML sin recarga; `/reclutamiento` hidrata el store y comparte el grid.
9. **Navbar reestructurada:** enlaces con scroll; iconos y panel en un grupo `relative` fuera del `overflow-x-auto`; bloque de usuario en flujo flex.
10. **Escala z-index:** header `z-30`, panel `z-40`, modales `z-50`, confirmaciones `z-[60]`, toast `z-[70]`.
11. **Sin dependencias nuevas.**

### 3.1. Endpoints

| Método | Ruta | Respuesta | Efecto |
|---|---|---|---|
| `GET` | `/api/exploraciones` | JSON compacto de activas + pendientes, sin archivadas | Solo lectura |
| `GET` | `/api/reclutamiento` | JSON compacto de reclutables | Solo lectura |
| `GET` | `/exploraciones/panel/contenido?incluir_archivadas=0\|1` | Fragmento HTML | Solo lectura |
| `GET` | `/exploraciones/{exploracion}/panel/detalle` | Fragmento HTML de la tarjeta | Solo lectura |
| `POST` | `/exploraciones/{exploracion}/archivar` | JSON con expedición y colección | Persiste `archived_at` |
| `POST` | `/api/exploraciones/store-individual` | JSON ampliado con expedición y colección | Crea expedición |
| `POST` | `/exploraciones/{exploracion}/cancelar` | JSON ampliado con colección | Cancela |
| `POST` | `/exploraciones/{exploracion}/recoger` | JSON ampliado con colección; 409 si ocupado | Recoge |
| `POST` | `/exploraciones/{exploracion}/cerrar` | JSON ampliado con colección | Borra |
| `POST` | `/reclutamiento/recruit` | JSON ampliado con reclutables | Recluta |
| `POST` | `/reclutamiento/discard` | JSON ampliado con reclutables | Descarta |
| `POST` | `/reclutamiento/discard-all` | JSON ampliado con reclutables | Descarta todos |

Todas las rutas nuevas se registran dentro del grupo `auth` existente. `{exploracion}` usa `whereNumber`. Los controladores comprueban `user_id` explícitamente además del global scope.

### 3.2. Modelo y DTOs

- Migración: `archived_at` timestamp nullable en `exploraciones_activas`, índice `(user_id, archived_at)`.
- Modelo: añadir `archived_at` a `$fillable` y cast `datetime`.
- DTOs: `ExpedicionResumen`, `ExpedicionesResumenCollection`, `ReclutableResumen`, `ReclutablesResumenCollection`.
- Dominio/aplicación: `EstadoExpedicion`, calculadores de estado/progreso, consultor, formateador de tiempo, agrupador, constructor de resumen, archivador, constructor de reclutables.
- Presentador: ampliar `PresentadorExploraciones` con etiquetas de fecha y estado de archivado.
- Reglas: archivar solo completada no cancelada; idempotente; `422` para activa/cancelada; `404` para otro usuario; `cerrar` sigue borrando.

## 4. Archivos que se crearán o modificarán

### Base de datos

- `database/migrations/2026_11_10_000001_add_archived_at_to_exploraciones_activas.php` — **database**.
- `app/Models/ExploracionActiva.php` — **database**.

### Backend

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
- `src/Exploraciones/Presentation/PresentadorExploraciones.php` — modificar.
- `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php` — nuevo.
- `src/Reclutamiento/Infra/Controllers/ReclutamientoApiController.php` — nuevo.
- `app/Http/Controllers/ExploracionActivaController.php` — modificar.
- `app/Http/Controllers/ReclutamientoController.php` — modificar.
- `app/Http/Controllers/PlayerController.php` — modificar.
- `routes/exploraciones.php` — modificar.
- `routes/player.php` — modificar.

### Frontend JS

- `resources/js/stores/comun.js` — nuevo.
- `resources/js/stores/expediciones.js` — nuevo.
- `resources/js/stores/reclutamiento.js` — nuevo.
- `resources/js/bootstrap.js` — modificar.

### Blade de layout y modales

- `resources/views/components/ui/modal.blade.php` — nuevo.
- `resources/views/layouts/partials/navbar-acciones.blade.php` — nuevo.
- `resources/views/layouts/partials/panel-expediciones.blade.php` — nuevo.
- `resources/views/layouts/partials/modal-reclutamiento.blade.php` — nuevo.
- `resources/views/layouts/partials/modal-historial-expediciones.blade.php` — nuevo.
- `resources/views/layouts/partials/modal-detalle-expedicion.blade.php` — nuevo.
- `resources/views/layouts/app.blade.php` — modificar.

### Blade de páginas y parciales

- `resources/views/exploraciones/partials/_contenido.blade.php` — nuevo.
- `resources/views/exploraciones/partials/_tarjeta-activa.blade.php` — nuevo.
- `resources/views/exploraciones/partials/_tarjeta-terminada.blade.php` — nuevo.
- `resources/views/exploraciones/partials/_detalle.blade.php` — nuevo.
- `resources/views/exploraciones/index.blade.php` — modificar.
- `resources/views/reclutamiento/partials/_grid.blade.php` — nuevo.
- `resources/views/reclutamiento/partials/_modal-descartar.blade.php` — nuevo.
- `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php` — nuevo.
- `resources/views/reclutamiento/index.blade.php` — modificar.
- `resources/views/habitats/show.blade.php` — modificar solo el hunk de `confirmExploration`.

### Responsables

- `database`: migración y modelo.
- `backend`: dominio, aplicación, presentador, controladores y rutas.
- `frontend`: stores JS, layout, parciales, modales y páginas.
- `reviewer`: revisión independiente.
- `security`: auditoría de endpoints y datos.
- `documenter`: no aplica salvo que el desarrollador pida ADR; el diseño queda en `architecture.md`.

## 5. Orden de ejecución y dependencias

1. **Database:** migración `archived_at` y modelo.
2. **Backend dominio/aplicación:** enum, calculadores, DTOs, colecciones, consultores, constructores y archivador.
3. **Backend presentación/HTTP:** presentador, controladores, rutas y respuestas ampliadas.
4. **Frontend stores:** `comun.js`, `expediciones.js`, `reclutamiento.js`, registro en `bootstrap.js`.
5. **Frontend layout/modales:** componente modal, navbar, panel, modales.
6. **Frontend páginas/parciales:** extraer parciales, adaptar `/exploraciones`, `/reclutamiento` y el hunk de hábitats.
7. **Verificación estática:** sintaxis, Pint, rutas, Blade, build, greps de alcance.
8. **Revisión y seguridad:** `reviewer` y `security`.
9. **Correcciones y cierre:** informe final.

Dependencias:

- El frontend no empieza hasta que los contratos de backend y las respuestas estén implementados.
- Los parciales de página dependen del presentador ampliado.
- El store depende de las rutas y los formatos JSON/HTML.
- `resources/views/habitats/show.blade.php` tiene cambios locales preexistentes; se aplicará solo el hunk autorizado, sin reformatear ni revertir el resto.
- No hay implementación paralela sobre archivos compartidos.

## 6. Criterios de aceptación

1. Reclutamiento y Exploraciones aparecen como iconos FontAwesome con `aria-label`; dejan de ser enlaces de texto.
2. El panel de expediciones muestra tres columnas: Pokémon, barra de progreso y tiempo restante; la barra usa `aria-valuenow` y los valores vienen del backend.
3. La flecha de cada fila abre un modal con la tarjeta completa servida por el backend.
4. El panel no se recorta por `overflow-x-auto`; se abre/cierra con click, Escape y click fuera; el foco vuelve al trigger.
5. «Ver historial» abre un modal con activas, terminadas pendientes y archivadas, con su estado visible.
6. Archivar solo se ofrece en completadas no archivadas; persiste `archived_at`; es idempotente; devuelve `422` para activas/canceladas y `404` para otro usuario.
7. Tras archivar, la expedición desaparece del panel y de la página, pero permanece en el historial como «Archivada».
8. `cerrar` sigue borrando la fila, también si estaba archivada; el panel y el historial la quitan.
9. El icono de Reclutamiento abre directamente el modal; reclutar, descartar y descartar todos actualizan la lista sin recargar.
10. Abrir dos veces el panel dentro de 15 s no repite la petición salvo revalidación en segundo plano; al recuperar foco con panel/modal abierto se revalida; no hay `localStorage`, `sessionStorage`, `setInterval` ni polling.
11. Tras crear una expedición, reclutar, descartar, cancelar, recoger, cerrar o archivar, la colección mostrada coincide con la devuelta por el backend; el cliente no ordena.
12. Crear una expedición desde hábitats cierra el modal, marca al Pokémon como en expedición, actualiza la navbar y no ejecuta `location.reload()`.
13. `/exploraciones` actualiza sus secciones y contadores sin recarga completa tras las acciones; `/reclutamiento` comparte el estado con el modal.
14. Los GET nuevos no ejecutan procesamiento ni mutan datos; una expedición con `fin` pasado y `regreso = null` se muestra como «Pendiente de procesar», progreso 100.
15. Sin sesión redirige a login; un usuario B recibe `404` al consultar o archivar una expedición de A; los POST sin CSRF fallan.
16. Navegación por teclado, Escape, retorno de foco y `aria-live` funcionan; en móvil el panel ocupa el ancho disponible y los modales hacen scroll sin desbordar.

## 7. Verificación requerida (sin tests)

- `php -l` sobre todos los PHP nuevos/modificados.
- `vendor/bin/pint --dirty --format agent` para corregir estilo; no usar `--test` como sustituto.
- `php artisan route:list --path=exploraciones` y `php artisan route:list --path=api/reclutamiento`.
- `php artisan view:cache` y `php artisan view:clear`.
- `npm run build` para validar Vite.
- `git diff --check`.
- Greps de alcance:
  - no deben quedar `exploracionesPage` ni `reclutamientoApp`;
  - no debe quedar `location.reload` en el flujo de `confirmExploration`;
  - los parciales de `exploraciones/partials` no deben contener directivas Alpine;
  - no debe aparecer `withoutUserScope` en los controladores nuevos;
  - no deben quedar `localStorage`/`sessionStorage`/`setInterval` para estas colecciones.
- Verificación manual de criterios de aceptación por el desarrollador.
- **No se ejecutan tests ni la suite.**

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Estado desfasado por el proceso programado cada 5 min | Estado «Pendiente de procesar», revalidación al abrir/foco y botón «Actualizar»; sin polling. |
| HTML inyectado con `x-html` sin inicializar Alpine | Parciales JS-free: `<details>`, fechas de servidor y botones `data-*`; listener delegado global. |
| Carrera entre archivar, cerrar y el procesador | Transacción + `lockForUpdate`; `422`/`404`; el cliente refresca y resincroniza. |
| Colección completa en cada mutación | Payload acotado y compacto; sin eventos ni diario; se evita lógica de orden en cliente. |
| Historial sin paginación | Índice `(user_id, archived_at)`; se documenta como deuda futura. |
| Cambios locales preexistentes en `habitats/show.blade.php` | Aplicar solo el hunk de `confirmExploration`; revisar `git diff` antes y después. |
| Etiquetas de tiempo estáticas mientras el panel está abierto | Se actualizan al reabrir, al recuperar foco o con «Actualizar»; sin polling. |
| Multi-pestaña desfasada | Sin almacenamiento persistente; revalidación en foco; se acepta. |
| `cerrar` borra también archivadas | Comportamiento actual conservado y documentado; si se quiere archivo permanente, se cambia en otra tarea. |
| Complejidad de 45 archivos | Implementación por fases con contratos congelados y revisión al final. |

## 9. Rollback

- Revertir los archivos modificados y eliminar los nuevos.
- `php artisan migrate:rollback --step=1` para eliminar `archived_at` y su índice.
- No hay borrado de datos existentes: la migración solo añade una columna nullable.
- Si se detecta un problema en producción, desplegar el código anterior y revertir la migración; las filas con `archived_at` quedarían con la columna eliminada, sin pérdida de expediciones.
- No se hace `git push`.

## 10. Decisiones asumidas y preguntas abiertas

Decisiones asumidas (si el desarrollador no indica lo contrario al aprobar):

1. `cerrar` sigue borrando también las archivadas.
2. No se implementa «desarchivar».
3. Las canceladas no aparecen en panel ni historial.
4. Se devuelven todas las archivadas, sin paginación ni retención.
5. El panel incluye terminadas pendientes con estado «Terminada» y progreso 100.
6. Archivar también está disponible desde el detalle.
7. Las etiquetas de fecha se formatean en la zona horaria del servidor; los ISO se mantienen en el HTML si se necesita.
8. Iconos provisionales `fa-user-plus` y `fa-compass`.
9. Sin badges/contadores en los iconos.
10. El panel se abre solo con click, no con hover.

Preguntas abiertas para el desarrollador (no bloquean la aprobación; se aplican los defaults):

- ¿«Cerrar resultados» debe seguir borrando una expedición archivada o quedar deshabilitado para conservarla?
- ¿Se necesita «desarchivar»?
- ¿Debe haber un contador visual en los iconos?
- ¿El historial debe paginarse o limitarse en el futuro?

## 11. Aprobación

Este plan no autoriza implementación. Se requiere una respuesta explícita que mencione la versión, por ejemplo:

- `Aprobar plan v1`
- `Implementa el plan v1`
- `Apruebo la versión 1 del plan`

Si se solicita cualquier cambio de alcance, se generará `plan v2` y se pedirá una nueva aprobación.
