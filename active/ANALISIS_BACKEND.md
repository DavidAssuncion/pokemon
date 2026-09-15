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