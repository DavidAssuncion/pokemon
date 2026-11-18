# TASK-058 — Ejecución

## Preflight (2026-11-17)

- Repo: `/home/david/pokemon`.
- Rama: `master`.
- Último commit: `ffa6cc7 feat(gimnasios): nombre canónico del líder en el dominio`.
- Árbol de trabajo **sucio** con cambios preexistentes que no se tocan:
  - TASK-049/050: `resources/views/equipos/*`, `gimnasios/show.blade.php`,
    `habitaciones/partials/modal-formacion.blade.php`, `partials/leyenda-ivs-evs.blade.php`.
  - TASK-051 (tema): `resources/css/*`, `resources/js/app.js`,
    `resources/views/layouts/*`, `livewire/*`, `combate_page.blade.php`,
    `partials/tipos-tokens.blade.php`, `sendas/index.blade.php`, etc.
  - TASK-053: `public/images/trainers/`, manifiesto, `SpritesEntrenador`,
    `HabitatsController`, `GimnasiosViewController`, vistas de hábitats y gimnasios,
    `THIRD_PARTY_NOTICES.md`, docs de gimnasios.
  - TASK-054 a TASK-057: otras peticiones pendientes.
- No se sobrescribe ninguno de esos cambios.

## Estado

`RECEIVED → ANALYZING → AWAITING_APPROVAL`

## Verificación de causa raíz (2026-11-18)

### Reclutamiento

- Render real de `/reclutamiento` con el usuario 2 y `Request::setUserResolver`:
  la vista se hidrata con 13 reclutables (`JSON.parse('[{"id":23,...}]')`).
  No hay fallo de datos ni de servidor.
- API `GET /api/reclutamiento` con el usuario 2: 13 items, shape
  `{id, pokemon_id, nombre, icono, cantidad}` correcto.
- `php artisan route:list --path=reclutamiento`: 5 rutas bajo `web` +
  `Authenticate`.
- Sesión: driver `database`, tabla `sessions` existe; `same_site=lax`.
- **Alpine 3.16.3** (`node_modules/alpinejs/dist/module.esm.js`):
  - `bind()` `:1445-1471` solo usa `bindAttributeAndProperty` para
    `checked`/`selected`; el resto va a `bindAttribute()`.
  - `bindAttribute()` `:1513-1524`: elimina el atributo solo para
    `null`, `undefined` o `false`; para `''` ejecuta
    `setAttribute('disabled', 'disabled')`.
  - Por tanto `:disabled="accionEnCurso"` con `accionEnCurso = ''` deshabilita
    el control al cargar. `accionEnCurso` solo se limpia dentro de la acción
    bloqueada: deadlock.
- `grep ':disabled='` en reclutamiento: exactamente 3 usos con
  `accionEnCurso` (grid, modal de descarte y modal de descarte total). El resto
  de `:disabled` del layout usa booleanos.

### Expediciones

- `Schema::getColumnListing('exploraciones_activas')`: `archived_at` existe; el
  error de `laravel.log` es histórico y no aplica.
- BD: `ExploracionActiva` con `regreso IS NULL` = 1 fila:
  `id=29`, `user_id=2`, `fin=2026-11-17 21:38:46`, `archived_at=null`,
  `eventos.ultimo_procesado=null` → `estado=pendiente_resolucion`.
- `CalculadorEstadoExpedicion`: `PENDIENTE_RESOLUCION` con `fin <= now` y
  `regreso null`.
- `ExpedicionesPanelController` no inyecta ni llama a
  `ProcesarExploracionesPendientes`; `index()`, `detalle()` y `contenido()` solo
  consultan.
- `PresentadorExploraciones:786`: etiqueta «Pendiente de procesar».
- `PresentadorExploraciones`: `puede_recoger` exige `indefinido` y
  `puede_archivar` exige `TERMINADA`; una expedición no indefinida y pendiente
  no ofrece ninguna acción.

### Descartado como causa

- `Uncaught TypeError: Cannot redefine property: $persist` en `browser.log`:
  error real de doble arranque de Alpine, pero ocurre tras el primer arranque y
  no explica el bloqueo de reclutamiento. Se registra como deuda separada.

### Estado

`RECEIVED → ANALYZING → AWAITING_APPROVAL → IMPLEMENTING → REVIEWING → COMPLETED`.
Sin tests, sin escrituras en BD, sin cambios de aplicación.

## Implementación (2026-11-18)

### Frontend — reclutamiento

- `resources/views/reclutamiento/partials/_grid.blade.php:35`
- `resources/views/reclutamiento/partials/_modal-descartar.blade.php:80`
- `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php:35`

Cambio: `:disabled="$store.reclutamientoNavbar.accionEnCurso"` →
`:disabled="$store.reclutamientoNavbar.accionEnCurso !== ''"`.
Sin cambios en el store ni en el resto de las vistas.

### Backend — expediciones

- `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php`
  - Import y parámetro promocionado `ProcesarExploracionesPendientes $pendientes`.
  - `index()`: `$userId` + `paraUsuario($userId)` antes de `panel($userId)`.
  - `detalle()`: `una($exploracion)` + `$exploracion->refresh()` tras los `abort`.
  - Docblock de clase actualizado: los GET del panel ya no son solo lectura.

## Verificación

- `php -l` del controlador: OK.
- `vendor/bin/pint --dirty --format agent`: pass.
- `php artisan view:cache`: OK; `view:clear`: OK.
- `git diff --check`: limpio.
- `git status --short`: solo los 4 archivos autorizados (más los cambios
  preexistentes ajenos).
- Revisión independiente: **APPROVED**, sin BLOCKER/HIGH/MEDIUM.
- No se ejecutaron tests. No se procesó ninguna expedición durante la
  verificación (la comprobación funcional la hace el desarrollador).

## Hallazgos menores aceptados

- `una()` también se invoca sobre expediciones cerradas (no-op, sin duplicar
  recompensas). Seguimiento opcional: guard `regreso === null`.
- Comentario de `routes/exploraciones.php:9` obsoleto (deuda documental).
- `contenido()` no procesa (fuera del alcance aprobado).

## Estado

`RECEIVED → ANALYZING → AWAITING_APPROVAL → IMPLEMENTING → REVIEWING → COMPLETED`.
Sin commit ni push.
