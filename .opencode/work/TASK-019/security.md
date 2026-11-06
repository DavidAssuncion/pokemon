# TASK-019 — Auditoría de seguridad

- **Fecha:** 2026-11-05
- **Base:** rama `task-011`, commit `0317179`
- **Alcance:** revisión estática de autenticación, autorización, entrada, SQL, salida, CSRF, sesión, archivos, secretos, dependencias y endpoints. Sin explotación, sin tests, sin cambios de código.
- **Veredicto:** `PASS_CON_HALLAZGOS` — 0 críticos, 3 altos, 7 medios, 5 bajos/informativos.

## 1. Resumen ejecutivo

1. **No se encontraron vulnerabilidades críticas explotables** (sin SQLi directa, sin XSS con datos de usuario, sin subida de archivos, sin IDOR generalizado).
2. **Alto — CRUD de gimnasios sin autorización de administrador.** Cualquier jugador autenticado puede crear/editar gimnasios y etapas.
3. **Alto — mutaciones globales de hábitats sin autorización.** Cualquier jugador autenticado puede asignar/quitar familias y mover Pokémon de nivel.
4. **Alto — ausencia total de rate limiting** en login, registro y acciones de juego.
5. **Medio — 17 avisos de `composer audit`** en Laravel 12.67.0, Livewire 4.4.2, Symfony y CommonMark.
6. **Medio — `users.name` sin `unique` en base de datos**; el login resuelve el primer registro coincidente.
7. **Medio — `BelongsToUser` falla abierto sin autenticación** (`return` si no hay usuario).
8. **Medio — `unserialize()` sin `allowed_classes`** en la carga de batalla de sesión.
9. **Medio — sesión de un año, sin cifrado y sin `Secure` forzado.**
10. **Controles correctos:** CSRF en formularios, escape de salida, whitelists del datagrid, validación de propiedad de recursos, regeneración de sesión y sin SQL crudo concatenado.

## 2. Hallazgos

### 2.1 Altos

#### S-01 — CRUD de gimnasios sin rol de administrador

- **OWASP/CWE:** A01 / CWE-862.
- **Evidencia:** `routes/gimnasios.php:14-18`; `src/Gimnasios/Infra/Controllers/AdminGymController.php` (sin `authorize`, `Gate` ni middleware de rol). `route:list` confirma middleware `web, Illuminate\Auth\Middleware\Authenticate` para `/api/admin/gyms` y `/api/admin/gyms/{slug}`. `routes/web.php:37` expone `/gimnasios/admin` dentro del grupo `auth`.
- **Impacto:** un jugador autenticado puede crear contenido PvE persistente, editar medallas/tipos/niveles y reescribir equipos rivales.
- **Remediación:** columna/tabla de rol admin, `Gate::define('admin')`, middleware `can:admin` en `/api/admin/*` y `/gimnasios/admin`, y `authorize()` en el controlador. No confiar en ocultar la vista.
- **Verificación:** usuario normal → 403; admin → 200; `route:list` debe mostrar el middleware.

#### S-02 — Mutaciones globales de hábitats sin autorización

- **OWASP/CWE:** A01 / CWE-862.
- **Evidencia:** `routes/habitats.php:25-29`; `app/Http/Controllers/HabitatsController.php:108-163` (`assignFamily`, `removeFamily`, `movePokemonLevel` sin `authorize`). `route:list` confirma solo `web + Authenticate`.
- **Impacto:** alteración del spawn global, economía de exploración y composición de hábitats para todos los jugadores.
- **Remediación:** mismo control de rol; separar endpoints de administración en `/api/admin/habitats/*` y aplicar `can:admin`.

#### S-03 — Ausencia de rate limiting

- **OWASP/CWE:** A07 / CWE-307.
- **Evidencia:** `bootstrap/app.php:15-17` (`withMiddleware` vacío); `grep` de `throttle|RateLimiter` sin resultados en `routes` y `app/Http`; `AuthController.php:21-37` usa `Auth::attempt()` sin límite.
- **Impacto:** fuerza bruta de contraseñas, enumeración/creación masiva de cuentas, spam de acciones costosas (forja, exploraciones, cosecha, combates) y agotamiento de recursos.
- **Remediación:** `RateLimiter::for('login', ...)`, `throttle:login` en login/registro, `throttle` global en el grupo `auth` y límites específicos en escrituras.

### 2.2 Medios

| ID | Hallazgo | Evidencia | Impacto | Remediación |
|---|---|---|---|---|
| S-04 | `APP_KEY` concreta en `.env.example` (reportado por el auditor; no se reprodujo el valor por política de secretos) | `.env.example:3` según auditoría | Si se reutiliza en producción, compromete sesiones/cookies/firmas | Vaciar `APP_KEY=` en el ejemplo, rotar claves usadas y revisar historial |
| S-05 | 17 avisos de dependencias | `composer audit --locked`: Laravel 3, CommonMark 2, Livewire 1, HttpFoundation 5, HttpKernel 1, Mime 1, Routing 1, Config 1, Filesystem 1, Yaml 1 | Ventana de exposición creciente | Actualizar Laravel ≥12.69, Livewire ≥4.4.8 y Symfony parcheado; repetir `composer audit` |
| S-06 | `users.name` sin `unique` | `database/migrations/*create_users_table.php:17-18` | Login ambiguo y carrera de registro | `unique` a nivel BD (o `lower(name)`) y mensaje genérico |
| S-07 | Scope `BelongsToUser` fail-open | `app/Models/Concerns/BelongsToUser.php:23-28` | Consultas sin auth devuelven todos los registros | Fallar cerrado en HTTP; scope global explícito para CLI/jobs |
| S-08 | `unserialize()` sin `allowed_classes` | `app/Support/BattleSessionService.php:108` | Object injection si se controla el payload de sesión | `unserialize($payload, ['allowed_classes' => [...]])` y validar `battle_id` |
| S-09 | Sesión de un año, sin cifrado ni `Secure` forzado | `config/session.php:37,52,174,187,204` | Ventana de robo de sesión amplia | `SESSION_LIFETIME=120`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` en producción |
| S-10 | Mensajes internos en respuestas JSON | `bootstrap/app.php:19-27` | Reconocimiento y fuga de detalles | Mensajes públicos estables + log interno |

### 2.3 Bajos / informativos

| ID | Hallazgo | Evidencia | Remediación |
|---|---|---|---|
| S-11 | Fallback `user_id => 1` al otorgar caramelos | `app/Http/Controllers/ReclutamientoController.php:146` | Excepción si `userId === null` |
| S-12 | `.env.testing` versionado | `git ls-files` | Revisar que no contenga claves reales; rotar si aplica |
| S-13 | Credenciales de desarrollo en `docker-compose.yml` | `docker-compose.yml` | No reutilizar el compose en hosts accesibles |
| S-14 | Sin cabeceras CSP/XFO/Referrer-Policy/HSTS | `bootstrap/app.php` | Añadir en producción |
| S-15 | `POST _boost/browser-logs` público en dev | `route:list`; dependencia `require-dev` | Asegurar `composer install --no-dev` en producción |

### 2.4 Falsos positivos descartados

- **XSS en `{!! $entry['html'] !!}`** (`resources/views/livewire/partials/battle-log.blade.php:5`): el productor escapa los datos y solo construye `<img>` con atributos escapados.
- **SQLi en datagrid** (`app/Datagrid/DatagridService.php`): `search`, `filter`, `sort` y `order` usan whitelists; `per_page` acotado.
- **`x-html` de estrellas** (`resources/views/habitats/show.blade.php:503`): valor numérico de BD, sin entrada de usuario.
- **Rutas `storage/{path}`**: exigen URL firmada; no hay escritura anónima.
- **IDOR generalizado**: el scope de usuario y las reglas `Rule::exists(...)->where('user_id', ...)` cubren equipos, reclutados, exploraciones, huevos, inventario y favoritos.

## 3. Autenticación y autorización

- Registro/login con validación de forma, hash por cast, `session()->regenerate()` en login/registro y logout con `invalidate()` + `regenerateToken()`.
- **No hay roles, gates ni policies** en el repositorio; los únicos `authorize()` son comprobaciones triviales de `user() !== null` en `FabricarRequest` y `PlantarBayaRequest`.
- Propiedad de recursos correcta en el núcleo; binding de `Team`, `Reclutado`, `ExploracionActiva` y `Huevo` devuelve 404 para recursos ajenos.
- Endpoints de administración sin rol: `AdminGymController`, mutaciones de hábitats, `/cruds` y `/gimnasios/admin`.

## 4. Entrada, SQL e IDOR

- Sin SQLi explotable: `DB::raw`/`whereRaw`/`selectRaw` solo con constantes o columnas fijas.
- `fillable` amplios con `user_id` en algunos modelos, pero no se encontró `create($request->all())`/`update($request->all())`; riesgo latente.
- Parámetros de ruta con `whereNumber`/casts en granjas, mazmorras, ruta y habitats.
- Único punto a vigilar: `HabitatsController::show` expone `ExploracionActiva` sin filtro explícito de `user_id`, aunque el scope global lo limita.

## 5. Salida, CSRF y sesión

- Blade escapa por defecto; único `{!! !!}` y único `x-html` revisados y seguros con los productores actuales.
- Formularios con `@csrf`; Livewire incluye CSRF y `RequireLivewireHeaders`.
- Cookies `http_only=true`, `same_site=lax`; no se fuerza `secure` ni HTTPS.
- Sin CORS permisivo (no hay rutas `api` con tokens).

## 6. Archivos, secretos y dependencias

- Sin subida de archivos en la aplicación; lectura de imágenes con regex segura y ruta numérica.
- `.env` ignorado; `.env.testing` versionado; `.env.example` con `APP_KEY` reportada; `docker-compose` con credenciales de desarrollo.
- `composer audit`: 17 avisos; `npm audit --omit=dev`: 0 vulnerabilidades (según auditoría).

## 7. Plan de remediación priorizado

| # | Acción | Impacto | Esfuerzo | Verificación |
|---:|---|---|---|---|
| 1 | Rol admin + `Gate`/middleware en `/api/admin/*` y `/gimnasios/admin` | Cierra S-01 | S | 403 con jugador, 200 con admin |
| 2 | Autorización admin en mutaciones de hábitats | Cierra S-02 | S | 403 con jugador |
| 3 | Rate limiting en login/registro y escrituras | Cierra S-03 | S | 429 tras N intentos |
| 4 | Revisar/rotar `APP_KEY` y `.env.testing` | Cierra S-04 | S | `APP_KEY=` vacío; claves rotadas |
| 5 | `unique` en `users.name` + mensaje genérico | Cierra S-06 | S | Migración + duplicado controlado |
| 6 | Actualizar Laravel/Livewire/Symfony | Cierra S-05 | M | `composer audit` sin avisos aplicables |
| 7 | Scope fail-closed sin auth | Mitiga S-07 | S | Consulta sin auth → 0/401 |
| 8 | `unserialize` con `allowed_classes` + validar `battle_id` | Mitiga S-08 | S | Payload manipulado descartado |
| 9 | Endurecer sesión y cabeceras | Mitiga S-09/S-14 | S | Cookie `Secure`+`HttpOnly`; CSP/HSTS |
| 10 | Acotar `fillable`, quitar fallback `user_id=1`, mensajes públicos | Mitiga S-11/S-10 | M | Revisión estática + respuestas sin detalles |

## 8. Verificación ejecutada

- `php artisan route:list --json`: 111 rutas; middleware de admin y hábitats verificado.
- `composer audit --locked --format=json`: 17 avisos, 0 abandonados.
- `npm audit --omit=dev`: 0 vulnerabilidades (según auditoría).
- `grep` de SQL crudo, `{!! !!}`, `x-html`/`innerHTML`, `@csrf`, `throttle`, `Gate/Policy`, `Storage`, secretos y mass assignment.
- Lectura estática de `bootstrap/app.php`, `config/session.php`, migraciones de `users` y `exploraciones_activas`, controladores y rutas.
- **No se ejecutaron tests, exploits ni peticiones destructivas; no se leyó `.env` ni `.env.testing`.**

## 9. Preguntas abiertas

- ¿Existe un rol de administrador previsto fuera de este repositorio? Hoy no hay columna ni gate.
- ¿`.env.testing` contiene credenciales reales? Requiere revisión y rotación si aplica.
- ¿Se despliega con `composer install --no-dev`?
- ¿Producción usa HTTPS y `SESSION_SECURE_COOKIE=true`?
- ¿El registro funciona con `users.email` no nulable y sin email en el formulario? Puede ser un fallo funcional o una migración posterior.

**Riesgo residual:** aunque se corrijan S-01/S-02, cualquier usuario autenticado conserva acceso completo a su propio dominio de juego; la integridad económica depende de que los cálculos server-side sigan siendo la única fuente de verdad. Los sinks `x-html` y la ausencia de CSP mantienen un riesgo bajo si en el futuro se interpola entrada de usuario.
