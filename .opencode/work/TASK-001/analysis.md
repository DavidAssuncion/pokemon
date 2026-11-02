# Análisis — TASK-001

Resultado del `analyst` (sesión `ses_e6c3049daffeZunnHOib0a9VgQ`). Solo lectura; no modificó archivos.

## Resumen

1. **No existe ningún test que cubra Sendas.** `grep -rin "senda" tests` no devuelve coincidencias; `SendaProgreso`, `ArbolSendas`, `SendasController` y `player_type_paths` no tienen cobertura. Solo `PersistirRecompensasTest` toca `expTipoPorMiembro`, pero no asserta `player_type_paths`.
2. **`required_level` no supera 5 en datos**: 639 nodos; distribución `{1:187, 2:114, 3:160, 4:104, 5:74}` (18 tipos). El esquema admite hasta 255 (`unsignedTinyInteger`), y el seeder solo genera 1–5 vía `SendaNiveles`.
3. **`player_type_paths.level` es un caché con cap práctico 255** (`unsignedTinyInteger`) y **no se lee en ningún sitio**: solo se escribe en `PersistirRecompensas.php:181`. `ArbolSendas` deriva el nivel de `exp` (`ArbolSendas.php:105`).
4. **Datos actuales**: 4 filas en `player_type_paths`, `exp` máx. 9050, `level` máx. 4. Cambiar la curva solo a partir del nivel 5 no invalida los niveles cacheados existentes; cambiar los umbrales 1–4 sí exigiría backfill.
5. La UI actual está fuertemente acoplada a `cap`, `bloqueado_por_cuenta`, `nivel_maximo` y `progreso_vertical`; eliminar el cap obliga a tocar `show.blade.php` e `index.blade.php` en el mismo cambio porque el contrato es un array sin tipos.
6. Existe patrón consolidado de **colapsables** (`exploraciones/index.blade.php:101-115`) y de **filtro por nombre con normalización de acentos** (`equipos/index.blade.php:1044`, `1364-1368`, `1722-1736`); y patrón **transaccional antifraude** de consumo de inventario (`ReclutadoController.php:79-116`, espejo en `equipos/index.blade.php:3050-3075`).
7. **Conflicto de producto detectado**: el mismo item `tipo:{slug}` se gasta en desbloquear movimientos (10–400) y ahora también en exp (100 exp/unidad). Sin cap, el caramelo pasa a ser un sumidero infinito de exp que compite con el desbloqueo.

## Requisitos

### Funcionales

- R1. Agrupar movimientos por `nivel_requerido` dentro de cada una de las 3 columnas (`fisico`, `especial`, `estado`), con divisor colapsable por grupo.
- R2. Sustituir el `aside` de progreso vertical por un filtro de nombres en barra superior a ancho completo.
- R3. Eliminar el cap de nivel (absoluto 5 y cap por nivel de cuenta) y derivar el nivel solo de `player_type_paths.exp`.
- R4. Curva geométrica: tramos 1→4 sin cambios; a partir del tramo 5→6 cada tramo crece ×1.5.
- R5. Consumir caramelos de tipo (`player_inventory`, `tipo:{slug}`) para sumar 100 exp a la Senda de ese tipo, con UI x1/x10/x100.
- R6. Actualizar el nivel (y el caché `level`) tras el consumo y devolver el árbol recalculado.

### No funcionales

- N1. `resources/**` no calcula reglas: nivel, porcentaje, `exp_siguiente` y flags deben venir del backend (`.ai/rules/frontend-minimal-logic.md:5-7`).
- N2. Nada de `array` como contrato nuevo; el contrato actual (`ArbolSendas::para()`) ya incumple esto y no debe empeorarse (`.ai/rules/arrays-dto-collections.md:5-32`).
- N3. Constantes de negocio con fuente única: `100 exp/caramelo` no debe quedar duplicado entre Sendas y `ReclutadoController`.
- N4. Consumo atómico y aislado por usuario (patrón `lockForUpdate` + `withoutGlobalScope` + `user_id` explícito).
- N5. Accesibilidad de colapsables (`aria-expanded`, `aria-controls`) y `x-cloak`.

### Criterios de aceptación

- Cada categoría muestra solo grupos con nodos visibles; el contador refleja el filtro.
- El filtro ignora mayúsculas y acentos y se limpia con un botón/escape.
- Un usuario con `exp = 20000` aparece como nivel 5 y con `exp = 28500` como nivel 6 (según curva propuesta).
- Consumir x10 con 3 caramelos consume 3 y suma 300 exp (mismo criterio que `darCaramelo`).
- Tras consumir, el badge de caramelos, el nivel, el porcentaje y los botones `Desbloquear` se actualizan sin recargar.
- Ningún texto de la página menciona cap ni `/5`.

### Ambigüedades detectadas

- A1. "Tramo crece 50 %": ¿el **delta** ×1.5 (propuesto) o el **umbral** ×1.5?
- A2. ¿Se elimina también el cap por nivel de cuenta o solo el techo 5?
- A3. Sin rebalancear `required_level`, a partir del nivel 6 no hay nada nuevo que desbloquear.
- A4. ¿Se mantiene, se deja de escribir o se elimina la columna `level`?
- A5. Redondeo de los tramos no enteros (45562.5) y límite duro.

## Alcance afectado

- **Módulos**: Sendas (progreso de cuenta), Inventario de items, Exploraciones (otorgamiento de exp de tipo), Serialización de reclutados (solo lectura de `required_level`).
- **Tablas/contratos**: `player_type_paths` (`exp`, `level`), `type_path_nodes` (`required_level`), `player_inventory` (`item_key`, `cantidad`); contrato JSON de `ArbolSendas::resumen()`/`para()` y de `POST /sendas/desbloquear`.
- **Vistas/endpoints**: `GET /sendas` (`sendas.index`), `GET /sendas/{tipo}` (`sendas.show`), `POST /sendas/desbloquear` (`sendas.desbloquear`), nuevo endpoint de consumo de caramelos.

## Evidencia

- `app/Support/SendaProgreso.php:15` — `NIVEL_MAXIMO = 5` (cap absoluto).
- `app/Support/SendaProgreso.php:18-24` — `UMBRALES` fijos 0/500/2.000/6.000/15.000; deltas 500/1.500/4.000/9.000.
- `app/Support/SendaProgreso.php:26` + `:42-50` — `NIVELES_POR_TRAMO_CUENTA = 20` y `capPorNivelCuenta()` (`min(5, intdiv(nivel,20)+1)`).
- `app/Support/SendaProgreso.php:28-40` — `nivel()` itera `UMBRALES` y aplica `min(NIVEL_MAXIMO, …)`.
- `app/Support/SendaProgreso.php:75-76` — `expInicio`/`expSiguiente` por lookup; `:78-79` rama "sin siguiente" = nivel máximo.
- `app/Support/SendaProgreso.php:88-90` — `progreso_vertical` divide por `NIVEL_MAXIMO` (solo lo usa el aside).
- `app/Support/SendaProgreso.php:97,103-104` — expone `nivel_maximo`, `maximo` y `bloqueado_por_cuenta`.
- `app/Support/ArbolSendas.php:21-22` — docblock "nivel requerido 1-5".
- `app/Support/ArbolSendas.php:61,65` — `exp` del path y `SendaProgreso::resumen($exp, $nivelCuenta)`.
- `app/Support/ArbolSendas.php:98,105` — mismo patrón en `para()`.
- `app/Support/ArbolSendas.php:123` — `min(5, required_level)` (clamp defensivo; con datos 1–5 es no-op).
- `app/Support/ArbolSendas.php:126,142-144` — `disponible`/`puede_desbloquear` derivados del nivel.
- `app/Support/ArbolSendas.php:195-201` — `nodo()` recorre `ramas` como lista plana: **cambiar la forma de `ramas` rompe este método**.
- `app/Support/SendaCostes.php:14-27` — costes solo 1–5 y `min(5, …)`: si algún día `required_level > 5`, el coste sería el de nivel 5 silenciosamente.
- `app/Support/SendaNiveles.php:9-14,43-63` — asigna 1–5 por potencia/nivel de catálogo; confirma que el seeder nunca genera >5.
- `database/seeders/TypePathNodeSeeder.php:59` — `required_level = SendaNiveles::para($move)`.
- `database/migrations/2026_10_30_162640_create_type_path_nodes_table.php:22` — `required_level` `unsignedTinyInteger` (máx. 255 en esquema).
- `database/migrations/2026_10_30_162641_create_player_type_paths_table.php:21-22` — `exp` `unsignedBigInteger`, `level` `unsignedTinyInteger` (cap práctico 255); `:25` único `(user_id,type_id)`.
- BD (dev, solo lectura): `type_path_nodes` → `required_level` `{1:187,2:114,3:160,4:104,5:74}` (639 filas, 18 tipos).
- BD (dev, solo lectura): `player_type_paths` → 4 filas, `exp` máx. 9050, `level` máx. 4.
- `app/Http/Controllers/SendasController.php:25,41,70` — `$nivelCuenta` se propaga a `resumen()`, `para()` y `nodo()`.
- `app/Http/Controllers/SendasController.php:87-116` — patrón transaccional de `desbloquear`: `withoutGlobalScope` + `user_id` + `lockForUpdate` + `firstOrCreate` + `decrement`.
- `app/Http/Controllers/SendasController.php:127-128` — respuesta devuelve `nodo` y `arbol` recalculados (patrón a replicar).
- `src/Exploraciones/App/PersistirRecompensas.php:125` — única vía actual de exp a Sendas: `otorgarExpSendas`.
- `src/Exploraciones/App/PersistirRecompensas.php:166-181` — `firstOrCreate` + `increment('exp')` + `refresh()` + `update(['level' => SendaProgreso::nivel(exp)])`; usa `SendaProgreso::nivel()`, no `resumen()`, por lo que el cambio de firma de `resumen()` no le afecta.
- `resources/views/sendas/show.blade.php:6` — raíz `x-data="sendasApp(@js($arbol))"`.
- `resources/views/sendas/show.blade.php:16` — "Nivel X / `arbol.nivel_maximo`".
- `resources/views/sendas/show.blade.php:21-26` — badge de caramelos en la cabecera (ubicación natural de x1/x10/x100).
- `resources/views/sendas/show.blade.php:33-52` — aside: barra vertical y textos `/5` (`:48`), `Nivel máximo`/`Cap de cuenta` (`:50`).
- `resources/views/sendas/show.blade.php:42` — `x-for="nivel in [1,2,3,4,5]"` hardcodeado.
- `resources/views/sendas/show.blade.php:56-81` — tarjeta de progreso con `:62-67` `exp para nivel`/`cap alcanzado`, `:72` "Cap de cuenta: nivel X", `:78-80` banner de cap.
- `resources/views/sendas/show.blade.php:83-135` — grid de 3 columnas y `x-for` plano de nodos; `:91` contador; `:111` "· Nv Senda N".
- `resources/views/sendas/show.blade.php:142-200` — `sendasApp`; `:158-160` rama `bloqueado_por_cuenta` en `textoBoton`; `:169-198` `desbloquear()` con `fetch` y reemplazo de `arbol`.
- `resources/views/sendas/index.blade.php:32` — "Nivel X / `nivel_maximo`"; `:33-35` "· cap N".
- `resources/views/sendas/index.blade.php:47-53` — ramas `exp_siguiente === null` → "Nivel máximo" y `bloqueado_por_cuenta` → "cap".
- `resources/views/sendas/index.blade.php:14` — `$nivelJugador` proviene del view composer (`app/Providers/AppServiceProvider.php:82`), no del controlador.
- `app/Http/Controllers/ReclutadoController.php:35-115` — `darCaramelo`: validación `cantidad` 1..100 (`:40`), clamp al faltante (`:77`), transacción con `lockForUpdate` (`:79-98`), consumo mínimo (`:91-92`), `100 * consumidos` (`:105,113`).
- `resources/views/reclutado/show.blade.php:50` — `@foreach ([1,10,100] as $cantidad)`; `:84-110` fetch con CSRF y recarga.
- `resources/views/equipos/index.blade.php:1044` — input de filtro por nombre; `:1364-1368` `normalizarTexto()` (lower + NFD + quita diacríticos); `:1722-1736` getter `movimientosFiltrados` con filtrado cliente.
- `resources/views/equipos/index.blade.php:3050-3075` — `alimentarCaramelo()`: misma cabecera CSRF/JSON y respuesta con `caramelos_disponibles`/`actual`.
- `resources/views/exploraciones/index.blade.php:101-115` — patrón colapsable canónico: `x-data="{ open: false }"`, `@click="open = !open"`, `:aria-expanded`, `aria-controls`, icono `rotate-90`, `x-show` + `x-cloak`.
- `routes/player.php:64-67` — rutas de Sendas dentro del grupo `auth`.
- `database/migrations/2026_08_29_000007_create_player_inventory_table.php:19,22` — `cantidad` `unsignedInteger` y único `(user_id,item_key)`.
- `.ai/rules/frontend-minimal-logic.md:5-7` — backend dueño de lógica y orden; frontend solo filtros de UI.
- `.ai/rules/arrays-dto-collections.md:34-42` — valores de negocio a constantes con fuente única (aplica a `100`).

## Dependencias

- Internas: `ArbolSendas` ← `SendaProgreso`, `SendaCostes`, `SendaNiveles`; `SendasController` ← `ArbolSendas`, `ItemCatalogo`; `PersistirRecompensas` ← `SendaProgreso` + `PlayerTypePath`; `ReclutadoSerializer` ← `TypePathNode` + `SendaCostes`.
- Modelos: `PlayerTypePath`, `TypePathNode`, `PlayerInventory`, `PlayerMoveUnlock`, `Move`.
- Framework: Laravel 12 / PHP 8.4; Blade + Alpine (Alpine inicializado en `resources/js/bootstrap.js` vía Livewire).
- Rutas bajo middleware `auth`.
- Tests: PHPUnit con PostgreSQL `laravel_test` según `phpunit.xml`; testing diferido según `AGENTS.md`.

## Duplicidades y deuda técnica

- **`100` exp/caramelo duplicado**: `ReclutadoController.php:77,105,113` ya repite el literal; añadirlo a Sendas sin constante crearía una tercera/cuarta copia. Fuente única propuesta: `SendaProgreso::EXP_POR_CARAMELO` o `ReglasSenda`.
- **Reglas de negocio en `app/Support`**: patrón consolidado del proyecto; no reestructurar dentro de esta tarea.
- **Contratos con `array<string,mixed>`**: deuda aceptada existente; el cambio debe ser atómico entre backend y vistas.
- **`player_type_paths.level` es un caché muerto**: se escribe pero no se lee; riesgo de deriva si el endpoint nuevo olvida actualizarlo.
- **`progreso_vertical`, `maximo`, `exp_inicio`, `nivel_por_exp`**: expuestos pero sin uso fuera del aside/`textoBoton`; quedarán muertos.
- **`SendaCostes` solo 1–5 con clamp silencioso**: trampa latente si en el futuro se crean nodos de nivel >5.
- **Ausencia total de tests de Sendas**.
- **Dos sinks para el mismo caramelo** (desbloqueo vs exp) sin cap: decisión de producto que puede devaluar el desbloqueo.

## Riesgos

- [alto] **Cambio de contrato de `SendaProgreso::resumen()` y de las claves del array**: eliminar/renombrar `cap`, `bloqueado_por_cuenta`, `nivel_maximo`, `progreso_vertical` provoca "Undefined array key" si alguna vista o llamada no se actualiza en el mismo cambio. Mitigación: `grep` de cada clave antes/después y actualizar `show.blade.php` + `index.blade.php` + controlador en el mismo commit.
- [alto] **Producto/economicidad**: 100 exp por caramelo + sin cap convierte el caramelo en fuente infinita de nivel y compite con el coste de desbloqueo (10–400). Impacto: progresión trivializada o desbloqueos percibidos como caros. Mitigación: decisión explícita del arquitecto/producto.
- [medio] **Curva geométrica: redondeo y desbordamiento**. Con `intdiv(delta*3,2)` los tramos difieren en 1 exp respecto a `round(delta*1.5)`. Además `level` es `unsignedTinyInteger` (255) y `exp` `unsignedBigInteger`; el bucle de cálculo debe acotarse para no desbordar `int` en niveles altos. Mitigación: fórmula cerrada con control de desbordamiento, test de niveles 1..30, política de redondeo documentada.
- [medio] **Concurrencia en `PlayerTypePath::firstOrCreate`**: dos peticiones simultáneas del mismo usuario/tipo pueden chocar con el único `(user_id,type_id)`. Mitigación: bloquear también la fila del path dentro de la transacción o usar `upsert`/reintento.
- [medio] **Caché `level` desincronizado**: el endpoint nuevo debe recalcularlo; si se decide dejar de escribir, documentarlo. Mitigación: actualizar en la misma transacción con `SendaProgreso::nivel($nuevaExp)`.
- [bajo] **Pérdida del indicador vertical de progreso** al eliminar el aside: la tarjeta de progreso horizontal ya cubre la información; aceptado por producto.
- [bajo] **`required_level` >5**: el esquema lo permite pero no hay datos ni seeder que lo generen, y `ArbolSendas.php:123` lo recorta a 5.
- [bajo] **Regresión silenciosa por falta de tests**.
- [bajo] **Accesibilidad/UX de colapsables con filtro**: grupos colapsados pueden ocultar resultados del filtro. Mitigación: autoexpandir mientras hay filtro activo y mostrar recuento filtrado.

## Opciones evaluadas

1. **Curva: función `umbral(nivel)` calculada on-the-fly** (base 1–5 fija + deltas ×1.5 desde el tramo 4→5), con `SendaProgreso::nivel()/umbral()` como única fuente y `resumen()` sin cap ni `nivelCuenta`. Ventaja: sin migración, sin backfill. Riesgo: redondeo a definir.
2. **Curva: tabla de umbrales precalculada hasta nivel N** en constante. Ventaja: determinista y testeable; desventaja: valor mágico de corte y mantenimiento manual.
3. **Agrupación por nivel**: (a) cliente sobre la lista ya ordenada por backend, sin tocar el contrato ni `ArbolSendas::nodo()`; (b) backend devuelve `ramas[categoria]` como grupos, más limpio para la regla de orden en backend, pero rompe `nodo()` y `arbol.ramas[...]`. Recomendada (a) para minimizar regresión.
4. **Consumo de caramelos**: endpoint nuevo `POST /sendas/experiencia` (o `/sendas/caramelos`) con `{tipo, cantidad}` en `[1,10,100]`, transacción con `lockForUpdate`, consumo `min(cantidad, stock)`, `exp += 100*consumidos`, `level` recalculado y respuesta `{success, caramelos, exp_ganada, arbol}`.
5. **Columna `level`**: (a) mantener y sincronizar (mínimo cambio); (b) dejar de escribir y marcar deprecada; (c) eliminar con migración. Recomendada (a) a corto plazo.

## Suposiciones del analista

- "Tramo crece 50 %" = **delta** ×1.5 desde el tramo 4→5: `U(5)=15.000`, `U(6)=28.500`, `U(7)=48.750`, `U(8)≈79.125`, `U(9)≈124.687`, `U(10)≈193.030` (con `intdiv`).
- Los umbrales 1–4 permanecen intactos (0/500/2.000/6.000), por lo que no hace falta backfill.
- Eliminar el cap incluye el cap por nivel de cuenta.
- No se rebalancean `required_level` ni `SendaCostes`.
- El consumo de caramelos se aplica a la Senda del tipo del propio caramelo.
- La BD consultada es la de desarrollo; producción podría diferir.

## Preguntas bloqueantes (para el arquitecto)

1. ¿La curva es delta ×1.5 (U6 = 28.500) o umbral ×1.5 (U6 = 22.500)?
2. ¿Qué política de redondeo para tramos fraccionarios: `floor` por tramo (`intdiv`) o `round`?
3. ¿Se elimina también el cap por nivel de cuenta y, con él, `cap`/`bloqueado_por_cuenta` de todo el contrato?
4. Al superar nivel 5 no queda contenido desbloqueable: ¿es aceptable mostrar niveles >5 sin recompensa?
5. ¿Se mantiene y sincroniza `player_type_paths.level`, se deja de escribir o se elimina con migración?
6. ¿La agrupación por nivel se calcula en frontend sobre la lista ordenada o el backend debe entregar grupos explícitos?
7. ¿El filtro busca solo `name` o también `description`? ¿Debe autoexpandir grupos al filtrar?

## Recomendación del analista

Implementar en un único cambio coherente: (1) reescribir `SendaProgreso` sin cap con `umbral()` iterativo y `resumen()` sin `nivelCuenta`; (2) propagar la firma en `ArbolSendas` y `SendasController`; (3) añadir `POST /sendas/experiencia` con el patrón transaccional de `desbloquear`/`darCaramelo` y sincronizar `level`; (4) refactor de `show.blade.php` (fuera aside, barra de filtro, grupos colapsables calculados en cliente sobre el orden del backend, botones x1/x10/x100 en la cabecera) e `index.blade.php` (quitar `/5`, `cap`, "Nivel máximo"); (5) añadir la constante `EXP_POR_CARAMELO` con fuente única. No hay tests de Sendas: la cobertura debe planificarse al cierre del módulo.

## Archivos modificados

**Ninguno.** Solo lectura y consultas `SELECT` de verificación.

## Archivos candidatos a modificar

| Archivo | Motivo |
|---|---|
| `app/Support/SendaProgreso.php` | Curva geométrica, sin cap, campos nuevos/eliminados, constante 100 exp/caramelo |
| `app/Support/ArbolSendas.php` | Quitar `$nivelCuenta` de `resumen/para/nodo`; docblock; clamp `min(5,…)` a revisar; posibles contadores de grupo |
| `app/Http/Controllers/SendasController.php` | Quitar `$nivelCuenta`; nuevo endpoint de consumo de caramelos; respuesta con `arbol` |
| `routes/player.php` | Nueva ruta `POST /sendas/experiencia` |
| `resources/views/sendas/show.blade.php` | Eliminar aside, barra de filtro, grupos colapsables, botones x1/x10/x100, textos sin cap |
| `resources/views/sendas/index.blade.php` | Quitar `/5`, `cap`, "Nivel máximo" |
| `src/Exploraciones/App/PersistirRecompensas.php` | Solo si se decide cambiar/eliminar la escritura de `level` |
| `tests/Unit/.../SendaProgresoTest.php` (nuevo) | Cobertura de curva y niveles |
| `tests/Feature/.../SendasTest.php` (nuevo) | Endpoints show/desbloquear/experiencia e aislamiento por usuario |

No requiere migración si se mantiene `level` (255 es inalcanzable en la práctica). Requiere migración solo si se elimina/ensancha `level` o si se extienden `required_level` >5.

## Pruebas

- **Ejecutadas: ninguna** (testing diferido; además no hay tests de Sendas).
- **Existentes relevantes**: `tests/Feature/PersistirRecompensasTest.php` (exp de tipo al reclutado, sin assertar `player_type_paths`), `tests/Feature/InventarioTest.php` (consumo/stock/aislamiento de caramelos), `tests/Feature/UserScopeTest.php` (scope por usuario).
- **Cobertura futura necesaria**: `SendaProgreso::nivel/umbral/resumen` (umbrales 1–10, sin cap, borde exacto, exp 0, exp enorme), `ArbolSendas` (orden y flags con nivel >5), `SendasController` (show 200/404, desbloquear ok/sin nivel/sin caramelos/duplicado, consumir ok/sin stock/validación/tipo inválido, aislamiento entre usuarios), `PersistirRecompensas` (exp de tipo en `player_type_paths` y `level`).

## Problemas detectados

- Incoherencias de cap en `/sendas` (`show.blade.php:16,48,50,62-67,72,78-80,158-160`; `index.blade.php:32,33-35,47-53`).
- `player_type_paths.level` es caché muerto y con cap 255.
- `SendaCostes` no soporta niveles >5 (clamp silencioso).
- `ArbolSendas::nodo()` depende de que `ramas` sea lista plana.
- Economía del caramelo: dos sinks compitiendo.
- Sin tests de Sendas.
