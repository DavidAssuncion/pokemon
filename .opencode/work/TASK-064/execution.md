# TASK-064 — Ejecución

- **ID:** TASK-064
- **Proyecto:** `/home/david/pokemon`
- **Estado:** IMPLEMENTING
- **Fecha de preflight:** 2026-11-19

## Preflight

### `git branch --show-current`

```text
master
```

### `git status --short`

```text
 M .githooks/pre-commit
 M app/Http/Controllers/PlayerController.php
 M app/Providers/DatagridServiceProvider.php
 M database/seeders/AbilitySeeder.php
 M resources/views/pokedex/index.blade.php
 M storage/data/pokemon_habitat.csv
?? .opencode/work/TASK-061/
?? .opencode/work/TASK-062/
?? .opencode/work/TASK-063/
?? app/Support/Pokedex/
?? docs/adr/0005-filtros-pokedex-y-descripciones-habilidades.md
?? resources/views/components/ui/combobox.blade.php
?? resources/views/partials/combobox-filtro-script.blade.php
?? storage/data/abilities_descripciones.json
```

### `git diff --stat`

```text
 .githooks/pre-commit                      |    0
 app/Http/Controllers/PlayerController.php |    3 +
 app/Providers/DatagridServiceProvider.php |   21 +
 database/seeders/AbilitySeeder.php        |  290 +++++-
 resources/views/pokedex/index.blade.php   |   42 +-
 storage/data/pokemon_habitat.csv          | 1574 ++++++++++++++---------------
 6 files changed, 1103 insertions(+), 827 deletions(-)
```

## Advertencia de cambios preexistentes

Hay trabajo sin commitear de tareas anteriores. En particular:

- `app/Http/Controllers/PlayerController.php` ya tiene un cambio (filtros de
  habilidad/movimiento) que debe preservarse.
- `resources/views/pokedex/index.blade.php` ya tiene cambios de filtros y
  combobox que deben preservarse.
- `app/Support/Pokedex/`, `resources/views/components/ui/combobox.blade.php`,
  `resources/views/partials/combobox-filtro-script.blade.php` y
  `storage/data/abilities_descripciones.json` son archivos nuevos sin seguimiento.

**Regla para esta tarea:** se trabaja encima de esos cambios; no se revierten ni
se sobrescriben. Cualquier modificación a esos archivos debe ser aditiva y
delimitada al alcance de TASK-064.

## Delegaciones

### 2026-11-19 — `analyst` (sesión `ses_e0d4845d1ffenhdT1E7xOuhe4s`)

- **Objetivo:** investigar modo QA actual, experiencia, inventario, reclutamiento y
  puntos de extensión.
- **Resultado:** guardado en `analysis.md`. Sin archivos modificados.
- **Hallazgos clave:** no existe `/debug`; el flag QA ya existe; el reclutamiento
  debe reutilizar `CrearReclutado`; `PlayerController.php` está ocupado por
  TASK-061; `ActualizarPokedexJob` es síncrono; no hay toasts; el modal de Pokédex
  tiene un punto de inserción claro tras la línea 603.
- **Verificación:** lectura estática, `route:list --except-vendor` (109 rutas),
  counts de solo lectura. Sin tests.

### 2026-11-19 — `analyst`, verificación acotada (misma sesión)

- **Objetivo:** cerrar ubicación exacta en el modal, solapamientos y firmas.
- **Resultado:** anexado a `analysis.md`.
- **Corrección relevante:** la experiencia usa ±1.000/±10.000/±100.000/±1.000.000;
  `100/255/3072` aplica solo a caramelos.

### 2026-11-19 — `architect` (sesión `ses_e0cf94b14ffeb3pQHP7WF7NSpp`)

- **Objetivo:** diseñar rutas, middleware, contratos, servicios y vistas.
- **Resultado:** guardado en `architecture.md`. Sin archivos modificados.
- **Decisiones:** un gate de QA con middleware + guarda; rutas en
  `routes/debug.php` fuera del grupo `auth`; lógica en `app/Support/Qa`;
  `/debug` con formularios POST + flash; Pokédex con fetch + feedback inline.
- **Verificación:** lectura estática y contratos de `vendor/`; sin tests.

## Aprobación

- **Plan:** v1.
- **Estado:** APROBADO (2026-11-19).
- **Texto literal:** `Aprobar plan v1`.
- Registrado en `approval.md`.

## Implementación

Delegación de backend y frontend en paralelo, con archivos disjuntos.

### 2026-11-19 — `backend` (sesión `ses_e0ce784efffeVLMduLfjP97xfl`)

- **Resultado:** implementación backend completada.
- **Archivos creados:** `app/Http/Middleware/SoloEnEntornoDePruebas.php`,
  `app/Support/Qa/{ModoQa,AjustesQa,ResultadoAjusteQa,PanelQa,ContenidoPanelQa,FilaAjusteQa,ReclutarDesdePokedex}.php`,
  `app/Http/Controllers/{DebugController,PokedexQaController}.php`,
  `routes/debug.php`.
- **Archivos modificados:** `bootstrap/app.php` (alias `solo-local`),
  `routes/web.php` (`require routes/debug.php`).
- **Verificación:** `php -l` OK; `route:list --path=debug -v` y
  `--path=pokedex/qa -v` con orden `web → SoloEnEntornoDePruebas → Authenticate`;
  Pint pass. `composer analyse` y `composer arch` reportan deuda preexistente,
  sin errores nuevos en archivos de TASK-064.
- **Incidencia:** `git diff PlayerController.php` no está vacío por cambios
  preexistentes de TASK-061; no se tocó.

### 2026-11-19 — `frontend` (sesión `ses_e0ce784eeffe1gw4V6B2fBK8tX`)

- **Resultado:** implementación frontend completada.
- **Archivos creados:** `resources/views/debug/index.blade.php`,
  `resources/views/debug/partials/_seccion-caramelos.blade.php`.
- **Archivo modificado:** `resources/views/pokedex/index.blade.php` (bloque QA,
  reset mínimo en `openDetail`, estado/métodos al final de `pokedexApp()`),
  marcado `TASK-064`.
- **Verificación:** `php artisan view:cache` OK; `php -l` de vistas compiladas OK;
  `/debug` no aparece en el menú; `git diff --stat` solo muestra la vista de
  Pokédex entre los archivos modificados por esta tarea.
- **Nota:** las clases nuevas de Tailwind requieren `npm run dev` o `npm run build`
  si el build está en producción.

## Estado

- **TASK-064:** implementación terminada, **pendiente de auditoría de seguridad y
  revisión independiente**. No se declara completada.
- **Pausa:** el desarrollador solicitó una revisión del filtro por nombre de la
  Pokédex; se atiende en TASK-066. TASK-064 se retomará después.
