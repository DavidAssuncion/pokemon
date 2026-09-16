# ANÁLISIS BACKEND — Correcciones QA del módulo Huevos + CP + Ruta

Fecha: 2026-09-15
Rol: Backend (Laravel / PHP 8.4).
Origen: QA bloqueó el commit `79b7e45b2783` con 2 desviaciones de contrato (B1, B2) y 4 observaciones a corregir (O1, O2, O3, O6), todas aceptadas por el Analista.

## Objetivo

1. **B1** — Cumplir el contrato de tiempos de huevo confirmado por el cliente:
   - Puesta: `horas = 255 / capture_rate` (sin clamp ni ×2).
   - Eclosión: `horas = ceil(hatch / 15)` (DIVISOR_HATCH=15), usando el `hatch` de la especie.
   - `GET /api/huevos` devuelve la respuesta en **una única unidad (horas, float)**: `tiempo_total_horas`, `tiempo_restante` y `progreso` (0-100), documentado en el contrato del response.
2. **B2** — CP del reclutado al **nivel del usuario** (`auth()->user()->nivel()`) y con los **EV reales** del reclutado como 2º argumento de `CalculadorCP::calcular`; propagar `nivelUsuario` opcional desde `serializar()` a `cpDe()` y desde los callers.
3. **O1** — Semántica de límites: máx. 1 huevo en GESTANDO y máx. 1 en INCUBANDO (2 huevos activos de distinto estado pueden convivir) + máx. 1 huevo activo por familia → columna `huevos.evolution_chain_id` (índice, sin FK estricta).
4. **O2** — Requisito de pareja por **familia** (`evolution_chain_id`), no por especie: ≥2 reclutados DISTINTOS de la misma cadena; dedupe por familia en el listado; especies distintas de la misma cadena OK.
5. **O3** — Propagar `es_shiny` al modal de capturas de ruta: `RecompensaCaptura::$esShiny`, `OtorgarRecompensasRuta` con `esShiny: true` en el slot shiny y `ResultadoRuta::aArray()` con clave `es_shiny`.
6. **O6** — Key case consistente: `Combatiente::aArrayVista()` emite `es_shiny` (snake) en lugar de `esShiny`.

NOTA: el frontend ya consume `tiempo_restante` como horas (`formatearDuracion`) y ya soporta `es_shiny` en cards (`_pokemon-card.blade.php` y `combate.blade.php`); el backend se alinea con ambas lecturas.

## Archivos afectados

### Modificados
- `src/Huevos/Domain/CalculadorTiemposHuevo.php` — fórmulas nuevas (255/cr, ceil(hatch/15), suma en horas).
- `src/Huevos/Domain/CalculadorEstadoHuevo.php` — recibe `hatch`, estados por horas float, `restante_horas`.
- `src/Huevos/App/ListarHuevos.php` — serializa con horas float + `progreso`, `disponibles` por familia (≥2 individuos + dedupe + sin huevo activo por familia + hatch no nulo), `limites` con hatch.
- `src/Huevos/App/CrearHuevo.php` — requisito por familia (≥2 individuos), bloqueos GESTANDO-activa y 1-activo-por-familia, persiste `evolution_chain_id` + `hatch`.
- `src/Huevos/App/EclosionarHuevo.php` — pasa `hatch` a `estadoDe`.
- `src/Huevos/Infra/Controllers/HuevoController.php` — `eclosionar` serializa el nuevo reclutado con el nivel del usuario.
- `app/Models/Huevo.php` — fillable con `hatch` + `evolution_chain_id`.
- `app/Support/ReclutadoSerializer.php` — `serializar(?, int $nivelUsuario = null)` y `cpDe` con nivel del usuario (fallback nivel del reclutado) + EVs reales.
- `app/Http/Controllers/PlayerController.php` — `equipos` pasa `auth()->user()?->nivel()`.
- `app/Http/Controllers/ReclutadoController.php` — `listarTodos`/`listarFavoritos` pasan `auth()->user()?->nivel()`.
- `src/Exploraciones/Domain/Recompensas/RecompensaCaptura.php` — `bool $esShiny = false`.
- `src/CombateRuta/App/OtorgarRecompensasRuta.php` — captura shiny con `esShiny: true`.
- `src/CombateRuta/Domain/DataTransferObjects/ResultadoRuta.php` — `aArray()` incluye `es_shiny`.
- `src/Battle/Domain/Combatiente.php` — `aArrayVista()` emite `es_shiny`.

### Nuevos
- `database/migrations/2026_09_15_000003_add_hatch_and_evolution_chain_id_to_huevos_table.php` — columna `hatch` y `evolution_chain_id` (+ índice) en `huevos`.

### Verificados sin cambio
- `app/Livewire/Presenters/PresentadorResultadoBatalla.php::procesarRuta` — `rewards = $resultado->aArray()` llega directo al modal; `es_shiny` del DTO fluye (no se pierde).
- `routes/huevos.php` / `routes/web.php` — bajo `auth`; sin cambios.
- `resources/views/livewire/_pokemon-card.blade.php` — ya lee `$p['es_shiny']` (con fallback al icono).

## Comportamientos a cubrir en el testing futuro

### Unit
- `CalculadorTiemposHuevo::puestaHoras` — CR 255 → 1.0 h; CR 3 → 85.0 h; CR 0/negativo → 255 h (max(rate,1)).
- `CalculadorTiemposHuevo::eclosionHoras` — hatch 15 → 1.0; hatch 30 → 2.0; hatch 1 → 1.0 (ceil); hatch 10240 → 683 h.
- `CalculadorTiemposHuevo::sumaHoras` — puesta + eclosión.
- `CalculadorEstadoHuevo::estadoDe` — GESTANDO con restante en horas; transición GESTANDO→INCUBANDO en `puesta_fin`; INCUBANDO→LISTO en `hatched_at`; `restante_horas` 0 en LISTO.
- `ReclutadoSerializer::serializar` — `nivelUsuario` dado → CP con ese nivel y EVs reales (suma `evs.toArray()`); sin `nivelUsuario` → fallback nivel del reclutado (test existente `ReclutadoSerializerCPTest` conservado).
- `RecompensaCaptura` — `esShiny` default false; captura shiny con `esShiny: true`.
- `ResultadoRuta::aArray` — cada captura lleva `es_shiny` (bool).
- `Combatiente::aArrayVista` — emite `es_shiny` (snake), no `esShiny`.

### Feature
- `GET /api/huevos` — `tiempo_total_horas` y `tiempo_restante` en horas (float) y `progreso` 0-100; contrato exacto del response.
- `POST /api/huevos` — 422 con 1 solo individuo de la familia; 422 con solo 2 de distinta familia; OK con 2 individuos de distinta especie misma cadena; 422 con huevo GESTANDO activo (otra familia); OK con huevo INCUBANDO activo de otra familia (2 activos de distinto estado); 422 con huevo activo (cualquier estado) de la MISMA familia.
- `POST /api/huevos` — 422 si la especie representativa no tiene `hatch`.
- `POST /api/huevos/{id}/eclosionar` — el reclutado serializado trae `cp` con el nivel del usuario.
- Equipos `/equipos` y `listarTodos`/`listarFavoritos` — `cp` calculado con `auth()->user()->nivel()` y EVs del reclutado.
- Combate de ruta ganado con shiny — modal `capturas[].es_shiny = true` solo en el slot shiny; normales `false`.

## Diseño

### B1 — Tiempos (Domain puro)
- `CalculadorTiemposHuevo`:
  - `PUESTA_HORAS_BASE = 255`; `DIVISOR_HATCH = 15`.
  - `puestaHoras(int $captureRate): float = 255 / max($captureRate, 1)` (int→float con widen explícito).
  - `eclosionHoras(int $hatch): float = ceil($hatch / 15)` (float).
  - `sumaHoras(int $captureRate, int $hatch): float = puesta + eclosión`.
- `CalculadorEstadoHuevo::estadoDe(creadoEn, captureRate, hatch, ahora)`:
  - `puesta_fin = created_at + puestaHoras` horas; `hatched_at = puesta_fin + eclosionHoras` horas.
  - GESTANDO si `now < puesta_fin`; INCUBANDO si `puesta_fin <= now < hatched_at`; LISTO si `now >= hatched_at`.
  - Devuelve `{estado, restante_horas}` (float horas, redondeado a 2 decimales para el formateo del frontend).
- Persistencia: `huevos.hatch` snapshot (mismo criterio que `capture_rate`, para no depender del catálogo); `huevos.evolution_chain_id` para el bloqueo por familia. Read normaliza `hatch ?? 1` (tolerancia a filas legacy sin el snapshot).

### O1/O2 — Límites y pareja
- `CrearHuevo::crear`:
  - Bloquea si existe huevo ACTIVO (sin eclosionar) de la USUARIO en GESTANDO con la misma familia → msgs distintos (gestación en curso / huevo activo de esta familia).
  - INCUBANDO de otra familia NO bloquea (permite gestando + incubando simultáneos).
  - Pareja: `count(Reclutado whereHas pokemon.evolution_chain_id = X) >= 2` (individuos, permitiendo especies distintas de la misma cadena).
  - Especie del huevo: miembro de la familia con menor `species_id` (regla "primer integrante" del proyecto).
- `ListarHuevos::disponibles`: 1 entrada por `evolution_chain_id` con ≥2 individuos, sin huevo activo de esa familia, sin representante con `hatch` null; orden por chain id.

### B2 — CP
- `ReclutadoSerializer::serializar(Reclutado $reclutado, ?int $nivelUsuario = null)` → `cpDe($reclutado, $nivelUsuario)`.
- `cpDe`: `$nivel = $nivelUsuario ?? NivelHelper::nivelDesdeExperiencia($reclutado->exp->total())`; `$ev = (int) floor(array_sum($reclutado->evs->toArray()))` → `CalculadorCP::calcular($stats, $ev)`.
- Callers (equipos, listarTodos, listarFavoritos, eclosionar) pasan `auth()->user()?->nivel()`.

### O3 — Shiny en capturas
- `RecompensaCaptura(pokemonId, cantidad, esShiny = false)`; `OtorgarRecompensasRuta` crea la captura directa del slot shiny con `esShiny: true`; `ResultadoRuta::aArray()` emite `es_shiny` por captura.

### O6 — snake_case
- `Combatiente::aArrayVista()` renombra `esShiny` → `es_shiny` (consumidor `_pokemon-card.blade.php` ya lee snake).

## Riesgos

- **Frontend `limiteExcedido()`**: `equipos/index.blade.php` deshabilita "Poner huevo" si hay un huevo INCUBANDO o LISTO (`incubacionActiva()`), mientras que el backend ahora permite crear un GESTANDO con INCUBANDO de otra familia. El ticket es de backend; la UI quedará coherente cuando el frontend consuma `limites`/estados (ticket frontend pendiente). El backend `limites` se documenta con las nuevas semánticas.
- **Filas legacy de huevos** sin `hatch`/`evolution_chain_id` (migración aditiva nullable): lectura con `hatch ?? 1` y bloqueo por familia ignora nulos (`where evolution_chain_id = ?` no casa con null).
- **Especies sin `hatch`**: el seeder deja `hatch` null si el `hatch_counter` está vacío; `disponibles` filtra esas familias y `CrearHuevo` las rechaza con 422 (no se puede calcular la eclosión).
- **`Tests de ReclutadoSerializerCPTest`**: conservados — sin `nivelUsuario`, `cpDe` cae al fallback nivel del reclutado (misma expectativa que el test existente, EV=0 por defecto).
- **Migración**: se añade con migración NUEVA (no se toca `2026_09_15_000002_create_huevos_table`) para que aplique también sobre BDs que ya tienen la tabla (patrón del bug 23503).
- **Testing diferido** por convención del proyecto: no se ejecutan tests.

---

# ANÁLISIS BACKEND — 3 correcciones menores del Arquitecto (idempotencia eclosión + defensa en profundidad + SESSION_VERSION 10)

Fecha: 2026-09-16
Rol: Backend (Laravel / PHP 8.4).
Origen: El Arquitecto señaló 3 correcciones menores a aplicar sin testing (testing diferido al cierre del módulo).

## Objetivo

1. **Idempotencia de eclosión** — `EclosionarHuevo` debe rechazar con `ViolacionReglaNegocio` (422) cualquier intento de eclosionar un huevo que ya tiene `eclosionado_en` marcado.
2. **Defensa en profundidad (user_id)** — `EclosionarHuevo` debe revalidar que el huevo pertenece al usuario; el route-model binding con el scope `BelongsToUser` ya acota por usuario (contrato preservado), este check solo protege llamadas que lleguen con un `Huevo` cargado fuera del scope (p.ej. jobs). Se revisa también `CrearHuevo` (no requiere cambio: no recibe modelos ajenos; todas sus queries ya acotan por `user_id`).
3. **SESSION_VERSION 9 → 10** — `BattleSessionService` sube la versión de sesión de batalla. El cambio estructural relevante es que `BattleStats` ahora puede llevar `?StatsValue $ivs` (propiedad opcional). Un payload v9 sin esa clave debe seguir cargando (la propiedad se reconstruye en `null` sin migración).

## Archivos afectados

### Modificados
- `src/Huevos/App/EclosionarHuevo.php` — checks al inicio (tras `load('pokemon')`): pertenencia al usuario (`RecursoNoExiste` 404) y idempotencia (`ViolacionReglaNegocio` 422).
- `app/Support/BattleSessionService.php` — `SESSION_VERSION` 9 → 10 + compuerta de versión que sigue aceptando payloads v9 (mínimo compatible).

### Revisados sin cambio
- `src/Huevos/App/CrearHuevo.php` — no hay objeto de otro usuario que revalidar: recibe `int $userId` y todas sus consultas (`Reclutado`, `Huevo`) acotan por `where('user_id', $userId)`. Sin IDOR posible.
- `src/Huevos/Infra/Controllers/HuevoController.php` — ya depende del route-model binding `{huevo}` (scope `BelongsToUser` bajo `auth`): sin cambios.
- `app/Models/Huevo.php` — la columna correcta es `eclosionado_en` (confirmada en la migración `2026_09_15_000002_create_huevos_table.php`, cast `datetime` en el modelo). Sin cambios.

### Verificados (sin cambio)
- Tests `GimnasioLivewireTest` / `MazmorraLivewireTest` serializan con `SESSION_VERSION = 9`; la compuerta de v10 debe seguir aceptando v9 (por eso el mínimo compatible es 9).

## Comportamientos a cubrir en el testing futuro

### Unit
- `EclosionarHuevo::eclosionar` con huevo ya eclosionado (`eclosionado_en` no nulo) → `ViolacionReglaNegocio` 422 'Este huevo ya eclosionó.'.
- `EclosionarHuevo::eclosionar` con huevo de otro usuario (`user_id` distinto al recibido) → `RecursoNoExiste` 404, sin mutar nada.

### Feature
- `POST /api/huevos/{huevo}/eclosionar` idempotente: segunda llamada al mismo huevo → 422 sin duplicar reclutados.
- `POST /api/huevos/{huevo}/eclosionar` con huevo ajeno → 404 (el route-model binding ya lo cubre; el check interno es cinturón de seguridad).

### Acceptance
- Una partida en curso con sesión de batalla v9 (guardada antes del despliegue) sigue cargando tras el bump a v10 (sesiones activas no se pierden).

## Diseño

### Idempotencia + defensa en profundidad (`EclosionarHuevo`)
- Orden de checks tras `$huevo->load('pokemon')`:
  1. `(int) $huevo->user_id !== $userId` → `RecursoNoExiste` (404; no revela existencia).
  2. `$huevo->eclosionado_en !== null` → `ViolacionReglaNegocio('Este huevo ya eclosionó.')` (422).
  3. Estado LISTO (check existente).
- La columna `eclosionado_en` es `timestamp nullable` con cast `datetime` en `Huevo`; `null` → no eclosionado, `Carbon` → eclosionado.

### SESSION_VERSION (`BattleSessionService`)
- El mecanismo NO tiene método `migrar`/switch: la migración de formatos internos vive en `__unserialize()` de los objetos de dominio (`Combatiente` v8 'etapas' → v9 'multiplicadores'; `MovimientoBatalla` legacy v9). `BattleSessionService` solo versiona y descarta lo incompatible.
- `SESSION_VERSION = 10` (las sesiones NUEVAS se guardan como v10). Se añade `SESSION_VERSION_MINIMA = 9`: la compuerta descarta `< 9` (v8 y anteriores, igual que hoy) y sigue aceptando v9 porque la única diferencia estructural es `BattleStats.$ivs` **opcional** (default `null`); `unserialize` reconstruye payloads v9 sin esa clave con `ivs = null`, sin TypeError ni función de migración.
- No se tocan los tests.

## Riesgos

- Si en el futuro se añade una propiedad NO opcional a una clase serializada en el grafo de batalla, el mínimo compatible deberá subirse (o añadirse `__unserialize`) — documentado en el docblock de `BattleSessionService`.
- Los tests de Gimnasios/Mazmorras serializan v9; el mínimo compatible = 9 los preserva (testing diferido, no ejecutado ahora).

---

# ANÁLISIS BACKEND — Hardening P1 de guards capture_rate/hatch en Huevos (4 patches validados por el Hardener)

Fecha: 2026-09-16
Rol: Backend (Laravel / PHP 8.4).
Origen: 4 patches de hardening validados por el Hardener, sin cambiar comportamiento público ni happy paths. Testing diferido.

## Objetivo

Proteger el cálculo de tiempos y el flujo de creación/eclosión/listado de huevos frente a datos null o ≤ 0 que llegarían a `CalculadorTiemposHuevo`/`CalculadorEstadoHuevo` con tipos `int` (un `null` pasaría por `?? 0`/`?? 1` y un `0` quebraría la probabilidad o la división):

1. `CalculadorTiemposHuevo::eclosionHoras` — guard `max($hatch, 1)` coherente con el guard existente `max($captureRate, 1)` de `puestaHoras`.
2. `CrearHuevo::crear` — rechazar especies con `capture_rate === null` (además del `hatch === null` ya existente).
3. `EclosionarHuevo::eclosionar` — `capture_rate ?? 0` al pedir estado (mismas reglas de robustez que el resto).
4. `ListarHuevos::serializarUno` y `ListarHuevos::estadoDe` — `capture_rate ?? 0` al calcular tiempos y estado.

## Archivos afectados

### Modificados
- `src/Huevos/Domain/CalculadorTiemposHuevo.php` — `eclosionHoras`: `ceil($hatch / 15)` → `ceil(max($hatch, 1) / 15)`.
- `src/Huevos/App/CrearHuevo.php` — guard: `|| $pokemon->capture_rate === null` (mismo import/mensaje existentes).
- `src/Huevos/App/EclosionarHuevo.php` — `$huevo->capture_rate` → `$huevo->capture_rate ?? 0` (mantiene `$huevo->hatch ?? 1`).
- `src/Huevos/App/ListarHuevos.php` — `$huevo->capture_rate` → `$huevo->capture_rate ?? 0` en `serializarUno` (variable local `$captureRate`, usada en `sumaHoras`) y en `estadoDe` (parámetro de `CalculadorEstadoHuevo::estadoDe`).

## Comportamientos a cubrir en el testing futuro

### Unit
- `CalculadorTiemposHuevo::eclosionHoras(0)` y `eclosionHoras(-5)` → 1.0 (guard suelo en 1 hora); `eclosionHoras(15)` → 1.0; `eclosionHoras(16)` → 2.0; `eclosionHoras(30)` → 2.0. `puestaHoras(0)` → 255.0 (guard ya existente).
- `CalculadorEstadoHuevo::estadoDe` con `captureRate = 0` (caso `?? 0`) no lanza división/TypeError y devuelve estado GESTANDO/INCUBANDO/LISTO coherente.
- `CrearHuevo::crear` con especie cuyo `capture_rate` es null → `ViolacionReglaNegocio` 422 'Esta especie no tiene datos de eclosión'.

### Feature
- `GET /api/huevos` y `POST /api/huevos` con huevos legacy que tienen `capture_rate` null → el listado serializa con `tiempo_total_horas`/`progreso` válidos (sin 500) y `CrearHuevo` rechaza a esa especie con 422.
- `POST /api/huevos/{huevo}/eclosionar` sobre un huevo legacy con `capture_rate` null → estado calculado correctamente (sin 500); al estar LISTO eclosiona OK.

### Acceptance
- Puesta con `capture_rate = 255` → 1.0 h; eclosión con `hatch ≥ 1` → `ceil(hatch/15)` (happy path idéntico al contrato B1).

## Diseño

- Sin abstracciones nuevas: los guards son locales en las 4 clases (dominio y casos de uso), coherentes con `puestaHoras` (que ya aplicaba `max($captureRate, 1)`).
- Los `?? 0`/`?? 1` en App cubren huevos legacy/nullables; `max(..., 1)` en Domain cierra el rango de entrada del cálculo puro (el dominio nunca recibe ≤ 0 con efecto divisivo).
- Comportamiento preservado para datos válidos: `max($hatch, 1) === $hatch` para `hatch ≥ 1`; `$huevo->capture_rate ?? 0` devuelve el valor original cuando es no-null (un `capture_rate` legítimo nunca es 0, y de serlo `puestaHoras` ya tiene suelo 1).

## Riesgos

- Filas legacy con `capture_rate` null en `disponibles` (`ListarHuevos::disponibles`) usan el valor directo de `$pokemon->capture_rate` (Eloquent devuelve null → el `array_map` los emite como null en `capture_rate`); no es parte de estos 4 patches y el frontend podría mostrar `null`. Documentado; el filtro de disponibilidad actual solo exige `hatch`, no `capture_rate` (posible ampliación futura, fuera de alcance).
- Testing diferido: no se ejecutaron tests; los comportamientos quedan documentados arriba.