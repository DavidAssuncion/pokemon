# TASK-042 — Auditoría de seguridad

- **Estado:** PASS (PASS_CON_HALLAZGOS)
- **Auditor:** `security` (sesión `ses_e362b6a81ffer5AC4UxlPSfwrO`)
- **Fecha:** 2026-11-11
- **Plan auditado:** v1 aprobado
- **Nota:** informe final del auditor, sin alterar sus conclusiones. No se ejecutaron tests (testing diferido).

---

```text
VEREDICTO
PASS — (PASS_CON_HALLAZGOS)
Sin hallazgos CRITICAL ni HIGH. No hay cambios obligatorios antes de la entrega.
El único riesgo MEDIO es un DoS autocontenido ya aceptado explícitamente en plan v1;
el resto son hallazgos BAJOS/INFORMATIVOS de endurecimiento.

TASK-042 · Auditoría de seguridad de la implementación del plan v1
Base: rama task-011 · Fecha: 2026-11-11
Método: revisión estática del código en working tree (sin tests, sin peticiones HTTP),
`php artisan route:list -v`, `composer audit`. Solo lectura: no se modificó ningún archivo.

Cambios posteriores al informe parcial re-verificados:
- routes/exploraciones.php:23-28 → recoger/cancelar/cerrar con whereNumber (confirmado).
- ExpedicionesPanelController@contenido → `contexto` con whitelist estricta (confirmado).
- PresentadorExploraciones::toActiva unificado (sin superficie de seguridad nueva).
- Foco/pila de modales: comun.js 100-167 + modal.blade.php 33-68 (sin endpoints ni datos nuevos).
```

## 1. Superficie analizada

- Rutas: `routes/exploraciones.php`, `routes/player.php`, `routes/web.php:18-49`, `bootstrap/app.php:9-28`.
- Controladores: `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php`, `src/Reclutamiento/Infra/Controllers/ReclutamientoApiController.php`, `app/Http/Controllers/ExploracionActivaController.php`, `app/Http/Controllers/ReclutamientoController.php`, `app/Http/Controllers/PlayerController.php`.
- Dominio/aplicación: `ArchivarExpedicion`, `ConsultarExpedicionesUsuario`, `ConsultorEstadoExpedicion`, `ConstructorExpedicionesResumen`, `ExpedicionesAgrupadas`, `ConstructorReclutablesResumen`, `PresentadorExploraciones`, `ExpedicionResumen`, `ReclutableResumen`, `EstadoExpedicion`.
- Persistencia: `app/Models/ExploracionActiva.php`, `app/Models/Concerns/BelongsToUser.php`, migración `2026_11_10_000001_add_archived_at_to_exploraciones_activas.php`.
- Frontend: `resources/js/stores/{comun,expediciones,reclutamiento}.js`, `resources/js/bootstrap.js`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/partials/*`, `resources/views/exploraciones/partials/*` y parciales reutilizados (`_explorador`, `_diario`, `_evento`, `_caramelo`, `_perdidas`), `resources/views/reclutamiento/**`, `resources/views/components/ui/modal.blade.php`, hunk `confirmExploration` de `habitats/show.blade.php`.

## 2. Hallazgos por severidad

### [CRITICAL] — ninguno

### [HIGH] — ninguno

### [MEDIO]

**M-01 · Historial sin paginación ni límite y colección completa en cada mutación (DoS autocontenido)**
- Ubicación: `src/Exploraciones/App/ConsultarExpedicionesUsuario.php:32-41`; `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php:60-75`; `resources/js/stores/expediciones.js:323`; `src/Exploraciones/App/ConstructorExpedicionesResumen.php:29-43`.
- Vector: usuario autenticado con N expediciones archivadas solicita `GET /exploraciones/panel/contenido?incluir_archivadas=1`. La query carga todas las filas del usuario con `with(['reclutado.pokemon.types','habitat'])` y renderiza todas las tarjetas; cada mutación devuelve además la colección completa del panel.
- Evidencia: `para()` no aplica `limit` ni paginación; la consulta solo filtra por `user_id` y (opcionalmente) `archived_at`. El índice `(user_id, archived_at)` de la migración `:21` acota la búsqueda pero no el tamaño del conjunto.
- Impacto: degradación de latencia/memoria proporcional al histórico del propio usuario (auto-DoS). No permite afectar a otros usuarios ni exfiltrar datos ajenos: la query está acotada por `user_id` y por el global scope `BelongsToUser` (`app/Models/Concerns/BelongsToUser.php:21-31`). Sin amplificación entre usuarios.
- Condición de explotación: acumular expediciones archivadas (crecimiento histórico natural, no requiere atacante). No requiere privilegios especiales.
- Remediación: cuando el histórico crezca, límite configurable en la consulta del historial (p. ej. últimas 50 + «cargar más») y/o retención. Riesgo aceptado en `plan.md:48` (fuera de alcance) y `plan.md:235`.

### [BAJO]

**B-01 · Respuestas POST sin `Cache-Control: no-store, private`**
- Ubicación: `app/Http/Controllers/ExploracionActivaController.php:291-300` (`storeIndividual`), `:405-410` (`cancelar`), `:442-447` (`recoger`), `:464-469` (`cerrar`).
- Vector: respuestas JSON de mutación con datos del usuario sin cabecera de no-cache, a diferencia de los GET y de `archivar` (`ExpedicionesPanelController.php:119-123`).
- Impacto: bajo. RFC 9111 no permite cachear POST en cachés compartidas por defecto y el frontend usa `cache: 'no-store'` (`comun.js`), por lo que no se ha demostrado fuga; es una inconsistencia de defensa en profundidad.
- Remediación: homogeneizar `['Cache-Control' => 'no-store, private']` en todas las respuestas con datos de usuario.

**B-02 · `onerror` construye una cadena JS con `$candySrc` (slug persistido)**
- Ubicación: `resources/views/exploraciones/_evento.blade.php:22-24` (y usos `:128, :139, :156, :174, :185`); `resources/views/exploraciones/_caramelo.blade.php:2, :13`; origen del valor en `src/Exploraciones/Presentation/EventoExploracionVista.php:244-268, :301-305`.
- Vector: el HTML se inyecta con `x-html` (`modal-historial-expediciones.blade.php:57`, `modal-detalle-expedicion.blade.php:35`). El atributo se escapa con `{{ }}`, pero la entidad HTML se decodifica antes de ejecutar el JS: un valor con comilla simple podría romper el literal `this.src='…'`.
- Impacto: sink latente de XSS almacenado. Hoy `candy_src` se compone de `SlugTipo::de(...)` / `StatEnum::slug()` o de una ruta con id entero (`/images/candy_pokemon/{int}.webp`), no de entrada directa del usuario, y `eventos` no se acepta desde ningún request (`ExploracionActivaController.php:312-321, :358-370`; único `update` de `eventos` en `:402` y `RepartidorRecompensasExploracion.php:79`, ambos con datos generados por el servidor). No se ha demostrado explotación.
- Condición de explotación: requeriría que un slug persistido en `eventos` llegara con comillas (hoy imposible por los caminos de escritura auditados).
- Remediación: serializar con `Illuminate\Support\Js::from()` como ya hace `resources/views/components/sprite.blade.php:58-66`, en lugar de interpolar la URL en el literal JS.

### [INFORMATIVO]

**I-01 · `x-ui.modal` inyecta expresiones Alpine crudas**
- Ubicación: `resources/views/components/ui/modal.blade.php:35` (`x-show`), `:36` (`x-init` con `$watch('{{ $abierto }}', …)` y `'{{ $id }}'`), `:42` (`@keydown.escape.window`), `:45, :56` (`@click="{{ $cerrar }}"`).
- Vector: props `abierto`/`cerrar`/`id` se insertan como código Alpine/JS, no como datos. Un llamador que pase datos dinámicos permitiría ejecución de expresión.
- Impacto: nulo en el estado actual: los cuatro llamadores pasan literales (`layouts/partials/modal-historial-expediciones.blade.php:11-12`, `modal-detalle-expedicion.blade.php:11-12`, `modal-reclutamiento.blade.php:11-12`, `reclutamiento/partials/_modal-descartar.blade.php:10-11`, `_modal-descartar-todos.blade.php:10-11`).
- Remediación: documentar en el componente que `abierto`/`cerrar`/`id` deben ser literales de plantilla y no admiten datos de usuario (o derivar la expresión internamente a partir de un `store`/`prop` cerrada).

**I-02 · `archived_at` en `$fillable` (riesgo latente de mass assignment)**
- Ubicación: `app/Models/ExploracionActiva.php:17-31` (incluye `user_id` y `archived_at`).
- Vector: si un futuro controlador pasara `$request->validated()`/`all()` a `create()`/`update()`/`fill()`, un cliente podría fijar `archived_at` (y `user_id`).
- Impacto: nulo hoy. Todos los escritos son arrays explícitos: `ExploracionActivaController.php:358-370` y `:402`, `ArchivarExpedicion.php:60-61`, `RepartidorRecompensasExploracion.php:79`. Ningún `FormRequest`/`validate()` incluye `archived_at`.
- Remediación: mantener los escritos explícitos y, si se añade CRUD, asignar `archived_at` solo en el caso de uso.

**I-03 · `discardAll` se apoya solo en el global scope**
- Ubicación: `app/Http/Controllers/ReclutamientoController.php:116-120`.
- Vector: `Reclutable::with('pokemon')->lockForUpdate()->get()` + `Reclutable::query()->delete()` sin `where('user_id', …)` explícito (a diferencia de `recruit`/`discard`, líneas 40 y 89).
- Impacto: nulo verificado; `BelongsToUser` añade `where user_id = Auth::id()` y la ruta está bajo `auth`, por lo que la consulta nunca queda sin filtro. Es una asimetría de defensa en profundidad, no una vulnerabilidad.
- Remediación: añadir el `where('user_id', (int) auth()->id())` explícito por coherencia con las otras dos acciones.

**I-04 · Fallback de atribución de caramelos a `user_id = 1`**
- Ubicación: `app/Http/Controllers/ReclutamientoController.php:162-165` (`['user_id' => $userId ?? 1, …]`).
- Impacto: inalcanzable desde HTTP (rutas bajo `auth`, y `$userId` se deriva de `auth()->id()` en las tres acciones); solo sería relevante en un llamador CLI/Job sin usuario. Preexistente, no introducido por TASK-042.
- Remediación (fuera de alcance): lanzar excepción en lugar de atribuir a un usuario por defecto.

**I-05 · `window.trampaDeFoco` expuesto globalmente**
- Ubicación: `resources/js/bootstrap.js:10`.
- Impacto: nulo; helper puro de foco DOM, sin datos ni privilegios. Se expone así porque se usa en directivas `x-on` del Blade (`modal.blade.php:43`).

**I-06 · Advisories de dependencias preexistentes**
- Ubicación: `composer.lock` (sin cambios en el diff; `git status` no muestra `composer.json/lock` ni `package.json/lock`).
- Evidencia `composer audit`: 17 advisories en `laravel/framework` (3), `league/commonmark` (2), `livewire/livewire` (1), `symfony/config` (1), `symfony/filesystem` (1), `symfony/http-foundation` (5), `symfony/http-kernel` (1), `symfony/mime` (1), `symfony/routing` (1), `symfony/yaml` (1). Versiones instaladas: `laravel/framework v12.67.0`, `livewire/livewire v4.4.2`, `symfony/* v7.4.17`.
- Impacto: no atribuible a TASK-042 (no se añadieron ni actualizaron dependencias); requiere decisión de actualización de framework fuera de este alcance. `symfony/routing` (bypass de control de acceso basado en path) no aplica al modelo de autorización de esta aplicación, que es por middleware `auth` + comprobación de `user_id` en código, no por rutas de servidor web.

## 3. Checklist de los 11 puntos

| # | Punto | Resultado | Evidencia |
|---|---|---|---|
| 1 | Autenticación: rutas nuevas dentro de `auth`; comportamiento sin sesión | **Cumple** | `routes/web.php:18-49`; `php artisan route:list --path=exploraciones -v` → las 11 rutas exponen `web` + `Illuminate\Auth\Middleware\Authenticate`. Sin sesión, `Authenticate` redirige a login (GET) o 401 (Accept: application/json), consumido en `comun.js:40-52`. |
| 2 | Autorización/IDOR: `user_id` explícito en panel, archivado, acciones y reclutamiento | **Cumple** | `ExpedicionesPanelController.php:45, 61, 84, 86, 113, 115, 122`; `ArchivarExpedicion.php:32-46` (lock + `where user_id`); `ConsultarExpedicionesUsuario.php:32-38`; `ExploracionActivaController.php:385, 430, 459`; `ReclutamientoController.php:39-41, 88-91`; `ReclutamientoApiController.php:25`; `PlayerController::reclutamiento` usa el usuario autenticado. Binding de `{exploracion}` con `whereNumber` + `abort_unless(..., 404)` (respuesta indistinguible de «no existe»). |
| 3 | CSRF: POST bajo middleware web; token en el frontend; GET sin efectos | **Cumple** | `bootstrap/app.php:15-17` sin `validateCsrfTokens(except:)`; POST en `routes/exploraciones.php:15, 20, 23, 25, 27, 32` y `routes/player.php:24-26`; `comun.js:54-72` envía `X-CSRF-TOKEN` desde `meta[name=csrf-token]` (`layouts/app.blade.php:6`); los GET nuevos (`index`, `contenido`, `detalle`) no llaman a `ProcesarExploracionesPendientes` (solo `ExploracionActivaController::index:56` y `recoger:432`). |
| 4 | XSS: `x-html` solo con HTML same-origin JS-free; `{{ }}` en datos; sink `onerror` con slugs controlados | **Cumple con observación (B-02)** | Grep en `exploraciones/**`, `layouts/partials/**`, `reclutamiento/**`, `layouts/app.blade.php`, `components/ui/modal.blade.php`: **0** `{!! !!}`. `x-html` solo consume `/exploraciones/panel/contenido` y `/exploraciones/{id}/panel/detalle` (`modal-historial:57`, `modal-detalle:35`); el único `innerHTML` es `expediciones.js:327` con respuesta del propio backend. Panel/grid usan `x-text`/`x-bind`. Sin `<script>` ni handlers inline con datos de usuario en los parciales. El sink `onerror` queda documentado en B-02 sin explotación demostrable. |
| 5 | Mass assignment: ningún request permite fijar `archived_at` | **Cumple** | `ExploracionActiva.php:30` (fillable) y `:38` (cast); `validate()` de `store`/`storeIndividual` (`ExploracionActivaController.php:312-321`) no incluye `archived_at` ni `user_id`; escrituras explícitas en `:358-370`, `:402`, `ArchivarExpedicion.php:60-61`. Riesgo latente en I-02. |
| 6 | SQL: consultas parametrizadas; `whereNumber` en rutas dinámicas | **Cumple** | Todo el acceso es Eloquent/Query Builder con bindings (`ConsultarExpedicionesUsuario.php:32-41`, `ArchivarExpedicion.php:32-36, 42`, `ConstructorReclutablesResumen.php:23-27`); sin concatenación SQL. `whereNumber` en `routes/exploraciones.php:18-28` (detalle, archivar, recoger, cancelar, cerrar). Sin ids dinámicos en `store-individual` ni en las rutas de reclutamiento. |
| 7 | Exposición de datos: JSON compacto sin diario/resultado/eventos; `no-store, private` | **Cumple con observación (B-01)** | `ExpedicionResumen::toArray()` (`:74-101`) y `ReclutableResumen::toArray()` (`:33-42`) solo exponen campos de lista; no incluyen `eventos`, `diario`, `resultado` ni datos de terceros. `no-store, private` en `index:51`, `contenido:75`, `detalle:104`, `archivar:123`. Los POST restantes carecen de la cabecera (B-01, sin fuga demostrada). |
| 8 | DoS/abuso: historial sin paginación, payload completo en mutaciones | **No cumple — riesgo aceptado** | M-01. Aceptado en `plan.md:48` y `plan.md:235` (paginación/retención fuera de alcance). No hay rate limiting específico en los endpoints nuevos; impacto acotado al propio usuario. |
| 9 | Secretos: sin credenciales, tokens ni `.env` en el diff | **Cumple** | Grep de patrones (`api_key`, `secret`, `password`, `bearer`, `token =`, `BEGIN PRIVATE/RSA`) en stores JS, `src/Exploraciones/**`, `src/Reclutamiento/**` y parciales: sin coincidencias. No se leyó ni se incluyó ningún `.env`; `git status` no muestra cambios en archivos de entorno. |
| 10 | Errores: sin trazas ni mensajes internos en JSON; el frontend maneja 404/409/419/422 | **Cumple** | `bootstrap/app.php:19-27` mapea excepciones de dominio a `{message}` con 404/422/400; `abort_unless(...,404)` produce la página/JSON estándar de Laravel. `comun.js:40-46` traduce 419 y usa `message` del servidor; `expediciones.js:293` y `reclutamiento.js:239` resincronizan ante 404/409/422; `expediciones.js:228-235` cierra el detalle ante 404. No devuelve trazas ni SQL. (Comportamiento verificado por lectura del código; no se ejecutaron peticiones.) |
| 11 | Dependencias: sin dependencias nuevas | **Cumple** | `git status` sin cambios en `composer.json`, `composer.lock`, `package.json`, `package-lock.json`; `composer audit` solo reporta advisories preexistentes (I-06), ninguno introducido por el cambio. |

## 4. Riesgos residuales aceptados

1. **Historial sin paginación (M-01)** — aceptado en plan v1. Recomendación al cerrar el módulo: límite + «cargar más» o retención; mientras no exista, monitorizar tamaño de `exploraciones_activas` por usuario.
2. **Sink `onerror` con slugs persistidos (B-02)** — sin entrada de usuario en los caminos de escritura auditados. Si en el futuro se aceptan slugs/etiquetas desde request o desde datos importados, pasa a ser un sink crítico: endurecer antes con `Js::from()`.
3. **Sin rate limiting en los POST nuevos** — el abuso solo afecta al propio usuario y a la carga del servidor. Aceptado; candidato a `throttle` global si el proyecto lo introduce.
4. **`fillable` amplio en `ExploracionActiva` (I-02)** — seguro mientras los escritos sigan siendo arrays explícitos.
5. **Advisories de framework (I-06)** — 17 avisos preexistentes (Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17); decisión de actualización fuera del alcance de TASK-042.
6. **Atribución por defecto a `user_id = 1` en caramelos (I-04)** — inalcanzable por HTTP; preexistente.
7. **Caché cliente en memoria y multi-pestaña** — sin `localStorage`/`sessionStorage` para estas colecciones (`expediciones.js`, `reclutamiento.js`), sin polling; el desfase entre pestañas es un compromiso de producto aceptado (`plan.md:238`).

## 5. Recomendaciones fuera de alcance (no bloquean)

- Homogeneizar `Cache-Control: no-store, private` en todas las respuestas con datos de usuario (B-01).
- Sustituir la interpolación manual de JS por `Js::from()` en `_evento.blade.php` y `_caramelo.blade.php` (B-02), replicando `components/sprite.blade.php:58-66`.
- Documentar en `x-ui.modal` la restricción de literales para `abierto`/`cerrar`/`id` (I-01).
- Añadir `where('user_id', …)` explícito en `discardAll` (I-03) y eliminar el fallback `?? 1` de `otorgarCaramelos` (I-04).
- Planificar actualización de dependencias para los advisories de I-06, evaluando en particular `symfony/routing` y `laravel/framework`.

## 6. Controles correctos (resumen)

- Autorización en profundidad: global scope `BelongsToUser` **más** `where('user_id')` explícito **más** `abort_unless(user_id === Auth::id(), 404)` en los endpoints con id; 404 uniforme que no revela existencia de recursos ajenos.
- `ArchivarExpedicion` con transacción + `lockForUpdate()` acotado por `user_id`, idempotencia explícita y reglas 404/422 separadas; sin escritura de `archived_at` desde request.
- Separación lectura/escritura: los tres GET nuevos son puros; el procesamiento solo ocurre en la página, `recoger` y el comando programado.
- JSON compacto sin `eventos`/`diario`/`resultado`; HTML de detalle/historial solo para el propietario y con 404 si está cancelada.
- Fragmentos HTML sin `{!! !!}`, sin `<script>` y sin handlers inline con datos; la única inyección es `x-html` de respuestas same-origin solicitadas con `credentials: 'same-origin'` y `cache: 'no-store'`.
- Frontend con `X-CSRF-TOKEN` desde el meta del layout, `Accept` explícito y manejo diferenciado de 419/404/409/422 sin mutación optimista.
