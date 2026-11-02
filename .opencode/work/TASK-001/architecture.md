# Diseño — TASK-001

Resultado del `architect` (sesión `ses_e6c0d83b9ffewwrHKzDPfJ477X`). Solo lectura; no modificó archivos.

## Resultado

Diseño listo y verificable. Sin cambios de archivo. Sin migración. El diseño se integra con los cambios locales preexistentes de `SendasController::index()` (totales calculados en controlador) e `index.blade.php` (sin bloque `@php`): ambos se conservan.

## Decisiones

**D1 — Curva geométrica sobre el delta, con redondeo por tramo (enteros, sin float).**
Cada delta crece ×1.5 y se redondea al entero más cercano en cada tramo. Para evitar deriva por `float` y ser determinista, el redondeo se implementa en aritmética entera:

```
delta(n → n+1) = delta + intdiv(delta, 2) + (delta % 2)
```

Equivale a `round(delta * 1.5)` con PHP `round()` (half-away-from-zero): si `delta` es impar, `delta*1.5 = x.5` → sube.

| Nivel | Umbral U(n) | Delta desde anterior |
|---|---|---|
| 1 | 0 | — |
| 2 | 500 | 500 |
| 3 | 2.000 | 1.500 |
| 4 | 6.000 | 4.000 |
| 5 | 15.000 | 9.000 |
| 6 | 28.500 | 13.500 |
| 7 | 48.750 | 20.250 |
| 8 | 79.125 | 30.375 |
| 9 | 124.688 | 45.563 |
| 10 | 193.033 | 68.345 |

**D2 — Sin cap, nivel derivado solo de `exp`.** `nivel(exp)` = mayor `n` con `U(n) <= exp`. Umbrales 1–4 intactos → no hay backfill. `exp` es `unsignedBigInteger` (≈1.8e19); el nivel máximo alcanzable con ese techo es ≈197 (< 255), por lo que `player_type_paths.level` (`unsignedTinyInteger`) nunca desborda. No hace falta migración.

**D3 — `player_type_paths.level` se mantiene como caché sincronizado.** El endpoint nuevo lo recalcula en la misma transacción; `PersistirRecompensas` ya lo hace con `SendaProgreso::nivel()` y no se toca (su llamada a `nivel()` no cambia de firma).

**D4 — Agrupación por nivel en el frontend** sobre `ramas` planas ya ordenadas por backend. `ArbolSendas::nodo()` sigue iterando `ramas` planas; no se cambia su forma.

**D5 — Filtro solo por `name`**, ignorando mayúsculas/acentos (mismo `normalizarTexto` de `equipos`), con autoexpansión de grupos mientras hay texto.

**D6 — 100 exp por caramelo**, fuente única `SendaProgreso::EXP_POR_CARAMELO = 100`, botones x1/x10/x100, consumo `min(cantidad, stock)`.

**D7 — Clamp `min(5, required_level)` y `SendaCostes` se conservan** (no-op con datos reales 1–5). Se documenta como deuda latente; no es incoherente con los datos actuales.

## Contratos

### `SendaProgreso` (reescrito, sin cap)

```php
final class SendaProgreso
{
    public const EXP_POR_CARAMELO = 100;          // fuente única

    // Base fija 1..5 (reemplaza a UMBRALES y elimina el array mágico)
    // private const UMBRALES_BASE = [1=>0, 2=>500, 3=>2000, 4=>6000, 5=>15000];
    // private const DELTA_BASE = 9000;             // delta del tramo 4→5

    public static function umbral(int $nivel): int;      // $nivel clampado a >=1; U(n)
    public static function nivel(int $exp): int;         // $exp clampado a >=0; sin cap
    public static function resumen(int $exp): array;
}
```

**Eliminados:** `NIVEL_MAXIMO`, `NIVELES_POR_TRAMO_CUENTA`, `UMBRALES` (público), `capPorNivelCuenta()`, y del resultado: `cap`, `nivel_maximo`, `nivel_por_exp`, `maximo`, `bloqueado_por_cuenta`, `progreso_vertical`, `exp_inicio`.

**`resumen(int $exp): array`** — claves exactas y tipos:

```php
[
    'exp' => int,               // >= 0
    'nivel' => int,             // derivado solo de exp (sin cap)
    'exp_siguiente' => int,     // U(nivel+1), SIEMPRE int (sin máximo)
    'exp_faltante' => int,      // exp_siguiente - exp (>= 0)
    'porcentaje' => int,        // 0..100, floor del tramo actual
    'exp_por_caramelo' => int,  // = self::EXP_POR_CARAMELO
]
```

`porcentaje` = `floor((exp - U(nivel)) / (U(nivel+1) - U(nivel)) * 100)`, clampado 0..100 (idéntica semántica al código actual, sin rama "sin siguiente").

Nota de deuda: `resumen()` sigue devolviendo `array<string, mixed>` (contrato preexistente). No empeorarlo; la deuda se señala pero no se reestructura en esta tarea (regla `.ai/rules/arrays-dto-collections.md`).

### `ArbolSendas` (firmas sin `$nivelCuenta`)

```php
public static function resumen(int $userId): array;                 // list<array>
public static function para(int $userId, TipoEnum $tipo): array;    // array<string, mixed>
public static function nodo(string $slug, int $userId): ?array;
```

- `SendaProgreso::resumen($exp)` (un solo argumento) en `resumen()` y `para()`.
- `exp_por_caramelo` llega a ambos vía el `array_merge` con `$progreso` (fuente única, sin duplicar).
- `ramas` sigue siendo `['fisico'=>list<nodo>, 'especial'=>list<nodo>, 'estado'=>list<nodo>]`, listas planas ordenadas por `nivel_requerido`, `base_power`, `name` (orden actual). `nodo()` intacto.
- Docblock actualizado ("nivel requerido" sin "1-5").
- Clamp `$nivelRequerido = max(1, min(5, (int) $nodo->required_level));` se conserva.
- `disponible`/`puede_desbloquear` derivados de `$progreso['nivel']` (ahora sin techo).

### Endpoint de consumo

- **Ruta:** `POST /sendas/experiencia` → nombre `sendas.experiencia`, en `routes/player.php` (grupo `auth`, CSRF activo por middleware `web`).
- **Validación:** `tipo` (string, slug, obligatorio); `cantidad` (`integer|in:1,10,100`).
- **Autorización/aislamiento:** `$userId = (int) auth()->id()`; queries con `withoutGlobalScope(USER_SCOPE_NAME)` + `where('user_id', $userId)`. Sin parámetro de recurso en ruta → no hay IDOR de objeto; el aislamiento entre usuarios se garantiza por el `user_id` explícito.
- **Transacción (serialización por el lock de inventario):**
  1. `PlayerInventory` (`item_key = ItemCatalogo::keyTipo($tipo->label())`) con `lockForUpdate()`. Si `null` o `cantidad <= 0` → `422 {"error":"No tienes caramelos de {label}"}`.
  2. `$consumidos = min($cantidad, $caramelo->cantidad)`.
  3. `PlayerTypePath` `firstOrCreate(['user_id','type_id'], ['exp'=>0,'level'=>1])`, `increment('exp', 100*$consumidos)`, `refresh()`, `update(['level' => SendaProgreso::nivel($exp)])`.
  4. `decrement('cantidad', $consumidos)`.
  - El lock de la fila de inventario (una por `tipo:{slug}`) es el punto de serialización: dos peticiones concurrentes del mismo usuario+tipo se bloquean ahí, evitando el race de `firstOrCreate` de `PlayerTypePath` y el doble consumo. No hace falta `lockForUpdate` adicional sobre el path (mismo razonamiento que `desbloquear`).
- **Respuesta éxito (fuera de la transacción, tras commit):**

```json
{
  "success": true,
  "caramelos": 12,
  "cantidad_consumida": 3,
  "exp_ganada": 300,
  "arbol": { }
}
```

- **Códigos:** `422` tipo desconocido / sin stock / validación; `200` éxito. Consistente con `desbloquear` y `darCaramelo`.

### `SendasController`

- `index()`, `show()`, `desbloquear()`: eliminar `$nivelCuenta` y propagar a `resumen()/para()/nodo()` sin ese argumento.
- `index()` conserva el cálculo de `$totalDesbloqueados`/`$totalNodos` ya presente (cambio local preexistente).
- Nuevo método `experiencia(Request): JsonResponse` con la lógica anterior.
- `desbloquear()` conserva su respuesta `{success, caramelos, nodo, arbol}` (las llamadas internas pasan a `nodo($slug, $userId)` y `para($userId, $tipo)`).

## UI

### `sendasApp` (Alpine) — estado y métodos

**Estado:**

```js
arbol: arbolInicial,
filtro: '',
gruposCerrados: {},
saving: false,
feedback: { tipo, mensaje },
```

**Métodos/getters (presentación únicamente; sin reglas de negocio):**

- `normalizarTexto(v)` → NFD/lower/quitadiacríticos (copia del patrón `equipos`).
- `hayFiltro()` → `filtro` normalizado no vacío.
- `limpiarFiltro()`.
- `ramasFiltradas(categoria)` → `arbol.ramas[categoria].filter(n => !hayFiltro() || normalizarTexto(n.name).includes(filtroNormalizado))`. Solo filtra; no reordena (el orden ya es del backend).
- `gruposDe(categoria)` → agrupa `ramasFiltradas` por `nivel_requerido` (en orden de aparición, que coincide con el orden del backend). Devuelve `[{nivel, nodos:[...]}]`.
- `contadorCategoria(categoria)` → `ramasFiltradas(categoria).length` (contador filtrado).
- `contadorGrupo(grupo)` → `grupo.nodos.length`.
- `totalVisible()` → suma de los tres contadores (para estado vacío global).
- `estaAbierto(categoria, nivel)` → `hayFiltro() ? true : !this.gruposCerrados[categoria + ':' + nivel]` (autoexpansión con filtro).
- `toggleGrupo(categoria, nivel)`.
- `consumir(cantidad)` → `POST /sendas/experiencia` con `{ tipo: arbol.tipo.slug, cantidad }`, headers CSRF/JSON; éxito → `this.arbol = data.arbol`, feedback `'+X exp'`; error → feedback de `data.error`.
- `desbloquear(nodo)` → igual que ahora; éxito → `this.arbol = data.arbol`.
- `textoBoton(nodo)` → simplificado: si `!disponible` → `'Nivel de Senda ' + nivel_requerido`; si `caramelos < coste` → `'Faltan N caramelos'`; si no → `'Desbloquear · N caramelos'`. Elimina la rama `bloqueado_por_cuenta`.
- `etiquetaCategoria`, `categoriaIcono` → sin cambios.

**Nota de regla N1:** el backend entrega `nivel`, `porcentaje`, `exp_siguiente`, `exp_faltante`, `exp_por_caramelo`, `puede_desbloquear` y `disponible` ya calculados. Alpine solo agrupa, filtra, cuenta y colapsa (operaciones de presentación permitidas por `frontend-minimal-logic.md`). El frontend no calcula `100 × cantidad` (la ganancia llega en `exp_ganada` del backend); los textos de los botones (x1/x10/x100) son cantidades de envío, no reglas.

### Estructura de `show.blade.php`

1. **Cabecera:** conserva título/ícono; sustituye `Nivel X / nivel_maximo` por `Nivel X`; conserva badge de caramelos. Añade en la cabecera (o junto al badge) los botones x1 / x10 / x100 con `@click="consumir(1|10|100)"`, `:disabled="saving || arbol.caramelos === 0"`, y etiqueta accesible (`aria-label`). Muestra `arbol.exp_por_caramelo` como dato informativo ("+100 exp/caramelo") sin cálculo.
2. **Eliminar `aside`** (barra vertical, `/5`, "Nivel máximo", "Cap de cuenta").
3. **Barra de filtro** a ancho completo sobre el grid: input `x-model="filtro"` con `aria-label="Filtrar movimientos por nombre"`, icono lupa, botón "Limpiar" `x-show="hayFiltro()"`. Reutiliza el estilo del input de `equipos/index.blade.php:1044`.
4. **Tarjeta de progreso** horizontal: conserva `exp`/`exp_siguiente`/`porcentaje`/barra, sin ramas de cap (`exp_siguiente` ya nunca es `null`; se eliminan `bloqueado_por_cuenta`/`cap` y el banner de cap).
5. **Grid de 3 columnas** (`fisico/especial/estado`), cada columna:
   - Cabecera con `contadorCategoria(categoria)` filtrado.
   - `template x-for="grupo in gruposDe(categoria)"`:
     - **Divisor colapsable** (estado centralizado): botón con `:aria-expanded="estaAbierto(...)"`, `:aria-controls="'grupo-' + categoria + '-' + grupo.nivel"`, icono chevron `rotate-90` (patrón `exploraciones/index.blade.php:101-115`), texto "Nv Senda N" + `contadorGrupo(grupo)`.
     - Contenido `x-show="estaAbierto(...)"` + `x-cloak`, `id` coincidente con `aria-controls`, con los nodos (tarjeta idéntica a la actual, sin cambios en el nodo).
   - Estado vacío de categoría: `x-show="contadorCategoria(categoria) === 0"` → "Sin movimientos en esta categoría."
6. **Estado vacío global:** `x-show="totalVisible() === 0"` con mensaje según haya filtro o no.
7. **Accesibilidad:** `x-cloak` en raíz, `aria-expanded`/`aria-controls` en divisores, `role="progressbar"` con `aria-valuenow` en la barra, `role="status"`/`role="alert"` en feedback.

### `index.blade.php` (ajustes, sin revertir el cambio local)

- Línea 32: `Nivel {{ $tipo['nivel'] }}` (quitar ` / {{ $tipo['nivel_maximo'] }}`).
- Eliminar el `@if($tipo['bloqueado_por_cuenta']) · cap …` (33-35).
- Bloque 45-54: reemplazar las 3 ramas (`exp_siguiente === null` → "Nivel máximo", `bloqueado_por_cuenta` → "cap") por siempre `{{ number_format(exp) }} / {{ number_format(exp_siguiente) }} exp`.
- Se conserva el badge "Nv cuenta {{ $nivelJugador }}" (nivel de cuenta, no es referencia a cap) y los totales ya calculados en el controlador.

## Archivos y secuencia

Ninguno modificado ahora. Lista para implementación (backend → frontend, en un único cambio coherente para no dejar el contrato roto):

| # | Archivo | Responsable | Cambio |
|---|---|---|---|
| 1 | `app/Support/SendaProgreso.php` | backend | Reescribir sin cap; `EXP_POR_CARAMELO`, `umbral()`, `nivel()`, `resumen()`; eliminar cap/métodos/claves |
| 2 | `app/Support/ArbolSendas.php` | backend | Quitar `$nivelCuenta` en `resumen/para/nodo`; docblock; `SendaProgreso::resumen($exp)` |
| 3 | `app/Http/Controllers/SendasController.php` | backend | Quitar `$nivelCuenta`; añadir `experiencia()`; conservar totales de `index()` |
| 4 | `routes/player.php` | backend | `Route::post('/sendas/experiencia', ...)->name('sendas.experiencia')` |
| 5 | `resources/views/sendas/show.blade.php` | frontend | Refactor completo (filtro superior, 3 columnas con grupos colapsables, botones x1/x10/x100, sin cap) |
| 6 | `resources/views/sendas/index.blade.php` | frontend | Quitar `/nivel_maximo`, "cap", "Nivel máximo" |

Sin solapamientos: pasos 1–4 backend (mismo dueño), 5–6 frontend; el contrato (`resumen`/`para`) queda fijado en 1–2 antes de tocar vistas. No se toca `PersistirRecompensas` (usa `nivel()`, firma inalterada).

## Base de datos

- **Tablas:** `player_type_paths`, `player_inventory` (lectura/escritura existentes). Sin cambios de esquema.
- **Migraciones:** ninguna. `exp` `unsignedBigInteger` y `level` `unsignedTinyInteger` no se tocan; el nivel máximo alcanzable con el techo de `exp` es ≈197 < 255; `required_level` sigue 1–5.
- **Índices/constraints:** sin cambios; el único `(user_id, type_id)` de `player_type_paths` y `(user_id, item_key)` de `player_inventory` ya existen y soportan el `firstOrCreate` + lock.
- **Transacciones:** `DB::transaction` en `experiencia()` (lock inventario → path → decrement) y en `desbloquear()` (existente).

## Pruebas futuras (diferidas; no ejecutar)

**Unit `SendaProgresoTest`** (nuevo):
- `umbral()` niveles 1–10 con los valores exactos de la tabla.
- `nivel()` en bordes: `0→1`, `499→1`, `500→2`, `1999→2`, `2000→3`, `5999→3`, `6000→4`, `14999→4`, `15000→5`, `28499→5`, `28500→6`, `48749→6`, `48750→7`, `124688→9`, `193033→10`.
- Sin cap: `nivel(1e15) > 5`.
- `resumen()`: claves exactas, tipos, `exp_faltante` en borde, `exp_por_caramelo === 100`, `porcentaje` para `exp=20000` (nivel 5, 37%).

**Feature `SendasTest`** (nuevo):
- `GET /sendas`: 200, sin textos "cap", "/5", "Nivel máximo".
- `GET /sendas/{tipo}`: 200 válido / 404 inválido.
- `POST /sendas/experiencia`: ok x1 (+100 exp, `level` actualizado, `caramelos` decrementado, `arbol` con `nivel`/`porcentaje` nuevos); x10 con 3 en stock → `cantidad_consumida=3`, `exp_ganada=300`; `cantidad=5` → 422; `tipo` desconocido → 422; stock 0 → 422; aislamiento (caramelos de usuario A no afectados por B).
- `POST /sendas/desbloquear`: sin regresión (ok / sin nivel / sin caramelos / duplicado).
- `PersistirRecompensasTest` (existente): asegurar que sigue escribiendo `level` vía `SendaProgreso::nivel()`.

## Riesgos y mitigaciones

- **[alto] Rotura de contrato** al quitar `cap`/`nivel_maximo`/`bloqueado_por_cuenta`/`progreso_vertical`/`nivel_por_exp`/`exp_inicio`. Mitigación: `grep` de cada clave antes/después; back+front en el mismo cambio; solo 2 vistas + 1 controlador consumen el contrato.
- **[medio] Economicidad:** caramelo como sumidero infinito de exp compite con el desbloqueo (10–400). Mitigación: decisión de producto explícita (cerrada en el alcance); dejar constancia en el informe.
- **[medio] Desbordamiento/precisión en la curva:** con `float * 1.5` habría deriva. Mitigación: fórmula entera `delta + intdiv(delta,2) + (delta%2)`; bucle acotado por el techo de `exp` (~197 iteraciones).
- **[medio] Concurrencia `PlayerTypePath::firstOrCreate`:** Mitigación: el `lockForUpdate` sobre la fila de inventario serializa las peticiones del mismo usuario+tipo.
- **[medio] Caché `level` desincronizado:** Mitigación: se recalcula y escribe en la misma transacción.
- **[bajo] `required_level`/`SendaCostes` limitados a 5:** sin datos >5 hoy; deuda señalada.
- **[bajo] Sin tests de Sendas hoy:** cobertura diferida al cierre del módulo.
- **Rollback:** `git revert` del cambio; sin migración ni cambio de datos, es revertible solo con código.

## Borrador de ADR

- **Título:** Sendas sin cap de nivel, curva geométrica por delta y consumo de caramelos de tipo por exp.
- **Estado:** Propuesto.
- **Contexto:** El cap absoluto 5 y el cap por nivel de cuenta limitaban la progresión y la UI (`/5`, "Nivel máximo", "Cap de cuenta"). Se quiere un sumidero de caramelos de tipo y un nivel derivado exclusivamente de la exp acumulada.
- **Decisión:** Curva geométrica sobre el delta (`delta ×1.5` por tramo, redondeo entero por tramo), umbrales 1–4 intactos; sin cap; `player_type_paths.level` como caché sincronizado (sin migración); agrupación por nivel en frontend sobre lista plana ordenada por backend; filtro por nombre normalizado con autoexpansión; `EXP_POR_CARAMELO = 100` con botones x1/x10/x100 y consumo acotado al stock; endpoint `POST /sendas/experiencia` transaccional con lock de inventario.
- **Consecuencias:** El caramelo compite con el desbloqueo como segundo sumidero (riesgo de producto); `SendaCostes`/`required_level` siguen acotados a 5 sin contenido nuevo por encima del nivel 5 (deuda aceptada); contrato `array` preexistente no empeora pero se documenta.

## Preguntas abiertas

Ninguna bloqueante. El diseño asume:
1. Redondeo por tramo acumulado (enteros encadenados) frente a fórmula cerrada con un solo redondeo.
2. `required_level`/`SendaCostes` acotados a 5 se mantienen como no-op defensivo.
3. La economía del caramelo (exp ilimitada + desbloqueo) queda aceptada y registrada como riesgo.

---

# Revisión v2 (2026-10-31) — sustituye la curva infinita de v1

Resultado del `architect` en sesión `ses_e6bf77e66ffe4Hc0E4AdEoWK7H`, usando `opencode-go/deepseek-v4.1-flash`. No modificó archivos.

## Decisión v2

Curva geométrica cerrada de 5 niveles:

- Umbrales de entrada: `0 / 15.625 / 31.250 / 62.500 / 125.000`.
- Nivel 5: tramo `125.000 → 500.000`.
- `EXP_MAXIMA = 500.000`; la exp real puede superarlo y se acumula en BD sin subir de nivel.
- `NIVEL_MAXIMO = 5`; `EXP_POR_CARAMELO = 100`.
- Sin cap por nivel de cuenta.
- `umbral(n > 5) = 500.000` (saturación usada como `exp_siguiente` del nivel 5).
- `nivel(exp)` acotado a 5.
- `resumen(exp)` devuelve la exp clampeada a `[0, 500.000]` para presentación; la exp real vive en `player_type_paths.exp`.
- `porcentaje` con aritmética entera para evitar errores de borde: `intdiv((exp - inicio) * 100, rango)`.

## Contrato `SendaProgreso::resumen()`

```php
array{
    exp: int,              // [0, 500_000] para presentación
    exp_maxima: int,        // 500_000
    nivel: int,             // 1..5
    nivel_maximo: int,      // 5
    exp_siguiente: int,     // U(nivel+1); en nivel 5 = 500_000
    exp_faltante: int,      // max(0, exp_siguiente - exp)
    porcentaje: int,        // 0..100
    exp_por_caramelo: int   // 100
}
```

Ejemplos canónicos:

| exp | nivel | exp_siguiente | faltante | % |
|---|---|---|---|---|
| 0 | 1 | 15.625 | 15.625 | 0 |
| 15.624 | 1 | 15.625 | 1 | 99 |
| 15.625 | 2 | 31.250 | 15.625 | 0 |
| 62.500 | 4 | 125.000 | 62.500 | 0 |
| 93.750 | 4 | 125.000 | 31.250 | 50 |
| 125.000 | 5 | 500.000 | 375.000 | 0 |
| 312.500 | 5 | 500.000 | 187.500 | 50 |
| 500.000 | 5 | 500.000 | 0 | 100 |
| 600.000 | 5 | 500.000 | 0 | 100 |

## Archivos a corregir respecto al backend v1

- **Modificar** `app/Support/SendaProgreso.php`: constantes y curva v2.
- **Sin cambios** `app/Support/ArbolSendas.php`, `app/Http/Controllers/SendasController.php`, `routes/player.php` (el backend v1 ya aplicó las firmas y el endpoint).
- **Modificar** `resources/views/sendas/show.blade.php`: refactor v1 + `Nivel X / 5` desde `nivel_maximo`, tarjeta de progreso siempre `exp / exp_siguiente`, nota de tope cuando `nivel >= nivel_maximo`, sin aside, filtro superior, grupos colapsables, x1/x10/x100.
- **Modificar** `resources/views/sendas/index.blade.php`: eliminar textos de cap; `Nivel X / nivel_maximo`; siempre `exp / exp_siguiente`; conservar el cambio local de totales.
- **Sin migraciones.**

## Riesgos v2 destacados

- **[alto] Reset de progresión:** con U(2)=15.625, un jugador con 9.050 exp pasa de nivel 4 a nivel 1. Los desbloqueos comprados se conservan, pero los nodos no comprados de niveles 2–4 dejan de estar disponibles. No se propone backfill salvo decisión de producto.
- **[alto] Economía:** llegar a nivel 5 exige 125.000 exp = 1.250 caramelos; antes 15.000 = 150. El caramelo gana peso como sumidero.
- **[alto] Ventana de contrato roto:** el backend v1 ya está en el árbol y las vistas aún leen claves eliminadas. Backend y vistas deben aterrizar juntos.
- **[medio] Caché `level` desincronizado** en filas existentes; se resincroniza en la próxima escritura.
