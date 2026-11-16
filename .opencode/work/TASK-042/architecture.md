# TASK-042 — Arquitectura

- **Estado:** COMPLETED
- **Arquitecto:** `architect` (sesión `ses_e3a56ff51ffectZPjBGy9A4yTT`)
- **Nota:** resultado del arquitecto, sin alterar sus conclusiones.

---

# TASK-042 — Arquitectura de la navbar de expediciones y reclutamiento

**Base:** `.opencode/work/TASK-042/analysis.md` y las 5 decisiones vinculantes del desarrollador.
**Estado:** diseño, sin implementación. No se ha modificado ningún archivo.
**Aclaración terminológica:** “expedición” y “exploración” son sinónimos en este repositorio; las rutas y clases existentes usan `exploraciones` y así se mantienen. La UI puede usar “Expediciones”.

---

## 1. Decisiones de diseño

1. **Dos bounded contexts existentes, sin aggregate nuevo.** Se trabaja sobre `Exploraciones` y `Reclutamiento`. No se crea una entidad `Expedicion` ni una tabla nueva: una expedición es una fila de `exploraciones_activas`.

2. **Separación lectura compacta / detalle HTML.**
   - La colección cacheable y reindexable del panel es **JSON compacto**: `GET /api/exploraciones`.
   - El detalle y el historial se sirven como **fragmentos HTML renderizados en servidor** reutilizando los parciales Blade de la página. No se serializa a JSON la tarjeta terminada completa.
   - Justificación: la tarjeta terminada contiene diario, derrotados, capturas, caramelos, items, pérdidas, flags derivados y spans de columnas calculados por `ResultadoExploracionVista`. Reproducirla en Alpine duplicaría presentación y lógica derivada; el HTML reutiliza el backend y respeta `frontend-minimal-logic.md`. El coste es un payload HTML mayor, aceptable porque se carga bajo demanda.

3. **Los GET nuevos no mutan estado.** `GET /api/exploraciones`, `GET /exploraciones/panel/contenido` y `GET /exploraciones/{id}/panel/detalle` solo leen. No llaman a `ProcesarExploracionesPendientes`. El procesamiento sigue ocurriendo en `GET /exploraciones` (página), en `recoger` y en el comando programado.

4. **Archivado como estado persistido, no como tabla histórica.** Se añade `archived_at` nullable a `exploraciones_activas`. Archivar solo se permite en expediciones completadas y no canceladas; es idempotente; oculta la fila del panel y de la página, pero la conserva en el historial. `cerrar` sigue borrando la fila, también si está archivada.

5. **Caché cliente en memoria, sin `localStorage`/`sessionStorage`.** Se implementa con `Alpine.store`, singleton compartido por layout, página y modales. Carga al abrir panel/modal, revalidación al recuperar foco/visibilidad y tras acciones. Sin polling.

6. **El backend es la única fuente de orden, progreso, tiempo y permisos de acción.** Las mutaciones devuelven la **colección canónica completa** ya ordenada. El cliente no inserta, ordena ni recalcula: reemplaza la colección. Esto elimina lógica de reindexado en JS. “Reindexar” = `reemplazar(coleccionCanonica)`.

7. **Los fragmentos HTML son JS-free.** No contienen `x-data`, `x-show`, `@click` ni `<script>`. Los colapsables usan `<details>/<summary>`; las fechas se sirven ya formateadas; los botones usan `data-expedicion-id` / `data-expedicion-accion`. Un único listener delegado en el store gestiona las acciones. Esto permite inyectarlos con `x-html` sin `Alpine.initTree`.

8. **Navbar reestructurada.** Los enlaces de texto se mantienen en un contenedor con scroll; los iconos de Reclutamiento/Exploraciones se mueven a un grupo de acciones `relative` **fuera** del `overflow-x-auto`. El panel se ancla a ese grupo y deja de recortarse. El bloque de usuario deja de ser `absolute` y pasa a flex para evitar solapes.

9. **Escala z-index explícita:** header `z-30`, panel `z-40` (dentro del stacking context del header), modales `z-50`, confirmaciones anidadas `z-[60]`, toast existente `z-[70]`. El header no se sube por encima de los modales.

10. **Modales reutilizables.** Se crea `x-ui.modal` para scrim, `role="dialog"`, `aria-modal`, Escape, cierre y foco. Los modales de descarte se convierten en confirmaciones anidadas del modal de reclutamiento; las de archivar/cerrar desde historial/detalle usan el mismo componente.

11. **Páginas existentes.**
    - `/exploraciones` deja de recargar la página tras acciones: refresca su contenido con el mismo fragmento HTML del historial (sin archivadas) y conserva su render inicial en servidor.
    - `/reclutamiento` pasa a consumir el store global; su grid y modales se extraen a parciales compartidos.
    - La creación desde hábitats elimina `location.reload()`: actualiza `reclutadosEnExploracion`, cierra el modal y emite un evento con la colección canónica.

12. **Sin dependencias nuevas.** Todo se resuelve con Laravel, Alpine, Tailwind y FontAwesome ya instalados.

### Capas y dirección de dependencias

- **Dominio (`src/Exploraciones/Domain`, `src/Reclutamiento/Domain`)**: `EstadoExpedicion`, calculadores puros, DTOs de presentación y colecciones tipadas. Sin Eloquent ni HTTP.
- **Aplicación (`src/.../App`)**: consulta/partición de expediciones, construcción de DTOs, archivado, consulta de estado. Puede usar Eloquent y Laravel.
- **Infraestructura (`src/.../Infra`, `app/Models`, migración, rutas)**: controladores nuevos, persistencia, binding.
- **Presentación (`src/Exploraciones/Presentation` existente, Blade, Alpine)**: el presentador actual se conserva para el HTML; los nuevos contratos JSON usan DTOs.

---

## 2. Modelo de datos y migración

### Tabla afectada

`exploraciones_activas` (sin tabla nueva).

| Columna | Tipo | Nulo | Índice | Notas |
|---|---|---:|---|---|
| `archived_at` | `timestamp` | sí | compuesto `(user_id, archived_at)` | `null` = visible en panel/página; no `null` = archivada |

**Nombre en inglés** (`archived_at`) por la regla de columnas en inglés de `ddd-modules.md`, aunque las columnas legadas estén en español. No se renombran columnas existentes.

### Migración

`database/migrations/2026_11_10_000001_add_archived_at_to_exploraciones_activas.php`

```php
public function up(): void
{
    Schema::table('exploraciones_activas', function (Blueprint $table): void {
        $table->timestamp('archived_at')->nullable()->after('regreso');
        $table->index(['user_id', 'archived_at']);
    });
}

public function down(): void
{
    Schema::table('exploraciones_activas', function (Blueprint $table): void {
        $table->dropIndex(['user_id', 'archived_at']);
        $table->dropColumn('archived_at');
    });
}
```

- **Sin FK**: no hay tabla destino.
- **Sin check constraint** para mantener compatibilidad con SQLite en memoria. La invariante `archived_at != null ⇒ regreso != null` se garantiza en la capa de aplicación y se verifica en revisión.
- **Sin borrado lógico**: no se usa `SoftDeletes`; `cerrar` sigue siendo un `delete()` físico.
- **Índice**: `(user_id, archived_at)` sirve tanto para `WHERE user_id = ? AND archived_at IS NULL` (panel/página) como para ordenar el historial por `archived_at DESC`. No se elimina el índice existente de `user_id` para no tocar migraciones previas.

### Modelo

`app/Models/ExploracionActiva.php`:

- Añadir `archived_at` a `$fillable`.
- Añadir cast `'archived_at' => 'datetime'`.
- No añadir lógica de negocio al modelo; la regla vive en `ArchivarExpedicion`.

### Reglas de archivado

| Caso | Resultado |
|---|---|
| `regreso === null` (activa) | `422 ViolacionReglaNegocio`: “Solo se pueden archivar expediciones completadas.” |
| `regreso !== null` y `eventos.cancelada` presente | `422`: “No se puede archivar una expedición cancelada.” |
| `regreso !== null`, no cancelada, `archived_at === null` | `archived_at = now()`, `200` |
| `archived_at !== null` | Idempotente: `200`, no se modifica la fecha |
| No existe o es de otro usuario | `404` |
| Carrera con el procesador | Transacción + `lockForUpdate`; si al bloquear `regreso` sigue `null`, `422`; el cliente refresca |
| Carrera con `cerrar` | `404` si la fila ya no existe |

`cerrar` no cambia: `abort_unless($exploracion->regreso !== null, 404)` y `delete()`. Archivar no impide cerrar/eliminar. **Pregunta abierta 1.**

---

## 3. Contratos HTTP y DTOs

### 3.1 Rutas nuevas y modificadas

Todas cuelgan de `Route::middleware('auth')` (`routes/web.php:18-49`).

| Método | Ruta | Controlador | Request | Response |
|---|---|---|---|---|
| `GET` | `/api/exploraciones` | `ExpedicionesPanelController@index` | — | `200` JSON panel |
| `GET` | `/exploraciones/panel/contenido` | `ExpedicionesPanelController@contenido` | query `incluir_archivadas` (`0/1`) | `200` HTML fragment |
| `GET` | `/exploraciones/{exploracion}/panel/detalle` | `ExpedicionesPanelController@detalle` | path numérico | `200` HTML fragment; `404` |
| `POST` | `/exploraciones/{exploracion}/archivar` | `ExpedicionesPanelController@archivar` | sin body | `200` JSON; `422`; `404` |
| `POST` | `/api/exploraciones/store-individual` | `ExploracionActivaController@storeIndividual` | body existente | `201` JSON ampliado |
| `POST` | `/exploraciones/{exploracion}/cancelar` | `ExploracionActivaController@cancelar` | sin body | `200` JSON ampliado; `422` |
| `POST` | `/exploraciones/{exploracion}/recoger` | `ExploracionActivaController@recoger` | sin body | `200` JSON ampliado; `409` |
| `POST` | `/exploraciones/{exploracion}/cerrar` | `ExploracionActivaController@cerrar` | sin body | `200` JSON ampliado; `404` |
| `GET` | `/api/reclutamiento` | `ReclutamientoApiController@index` | — | `200` JSON |
| `POST` | `/reclutamiento/recruit` | `ReclutamientoController@recruit` | `{reclutable_id}` | `200` JSON ampliado |
| `POST` | `/reclutamiento/discard` | `ReclutamientoController@discard` | `{reclutable_id,cantidad}` | `200` JSON ampliado |
| `POST` | `/reclutamiento/discard-all` | `ReclutamientoController@discardAll` | — | `200` JSON ampliado |

`POST /exploraciones` (redirect) se mantiene sin cambios. Si otra vista lo usa, su recarga completa refresca los datos de forma natural.

Orden de rutas en `routes/exploraciones.php`: primero las literales `/exploraciones/preview` y `/exploraciones/panel/contenido`, después las dinámicas `{exploracion}`. Añadir `->whereNumber('exploracion')`.

### 3.2 DTOs nuevos

#### `Src\Exploraciones\Domain\EstadoExpedicion`

```php
enum EstadoExpedicion: string
{
    case EXPLORANDO = 'explorando';
    case VOLVIENDO = 'volviendo';
    case PENDIENTE_RESOLUCION = 'pendiente_resolucion';
    case TERMINADA = 'terminada';
    case ARCHIVADA = 'archivada';
    case CANCELADA = 'cancelada';
}
```

`CANCELADA` no se muestra en panel ni historial, pero permite representar la fila en el detalle si se consulta directamente.

#### `Src\Exploraciones\Domain\DataTransferObjects\ExpedicionResumen`

```php
final readonly class ExpedicionResumen
{
    public function __construct(
        public int $id,
        public int $reclutadoId,
        public int $pokemonId,
        public string $explorador,
        public string $pokemon,
        public string $icono,
        public string $habitat,
        public int $nivel,
        public EstadoExpedicion $estado,
        public string $estadoLabel,
        public string $estadoClase,
        public int $progreso,
        public ?int $restanteMin,
        public ?string $restanteLabel,
        public string $inicio,
        public ?string $inicioVuelta,
        public ?string $fin,
        public bool $indefinido,
        public bool $archivada,
        public bool $puedeCancelar,
        public bool $puedeRecoger,
        public bool $puedeCerrar,
        public bool $puedeArchivar,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array;
}
```

Campos JSON exactos:

| Clave | Tipo | Origen / regla |
|---|---|---|
| `id` | int | `exploraciones_activas.id` |
| `reclutado_id` | int | `reclutado_id` |
| `pokemon_id` | int | `reclutado.pokemon_id` |
| `explorador` | string | alias del reclutado o nombre de especie |
| `pokemon` | string | nombre de especie |
| `icono` | string | `/images/iconos_webp/{pokemon_id}.webp` |
| `habitat` | string | `habitat.name` |
| `nivel` | int | `nivel` |
| `estado` | string | valor de `EstadoExpedicion` |
| `estado_label` | string | etiqueta en español |
| `estado_clase` | string | clases Tailwind del badge |
| `progreso` | int 0–100 | calculado en servidor |
| `restante_min` | int\|null | minutos restantes; `null` si no aplica |
| `restante_label` | string\|null | “1 h 27 min”, “Indefinida”, “Pendiente” |
| `inicio` | ISO 8601 | `inicio_exploracion` o `created_at` |
| `inicio_vuelta` | ISO 8601\|null | inicio de la fase de vuelta |
| `fin` | ISO 8601\|null | fin calculado |
| `indefinido` | bool | `indefinido` |
| `archivada` | bool | `archived_at !== null` |
| `puede_cancelar` | bool | `regreso === null` |
| `puede_recoger` | bool | `regreso === null && indefinido` |
| `puede_cerrar` | bool | `regreso !== null` |
| `puede_archivar` | bool | `estado === TERMINADA` |

`puede_*` son **flags de presentación calculados por el backend**; el frontend no deduce permisos.

#### `Src\Exploraciones\Domain\Collections\ExpedicionesResumenCollection`

```php
final class ExpedicionesResumenCollection extends Collection
{
    public string $type = ExpedicionResumen::class;

    /** @return list<array<string, mixed>> */
    public function toArray(): array;
}
```

#### `Src\Reclutamiento\Domain\DataTransferObjects\ReclutableResumen`

```php
final readonly class ReclutableResumen
{
    public function __construct(
        public int $id,
        public int $pokemonId,
        public string $nombre,
        public string $icono,
        public int $cantidad,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array;
}
```

JSON: `{id, pokemon_id, nombre, icono, cantidad}`.

#### `Src\Reclutamiento\Domain\Collections\ReclutablesResumenCollection`

Extiende `Src\Shared\Domain\Collection`, `public string $type = ReclutableResumen::class`, `toArray()`.

### 3.3 Respuesta compacta del panel

`GET /api/exploraciones`:

```json
{
  "expediciones": [
    {
      "id": 41,
      "reclutado_id": 7,
      "pokemon_id": 25,
      "explorador": "Chispa",
      "pokemon": "Pikachu",
      "icono": "/images/iconos_webp/25.webp",
      "habitat": "Bosque de Viridian",
      "nivel": 2,
      "estado": "explorando",
      "estado_label": "Explorando",
      "estado_clase": "bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400",
      "progreso": 42,
      "restante_min": 87,
      "restante_label": "1 h 27 min",
      "inicio": "2026-11-10T10:00:00+00:00",
      "inicio_vuelta": "2026-11-10T11:30:00+00:00",
      "fin": "2026-11-10T12:00:00+00:00",
      "indefinido": false,
      "archivada": false,
      "puede_cancelar": true,
      "puede_recoger": false,
      "puede_cerrar": false,
      "puede_archivar": false
    }
  ],
  "total": 1,
  "generado_en": "2026-11-10T10:25:00+00:00"
}
```

**Contenido:** activas + terminadas pendientes, excluyendo archivadas y canceladas. **Orden servido:** activas por `fin` ascendente (indefinidas al final) y luego por `inicio`; terminadas pendientes por `regreso` descendente. El cliente no reordena.

Headers: `Cache-Control: no-store, private`.

### 3.4 Historial y detalle HTML

`GET /exploraciones/panel/contenido?incluir_archivadas=0|1`

- `0` (por defecto): secciones **Activas** y **Resultados por revisar**.
- `1`: añade **Archivadas**.
- Devuelve `text/html; charset=UTF-8`, sin layout, con el mismo markup que la página.
- `Cache-Control: no-store, private`.

`GET /exploraciones/{exploracion}/panel/detalle`

- Devuelve la tarjeta completa de una expedición (activa, terminada o archivada) reutilizando los mismos parciales.
- `404` si no existe, es de otro usuario o está cancelada.
- `Cache-Control: no-store, private`.

**Por qué HTML y no JSON:** la tarjeta terminada es una composición grande y condicional; el backend ya calcula `tiene_resumen`, `tiene_derrotados`, `span_*`, `label`, `clase`, hitos, diario, etc. Un contrato JSON obligaría a replicar esas condiciones en Alpine y a mantener dos representaciones. El HTML reutiliza Blade y mantiene el frontend mínimo. El coste es mayor payload; se compensa con carga bajo demanda y caché en memoria.

### 3.5 Respuestas de acciones

Todas las mutaciones devuelven la colección canónica del panel para que el cliente reemplace sin recargar.

| Acción | Response `200` | Errores |
|---|---|---|
| `store-individual` | `{ok:true,id,inicio,fin,modo,estado,expedicion:{...},expediciones:[...]}` (`201`) | `422 {message}` |
| `cancelar` | `{ok:true,expediciones:[...]}` | `422 {message}` si ya no está activa |
| `recoger` | `{ok:true,expediciones:[...]}` | `409 {message}` si lock/procesamiento ocupado |
| `cerrar` | `{success:true,expediciones:[...]}` | `404` si no está completada |
| `archivar` | `{ok:true,expedicion:{...archivada...},expediciones:[...]}` | `422`, `404` |
| `recruit` | `{success:true,reclutables:[...]}` | `404`/`422` |
| `discard` | `{success:true,reclutables:[...]}` | `404`/`422` |
| `discard-all` | `{success:true,candies:{...},reclutables:[]}` | — |

`POST /api/reclutamiento` no existe; el listado es GET. Las acciones conservan sus rutas actuales.

### 3.6 Firmas de servicios y controladores

```php
final class CalculadorEstadoExpedicion
{
    public static function calcular(
        bool $archivada,
        bool $regresoMarcado,
        bool $cancelada,
        ?CarbonInterface $inicioVuelta,
        ?CarbonInterface $fin,
        CarbonInterface $ahora,
    ): EstadoExpedicion;
}

final class CalculadorProgresoExpedicion
{
    public static function calcular(
        ?CarbonInterface $inicio,
        ?CarbonInterface $fin,
        CarbonInterface $ahora,
    ): int;
}

final class ConsultorEstadoExpedicion
{
    public function estado(ExploracionActiva $exp, ?CarbonInterface $ahora = null): EstadoExpedicion;
    public function fin(ExploracionActiva $exp): ?CarbonInterface;
    public function inicioVuelta(ExploracionActiva $exp): ?CarbonInterface;
    public function estaCancelada(ExploracionActiva $exp): bool;
}

final class ConsultarExpedicionesUsuario
{
    public function para(int $userId, bool $incluirArchivadas = false): ExpedicionesAgrupadas;
}

final class ConstructorExpedicionesResumen
{
    public function panel(int $userId): ExpedicionesResumenCollection;
    public function resumen(ExploracionActiva $exp): ExpedicionResumen;
}

final class ArchivarExpedicion
{
    public function ejecutar(int $userId, int $expedicionId): ExploracionActiva;
}

final class ConstructorReclutablesResumen
{
    public function todos(int $userId): ReclutablesResumenCollection;
}
```

Controladores:

```php
final class ExpedicionesPanelController extends Controller
{
    public function index(): JsonResponse;
    public function contenido(Request $request): Response;
    public function detalle(ExploracionActiva $exploracion): Response;
    public function archivar(ExploracionActiva $exploracion): JsonResponse;
}

final class ReclutamientoApiController extends Controller
{
    public function index(): JsonResponse;
}
```

`ExpedicionesAgrupadas` es un DTO interno de `App` con tres `Illuminate\Database\Eloquent\Collection` (activas, terminadas, archivadas). No cruza a dominio ni a JSON.

---

## 4. Estado cliente y flujo de datos

### 4.1 Registro del store

- Archivos: `resources/js/stores/comun.js`, `expediciones.js`, `reclutamiento.js`.
- Registro en `resources/js/bootstrap.js`, **antes** de `Livewire.start()`:

```js
import { registrarStoreExpediciones } from './stores/expediciones';
import { registrarStoreReclutamiento } from './stores/reclutamiento';

registrarStoreExpediciones(Alpine);
registrarStoreReclutamiento(Alpine);

Livewire.start();
```

**Por qué no `alpine:init`:** `bootstrap.js` ya importa Alpine y llama a `Livewire.start()` al cargar el bundle. Registrar en `alpine:init` después de ese punto puede llegar tarde. Registrar con `Alpine.store` antes de `start()` garantiza disponibilidad.

**Por qué `Alpine.store` y no `Alpine.data`:** el estado debe ser singleton y compartido por layout, página, panel y modales. `Alpine.data` crearía instancias independientes.

### 4.2 Store `expedicionesNavbar`

```js
Alpine.store('expedicionesNavbar', {
  // UI
  panelAbierto: false,
  historialAbierto: false,
  detalleAbierto: false,
  detalleId: null,

  // Colección
  items: [],
  cargadoEn: 0,
  cargando: false,
  error: '',

  // HTML
  historialHtml: '',
  historialCargando: false,
  historialError: '',
  detalleHtml: '',
  detalleCargando: false,
  detalleError: '',

  // Acciones
  accionEnCurso: '',
  mensaje: '',

  _iniciado: false,
  _peticion: null,

  iniciar() {},                 // listeners focus/visibility/click/evento; idempotente
  abrirPanel() {}, cerrarPanel() {},
  refrescar() {},               // GET /api/exploraciones
  abrirHistorial() {}, cerrarHistorial() {},
  refrescarHistorial() {},      // GET /exploraciones/panel/contenido?incluir_archivadas=1
  abrirDetalle(id) {}, cerrarDetalle() {},
  ejecutarAccion(id, accion, contexto) {},
  refrescarPagina() {},         // GET /exploraciones/panel/contenido
  revalidarSiProcede() {},
});
```

**Lifecycle**

- `iniciar()` se invoca desde `x-init` del `<body>` del layout; idempotente con `_iniciado`.
- Registra:
  - `document.addEventListener('click', …)` para el listener delegado de `[data-expedicion-accion]` y cierre del panel al hacer click fuera.
  - `window.addEventListener('focus', …)` y `document.addEventListener('visibilitychange', …)` para revalidar.
  - `window.addEventListener('expediciones:actualizada', …)` para la colección emitida por hábitats.
- No hay `setInterval` ni polling.

**Carga inicial**

- El store arranca vacío. No se pide nada en todas las páginas.
- Al abrir el panel:
  - Si `items` está vacío o `Date.now() - cargadoEn > 15000`: muestra carga y llama `refrescar()`.
  - Si hay caché fresca: muestra al instante y revalida en segundo plano.
- Al abrir historial/detalle: muestra HTML cacheado si existe y revalida; si no, carga.
- La página `/reclutamiento` hidrata su store con `@js($reclutables)`; el panel de expediciones no se hidrata porque la página ya renderiza HTML, no JSON.

**Revalidación**

- `TTL_REVALIDACION_MS = 15000`.
- En `focus`/`visibilitychange` visible: si panel, historial o detalle están abiertos y la colección tiene más de 15 s, `refrescar()` y, si el historial está abierto, `refrescarHistorial()`.
- Si la página `/exploraciones` está presente y han pasado más de 30 s, `refrescarPagina()`.
- El store de reclutamiento hace lo mismo si su modal está abierto o su grid está en página.
- Sin polling. Si el usuario mantiene el panel abierto y no cambia de foco, los datos pueden quedar obsoletos; se acepta y se ofrece botón “Actualizar”.

**Reindexado**

- No hay inserción/ordenación local. `refrescar()` y toda mutación ejecutan:

```js
reemplazar(expediciones) {
  this.items = Array.isArray(expediciones) ? expediciones : [];
  this.cargadoEn = Date.now();
}
```

- La colección viene ordenada por el backend. El cliente solo la recorre con `x-for`.
- Evento `expediciones:actualizada`: `detail = { expediciones: [...] }`. El store reemplaza y actualiza `cargadoEn`.

**Acciones**

- Listener delegado:

```js
document.addEventListener('click', (event) => {
  const boton = event.target.closest('[data-expedicion-accion]');
  if (!boton) return;
  const id = Number(boton.dataset.expedicionId);
  const accion = boton.dataset.expedicionAccion;
  const contexto = boton.closest('[data-expediciones-contexto]')?.dataset.expedicionesContexto ?? 'panel';
  this.ejecutarAccion(id, accion, contexto);
});
```

- `detalle` no es POST: abre el modal.
- `cancelar`, `recoger`, `cerrar`, `archivar` hacen POST con CSRF y `Accept: application/json`.
- Confirmaciones con `window.confirm` (patrón existente) salvo archivar, que usa confirmación propia del modal `x-ui.modal` si se quiere; por simplicidad, `window.confirm` también.
- Guard por `accionEnCurso` para evitar dobles envíos.
- Al éxito:
  - `reemplazar(data.expediciones)`.
  - Si `data.expedicion` viene (archivar), se puede mostrar mensaje, pero la fuente de verdad es `expediciones`.
  - Si el historial está abierto, `refrescarHistorial()`.
  - Si el detalle está abierto, cerrarlo.
  - Si hay contenedor `#expediciones-contenido`, `refrescarPagina()`.
- Al fallo:
  - Mostrar `error`/`mensaje` con `role="status"`.
  - En `409` o `422` de carrera, llamar `refrescar()` para resincronizar.
  - No mutar optimistamente; el servidor es autoritativo.

**Errores y carga**

- `fetchJson`/`fetchHtml` en `comun.js`:
  - Añaden `X-CSRF-TOKEN` en POST.
  - `cache: 'no-store'`.
  - Parsean `message` de JSON en errores.
  - `419` → “La sesión ha expirado. Recarga la página.”
  - Error de red → mensaje reintentable.
- Estados: `cargando`, `error`, `accionEnCurso`, `mensaje`.
- Si hay caché y falla la revalidación, se mantienen los datos y se muestra aviso “No se pudo actualizar; mostrando datos anteriores.”

**Accesibilidad**

- Trigger: `aria-expanded`, `aria-controls`, `aria-label`.
- Panel: `role="region"`, `aria-label="Expediciones"`, `tabindex="-1"`, foco al abrir, Escape cierra, retorno de foco al trigger.
- Modales: `x-ui.modal` con `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, Escape, foco inicial y retorno.
- `aria-live="polite"` para mensajes; `aria-busy` en carga; `role="progressbar"` con valores.
- Iconos `aria-hidden="true"`; botones con `aria-label` que incluye nombre.

### 4.3 Store `reclutamientoNavbar`

```js
Alpine.store('reclutamientoNavbar', {
  abierto: false,
  items: [],
  cargadoEn: 0,
  cargando: false,
  error: '',
  accionEnCurso: '',
  mensaje: '',

  descarteAbierto: false,
  descarte: null,          // { id, nombre, cantidad }
  descarteCantidad: 1,
  descarteTodosAbierto: false,

  iniciar() {},
  hidratar(items) {},      // desde @js($reclutables)
  abrir() {}, cerrar() {},
  refrescar() {},          // GET /api/reclutamiento
  reclutar(id) {},
  solicitarDescarte(id) {},
  confirmarDescarte() {},
  solicitarDescarteTodos() {},
  descartarTodos() {},
  revalidarSiProcede() {},
});
```

- `hidratar` solo en `/reclutamiento`; asigna la lista del servidor y `cargadoEn = Date.now()`.
- `abrir` revalida si está vacío o viejo; el modal muestra la caché mientras carga.
- `reclutar`/`confirmarDescarte`/`descartarTodos` reemplazan `items` con la respuesta del backend.
- `descarte` guarda `{id,nombre,cantidad}`, no una referencia al objeto de la lista, para evitar datos obsoletos tras un refresco.
- `setPorcentaje(pct)` es solo ayuda de UI sobre `descarte.cantidad`.
- La página y el modal comparten `items`; cualquier cambio se refleja en ambos.

### 4.4 Flujo de datos resumido

1. Usuario pulsa icono de Exploraciones.
2. Store abre panel; si no hay caché, `GET /api/exploraciones`; renderiza 3 columnas.
3. Usuario pulsa flecha: `GET /exploraciones/{id}/panel/detalle`; inyecta HTML en modal.
4. Usuario pulsa archivar en una fila completada: `POST /exploraciones/{id}/archivar`; respuesta con `expedicion` y `expediciones`; el panel se reemplaza; si el historial está abierto se recarga; si la página está abierta se refresca su fragmento.
5. Usuario pulsa “Ver historial”: `GET /exploraciones/panel/contenido?incluir_archivadas=1`; modal con activas, terminadas y archivadas.
6. Usuario pulsa Reclutamiento: store abre modal; `GET /api/reclutamiento` si no hay caché.
7. Usuario recluta/descarta: POST; respuesta con lista completa; el grid se reemplaza.
8. Nuevo reclutable creado por job: no hay push; al recuperar foco o abrir el modal se revalida.
9. Nueva expedición desde hábitat: `store-individual` devuelve `expedicion` y `expediciones`; la página de hábitat actualiza su estado local y emite `expediciones:actualizada`; el store reemplaza sin recargar.

---

## 5. UI, componentes y parciales

### 5.1 Nueva estructura de navbar

```text
<header sticky z-30>
  <div px-4>
    <nav flex items-center gap-2 h-14>
      <div min-w-0 flex-1 overflow-x-auto>        ← enlaces + cosecha
        <div flex items-center gap-1 w-max>
          Pokédex · Hábitats · Equipos · Sendas · Gimnasios · Forja
        </div>
      </div>

      <div relative flex items-center gap-1 shrink-0>  ← acciones, FUERA del overflow
        <button reclutamiento fa-user-plus>
        <button exploraciones fa-compass>
        <div id="panel-expediciones" absolute right-0 top-full ... z-40>
          panel 3 columnas
        </div>
      </div>

      <div flex items-center gap-2 shrink-0>       ← usuario, nivel, tema
      </div>
    </nav>
  </div>
  <div barra progreso nivel>
</header>
```

- Se elimina `justify-center` y el bloque de usuario `absolute right-0`. El nav pasa a `justify-between` implícito con `flex-1` para los enlaces.
- El panel vive dentro del grupo de acciones, que es hermano del contenedor con scroll: no lo recorta `overflow-x-auto`.
- En móvil, el panel usa `fixed inset-x-2 top-16 max-h-[70vh]`; en `sm+`, `absolute right-0 top-full mt-2 w-[44rem]`.
- `z-40` dentro del header `z-30`; los modales `z-50` quedan por encima.
- Iconos provisionales: `fa-solid fa-user-plus` (Reclutamiento) y `fa-solid fa-compass` (Exploraciones). Ajustables sin cambiar estructura.

### 5.2 Panel de expediciones

Estructura de fila:

```text
| icono + explorador + hábitat/nivel | barra progreso + % | tiempo restante | archivar | chevron |
```

- Desktop: `grid grid-cols-[minmax(0,1fr)_10rem_8rem_auto_auto] items-center gap-3`.
- Móvil: `grid grid-cols-1`; progreso y tiempo debajo del nombre; acciones en fila.
- Pokémon: `<img src="{icono}">` + nombre + hábitat + badge nivel. Sin cálculos.
- Progreso: `role="progressbar"` con `aria-valuenow="{{ progreso }}"`; barra `style="width: {{ progreso }}%"`.
- Tiempo: `restante_label` o “Indefinida”/“Pendiente”.
- Archivar: solo si `puede_archivar`; icono `fa-box-archive`, `aria-label="Archivar expedición de {explorador}"`, `data-expedicion-accion="archivar"`.
- Detalle: chevron `fa-chevron-right`, `data-expedicion-accion="detalle"`.
- Estado: badge con `estado_clase` y `estado_label`.
- Cabecera del panel: título “Expediciones”, botón actualizar, botón cerrar.
- Pie: `flex justify-end border-t` con botón “Ver historial”.
- Estados:
  - Cargando: 3 filas skeleton + `aria-busy`.
  - Vacío: icono + “No hay expediciones activas” + enlace a `/habitats`.
  - Error: mensaje + “Reintentar”; si hay caché, aviso de datos anteriores.
- `PENDIENTE_RESOLUCION`: badge warning “Pendiente de procesar”, progreso 100, tiempo “Pendiente”. Se acepta que el procesador lo resuelva en ≤5 min o al visitar `/exploraciones`.

### 5.3 Modales

Componente nuevo `resources/views/components/ui/modal.blade.php`:

```blade
@props([
    'id',
    'titulo',
    'abierto',   // expresión Alpine, p. ej. "$store.expedicionesNavbar.historialAbierto"
    'cerrar',    // expresión Alpine
    'tamano' => 'max-w-lg',
])
```

- Scrim `.scrim`, `fixed inset-0 z-50`, `role="dialog"`, `aria-modal="true"`, `aria-labelledby`.
- `@keydown.escape.window="{{ $cerrar }}"`.
- Botón de cierre `data-modal-close`.
- Contenedor con `max-h-[85vh] overflow-y-auto`.
- Slot para contenido.
- Confirmaciones anidadas usan `z-[60]`.

Modales:

1. **Reclutamiento** (`modal-reclutamiento.blade.php`):
   - Cabecera “Reclutamiento” + actualizar + cerrar.
   - Grid compartido `_grid.blade.php`.
   - Botón “Descartar todos” si hay items.
   - Estados vacío/carga/error.
2. **Descartar uno** (`_modal-descartar.blade.php`):
   - Cantidad, 25/50/75/100 %, nombre, confirmar/cancelar.
   - Usa `$store.reclutamientoNavbar.descarte*`.
3. **Descartar todos** (`_modal-descartar-todos.blade.php`):
   - Confirmación destructiva; aviso de caramelos.
4. **Historial** (`modal-historial-expediciones.blade.php`):
   - `x-html="$store.expedicionesNavbar.historialHtml"`.
   - Título “Historial de expediciones”; actualizar; cerrar.
   - Contenido: secciones Activas, Terminadas pendientes, Archivadas.
5. **Detalle** (`modal-detalle-expedicion.blade.php`):
   - `x-html="$store.expedicionesNavbar.detalleHtml"`.
   - Título “Detalle de la expedición”.

Los modales viven en el layout, una sola vez, fuera del `@push('scripts')`. IDs con prefijo `navbar-` para no colisionar con modales de página.

### 5.4 Parciales de exploraciones

Extraer de `resources/views/exploraciones/index.blade.php`:

- `exploraciones/partials/_contenido.blade.php`: recibe `$activas`, `$terminadas`, `$archivadas`, `$contexto`, `$nivelJugador`. Renderiza secciones y estados vacíos.
- `exploraciones/partials/_tarjeta-activa.blade.php`: markup de `:36-148`, sin Alpine; `<details>` para bitácora; fechas con `inicio_label`/`fin_label`; botones con `data-*`.
- `exploraciones/partials/_tarjeta-terminada.blade.php`: markup de `:173-415`, sin Alpine; `<details>` para diario; badges de estado; botón archivar si `puede_archivar`; cerrar.
- `exploraciones/partials/_detalle.blade.php`: wrapper del detalle que incluye la tarjeta correspondiente.

Reglas de los parciales:

- Sin `x-data`, `x-show`, `x-text`, `@click`. Solo Blade + `data-*`.
- Sin consultas ni cálculos. Solo campos del presentador.
- `<details>` para colapsables; `summary` con `aria-controls` no es necesario, `<details>` ya es accesible.
- Fechas: `inicio_label`, `inicio_vuelta_label`, `fin_label` calculadas en el presentador.
- Botones:
  - `data-expedicion-id="{{ $exp['id'] }}"`.
  - `data-expedicion-accion="cancelar|recoger|cerrar|archivar|detalle"`.
  - El contexto lo aporta el contenedor `data-expediciones-contexto`.

### 5.5 Página `/exploraciones`

- Mantiene cabecera y aviso de procesamiento.
- El contenido pasa a:

```blade
<div id="expediciones-contenido" data-expediciones-contexto="pagina">
    @include('exploraciones.partials._contenido', [
        'activas' => $activas ?? [],
        'terminadas' => $terminadas ?? [],
        'archivadas' => [],
        'contexto' => 'pagina',
        'nivelJugador' => $nivelJugador ?? 1,
    ])
</div>
```

- Se elimina `x-data="exploracionesPage()"` y todo `@push('scripts')`.
- Tras una acción, el store hace `GET /exploraciones/panel/contenido` y reemplaza `#expediciones-contenido.innerHTML`. Sin recarga completa.
- El render inicial sigue procesando pendientes en `index()`; los refrescos posteriores no procesan.
- La página y el historial comparten exactamente los mismos parciales.

### 5.6 Página `/reclutamiento`

- Wrapper: `<div x-init="$store.reclutamientoNavbar.hidratar(@js($reclutables ?? []))">`.
- Cabecera: botón “Descartar todos” condicionado a `$store.reclutamientoNavbar.items.length > 0`; llama `solicitarDescarteTodos()`.
- Grid: `@include('reclutamiento.partials._grid', ['contexto' => 'pagina'])`.
- Se eliminan los modales inline y `reclutamientoApp()`.
- La página y el modal de layout comparten el store; no hay estado duplicado.
- El grid usa `item.icono` del backend, no concatena la URL en JS.

### 5.7 Hábitats

En `resources/views/habitats/show.blade.php`, solo el bloque de éxito de `confirmExploration()`:

- No `location.reload()`.
- `this.showExplorationModal = false`.
- `this.reclutadosEnExploracion.push(Number(this.selectedFavoritoId))`.
- `this.selectedFavoritoId = null; this.selectedLevel = null`.
- `window.dispatchEvent(new CustomEvent('expediciones:actualizada', { detail: { expediciones: data.expediciones } }))`.
- Si `data.expedicion` existe, se puede usar para un mensaje local; la colección canónica es `data.expediciones`.
- En error, mantener el modal abierto y mostrar el mensaje.
- **Cuidado:** el archivo tiene cambios locales preexistentes; aplicar solo este hunk sin reformatear ni revertir el resto.

### 5.8 Responsive y accesibilidad

- Navbar: enlaces con scroll horizontal; iconos fijos; nombre de usuario oculto en `sm`.
- Panel: ancho completo en móvil, 44rem en desktop; `max-h` con scroll; filas apiladas.
- Modales: `p-2 sm:p-4`, `max-h-[90vh]`, contenido con scroll; grid de reclutamiento `grid-cols-2 sm:grid-cols-4 md:grid-cols-6`.
- Historial: tarjetas a una columna en móvil; `lg:grid-cols-3` en desktop.
- Foco: al abrir modal, foco al botón de cierre; Escape cierra; al cerrar, foco vuelve al trigger. Bloqueo de scroll del body mientras hay modal.
- `aria-live` para mensajes de acción; `role="status"` para vacíos; `aria-busy` para carga.
- No se usa hover para abrir; solo click, para teclado y táctil.

---

## 6. Archivos nuevos/modificados, responsable y orden

### Fase 1 — Base de datos

| # | Archivo | Tipo | Responsable | Depende de |
|---|---|---|---|---|
| 1 | `database/migrations/2026_11_10_000001_add_archived_at_to_exploraciones_activas.php` | nuevo | `database` | — |
| 2 | `app/Models/ExploracionActiva.php` | modificar | `database` | 1 |

### Fase 2 — Dominio y aplicación

| # | Archivo | Tipo | Responsable | Depende de |
|---|---|---|---|---|
| 3 | `src/Exploraciones/Domain/EstadoExpedicion.php` | nuevo | `backend` | — |
| 4 | `src/Exploraciones/Domain/CalculadorEstadoExpedicion.php` | nuevo | `backend` | 3 |
| 5 | `src/Exploraciones/Domain/CalculadorProgresoExpedicion.php` | nuevo | `backend` | — |
| 6 | `src/Exploraciones/Domain/DataTransferObjects/ExpedicionResumen.php` | nuevo | `backend` | 3 |
| 7 | `src/Exploraciones/Domain/Collections/ExpedicionesResumenCollection.php` | nuevo | `backend` | 6 |
| 8 | `src/Exploraciones/App/ConsultorEstadoExpedicion.php` | nuevo | `backend` | 3, 4 |
| 9 | `src/Exploraciones/App/FormateadorTiempoRestante.php` | nuevo | `backend` | — |
| 10 | `src/Exploraciones/App/ExpedicionesAgrupadas.php` | nuevo | `backend` | — |
| 11 | `src/Exploraciones/App/ConsultarExpedicionesUsuario.php` | nuevo | `backend` | 8, 10 |
| 12 | `src/Exploraciones/App/ConstructorExpedicionesResumen.php` | nuevo | `backend` | 4, 5, 6, 7, 8, 9, 11 |
| 13 | `src/Exploraciones/App/ArchivarExpedicion.php` | nuevo | `backend` | 8 |
| 14 | `src/Reclutamiento/Domain/DataTransferObjects/ReclutableResumen.php` | nuevo | `backend` | — |
| 15 | `src/Reclutamiento/Domain/Collections/ReclutablesResumenCollection.php` | nuevo | `backend` | 14 |
| 16 | `src/Reclutamiento/App/ConstructorReclutablesResumen.php` | nuevo | `backend` | 14, 15 |

### Fase 3 — Presentación servidor y HTTP

| # | Archivo | Tipo | Responsable | Depende de |
|---|---|---|---|---|
| 17 | `src/Exploraciones/Presentation/PresentadorExploraciones.php` | modificar | `backend` | 3, 4, 5, 8, 9 |
| 18 | `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php` | nuevo | `backend` | 11, 12, 13, 17 |
| 19 | `src/Reclutamiento/Infra/Controllers/ReclutamientoApiController.php` | nuevo | `backend` | 16 |
| 20 | `app/Http/Controllers/ExploracionActivaController.php` | modificar | `backend` | 11, 12, 13, 17 |
| 21 | `app/Http/Controllers/ReclutamientoController.php` | modificar | `backend` | 16 |
| 22 | `app/Http/Controllers/PlayerController.php` | modificar | `backend` | 16 |
| 23 | `routes/exploraciones.php` | modificar | `backend` | 18, 20 |
| 24 | `routes/player.php` | modificar | `backend` | 19, 21 |

### Fase 4 — JS

| # | Archivo | Tipo | Responsable | Depende de |
|---|---|---|---|---|
| 25 | `resources/js/stores/comun.js` | nuevo | `frontend` | — |
| 26 | `resources/js/stores/expediciones.js` | nuevo | `frontend` | 18, 23, 25 |
| 27 | `resources/js/stores/reclutamiento.js` | nuevo | `frontend` | 19, 24, 25 |
| 28 | `resources/js/bootstrap.js` | modificar | `frontend` | 26, 27 |

### Fase 5 — Blade de layout y modales

| # | Archivo | Tipo | Responsable | Depende de |
|---|---|---|---|---|
| 29 | `resources/views/components/ui/modal.blade.php` | nuevo | `frontend` | — |
| 30 | `resources/views/layouts/partials/navbar-acciones.blade.php` | nuevo | `frontend` | 26, 27 |
| 31 | `resources/views/layouts/partials/panel-expediciones.blade.php` | nuevo | `frontend` | 26, 30 |
| 32 | `resources/views/layouts/partials/modal-reclutamiento.blade.php` | nuevo | `frontend` | 27, 29 |
| 33 | `resources/views/layouts/partials/modal-historial-expediciones.blade.php` | nuevo | `frontend` | 26, 29 |
| 34 | `resources/views/layouts/partials/modal-detalle-expedicion.blade.php` | nuevo | `frontend` | 26, 29 |
| 35 | `resources/views/layouts/app.blade.php` | modificar | `frontend` | 28, 30–34 |

### Fase 6 — Blade de páginas y parciales

| # | Archivo | Tipo | Responsable | Depende de |
|---|---|---|---|---|
| 36 | `resources/views/exploraciones/partials/_contenido.blade.php` | nuevo | `frontend` | 17 |
| 37 | `resources/views/exploraciones/partials/_tarjeta-activa.blade.php` | nuevo | `frontend` | 17 |
| 38 | `resources/views/exploraciones/partials/_tarjeta-terminada.blade.php` | nuevo | `frontend` | 17 |
| 39 | `resources/views/exploraciones/partials/_detalle.blade.php` | nuevo | `frontend` | 37, 38 |
| 40 | `resources/views/exploraciones/index.blade.php` | modificar | `frontend` | 36–39 |
| 41 | `resources/views/reclutamiento/partials/_grid.blade.php` | nuevo | `frontend` | 27 |
| 42 | `resources/views/reclutamiento/partials/_modal-descartar.blade.php` | nuevo | `frontend` | 27 |
| 43 | `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php` | nuevo | `frontend` | 27 |
| 44 | `resources/views/reclutamiento/index.blade.php` | modificar | `frontend` | 41–43 |
| 45 | `resources/views/habitats/show.blade.php` | modificar (hunk mínimo) | `frontend` | 20 |

**No se elimina ningún archivo.** Los parciales existentes `_explorador`, `_diario`, `_evento`, `_caramelo`, `_perdidas`, `components/sprite`, `components/tipo-badge` se reutilizan sin cambios.

**Documentación:** no se crean documentos de proyecto; el `orchestrator` guarda este diseño en `.opencode/work/TASK-042/architecture.md`. Si el desarrollador quiere, se puede añadir una nota a `docs/conventions.md` sobre el patrón `Alpine.store` + fragmentos HTML JS-free, pero no es parte del alcance.

---

## 7. Seguridad

- **Autenticación:** todas las rutas nuevas se añaden a los archivos incluidos dentro de `Route::middleware('auth')` (`routes/web.php:18-49`). Sin sesión → redirección a login.
- **Propiedad explícita:** todas las consultas nuevas usan `where('user_id', Auth::id())`, además del global scope `BelongsToUser`. No se usa `withoutUserScope` en ningún controlador nuevo.
- **Route model binding:** `{exploracion}` con `->whereNumber('exploracion')`; el controlador comprueba `abort_unless($exploracion->user_id === Auth::id(), 404)`. En `cancelar/recoger/cerrar/archivar/detalle` se añade la misma comprobación explícita como defensa en profundidad.
- **Reclutamiento:** `recruit/discard/discardAll` pasan de confiar solo en el global scope a `where('user_id', Auth::id())->findOrFail(...)`; la regla `exists` sigue sin filtrar usuario, pero el `findOrFail` acotado devuelve 404.
- **CSRF:** todos los POST usan el middleware web y el token `meta[name=csrf-token]`; el store lo envía en `X-CSRF-TOKEN`. Los GET no mutan.
- **Validación:** ids por ruta con `whereNumber`; `cantidad` validada como entero ≥1; `incluir_archivadas` validado como booleano. `archived_at` nunca se acepta desde el request.
- **No ejecutar procesamiento en lecturas:** `GET /api/exploraciones`, `/exploraciones/panel/contenido` y `/panel/detalle` no llaman a `ProcesarExploracionesPendientes`. Esto evita efectos secundarios y repetición de trabajo en cada apertura de panel.
- **Privacidad de respuestas:** JSON y HTML privados con `Cache-Control: no-store, private`. No se cachea en proxies.
- **XSS:** los fragmentos HTML se renderizan con Blade escapado. No se usa `{!! !!}` con datos de usuario. No se inyectan `<script>` ni atributos de evento inline. Los `data-*` solo contienen ids numéricos y acciones de un conjunto cerrado.
- **Inyección HTML:** `x-html` solo consume respuestas same-origin del propio backend; el store no concatena datos de usuario en HTML.
- **Secretos:** no se leen ni se incluyen `.env`, tokens ni claves.
- **Datos de otros usuarios:** el DTO compacto no expone `eventos` crudos ni información de otras cuentas; el detalle HTML solo se sirve si `user_id` coincide.
- **Autorización de acciones:** los flags `puede_*` son informativos; la autorización real la aplica el backend en cada POST (404/422).

---

## 8. Criterios de aceptación y verificación estática

### Criterios funcionales verificables

1. **Navbar:** Reclutamiento y Exploraciones aparecen como iconos FontAwesome con `aria-label`; ya no son enlaces de texto. El panel no se recorta y se abre/cierra con click, Escape y click fuera.
2. **Panel de 3 columnas:** muestra Pokémon, barra de progreso y tiempo restante con datos de `GET /api/exploraciones`; la barra usa `aria-valuenow`; el porcentaje y la etiqueta vienen del backend.
3. **Detalle:** la flecha de una fila abre un modal con la tarjeta completa; el contenido se sirve desde `/exploraciones/{id}/panel/detalle` y no se recalcula en JS.
4. **Historial:** “Ver historial” abre un modal con activas, terminadas pendientes y archivadas, cada una con su estado visible. Las archivadas no aparecen en el panel ni en la página.
5. **Archivar:** solo se ofrece en completadas no archivadas. `POST /archivar` persiste `archived_at`, es idempotente, devuelve `422` para activas/canceladas y `404` para otro usuario. Tras archivar, el panel la oculta y el historial la muestra como “Archivada”.
6. **Cerrar:** `cerrar` sigue eliminando la fila; el panel y el historial la quitan. Si estaba archivada, también se elimina.
7. **Reclutamiento:** el icono abre directamente el modal; reclutar, descartar y descartar todos actualizan la lista sin recargar; las cantidades y la desaparición de filas coinciden con la base de datos.
8. **Caché:** abrir dos veces el panel dentro de 15 s no repite la petición salvo revalidación en segundo plano; al recuperar foco con el panel/modal abierto se revalida; no hay `localStorage`, `sessionStorage`, `setInterval` ni polling.
9. **Reindexado:** tras crear una expedición, reclutar o archivar, la colección mostrada coincide con el orden devuelto por el backend; el cliente no ordena.
10. **Creación desde hábitats:** al confirmar una expedición, el modal se cierra, el Pokémon queda marcado como en expedición, el navbar la muestra sin recargar y no se produce `location.reload()`.
11. **Página `/exploraciones`:** cancelar/recoger/cerrar/archivar actualiza el contenido de la página mediante `/exploraciones/panel/contenido` sin recarga completa; las secciones y contadores son correctos.
12. **Estado desfasado:** una expedición con `fin` pasado y `regreso = null` se muestra como “Pendiente de procesar”, progreso 100; los GET no cambian `regreso` ni `eventos`.
13. **Seguridad:** sin sesión redirige a login; con usuario B, `GET /exploraciones/{id}/panel/detalle` y `POST /archivar` de una fila de A devuelven 404; los POST sin CSRF fallan.
14. **Accesibilidad:** navegación con teclado por iconos, panel y modales; Escape cierra; el foco vuelve al trigger; los mensajes se anuncian con `aria-live`.
15. **Responsive:** en viewport móvil el panel ocupa el ancho disponible, las filas se apilan y los modales hacen scroll sin desbordar.

### Verificación estática (sin tests)

```bash
# Sintaxis PHP
php -l app/Models/ExploracionActiva.php
php -l app/Http/Controllers/ExploracionActivaController.php
php -l app/Http/Controllers/ReclutamientoController.php
php -l app/Http/Controllers/PlayerController.php
php -l src/Exploraciones/... # todos los archivos nuevos/modificados
php -l src/Reclutamiento/...

# Estilo
vendor/bin/pint --test

# Rutas y middleware
php artisan route:list --path=exploraciones
php artisan route:list --path=api/reclutamiento

# Blade
php artisan view:cache
php artisan view:clear

# Frontend
npm run build

# Comprobaciones de alcance
grep -R "exploracionesPage\|reclutamientoApp" resources/views resources/js
grep -R "location.reload" resources/views
grep -R "x-data\|x-show\|x-text\|@click" resources/views/exploraciones/partials
grep -R "withoutUserScope" src/Exploraciones/Infra src/Reclutamiento/Infra
```

Criterios de grep:

- `exploracionesPage` y `reclutamientoApp` no deben existir.
- `location.reload` no debe aparecer en `habitats/show.blade.php` dentro de `confirmExploration`; si aparece en otras vistas, justificar.
- Los parciales de `exploraciones/partials` no deben contener directivas Alpine.
- `withoutUserScope` no debe aparecer en los controladores nuevos.

No se ejecutan tests ni la suite. La verificación es análisis estático + checklist manual.

---

## 9. Riesgos, tradeoffs y preguntas abiertas

### Riesgos y mitigaciones

| Riesgo | Severidad | Mitigación |
|---|---|---|
| El procesador cada 5 min deja estados desfasados | Alto | Estado `pendiente_resolucion`, progreso 100, etiqueta “Pendiente de procesar”; revalidación al abrir y al recuperar foco; `GET /exploraciones` sigue procesando |
| Los fragmentos HTML inyectados con `x-html` no inicializan Alpine | Alto | Parciales JS-free: `<details>`, fechas de servidor, botones `data-*`; listener delegado global. Criterio de grep en verificación |
| `archivar` compite con el procesador o con `cerrar` | Medio | Transacción + `lockForUpdate`; 422/404; el cliente refresca y resincroniza |
| Devolver la colección completa en cada mutación aumenta el payload | Medio | Colecciones acotadas (activas/pendientes); compactas; sin eventos ni diario; se evita lógica de orden en cliente |
| Historial sin paginación puede crecer | Medio | Índice `(user_id, archived_at)`; futuro: paginación o retención. No se implementa ahora |
| La página `/exploraciones` necesita un GET extra tras acciones | Medio | Fragmento HTML en caché de memoria; solo se pide si el contenedor existe; sin recarga completa |
| `habitats/show.blade.php` tiene cambios locales preexistentes | Medio | Aplicar solo el hunk de `confirmExploration`; no reformatear ni revertir el resto; revisar `git diff` antes y después |
| Etiquetas de tiempo estáticas mientras el panel está abierto | Bajo | Sin polling; se actualizan al reabrir, al recuperar foco o con “Actualizar”. No se recalcula en JS |
| Multi-pestaña: cada pestaña tiene su caché | Bajo | Sin `localStorage`; revalidación en foco; se acepta que una pestaña inactiva esté desfasada |
| `cerrar` elimina también archivadas | Medio | Comportamiento actual conservado; documentado. Si producto quiere archivo permanente, cambiar `cerrar` (pregunta abierta) |
| Iconos provisionales | Bajo | `fa-user-plus`/`fa-compass`; cambiar solo el nombre de clase |

### Tradeoffs aceptados

- **HTML frente a JSON para detalle/historial:** más bytes, menos duplicación y menos riesgo de divergencia. El JSON se reserva para la colección que necesita caché/reindexado.
- **Colección completa frente a actualización incremental:** más payload, cero lógica de orden en cliente. Coherente con `frontend-minimal-logic.md`.
- **Sin actualización optimista:** la UI espera la respuesta del servidor. Evita divergencias y rollback; a cambio, una acción tarda lo que tarde el POST.
- **Sin polling:** menos carga y menos ruido; a cambio, datos potencialmente obsoletos hasta foco/acción/apertura.
- **Archivado como columna, no tabla:** menos alcance y sin migración de datos; a cambio, el historial no tiene retención ni paginación propia.

### Preguntas abiertas (no bloqueantes; el diseño toma una decisión por defecto)

1. **`cerrar` sobre una archivada:** ¿debe seguir borrando (diseño actual) o quedar deshabilitado para conservar el historial?
   *Default del diseño:* sigue borrando, como hoy.
2. **Desarchivar:** ¿se necesita una acción para devolver una archivada al panel?
   *Default:* no se implementa; no estaba en la petición.
3. **Canceladas en el historial:** ¿deben aparecer con estado “Cancelada”?
   *Default:* no; se excluyen como en la página actual.
4. **Paginación/retención del historial:** ¿cuántas archivadas se conservan y durante cuánto tiempo?
   *Default:* se devuelven todas; el índice prepara el futuro.
5. **¿El panel debe incluir terminadas pendientes?**
   *Default:* sí, con estado “Terminada” y progreso 100; son accionables.
6. **¿Archivar también desde el detalle?**
   *Default:* sí, con el mismo flag `puede_archivar`.
7. **Zona horaria de las etiquetas de fecha:** el servidor formatea en `config('app.timezone')`; el JS anterior usaba la zona del navegador. ¿Se acepta la zona del servidor?
   *Default:* sí; si no, se añade un formateador global y se mantienen ISO en el HTML.
8. **Iconos definitivos:** `fa-user-plus` y `fa-compass` son provisionales.
9. **¿Badge/contador en los iconos?** No se incluye; el panel muestra la lista. Si se quiere, el backend ya devuelve `total`.
10. **¿Apertura por hover?** No; solo click para accesibilidad y táctil.

---

**Resumen de la decisión:** JSON compacto para la colección cacheable, HTML reutilizado para detalle/historial, `archived_at` como estado persistido, stores Alpine singleton registrados antes de `Livewire.start()`, mutaciones que devuelven la colección canónica y navbar reestructurada para sacar los iconos del contenedor con scroll. La implementación puede empezar por la migración y los DTOs, seguir por los endpoints y terminar por el layout y las páginas.
</subagent>