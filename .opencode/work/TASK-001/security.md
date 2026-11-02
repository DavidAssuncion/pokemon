# Seguridad — TASK-001

Resultado del `security` (sesión `ses_e6beca5bbffexUv7uj1YoldDdt`), modelo `opencode-go/deepseek-v4.1-flash`. Solo lectura; no modificó archivos.

## Veredicto

**PASS_WITH_FINDINGS** — sin CRITICAL/HIGH en el cambio; 2 LOW y 2 INFO.

## Superficie analizada

- `app/Http/Controllers/SendasController.php` (`index/show/desbloquear/experiencia`).
- `app/Support/SendaProgreso.php` (curva v2).
- `app/Support/ArbolSendas.php` (árbol, scopes, caramelos).
- `routes/player.php:64-68` dentro del grupo `auth` + `web`.
- `resources/views/sendas/show.blade.php` e `index.blade.php`.
- Modelos `PlayerInventory`/`PlayerTypePath`, trait `BelongsToUser`, `ItemCatalogo`, `SendaCostes`, migraciones, layout y `bootstrap/app.php`.

## Hallazgos

- **[LOW] Ausencia de rate limiting en endpoints mutantes de Sendas.**
  `routes/player.php:68` (y `:67`); `SendasController.php:131`. Una cuenta con stock puede repetir POSTs y cada éxito ejecuta transacción + lock + construcción del árbol completo. No permite doble gasto ni salto de nivel; el impacto es consumo de CPU/DB acotado por el inventario. Remediación: `throttle` por `user_id` (p. ej. 30/min) en las rutas de Sendas.

- **[LOW] Mass assignment latente por `$fillable` sobreexpuesto.**
  `app/Models/PlayerTypePath.php:20-25`; `app/Models/PlayerInventory.php:21-25`. Hoy no explotable: `experiencia()` solo escribe `level` calculado y `exp` por `increment`; no se aceptan `exp`, `level` ni `user_id` del cliente. Riesgo futuro si se añade `update($request->validated())`/`create($request->all())`. Remediación: excluir `user_id`/`exp`/`level` del `$fillable` y escribir con `forceFill`/repositorio.

- **[INFO] TOCTOU de disponibilidad/coste en `desbloquear`.**
  `SendasController.php:70-87` calcula `disponible`/`coste` fuera de la transacción; solo stock y duplicado se validan bajo lock. Impacto nulo hoy porque la exp es monótona creciente y `required_level`/`SendaCostes` son estáticos. Deuda si la exp puede decrecer o el catálogo se edita en caliente.

- **[INFO] `exp` real frente a `exp` de presentación.**
  `SendaProgreso.php:103`; `SendasController.php:177`; `show.blade.php:98`. `exp_ganada` refleja el incremento real, pero `arbol.exp` se clampa a 500.000. Solo presentación; decisión de producto documentada.

## Controles correctos

- **Autenticación:** `sendas.experiencia` bajo `auth`; sin rutas públicas ni `withoutMiddleware`.
- **CSRF:** grupo `web` activo; sin excepciones; meta `csrf-token` en layout; `fetch` envía `X-CSRF-TOKEN`; falla cerrado con 419.
- **Autorización/aislamiento:** `$userId = auth()->id()`; queries con `withoutGlobalScope(USER_SCOPE_NAME)` + `where('user_id')`; sin parámetro de recurso en la ruta → sin IDOR. `desbloquear` verifica que el move pertenezca al tipo y resuelve el nodo por usuario.
- **Transacción/concurrencia:** `DB::transaction` + `lockForUpdate` sobre `player_inventory` (único por `user_id,item_key`); `min(cantidad, stock)`; `decrement` bajo lock; `firstOrCreate` de `player_type_paths` serializado por el mismo lock y protegido por unique `(user_id,type_id)`; `level` recalculado tras `refresh`, acotado a 5.
- **Validación:** `tipo` string max:30 + resolución contra `TipoEnum`; `cantidad` required|integer|in:1,10,100; stock 0 o fila ausente → 422 sin efectos; JSON malformado → 422.
- **Overflow:** incremento máximo 10.000/req sobre `unsignedBigInteger`; porcentaje máximo 37,5M; `level` ≤5.
- **XSS:** `@js($arbol)` con flags JSON_HEX; datos de nodo/movimiento con `x-text`; sin `x-html` ni `{!! !!}` en Sendas; index usa `{{ }}`.
- **Mass assignment en el flujo auditado:** solo valores calculados; no se aceptan `exp`, `level` ni `user_id` del cliente.
- **Exposición:** respuesta JSON solo con datos de juego; errores genéricos; sin secretos en el diff; `console.error` sin payload sensible.

## Dependencias

- `composer validate --no-check-publish` → válido.
- `composer audit --no-dev` → 8 advisories preexistentes en 5 paquetes (Laravel, Symfony, CommonMark, Livewire), ajenos al diff. No se actualizan sin autorización.
- `vendor/bin/phpstan analyse` sobre los 3 PHP → solo desajustes de baseline y `cast.useless`; sin errores de seguridad.

## Riesgos residuales

- Economía aceptada: 100 exp/caramelo compite con el desbloqueo; nivel 5 = 1.250 caramelos; sin techo de exp real.
- Sin rate limiting (LOW): abuso de recursos acotado por inventario.
- Reset de progresión por U(2)=15.625 (producto).
- Caché `level` puede desfasarse si `experiencia()` y `PersistirRecompensas` se intercalan; no se lee para autorizar ni calcular nivel.
- Advisories de dependencias preexistentes.
- `APP_DEBUG` activo en producción podría exponer trazas en un 500; no verificable sin leer `.env` (no leído por política).

## Recomendación

Aprobable para entrega: no hay CRITICAL/HIGH y los controles de CSRF, aislamiento, transacción con lock y validación son correctos. Antes del cierre se recomienda aplicar `throttle` por usuario (LOW) y planificar aparte el endurecimiento de `$fillable` y la actualización de dependencias con advisories.
