# TASK-064 — Arquitectura

- **ID:** TASK-064
- **Estado:** COMPLETADO
- **Arquitecto:** `architect`
- **Fecha:** 2026-11-19

## 1. Decisión arquitectónica

1. **Un único gate de QA con dos capas**: middleware `solo-local` (404 fuera de
   `local`/`testing`) aplicado antes de `auth`, más guarda redundante
   `ModoQa::asegurarEntornoPermitido()` en cada acción. El flag
   `POKEDEX_UNLOCK_ALL` **no** habilita mutaciones: solo decide si la UI del modal
   muestra los botones, y siempre exige además `app()->environment('local','testing')`.
2. **Todas las rutas QA** (vista, mutaciones de debug y acciones de Pokédex) viven
   en `routes/debug.php`, cargado desde `routes/web.php` **fuera** del grupo `auth`
   existente, con su propio grupo `['solo-local', 'auth']`. Así el 404 por entorno
   se evalúa antes que la autenticación. No se toca `routes/player.php`.
3. **Lógica en `app/Support/Qa/`**: es infraestructura Laravel de QA, no dominio;
   no se crea un módulo `src/Qa`. Se reutilizan servicios existentes
   (`ResolverBonosFamilia`, `GeneradorIvs::reclutamiento`, `CrearReclutado`,
   `DatosNuevoReclutado`, `ItemCatalogo`) en lugar de duplicar fórmulas.
4. **Patrón de UI diferenciado**:
   - `/debug`: formularios POST + redirect + flash (`session('success')`/
     `session('error')` ya se renderizan en `layouts/app.blade.php:159-169`).
     Cero JS nuevo; estado autoritativo del servidor.
   - `/pokedex`: fetch POST + JSON + feedback inline `role="status"`, igual que
     `equipos/index.blade.php::darEv()`; no recarga ni pierde el modal.
5. **Ningún cambio en `PlayerController.php`**. La vista obtiene el flag QA
   llamando a `ModoQa::accionesPokedexHabilitadas($unlockAll)`, sin calcular
   autorización real en Blade; la autorización efectiva es el 404 del servidor.

## 2. Rutas

Archivo nuevo `routes/debug.php`:

```php
Route::middleware(['solo-local', 'auth'])->group(function (): void {
    Route::get('/debug', [DebugController::class, 'index'])->name('qa.debug.index');
    Route::post('/debug/experiencia', [DebugController::class, 'ajustarExperiencia'])->name('qa.debug.experiencia');
    Route::post('/debug/inventario', [DebugController::class, 'ajustarInventario'])->name('qa.debug.inventario');

    Route::post('/pokedex/qa/reclutar', [PokedexQaController::class, 'reclutar'])->name('qa.pokedex.reclutar');
    Route::post('/pokedex/qa/caramelo-familia', [PokedexQaController::class, 'carameloFamilia'])->name('qa.pokedex.caramelo-familia');
});
```

| Método | URI | Nombre | Middleware efectivo | Controlador@método |
|---|---|---|---|---|
| GET | `/debug` | `qa.debug.index` | `web`, `solo-local`, `auth` | `DebugController@index` |
| POST | `/debug/experiencia` | `qa.debug.experiencia` | `web`, `solo-local`, `auth` | `DebugController@ajustarExperiencia` |
| POST | `/debug/inventario` | `qa.debug.inventario` | `web`, `solo-local`, `auth` | `DebugController@ajustarInventario` |
| POST | `/pokedex/qa/reclutar` | `qa.pokedex.reclutar` | `web`, `solo-local`, `auth` | `PokedexQaController@reclutar` |
| POST | `/pokedex/qa/caramelo-familia` | `qa.pokedex.caramelo-familia` | `web`, `solo-local`, `auth` | `PokedexQaController@carameloFamilia` |

Registro en `routes/web.php`: `require __DIR__.'/debug.php';` a nivel raíz, fuera
del bloque `Route::middleware('auth')`.

Alias en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'solo-local' => \App\Http\Middleware\SoloEnEntornoDePruebas::class,
    ]);
})
```

Orden verificado: `Authenticate` está en `$middlewarePriority`; `solo-local` no,
por lo que se mantiene antes de `auth`. Aun así, el controlador repite la guarda.

## 3. Controladores y servicios

### `app/Http/Middleware/SoloEnEntornoDePruebas.php`

```php
final class SoloEnEntornoDePruebas
{
    public function handle(Request $request, Closure $next): Response
    {
        ModoQa::asegurarEntornoPermitido();

        return $next($request);
    }
}
```

### `app/Support/Qa/ModoQa.php`

```php
final class ModoQa
{
    public static function entornoPermitido(): bool;                 // local|testing
    public static function asegurarEntornoPermitido(): void;         // abort_unless(..., 404)
    public static function accionesPokedexHabilitadas(bool $unlockAll): bool; // $unlockAll && entorno
}
```

`POKEDEX_UNLOCK_ALL=true` en producción ⇒ `$unlockAll` true pero
`entornoPermitido()` false ⇒ UI sin acciones y POST 404.

### `app/Support/Qa/AjustesQa.php`

```php
final class AjustesQa
{
    /** @var list<int> */
    public const CANTIDADES_EXPERIENCIA = [1000, 10000, 100000, 1000000];

    /** @var list<int> */
    public const CANTIDADES_CARAMELOS = [100, 255, 3072];

    /** @return list<int> [-1000000, -100000, -10000, -1000, 1000, 10000, 100000, 1000000] */
    public static function deltasExperiencia(): array;

    /** @return list<int> [-3072, -255, -100, 100, 255, 3072] */
    public static function deltasCaramelos(): array;

    public function ajustarExperiencia(int $userId, int $delta): ResultadoAjusteQa;

    public function ajustarInventario(int $userId, string $clave, int $delta): ResultadoAjusteQa;
}
```

Semántica:
- `ajustarExperiencia`: `DB::transaction` → `User::whereKey($userId)->lockForUpdate()->firstOrFail()`
  → `actual = max(0, anterior + delta)` → `save()` si cambia. Sin tope superior.
- `ajustarInventario`: `DB::transaction`. Si `delta > 0`:
  `PlayerInventory::withoutUserScope()->firstOrCreate(...)` y luego
  `lockForUpdate()`. `actual = max(0, anterior + delta)`. Fila inexistente con
  `delta < 0` ⇒ resultado `0/0/0` sin crear fila.
- Usa `PlayerInventory::USER_SCOPE_NAME`/`withoutUserScope()` para no depender del
  `Auth` global.

### `app/Support/Qa/ResultadoAjusteQa.php`

```php
final readonly class ResultadoAjusteQa
{
    public function __construct(
        public string $clave,
        public int $anterior,
        public int $actual,
        public int $aplicado, // con signo
    ) {}
}
```

### `app/Support/Qa/FilaAjusteQa.php`

```php
final readonly class FilaAjusteQa
{
    public function __construct(
        public string $clave,   // tipo:{slug} | ev:{1..6}
        public string $nombre,
        public string $imagen,
        public int $cantidad,
    ) {}
}
```

### `app/Support/Qa/ContenidoPanelQa.php`

```php
final readonly class ContenidoPanelQa
{
    /**
     * @param  Collection<int, FilaAjusteQa>  $caramelosTipo
     * @param  Collection<int, FilaAjusteQa>  $caramelosEv
     */
    public function __construct(
        public int $experiencia,
        public int $nivel,
        public int $experienciaSiguienteNivel,
        public Collection $caramelosTipo,
        public Collection $caramelosEv,
    ) {}
}
```

### `app/Support/Qa/PanelQa.php`

```php
final class PanelQa
{
    /** @return list<string> 24 claves: 18 tipo:{slug} + 6 ev:{1..6} */
    public static function clavesPermitidas(): array;

    public function contenido(int $userId): ContenidoPanelQa;
}
```

Una consulta `PlayerInventory::withoutUserScope()->where('user_id',$userId)->whereIn('item_key', ...)->pluck('cantidad','item_key')`;
itera `TipoEnum::cases()` (clave `ItemCatalogo::keyTipo($tipo->label())`) y
`StatEnum::cases()` (clave `ItemCatalogo::keyEv($stat->value)`), resolviendo
nombre/imagen con `ItemCatalogo::resolve()`. Experiencia/nivel vía `User` +
`NivelHelper`.

### `app/Support/Qa/ReclutarDesdePokedex.php`

```php
final class ReclutarDesdePokedex
{
    public function __construct(
        private readonly CrearReclutado $crearReclutado,
        private readonly ResolverBonosFamilia $bonosFamilia,
    ) {}

    public function para(int $userId, int $pokemonId): Reclutado;
}
```

Pseudocódigo (equivalente a `ReclutamientoController::recruit`, sin `Reclutable`):

```php
$pokemon = Pokemon::query()->findOrFail($pokemonId);
$chainId = (int) ($pokemon->evolution_chain_id ?? 0);
$bonos = $chainId > 0 ? $this->bonosFamilia->paraFamilia($userId, $chainId) : null;
$ivs = GeneradorIvs::reclutamiento($bonos?->nido->nivel ?? 0);
$evs = $bonos?->evsFamilia;

return DB::transaction(fn (): Reclutado => $this->crearReclutado->crear(
    new DatosNuevoReclutado(userId: $userId, pokemonId: $pokemonId, ivs: $ivs),
    $evs,
));
```

Sin `lockForUpdate`: no consume recurso; solo inserta. `CrearReclutado` fija
`exp = 1500`, `es_shiny=false`, EVs de bonos y dispara `ActualizarPokedexJob`
(síncrono, dentro de la transacción, igual que `recruit`).

### `app/Http/Controllers/DebugController.php`

```php
final class DebugController extends Controller
{
    public function __construct(
        private readonly PanelQa $panel,
        private readonly AjustesQa $ajustes,
    ) {}

    public function index(Request $request): View;
    public function ajustarExperiencia(Request $request): RedirectResponse;
    public function ajustarInventario(Request $request): RedirectResponse;
}
```

- `index`: `ModoQa::asegurarEntornoPermitido()`; devuelve `view('debug.index', [
  'panel' => ..., 'deltasExperiencia' => AjustesQa::deltasExperiencia(),
  'deltasCaramelos' => AjustesQa::deltasCaramelos()])`.
- `ajustarExperiencia`: `$request->validate(['delta' => ['required','integer', Rule::in(AjustesQa::deltasExperiencia())]])`;
  flash `success`/`error`.
- `ajustarInventario`: valida `item_key` ∈ `PanelQa::clavesPermitidas()` y `delta`
  ∈ `AjustesQa::deltasCaramelos()`; flash con nombre de `ItemCatalogo::resolve()`.

### `app/Http/Controllers/PokedexQaController.php`

```php
final class PokedexQaController extends Controller
{
    public function __construct(
        private readonly ReclutarDesdePokedex $reclutar,
        private readonly AjustesQa $ajustes,
    ) {}

    public function reclutar(Request $request): JsonResponse;
    public function carameloFamilia(Request $request): JsonResponse;
}
```

`carameloFamilia` resuelve la cadena **en servidor** desde `pokemon_id` (nunca
acepta `evolution_chain_id` del cliente):

```php
$pokemon = Pokemon::query()->findOrFail((int) $datos['pokemon_id']);
$chainId = (int) ($pokemon->evolution_chain_id ?? 0);
if ($chainId < 1) {
    return response()->json(['message' => 'Este Pokémon no tiene familia evolutiva'], 422);
}
$clave = ItemCatalogo::keyFamilia($chainId);
$resultado = $this->ajustes->ajustarInventario($userId, $clave, (int) $datos['cantidad']);
```

## 4. Contratos de request/response

### POST `/debug/experiencia` (form HTML)

| Campo | Tipo | Regla |
|---|---|---|
| `delta` | int | `required`, `in:±1000,±10000,±100000,±1000000` |
| `_token` | string | CSRF (`@csrf`) |

`302` a `/debug` con `success` (`"Experiencia: 12000 → 13000 (+1000)."`) o `error`
(`"La experiencia ya es 0."` si `aplicado === 0`). Validación fallida → redirect
con `errors`.

### POST `/debug/inventario` (form HTML)

| Campo | Tipo | Regla |
|---|---|---|
| `item_key` | string | `required`, `in:` 24 claves de `PanelQa::clavesPermitidas()` |
| `delta` | int | `required`, `in:±100,±255,±3072` |

- Suma/resta aplicada: `302` + `success`.
- Retirada con stock parcial: clamp `min(stock, |delta|)`, flash con `aplicado` real.
- Stock 0 al retirar: sin mutación, `302` + `error`.
- `item_key`/`delta` manipulados: redirect con error de validación, sin mutación.

### POST `/pokedex/qa/reclutar` (JSON)

Request: `{ "pokemon_id": 25 }`; validación `required|integer|exists:pokemon,id`.

`200`:
```json
{"success": true, "mensaje": "Pokémon reclutado", "reclutado_id": 123, "pokemon_id": 25, "atrapado": true}
```
`422` validación; `404` si el Pokémon desaparece entre validación y `findOrFail`.

### POST `/pokedex/qa/caramelo-familia` (JSON)

Request: `{ "pokemon_id": 25, "cantidad": 100 }`; validación
`pokemon_id required|integer|exists:pokemon,id`, `cantidad required|integer|in:100,255,3072`.

`200`:
```json
{
  "success": true,
  "mensaje": "Familia Pikachu: 250 caramelos",
  "item_key": "familia:10",
  "evolution_chain_id": 10,
  "nombre": "Pikachu",
  "imagen": "/images/candy_pokemon/25.webp",
  "aplicado": 100,
  "cantidad": 250
}
```
`422` si `evolution_chain_id` nulo/0; `404` si no existe el Pokémon.

Códigos: `200` para mutaciones correctas, `422` validación/regla, `404` gate y
recurso inexistente, `419` CSRF.

## 5. Contratos de vistas

### `/debug` — `resources/views/debug/index.blade.php`

Variables: `$panel` (`ContenidoPanelQa`), `$deltasExperiencia` (`list<int>`),
`$deltasCaramelos` (`list<int>`).

Estructura: aviso de entorno, sección de experiencia con botones ±1.000,
±10.000, ±100.000, ±1.000.000; inclusión de
`debug.partials._seccion-caramelos` para tipos y stats. Cada fila: imagen
(con `onerror` a `candy_pokemon/0.webp`), nombre, cantidad actual y un formulario
con botones ±100/±255/±3072. Blade solo presenta y formatea.

### `/pokedex` — bloque QA en el modal

Punto de inserción: después del bloque "Movimientos por nivel" y antes del cierre
de `<div class="space-y-4">`, dentro de `<template x-if="showModal && selectedPokemon">`.
Anclaje por marcador, no por línea.

```blade
<template x-if="qaAcciones && detail">
    <div class="rounded-panel border border-dashed border-warning/60 bg-warning/5 p-4 space-y-3">
        <h3>Acciones QA</h3>
        <button type="button" @click="reclutarQa()" :disabled="qaProcesando">Reclutar</button>
        <template x-if="detail.family && detail.family.evolution_chain_id">
            <template x-for="cantidad in qaCantidades" :key="cantidad">
                <button type="button" @click="carameloFamiliaQa(cantidad)" :disabled="qaProcesando">
                    +<span x-text="cantidad"></span> familia
                </button>
            </template>
        </template>
        <p x-cloak x-show="qaFeedback.mensaje" role="status"
           :class="qaFeedback.tipo === 'error' ? 'text-danger' : 'text-success'"
           x-text="qaFeedback.mensaje"></p>
    </div>
</template>
```

Estado/métodos al final de `pokedexApp()`:

```js
qaAcciones: @json(\App\Support\Qa\ModoQa::accionesPokedexHabilitadas($unlockAll ?? false)),
qaCantidades: @json(\App\Support\Qa\AjustesQa::CANTIDADES_CARAMELOS),
qaProcesando: false,
qaFeedback: { tipo: '', mensaje: '' },
async _postQa(url, payload) { /* fetch POST + X-CSRF-TOKEN + Accept JSON */ },
async reclutarQa() { /* ...; selectedPokemon.atrapado = true; ... */ },
async carameloFamiliaQa(cantidad) { /* ... */ },
```

Reglas: sin `evolution_chain_id` no se muestran botones de familia y el servidor
devuelve 422; tras reclutar solo se marca `selectedPokemon.atrapado`, no se tocan
`counts`; no hay stock inicial de familia en el modal; doble clic mitigado con
`qaProcesando` y `lockForUpdate`.

## 6. Gating y seguridad

| Capa | Mecanismo | Efecto |
|---|---|---|
| Registro | `require routes/debug.php` fuera del grupo `auth` | Entorno antes de autenticar |
| Middleware | `solo-local` → 404 fuera de local/testing | 404 también para anónimos |
| Controlador | `ModoQa::asegurarEntornoPermitido()` en los 5 métodos | Defensa en profundidad |
| UI | `qaAcciones = $unlockAll && entornoPermitido()` | Sin botones con flag en producción |
| Datos | `auth()->id()` como único `user_id` | Sin IDOR |
| Cadena | `evolution_chain_id` desde `pokemon_id` en servidor | Sin claves arbitrarias |
| Inventario | `item_key` validado contra 24 claves | Sin claves arbitrarias |
| CSRF | `@csrf` / `X-CSRF-TOKEN` | POST sin token → 419 |
| Cantidades | `in:` cerrado sobre constantes | Sin deltas arbitrarios |

`/debug` no se añade a `$navItems` en `layouts/app.blade.php`.

## 7. Transacciones, concurrencia y errores

| Operación | Transacción | Lock | Clamp | Sin stock |
|---|---|---|---|---|
| Experiencia ± | `DB::transaction` | `users` `lockForUpdate` | `max(0, …)` | `aplicado = 0` → flash error |
| Inventario + | `DB::transaction` | `firstOrCreate` + `lockForUpdate` | — | n/a |
| Inventario − | `DB::transaction` | `lockForUpdate` | `min(stock, |delta|)`; `max(0, …)` | stock 0 → flash error; parcial → clamp |
| Reclutar | `DB::transaction` | no necesario | — | n/a |
| Caramelo familia + | vía `AjustesQa` | sí | — | n/a |

Nunca negativos: clamp en servidor (no hay `CHECK` en PostgreSQL; no se añade
migración). Doble clic/dos pestañas: `lockForUpdate` serializa; `unique` +
`firstOrCreate` cubre la carrera de creación. Mensajes en español; sin trazas ni
SQL. Validación: 422 JSON para fetch; redirect con `errors` para formularios.

## 8. Archivos nuevos/modificados

### Nuevos

| Archivo | Responsabilidad | Agente |
|---|---|---|
| `app/Http/Middleware/SoloEnEntornoDePruebas.php` | 404 fuera de local/testing | backend |
| `routes/debug.php` | 5 rutas QA con `solo-local` + `auth` | backend |
| `app/Http/Controllers/DebugController.php` | vista y mutaciones de `/debug` | backend |
| `app/Http/Controllers/PokedexQaController.php` | reclutar + caramelo familia (JSON) | backend |
| `app/Support/Qa/ModoQa.php` | política de entorno y flag QA | backend |
| `app/Support/Qa/AjustesQa.php` | mutaciones atómicas + constantes | backend |
| `app/Support/Qa/ResultadoAjusteQa.php` | DTO de resultado | backend |
| `app/Support/Qa/PanelQa.php` | read model + claves permitidas | backend |
| `app/Support/Qa/ContenidoPanelQa.php` | DTO del panel | backend |
| `app/Support/Qa/FilaAjusteQa.php` | DTO de fila | backend |
| `app/Support/Qa/ReclutarDesdePokedex.php` | caso de uso de reclutamiento QA | backend |
| `resources/views/debug/index.blade.php` | vista `/debug` | frontend |
| `resources/views/debug/partials/_seccion-caramelos.blade.php` | fila reutilizable | frontend |

### Modificados

| Archivo | Cambio | Agente |
|---|---|---|
| `bootstrap/app.php` | alias `solo-local` | backend |
| `routes/web.php` | `require __DIR__.'/debug.php';` | backend |
| `resources/views/pokedex/index.blade.php` | bloque QA + estado/métodos Alpine | frontend |

**No se modifica**: `app/Http/Controllers/PlayerController.php`, `routes/player.php`,
archivos de TASK-061/TASK-063.

## 9. Verificación estática y criterios de aceptación

```bash
find app/Http/Middleware app/Http/Controllers app/Support/Qa routes -name '*.php' -exec php -l {} \;
php artisan route:list --path=debug -v
php artisan route:list --path=pokedex/qa -v
php artisan route:list --except-vendor | tail -1
vendor/bin/pint --dirty --format agent
composer analyse
composer arch
grep -n "debug" resources/views/layouts/app.blade.php
git status --short
git diff -- app/Http/Controllers/PlayerController.php
```

Criterios:
1. `route:list -v` muestra `web` → `SoloEnEntornoDePruebas` → `Authenticate`.
2. `APP_ENV=production` y `curl -i /debug` devuelve 404, también sin sesión.
3. `GET /debug` en local: 200, 1 fila de experiencia + 18 de tipo + 6 de EV con
   cantidades actuales.
4. `POST /debug/inventario` aplica exactamente los deltas; `-3072` sobre 100 deja 0
   y aplica −100; stock 0 no crea filas.
5. `item_key=familia:1` en `/debug/inventario` → error de validación.
6. `POST /pokedex/qa/reclutar` crea `Reclutado` con `exp.total=1500`, IVs/EVs de
   bonos y `Pokedex.atrapado=true`.
7. `POST /pokedex/qa/caramelo-familia` incrementa `familia:{chain}` en 100/255/3072;
   cadena nula → 422.
8. En producción, `unlockAll` puede ser true pero `qaAcciones` false y POST 404.
9. `composer analyse`, `composer arch`, `pint` sin errores nuevos.
10. `git diff` de `PlayerController.php` vacío; `/debug` no aparece en el menú.

No se ejecutan tests (política del proyecto).

## 10. Riesgos y simplificaciones

| Riesgo | Mitigación |
|---|---|
| Orden de middleware alterado | `route:list -v` bloqueante + guarda en controlador |
| `route:cache` desplegado en producción | El middleware sigue devolviendo 404 |
| `POKEDEX_UNLOCK_ALL=true` en producción | Gate de UI por entorno y POST 404 |
| Conflicto de merge con TASK-061 | 2 inserciones marcadas `TASK-064`; implementar con cuidado |
| Doble submit en `/debug` | Recarga completa; QA acepta doble suma; lock evita corrupción |
| Job síncrono al reclutar en bucle | Solo QA/local; documentado |
| `users.experiencia` sin tope | La curva soporta nivel > 100; no se acota |
| Imagen de caramelo inexistente | `onerror` → `candy_pokemon/0.webp` |

Simplificaciones:
1. Sin módulo `src/Qa`: QA no es dominio.
2. Sin arrays como contrato: DTO readonly + `Collection` tipada.
3. Sin endpoint de stock inicial de familia en el modal: se muestra tras la acción.
4. Sin actualización de contadores globales: solo `selectedPokemon.atrapado`.
5. Sin flag QA adicional: se reutiliza `$unlockAll` + entorno.

Decisiones abiertas no bloqueantes:
- ¿Mostrar stock de `familia:{chain}` en `/debug`? No está en el encargo.
- ¿Reclutar también desde `/debug`? El encargo lo sitúa en Pokédex.
- ¿Botón "abrir /debug" en el badge "Modo test"? No solicitado.

## 11. Rollback

1. Antes de implementar:
   `git diff -- bootstrap/app.php routes/web.php resources/views/pokedex/index.blade.php > /tmp/opencode/task064-modificados.patch`.
2. Revertir: `git apply -R /tmp/opencode/task064-modificados.patch` y borrar los
   13 archivos nuevos con `rm` explícito.
3. Prohibido `git checkout --`, `reset --hard`, `clean` o `stash`.
4. El rollback no toca `PlayerController.php` ni archivos de TASK-061/TASK-063.

## 12. Verificación del arquitecto

Lectura estática, `analysis.md`, código y `vendor/`; no se modificaron archivos,
no se leyó `.env`, no se ejecutaron tests.
