# TASK-015 — Ejecución

## Estado

`COMPLETED` — commit `0ec0f7e` en `task-011`. Sin `reviewer` ni `security` por indicación del desarrollador.

## Preflight

- Fecha: 2026-11-04
- Proyecto: `/home/david/pokemon`
- Rama: `task-011`
- `git status`: rama con cambios locales preexistentes no relacionados.
  - 51 archivos modificados (combate, exploraciones, granjas, hábitats, layout, rutas, etc.).
  - Numerosos archivos y directorios untracked de tareas anteriores.
- `git diff`: cambios acumulados de tareas previas; no se han tocado ni revertido.
- Decisión: trabajar solo sobre los archivos que determine el plan de TASK-015 y commitear exclusivamente esos archivos en `task-011`.
- Riesgo: convivencia con cambios preexistentes en los mismos archivos (p. ej. `resources/views/habitats/show.blade.php`, `resources/views/layouts/app.blade.php`). Se revisará el diff por archivo antes de commitear.

## Registro

### Implementación

- `backend` (`ses_e580c251cffe0AmN8CziBeAHEZ`):
  - `PresentadorDetallePokemon`: `locations[]`, orden por `habitat_id`, `location` derivado de `locations[0]`.
  - `PresentadorDetalleEspecie`: `paraPokedex()`, `componer()`, `moves(..., bool)` con filtro backend `level-up`, `height_m`/`weight_kg`.
  - `DatagridServiceProvider`: detalle `pokemon` conectado a `paraPokedex()`.
- `frontend` (`ses_e580c251bffeEHoJQTL4g6ioSh`):
  - `pokedex/index.blade.php`: grid 3/5/6/8/10/12, sprite 128 centrado por flex, `min-h-[3.5rem]`, modal `max-w-3xl lg:max-w-4xl` con cabecera fija y cuerpo con scroll.
  - Modal: familia, habilidades, movimientos por nivel, stats base, datos de especie y ubicaciones con “Ir al hábitat”. Sin quality/EV/IV ni acciones de escritura.
  - `habitats/index.blade.php`: `object-contain p-2` y hover 1.03.

### Verificación ejecutada

- `php -l` sobre los tres PHP: sin errores.
- `vendor/bin/phpstan analyse` sobre los tres PHP: `[OK] No errors`.
- `vendor/bin/pint --test` sobre los tres PHP: `pass`.
- `php artisan view:cache` → OK; `php artisan view:clear` → OK.
- `php artisan route:list --path=datagrid` → `GET datagrid/{model}/{id}/detalle` intacta.
- `grep` del bloque del modal: 0 coincidencias de `quality`, `calidad`, `pokemon-modal-quality`, `--quality-`, `chart`, `loadout`, `effort`, `IV`, `EV`, `CP`, `shiny`, `Liberar`, `<form>`, `method="POST"`.
- `grep object-cover` en `habitats/index.blade.php`: 0.
- Comprobación runtime de solo lectura:
  - id 151: 305 movimientos totales, 7 en Pokédex, todos `level-up`; 2 habilidades; 1 ubicación; `height_m=0.4`, `weight_kg=4`.
  - id 645: 5 habilidades, 14 movimientos, `location === locations[0]`.
  - id 263 (sin hábitat): `locations=[]`, `location=null`, `habitat_name=null`.
  - `para()` conserva los 305 movimientos completos.
- `git diff --check`: sin errores de whitespace.
- `npm run build` → OK (Vite 7.3.6, 67 módulos; CSS `app-BsHN8Ytm.css`).
- Sin cambios en `tests/`, `resources/views/equipos/` ni `resources/views/habitats/show.blade.php`.

### Commit

- Rama: `task-011`
- Commit: `0ec0f7e feat(task-015): detalle read-only de Pokédex y rutas de hábitat`
- Archivos: los cinco del plan. Sin push.
- `PresentadorDetalleEspecie.php` se añadió como archivo nuevo (estaba untracked).

### Incidencias y notas

- `app/Support/PresentadorDetallePokemon.php` ya tenía un diff previo (overrides `visto`/`atrapado`); se conserva y entra en el commit para no dejar el archivo incoherente.
- `app/Support/PresentadorDetalleEspecie.php` era untracked; se añadirá al commit.
- La verificación visual en navegador real queda pendiente; se recomienda validar 360/768/1024/1920 y los ids 151, 645, 263 y uno sin movimientos de nivel.
- `docs/architecture.md` y `docs/adr/0002...` siguen afirmando que la Pokédex no cambia; deuda documental registrada, no incluida en el commit para no mezclar cambios preexistentes.
