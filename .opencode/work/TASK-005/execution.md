# TASK-005 — Ejecución

## Preflight (2026-11-02)

- **Rama:** `master`.
- **Estado:** WIP local extenso, 199 entradas en `git status --short`.
- **Diff:** 126 archivos, +5736 / -3290 (incluye TASK-003 y trabajo previo ajeno).
- **Riesgo:** no se debe revertir ni sobrescribir WIP. El alcance se limita a los archivos del diario y al contrato de eventos de Exploraciones.

## Estado

`RECEIVED`

## Delegaciones

### `analyst` — 2026-11-02

- **Paquete:** causa raíz de los 4 síntomas del diario, con evidencia archivo:línea y payloads reales.
- **Resultado:** guardado en `analysis.md`.
- **Hallazgos:** `nombresPokemon()` no escanea terminadas; `EventoExploracionVista` ignora `tipo_id`; `ExploradorVista::nombre()` inyecta «Sin reclutado»; el combate no persiste HP de entrada ni del rival.
- **Archivos modificados:** ninguno.

### `architect` — 2026-11-02

- **Paquete:** diseño del contrato de datos y presentación, resolución de dudas abiertas y propiedad de archivos.
- **Resultado:** guardado en `architecture.md`.
- **Decisiones:** sprite de especie como imagen principal del caramelo de familia; `oculto` + filtro en `DiarioExploracion`; `hp_inicial` del sub-combate decisivo; EV incluido en alcance; no tocar `equipo`; no enriquecer el evento raíz con PV.
- **Archivos modificados:** ninguno.

## Estado

`IMPLEMENTING` — plan v1 aprobado el 2026-11-02. Implementación delegada a `backend`; después `frontend`.

## Implementación v1 (2026-11-02)

### `backend`

- `EventoExploracionVista.php`: `oculto`, `candy_imagen`, `tipo_id` → `TipoEnum`/`SlugTipo`, EV con `StatEnum`, `rondas`/`rival_nombre`/`detalle_derrota`, `reason_texto`.
- `DiarioExploracion.php`: filtra eventos ocultos antes de total/grupos.
- `PresentadorExploraciones.php`: `nombresPokemon()` escanea bitácoras de terminadas; `activaToView()` filtra ocultos.
- `ExploradorVista.php`: fallback de nombre al Pokémon con `relationLoaded`.
- `CombateExploracion.php`: captura `hpInicial` y `rivalHpInicial/Final/Max`.
- `ResultadoBatallaExploracion.php`: 4 props opcionales y `toArray()` solo no-null.
- `ProcesarExploracionHandler.php`: persiste `hp_inicial`, `hp_max`, `rival_hp_*`.
- `EventoExploracion.php`: round-trip de las 5 claves.
- Tests unitarios y feature del contrato actualizados.

### `frontend`

- `resources/views/exploraciones/_evento.blade.php`: `candy_imagen` con fallback, `detalle_derrota` en encuentro/emboscada, `reason_texto` en derrota.
- Tests de vista/página ajustados.

### Validación v1

- `vendor/bin/phpunit --filter 'Exploraciones' --no-coverage` → **OK (397 tests, 2468 aserciones)**.
- `vendor/bin/phpstan analyse` → 0 errores fuera de `SendasController` (8 preexistentes ajenos).
- `vendor/bin/pint` → pass.
- Sin migraciones ni dependencias.

## v2 — Pérdidas legibles

- Petición del desarrollador: mostrar «⚠️ Perdiste objetos» con los caramelos de familia 147 y 34, y el detalle en el diario.
- `plan.md` actualizado a v2; pendiente de aprobación explícita.

## Estado

`IMPLEMENTING` — v1 implementado y validado; v2 aprobado el 2026-11-02. Implementación delegada a `backend`; después `frontend`.

## Implementación v2 (2026-11-02)

### `backend`

- `PresentadorExploraciones.php`: `objetosPerdidosVista()` resuelve `tipo_label`, `label` e `imagen` de familia/EV/tipo/captura; `nombresPokemon()` incorpora capturas perdidas.
- `ResultadoExploracionVista.php`: conserva los campos enriquecidos y elimina el fallback `label => id`.
- `ExploracionesPageTest.php`: expectativas de `vista['objetos_perdidos']` y caso `label=null` con familia/EV/tipo/captura.

### `frontend`

- `_perdidas.blade.php`: partial con aviso, imagen, tipo legible, nombre y cantidad.
- `index.blade.php`: partial en la tarjeta de resultados y dentro del diario; condición del diario ampliada a pérdidas.
- Tests de página/vista del partial y del aviso duplicado.

### Corrección de PV de entrada

- `EventoExploracionVista::detalleDerrota()` usa `hp_inicial` en la rama con rival: «Entró con 30/39 PS tras 4 rondas; a bronzor le quedaban 12/34 PS.».

### Validación

- `vendor/bin/phpunit --filter 'Exploraciones' --no-coverage` → **OK (400 tests, 2497 aserciones)**.
- `vendor/bin/phpstan analyse` → 0 errores fuera de `SendasController` (8 preexistentes ajenos).
- `vendor/bin/pint --test` sobre los archivos tocados → pass.
- Sin migraciones ni dependencias.

## Estado

`REVIEWING` — v1 y v2 implementadas y validadas; pendiente de revisión independiente.

## Validación final (2026-11-02)

- Suite `Exploraciones`: **403 tests / 2507 aserciones en verde**.
- PHPStan: 0 errores fuera de `SendasController` (8 preexistentes ajenos).
- Pint: pass en los archivos de TASK-005.
- Sin migraciones ni dependencias nuevas.
- WIP ajeno preservado; no se ejecutaron `reset`, `clean`, `checkout` ni `push`.

## Cierre

- Revisor independiente: **APPROVED** (`review.md`).
- QA: **PASS WITH KNOWN ISSUES** (`qa.md`).
- Seguridad: **NO APLICA** (`security.md`), cambio de presentación sin superficie de seguridad nueva.
- Documentación: `src/Exploraciones/context.md` y `README.md` actualizados con el contrato del diario, claves nuevas, pérdidas y calidad.

## Estado

`COMPLETED` — TASK-005 v1+v2 implementada, revisada, probada y documentada. Sin commit ni `git push`.

## v3 — Motivo de la pérdida

- Petición: mostrar por qué se perdieron los objetos en el bloque «⚠️ Perdiste objetos».
- `plan.md` actualizado a v3; pendiente de aprobación.

## Estado

`IMPLEMENTING` — v3 aprobado el 2026-11-02. Implementación delegada a `backend`; después `frontend`.

## Implementación v3 (2026-11-02)

- `PresentadorExploraciones::motivoPerdida()`: texto según `eventos['derrota']['reason']`; `explorador_debilitado` → «Tu explorador cayó debilitado en combate y perdió parte del botín.»; sin motivo → texto genérico; `reason` vacío normalizado con `trim`.
- `toTerminada()`: `motivo_perdida` solo se expone si hay `objetos_perdidos`; en exploraciones sin pérdidas es `null`.
- `ResultadoExploracionVista::toView()`: `motivo_perdida` en `vista`.
- `_perdidas.blade.php`: párrafo condicional bajo la cabecera.
- `index.blade.php`: `motivo` en las dos inclusiones (tarjeta y diario).
- Tests: `ExploracionesPageTest` (motivo con derrota, sin derrota, `reason` vacío, sin pérdidas) y `ExploracionesViewTest` (motivo sí/no).

### Validación v3

- `vendor/bin/phpunit --filter 'ExploracionesPageTest|ExploracionesViewTest'` → **OK (33 tests, 283 aserciones)**.
- `vendor/bin/phpunit --filter 'Exploraciones'` → **OK (412 tests, 2550 aserciones)**; el conteo incluye cambios concurrentes ajenos.
- PHPStan: 0 errores fuera de `SendasController` (8 preexistentes).
- Pint: pass.
- Sin migraciones ni dependencias.

## Estado

`COMPLETED` — TASK-005 v1+v2+v3 implementada, revisada, probada y documentada. Sin commit ni `git push`.

## v4 — Quitar el aviso del diario

- Petición: el aviso ya se muestra arriba; no duplicarlo en el diario.
- `plan.md` actualizado a v4; pendiente de aprobación.

## Estado

`IMPLEMENTING` — v4 aprobado el 2026-11-02. Implementación delegada a `frontend`.

## Implementación v4 (2026-11-02)

- `index.blade.php`: eliminado el include de `_perdidas` dentro del diario; condición del diario restaurada a `diario.total > 0`; se mantiene el include de la tarjeta con `motivo`.
- `_perdidas.blade.php`: `role="alert"` fijo; eliminado `$rol` (sin consumidores).
- `ExploracionesPageTest`: el aviso y el motivo se comprueban con `assertSame(1, substr_count(...))`; comentarios actualizados a «solo tarjeta».
- Sin cambios de backend, regla de pérdida ni contrato persistido.

### Validación v4

- `vendor/bin/phpunit --filter 'ExploracionesPageTest|ExploracionesViewTest'` → **OK (33 tests, 281 aserciones)**.
- `vendor/bin/phpunit --filter 'Exploraciones'` → **OK (416 tests, 2555 aserciones)**; el conteo incluye cambios concurrentes ajenos.
- PHPStan: 0 errores fuera de `SendasController` (8 preexistentes).
- Pint: pass.
- `grep _perdidas` → solo `index.blade.php:260` (tarjeta).

## Estado

`COMPLETED` — TASK-005 v1+v2+v3+v4 implementada, revisada, probada y documentada. Sin commit ni `git push`.

## v5 — Pérdidas como columna

- Petición: mostrar las pérdidas como columna roja del grid, junto a Derrotados y Recompensas obtenidas.
- `plan.md` actualizado a v5; pendiente de aprobación.

## Estado

`IMPLEMENTING` — v5 aprobado el 2026-11-02. Implementación delegada a `backend`; después `frontend`.
