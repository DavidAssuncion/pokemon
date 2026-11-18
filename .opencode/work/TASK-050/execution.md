# TASK-050 — Ejecución

## Preflight (2026-11-17)

- Repo: `/home/david/pokemon`.
- Rama: `master` (up to date con `origin/master`).
- `git status`: hay cambios locales **no relacionados con esta tarea** de TASK-049:
  - `resources/views/habitats/partials/modal-formacion.blade.php` (modificado)
  - `resources/views/partials/leyenda-ivs-evs.blade.php` (modificado)
  - `.opencode/work/TASK-049/` (untracked)
- `git diff --stat`: 2 archivos, +74/−44.
- Decisión: no sobrescribir ni revertir esos cambios. Si el plan los toca, se parte
  del estado actual y se delimita el alcance.

## Estado

`RECEIVED → ANALYZING → AWAITING_APPROVAL (v1) → IMPLEMENTING → REVIEWING → CORRECTING → REVIEWING (APPROVED v1) → AWAITING_APPROVAL (v2) → IMPLEMENTING (v2) → REVIEWING (APPROVED v2) → COMPLETED`

- v1 aprobado con `Aprobar plan v1` e implementado.
- Revisión v1: `CHANGES_REQUESTED` → correcciones → **APPROVED**.
- v2 aprobado con `Aprobar plan v2` e implementado.
- Revisión v2: **APPROVED**.
- Auditoría de seguridad omitida por instrucción del desarrollador (`Salta security`).

## Preflight de implementación (2026-11-17)

- Rama: `master`.
- Cambios preexistentes que NO se deben tocar:
  - `M resources/views/habitats/partials/modal-formacion.blade.php`
  - `M resources/views/partials/leyenda-ivs-evs.blade.php`
  - `?? .opencode/work/TASK-049/`
  - `?? .opencode/work/TASK-050/`
  - `?? .opencode/work/TASK-051/` (otra tarea concurrente; no tocar)
- `git diff --check`: limpio.
- Delimitación: TASK-050 modifica los archivos autorizados de v1 y, en v2, también
  `resources/views/partials/leyenda-ivs-evs.blade.php` (solo padding).

## Implementación v1 (`frontend`)

- `gimnasios/show.blade.php`: contenedor `max-w-6xl`; etapas en
  `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` con tarjeta vertical y líder en la última
  columna; modal de formación `max-w-6xl` con cabecera/footer fijos, cuerpo scrollable y
  leyenda `['columnas' => 3, 'colapsable' => true]`.
- `equipos/index.blade.php`: estado de colapso por equipo, estado del modal de edición,
  helpers de payload, `objetos`/`nombreObjeto`, `posturaEtiqueta`; eliminación de
  `composicionBadge`, `selectedTeamId` y guardados inline; `guardarEdicionEquipo` con 3
  PATCH secuenciales y parada en el primer error.
- `equipos/partials/panel-equipos.blade.php`: header con toggle (`aria-expanded` /
  `aria-controls`), 3 miniaturas, acciones renombrar/editar/eliminar; cuerpo colapsable;
  retirada de "Composición" y controles inline.
- Nuevo `equipos/partials/modal-editar-equipo.blade.php` con `<x-ui.modal>`, 3 slots,
  formación/postura/objeto y guardado único.

### Correcciones de revisión v1

- `cerrarEdicionEquipo()` bloquea el cierre si `edicionGuardando`.
- Cancelar deshabilitado durante el guardado.
- Eliminado `edicionFeedback` muerto.
- `aria-label` en selects deshabilitados de slots vacíos.

## Implementación v2 (`frontend`)

- `leyenda-ivs-evs.blade.php`: añadido `p-2` a los dos contenedores de tabla
  (`role="region"` de perfiles y desglose por Pokémon). Sin otros cambios; estructura de
  TASK-049 preservada.
- `equipos/index.blade.php`: helper `miembrosDeFormacion(team, formacion)` (solo
  presentación).
- `equipos/partials/panel-equipos.blade.php`: cuerpo en `grid grid-cols-2 gap-2` con
  columnas Vanguardia/Retaguardia, contadores, sprites de 96, detalle/quitar con bloqueo
  por exploración, resumen postura/objeto y "Sin Pokémon" por columna vacía.

## Verificación

- `php artisan view:cache`: OK.
- `php artisan view:clear`: OK.
- `npm run build`: OK (70 módulos). CSS emitido `app-B89k3RW1.css`.
- Clases comprobadas en el CSS: `max-w-6xl`, `max-w-3xl`, `max-h-[90vh]`,
  `min-h-[2.5rem]`, `lg:grid-cols-4`, `sm:grid-cols-2`, `sm:grid-cols-3`,
  `xl:grid-cols-3`, `p-2`, `grid-cols-2`, `rotate-180`, `overscroll-contain`.
- `git diff --check`: limpio.
- Revisión v1: APPROVED tras correcciones.
- Revisión v2: APPROVED.
- Sin tests.

## Nota de coordinación

El árbol de trabajo contiene cambios de otras tareas concurrentes (TASK-049 en la
leyenda/modal de hábitats; TASK-051/053 en `app/`, `src/`, `docs/`, `resources/css/`).
No forman parte de TASK-050. El commit debe seleccionar solo los archivos de TASK-050 y
mantener TASK-049 en su propio commit.

**Atención TASK-053:** esa tarea añade sprites y nombre canónico del líder en las etapas
de gimnasio, por lo que puede tocar `resources/views/gimnasios/show.blade.php`. Debe
hacerlo sobre la versión de TASK-050 y conservar la rejilla de 4 columnas con el líder a
la derecha y el modal ancho.
