# TASK-053 — Ejecución

## Preflight (2026-11-17)

- Repo: `/home/david/pokemon`.
- Rama: `master`.
- Cambios locales preexistentes que **no se tocan**:
  - `resources/views/equipos/index.blade.php`
  - `resources/views/equipos/partials/panel-equipos.blade.php`
  - `resources/views/gimnasios/show.blade.php` (TASK-050 v1; la v2 no toca esta vista)
  - `resources/views/habitats/partials/modal-formacion.blade.php` (TASK-049)
  - `resources/views/partials/leyenda-ivs-evs.blade.php` (TASK-049/050)
  - `resources/css/*`, `app/Livewire/Presenters/MovesPreviewPresenter.php`,
    `src/Battle/*`, `src/Exploraciones/*`, `src/Shared/Tipos/TipoPokemon.php`,
    `docs/conventions.md` (TASK-051)
  - `.opencode/work/TASK-049/`, `TASK-050/`, `TASK-051/`
- TASK-050 v2 consta como implementada y revisada (APPROVED) en su `execution.md`;
  no toca `gimnasios/show.blade.php`, por lo que la dependencia de la vista queda
  satisfecha.

## Estado

`RECEIVED → ANALYZING → DESIGNING → AWAITING_APPROVAL → IMPLEMENTING → REVIEWING → CORRECTING (v2) → COMPLETED`

## Aprobación

- Plan v1 aprobado el 2026-11-17 con el texto literal `Aprobar plan v1` (`approval.md`).

## Implementación

### Dominio (`backend`, commit `ffa6cc7`)

- `src/Gimnasios/Domain/CatalogoLideres.php` (nuevo): 18 nombres + `POR_DEFECTO`.
- `src/Gimnasios/Domain/Gimnasio.php`: `nombreLider` obligatorio; `nombreEtapa(4)`.
- `src/Gimnasios/Domain/CatalogoGimnasios.php`: 18 × `nombreLider`.
- `src/Gimnasios/Infra/EloquentGymCatalogoRepository.php`: `nombreLider` desde
  `$model->slug`.
- `EtapaGimnasio`, `DetalleGimnasio`, `GimnasioResumen` y colecciones intactos.

### Presentación (`frontend`)

- `resources/data/entrenadores_sprites.json`: 18 gimnasios, 56 hábitats, 74 clases,
  metadatos de fuente.
- `public/images/trainers/`: 74 PNG de 80×80 (~57 KB).
- `app/Support/UI/SpritesEntrenador.php`: manifiesto cacheado y `url()` con validación
  de slug y fallback.
- `resources/views/components/trainer-sprite.blade.php`: componente 80 px.
- `app/Http/Controllers/GimnasiosViewController.php` y `HabitatsController.php`:
  pasan `sprites` a las vistas.
- `resources/views/habitats/show.blade.php`: `sprites` + helpers
  `claseBaseHabitat`, `spriteEntrenador`, `spriteUrl`, `spriteAlt`.
- `resources/views/habitats/partials/columna-derecha.blade.php`: sprite en las 3
  tarjetas de entrenador y en el panel de ruta.
- `resources/views/habitats/partials/columna-izquierda.blade.php`: sprite en el botón
  «Entrenadores».
- `resources/views/gimnasios/show.blade.php`: sprite en las 4 etapas, nombre canónico
  en etapa 4, `title` «Líder/Rival», `sprites` inline y helpers. Se conserva la
  rejilla de 4 columnas, el líder a la derecha, el modal `max-w-6xl` y la leyenda de
  TASK-050.

### Documentación (`documenter`)

- `THIRD_PARTY_NOTICES.md`: sección «Sprites de entrenador» con repo, commit,
  Showdown, ausencia de LICENSE y uso privado.
- `docs/gimnasios.md`, `src/Gimnasios/context.md`, `src/Gimnasios/README.md`: etapa 4
  = nombre canónico; tabla de líderes y resolución del sprite.

## Verificación

- `php -l` de 7 PHP: OK.
- `vendor/bin/pint --dirty --format agent`: pass.
- `php artisan view:cache`: OK; `view:clear`: OK.
- `npm run build`: OK (`app-tpYuJOLa.css`).
- `git diff --check`: limpio.
- Manifiesto: 18 gimnasios, 56 hábitats, 74 clases; 74 PNG referenciados; 0 ausencias;
  0 huérfanos.
- Assets: 74 PNG de 80×80, 58 136 B; MD5 de muestra idéntico al clon.
- Runtime: `nombreEtapa(4)` devuelve Bugsy/Koga/Whitney/.../Clair; el shape
  `{etapa, nombre}` no cambia.
- Revisión independiente: **APPROVED**, sin BLOCKER/HIGH/MEDIUM.

## Hallazgos menores aceptados

- `aria-hidden` dinámico en el sprite de etapa (funciona; se puede simplificar).
- Fallback `onerror` duplicado en 4 vistas (deuda de mantenimiento).
- `x-trainer-sprite` sin consumidor (componente preparado para vistas servidor).
- `aria-hidden="false"` redundante en etapa 4.

## Notas

- El commit `ffa6cc7` es local; **no se ha hecho push**.
- Los cambios de TASK-049/050/051 siguen sin commitear y no forman parte de esta tarea.
- No se ejecutaron tests.
- Verificación visual en navegador pendiente: 80 px en 1×/2×, teclado, móvil y
  los 18 gimnasios/56 hábitats.


## Ajuste v2 — 80×80 (2026-11-17)

- Instrucción literal: `Ponlos a 80x80, lo permiten tanto en gym como en rutas`.
- `gimnasios/show.blade.php`: 4 etapas a 80×80; contenedor `h-20 w-20` con
  `rounded-card` (sin recorte circular).
- `columna-derecha.blade.php`: encabezado de ruta y 3 tarjetas a 80×80;
  «Entrenador N» con `truncate`.
- `columna-izquierda.blade.php`: botón lateral intacto a 40×40.
- `entrenadores_sprites.json`: `tamano_render_px = 80`.
- `trainer-sprite.blade.php`: default y fallback 80.
- Verificación: `view:cache` OK, `npm run build` OK (`app-tpYuJOLa.css`), `git diff --check`
  limpio; `h-20`/`w-20` presentes en el CSS emitido.
- Revisión v2: **APPROVED**; riesgo visual pendiente a 1024 px (gimnasio) y
  640-767 px (tarjetas).
