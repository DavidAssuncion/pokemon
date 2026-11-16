# TASK-034 — Seguridad

- **Estado:** PASS_WITH_FINDINGS — no bloqueante
- **Auditor:** `security` (sesión `ses_e40a4f7c1ffeG612GctXN4LMSo`)
- **Nota:** resultado del auditor, sin alterar sus conclusiones.

---

# Auditoría de seguridad — TASK-034

## 1. Veredicto

**PASS_WITH_FINDINGS** — no hay hallazgos CRITICAL ni HIGH atribuibles a TASK-034. **No bloquea la entrega.** Los hallazgos son INFO (endurecimiento) y deuda de dependencias preexistente, no introducida por este cambio.

## 2. Superficie revisada

- `app/Support/ProgresoRecompensas.php` — `capturar()` (líneas 31-48), `comparar()`, segmentos y resumen.
- `app/Support/SnapshotProgreso.php`, `ProgresoCuentaVista.php`, `ProgresoSendaVista.php`, `ProgresoRecompensasVista.php`, `ProgresoSegmentoVista.php`.
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php` — `$userId` desde `$meta` (líneas 78, 120, 183, 249), `medirProgreso()` (296-305), `adjuntarProgreso()` (314-323).
- `app/Livewire/Presenters/BatallaVistaPresenter.php:431` — reenvío de `progreso`.
- `resources/views/livewire/partials/reward-progreso.blade.php` y `_reward-progreso-barra.blade.php`.
- `resources/views/livewire/partials/combate-scripts.blade.php` (función `rewardProgreso()` y evento `reward-modal-visible`).
- `resources/views/livewire/combate.blade.php:148,247` — inclusiones.
- Cadena de confianza: `routes/web.php` (grupo `auth`), `BattleSessionService`, `CreadorBatallaSesion`, `IniciarCombate{Ruta,Entrenador,Gimnasio,Mazmorra}` y sus controladores, `BelongsToUser`, `GestorLifecycleBatalla`.

## 3. Hallazgos por severidad

### [MEDIUM] Deuda de dependencias preexistente — 17 advisories, 10 paquetes

**Ubicación:** `composer.lock` (no modificado por TASK-034).

**Vector:** dependencias con advisories conocidos siguen instaladas.

**Evidencia:** `composer audit` termina con código 10 y reporta `Found 17 security vulnerability advisories affecting 10 packages`. Los más relevantes al stack:

- `laravel/framework v12.67.0`: CVE-2026-114509 (bypass de `TokenGuard`; no se usa TokenGuard: `config/auth.php` solo define guard `session`), CVE-2026-114508 (destrucción de datos por inclusión de relaciones; no se construyen relaciones desde entrada de usuario en este cambio), CVE-2026-102279 (XSS en página de debug; solo con `APP_DEBUG`).
- `livewire/livewire v4.4.2`: GHSA-mhf7-xp6r-6qrc, “Authorization bypass in `#[Authorize]` via reordered parameter keys and `#[Json]` execution order” (afecta `>=4.3.0,<4.4.8`). **No hay usos de `#[Authorize]` ni `#[Json]` en `app/` ni `src/`** (grep sin coincidencias), por lo que no es explotable en el código actual.
- `symfony/http-foundation`, `http-kernel`, `routing`, `mime`, `config`, `filesystem` v7.4.17/7.4.21 (correcciones hasta 7.4.23): ReDoS en `Cache-Control`, bypass de `methods` con HEAD, host elegido por cliente, path traversal en Filesystem, etc.
- `league/commonmark 2.10.0` (≤2.10.1 afectado) y `symfony/yaml 8.1.9`.

**Impacto:** riesgo de plataforma, no de TASK-034. Si en el futuro se usa `#[Authorize]` en Livewire, el advisory de Livewire pasa a ser HIGH y sí sería bloqueante.

**Remediación:** actualizar dependencias en una tarea separada y con aprobación explícita (`composer update` acotado), verificando `composer audit` y `composer analyse`. No requiere cambios en TASK-034.

**No atribuible a TASK-034:** `git status` confirma que no se modificaron `composer.json`, `composer.lock`, `package.json` ni `package-lock.json`.

### [INFO] `capturar()` confía en que `$userId` es el usuario autenticado

**Ubicación:** `app/Support/ProgresoRecompensas.php:31-48`; `app/Livewire/Presenters/PresentadorResultadoBatalla.php:78,120,183,249`.

**Vector:** lectura de EXP de un `user_id` arbitrario con `withoutGlobalScope(PlayerTypePath::USER_SCOPE_NAME)`. No hay IDOR demostrable: `$userId` procede de `meta['user_id']`, escrita solo por `CreadorBatallaSesion::crearYGuardar()` (`app/Support/CreadorBatallaSesion.php:48-50`) desde los controladores autenticados, que usan `Auth::user()->id` (`CombateRutaController.php:69`, `EntrenadorController.php:63`, `GimnasioController.php:68`, `MazmorraController.php:67`) y validan `team_id` contra `Auth::id()`. La batalla y su meta viven en la sesión del propio usuario; un `battle_id` ajeno no existe en su sesión (`BattleSessionService::cargarMeta()` devuelve `null`). `$userId = 0` no actúa como comodín: `whereKey(0)` y `where('user_id', 0)` devuelven vacío.

**Evidencia:** `withoutGlobalScope` está compensado con `->where('user_id', $userId)` (línea 37); el patrón replica `CargarMapaSendaJugador.php:24-28`. `ProgresoRecompensas` no se invoca desde ningún endpoint, job ni ruta pública.

**Impacto:** nulo hoy. Se convierte en fuga de EXP ajena si un futuro llamador pasa entrada de usuario a `capturar()` o si se reutiliza meta de otra identidad.

**Remediación (defensa en profundidad):** documentar la invariante en el PHPDoc de `capturar()`; en `PresentadorResultadoBatalla`, usar `(int) Auth::id()` como `$userId` de los snapshots (o añadir una comprobación de coincidencia cuando haya sesión) para que la lectura no dependa de un dato almacenado. No es un cambio obligatorio para esta entrega.

### [INFO] `rewardsVista()` reenvía `progreso` sin validar forma

**Ubicación:** `app/Livewire/Presenters/BatallaVistaPresenter.php:431`.

**Vector:** `is_array($rewards['progreso'] ?? null) ? $rewards['progreso'] : null` acepta cualquier array. Si en el futuro un productor distinto del DTO incluyera datos influidos por el usuario, `_reward-progreso-barra.blade.php:56` interpola `color` dentro de `style` y `:25` interpola `icono` en `src`. No hay XSS porque Blade escapa (`{{ }}`) y `color`/`icono`/`label` provienen de `TipoPokemon` (enum estático, `src/Shared/Tipos/TipoPokemon.php:36-89`). Una inyección CSS/asset requeriría además romper el checksum de Livewire.

**Impacto:** nulo con el productor actual; sería una superficie de endurecimiento si el payload se amplía.

**Remediación:** validar/normalizar el array en la frontera (claves y tipos esperados) o serializar siempre desde `ProgresoRecompensasVista::aArray()`, descartando claves desconocidas. Opcional.

### [INFO] `@json()` dentro de atributo con comillas simples

**Ubicación:** `resources/views/livewire/partials/_reward-progreso-barra.blade.php:55`.

**Evidencia:** `data-segmentos='@json($barra['segmentos'])'`. El compilador de Blade usa por defecto `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT` (`vendor/.../CompilesJson.php:12`), por lo que `'` se serializa como `\u0027` y no rompe el atributo. Además, el payload de `segmentos` solo contiene enteros (`ProgresoSegmentoVista::aArray()`).

**Impacto:** ninguno hoy. Nota de endurecimiento si el contrato incluyera cadenas.

**Remediación:** ninguna obligatoria; si se amplía el contrato, usar comillas dobles con `{{ }}` o mantener el `@json` con flags hex.

## 4. Controles verificados

- **Autenticación/autorización:** `/combate` y todas las rutas que inician batallas están dentro de `Route::middleware('auth')` (`routes/web.php:20-32`). Los `IniciarCombate*` reciben `userId` de `Auth::user()->id`; `team_id` se valida con `Rule::exists(...)->where('user_id', Auth::id())`. No hay endpoints nuevos.
- **IDOR / cruce de usuarios:** no demostrado. `$meta` es server-side, creada junto a la batalla y almacenada en la sesión del usuario; `battle_id` en query se resuelve contra la sesión propia (`GestorLifecycleBatalla.php:33-46`). `where('user_id', $userId)` acota correctamente la consulta sin scope global.
- **SQL/ORM:** solo `SELECT` parametrizados (`whereKey`, `where`, `pluck`). Sin SQL crudo, sin `DB::raw` con entrada.
- **XSS:** parciales usan `{{ }}`; no hay `{!! !!}` (grep sin coincidencias). `@js($progreso)` usa `Illuminate\Support\Js` con `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_THROW_ON_ERROR` (`vendor/.../Js.php:23,111-141`), produce `JSON.parse('...\u0022...')` sin comillas dobles ni `<`/`>`/`&`/`'` literales; seguro en atributo HTML. Las animaciones usan `textContent` y `style.width` con números del backend.
- **CSRF/sesiones/cookies:** sin cambios. No hay formularios ni endpoints nuevos. `SESSION_VERSION = 21` y `MINIMUM_COMPATIBLE_SESSION_VERSION = 9` intactos (`BattleSessionService.php:47,60`); TASK-034 no modifica la serialización de la batalla.
- **Datos sensibles:** el payload `progreso` contiene solo enteros de EXP/nivel/porcentaje, etiquetas de tipo y rutas de assets; no incluye PII, email, tokens ni identificadores de sesión. No se registra en logs (sin `Log::`/`logger()` en los archivos tocados); no se persiste en sesión, solo viaja al cliente en el estado de Livewire.
- **Producción de datos:** `label`, `slug`, `color` e `icono` provienen de `TipoPokemon` (enum cerrado); `resumen_accesible` se genera con `sprintf` sobre esos valores y números. No hay texto controlado por el usuario en el payload.
- **Archivos/subidas/secretos:** sin subida de archivos, sin rutas nuevas, sin escritura en disco, sin secretos en los archivos nuevos.
- **Migraciones/tests/dependencias:** sin cambios (`git status -- tests database composer.json composer.lock package.json package-lock.json` vacío).
- **Verificación estática:** `php -l` correcto en los 8 archivos PHP del alcance.

## 5. Riesgos residuales

- **Invariante `meta['user_id'] === Auth::id()`:** se cumple en todos los flujos actuales, pero no está asertada en el punto de lectura. Riesgo bajo, cubierto por el hallazgo INFO.
- **Forma de `progreso` no validada:** riesgo bajo mientras el único productor sea el DTO; aumenta si se reutiliza el partial con otros arrays.
- **Advisories de dependencias:** riesgo de plataforma preexistente; el más sensible (`livewire/livewire 4.4.2`) no es explotable sin `#[Authorize]`/`#[Json]`, pero debe actualizarse.
- **Carrera entre snapshots:** puede mezclar una ganancia externa de EXP en la presentación; no es un problema de seguridad y el impacto es de exactitud visual.
- **Sin tests automatizados:** por regla del proyecto; una regresión futura que introduzca un productor no confiable de `progreso` no sería detectada por la suite.

## 6. Recomendación final

Aprobar TASK-034 desde el punto de vista de seguridad: no introduce CRITICAL/HIGH, no crea endpoints, no acepta entrada nueva, no amplía el acceso a datos de otros usuarios y escapa correctamente toda la salida.

Acciones recomendadas, fuera del alcance de esta entrega:

1. Programar la actualización de dependencias (`laravel/framework`, `livewire/livewire`, `symfony/*`, `league/commonmark`, `symfony/yaml`) en una tarea con aprobación explícita y volver a ejecutar `composer audit`.
2. Opcional: reforzar `capturar()`/`PresentadorResultadoBatalla` con la invariante de identidad y normalizar la forma de `progreso` en `rewardsVista()` como defensa en profundidad.
