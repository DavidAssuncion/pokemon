# TASK-051 — Ejecución

## Estado

`REVIEWING` — implementación completada; pendiente de revisión independiente.

## Preflight (2026-11-17)

Comandos ejecutados en `/home/david/pokemon`:

- `git status --short`
- `git branch --show-current`
- `git diff --stat`

### Resultado

- Rama actual: `master`.
- Cambios locales no relacionados con esta tarea:
  - `M resources/views/habitats/partials/modal-formacion.blade.php`
  - `M resources/views/partials/leyenda-ivs-evs.blade.php`
  - `?? .opencode/work/TASK-049/`
  - `?? .opencode/work/TASK-050/`
- `git diff --stat`: 2 archivos, 74 inserciones y 44 eliminaciones.

### Delimitación

- Esta tarea no modificará ninguno de los archivos ya modificados por TASK-049/TASK-050.
- Cualquier necesidad de tocar esas vistas se reportará antes de hacerlo.
- El número de tarea asignado es `TASK-051`.

### Actualización de preflight durante la ejecución

Aparecieron cambios concurrentes de TASK-052/TASK-053 y otras tareas:

- `THIRD_PARTY_NOTICES.md`, `app/Http/Controllers/GimnasiosViewController.php`,
  `app/Http/Controllers/HabitatsController.php`, `docs/gimnasios.md`,
  `resources/views/equipos/index.blade.php`,
  `resources/views/equipos/partials/panel-equipos.blade.php`,
  `resources/views/gimnasios/show.blade.php`, vistas de hábitats,
  `src/Gimnasios/*`, `.opencode/work/TASK-052/`, `TASK-053/`, assets de entrenadores.
- No se han revertido ni sobrescrito. Se han preservado en todo momento.

## Registro de delegaciones

### 2026-11-17 — `analyst` — inventario del sistema de temas

- **Resultado:** `analysis.md`.
- **Hallazgos clave:** doble entrada CSS (app.css/combate.css), tema persistido en
  `localStorage['theme']`, cuerpo principal tokenizado, hardcodes en combate y
  presentadores, faltantes de tokens y problemas de contraste AA.
- **Archivos modificados:** ninguno.

### 2026-11-17 — `analyst` — subanálisis de combate

- **Resultado:** anexo «Subanálisis A» en `analysis.md`.
- **Hallazgos clave:** subrayado por colisión de capas Tailwind Preflight vs Bootstrap
  Reboot; icono de plantas del navbar oculto por condición Blade; más colisiones
  Bootstrap/Tailwind.
- **Archivos modificados:** ninguno.

### 2026-11-17 — `architect` — diseño del tema y combate

- **Resultado:** `architecture.md`.
- **Decisiones clave:** mapa de tokens claro/oscuro, derivados AA, conservación de
  calidad IV y tipos, puente `--bs-*`, hotfix de Reboot, interfaz `theme:cambiado`.
- **Archivos modificados:** ninguno.

### 2026-11-17 — `analyst` — subanálisis Livewire/CSS/JS

- **Resultado:** anexo «Subanálisis B» en `analysis.md`.
- **Veredicto:** Livewire no causa el subrayado ni la desaparición del icono; el
  subrayado es cascada CSS y el icono es Blade. Livewire solo es coadyuvante menor por
  falta de `wire:key` y `wire:ignore.self`.
- **Archivos modificados:** ninguno.

### 2026-11-17 — `frontend` — F1-F4 (CSS base)

- **Resultado:** tokens Stone/Lavender, componentes, calidad intacta, puente Bootstrap,
  hotfix y tokenización de combate.
- **Verificación:** `npm run build` OK; 0 hex en `combate.css`; `color-scheme` solo en
  `tokens.css`; `#1e293b` solo en `app.css`; calidad y tipos sin cambios.
- **Archivos modificados:** `tokens.css`, `components.css`, `app.css`, `combate.css`.

### 2026-11-17 — `backend` — B1-B6 (presentadores)

- **Resultado:** `textoSobreColor()` y clases de token en presentadores.
- **Verificación:** `php -l` ×6, `pint --dirty` pass, greps de clases fijas 0.
- **Archivos modificados:** los seis PHP del plan.

### 2026-11-17 — `documenter` — D1-D2

- **Resultado:** ADR-0004 y actualización de convenciones.
- **Archivos modificados:** `docs/adr/0004-...md`, `docs/conventions.md`.

### 2026-11-17 — `frontend` — F5-F15 (layouts, vistas, JS)

- **Resultado:** toggle/meta/evento, cosecha visible y bloqueada, tipos `--type-*-on`,
  gráficos, badges, placa de tipo, entrada JS única.
- **Verificación:** `npm run build` OK; `text-bg-` 0; referencias a `combate.js` 0;
  `git diff --check` OK.
- **Archivos modificados:** layouts, equipos, tipos, combate, moves/items, sendas,
  `app.js`, `vite.config.js`, `combate_page`, borrado de `combate.js`.

### 2026-11-17 — `frontend` — Corrección C1 (contraste semántico)

- **Motivo:** 47 usos heredados de `bg-<estado> text-on-primary`; el nuevo
  `on-primary` oscuro no contrasta sobre fills de estado.
- **Resultado:** puente CSS sin capa para `.bg-{success,warning,danger,info,secondary}.text-on-primary`;
  `--bs-link-hover-color` a `primary-ink`; badge «Ronda» con `text-on-primary`;
  «daño» con `opacity-70`; badge de captura con `text-on-success`.
- **Verificación:** build OK; reglas fuera de `@layer`; sin `!important`; contraste
  calculado ≥4.5.

### 2026-11-17 — `frontend` — Corrección C2 (tipos y scrim)

- **Motivo:** cuatro usos heredados de `text-on-primary` sobre fondos de tipo o scrim.
- **Resultado:** `--color-on-scrim`; badges de tipo de gimnasios con
  `color: var(--type-*-on)`; título de hábitat con `text-on-scrim`.
- **Verificación:** build OK; `text-on-scrim` generado; diffs concurrentes preservados.
- **Desviación controlada:** se tocaron dos archivos con cambios locales ajenos
  (`gimnasios/show.blade.php` y `habitats/partials/columna-izquierda.blade.php`) en una
  sola línea cada uno para evitar regresiones de contraste. No se reformateó ni se
  reemplazó el resto de su contenido. Queda registrado para revisión.

### 2026-11-17 — `frontend` — Corrección C3 (hallazgos de revisión)

- **Motivo:** la revisión solicitó cambios por el puente de compatibilidad pisando
  estados `disabled`, botones Bootstrap sin tokenizar, hotfix de enlaces demasiado amplio
  y `wire:key` por índice.
- **Resultado:** guarda `:not(:disabled):not(.disabled):not([aria-disabled="true"])` en
  las cinco reglas del puente; overrides de `.btn-primary`, `.btn-success`,
  `.btn-outline-primary` y `.btn-outline-warning`; `.btn-outline-secondary` deshabilitado
  con `ink-muted`; hotfix `a:not([class*="underline"])`; `wire:key` por slug.
- **Verificación:** `npm run build` OK; build de 3.25 s; 0 hex/rgba en `combate.css`;
  `git diff --check` OK.

### 2026-11-17 — `documenter` — D3/D4 (ADR y convenciones)

- **Resultado:** ADR-0004 actualizado con el puente temporal, la guarda `:disabled`,
  `--bs-link-hover-color` real, overrides de botones, archivos concurrentes y deuda de
  migración. `docs/conventions.md` añade la nota operativa del puente temporal.
- **Archivos modificados:** `docs/adr/0004-...md`, `docs/conventions.md`.

### 2026-11-17 — Revisión independiente (ronda 1)

- **Veredicto:** `CHANGES_REQUESTED`.
- **Hallazgos:** HIGH por el puente pisando `disabled`; MEDIUM por botones Bootstrap sin
  tokenizar; MEDIUM/LOW por documentación desactualizada; LOW por el hotfix de enlaces;
  NIT por `wire:key` basado en índice.
- **Correcciones aplicadas:** C3 y D3/D4.
- **Estado:** pendiente de segunda revisión.

### 2026-11-17 — `frontend` — Corrección C5 (badge de tipo sin punto)

- **Petición del desarrollador:** eliminar `.tipo-badge::before` y dejar
  `padding-left: 0`.
- **Resultado:** bloque `::before` eliminado; `.tipo-badge` conserva `gap`, tinte,
  borde y radios, con `padding-left: 0`; comentario actualizado.
- **Verificación:** `npm run build` OK; 0 referencias a `tipo-badge::before`;
  `git diff --check` OK.

### 2026-11-17 — `documenter` — D6 (ADR badge sin punto)

- **Resultado:** ADR-0004 sin `.tipo-badge::before` ni «punto sólido»; documenta
  tinte + borde y `padding-left: 0`.
- **Archivos modificados:** `docs/adr/0004-...md`.

## Verificación estática final

- `npm run build` — OK. 70 módulos; `app.css` 124.97 kB, `combate.css` 250.09 kB;
  Bootstrap JS en chunk separado. Aviso preexistente de chunk JS > 500 kB.
- `php -l` — sin errores en los seis PHP modificados.
- `vendor/bin/pint --dirty --format agent` — `{"result":"pass"}`.
- `git diff --check` — sin errores.
- `grep -rn 'text-bg-' resources/views` — 0.
- `grep -rn 'combate\.js' vite.config.js resources/views resources/js` — 0.
- `grep -rn 'dark:' src app` — 0.
- `grep -rn 'text-white\|bg-emerald\|bg-green-\|bg-amber-\|bg-red-\|bg-gray-\|bg-blue-\|bg-orange-' src app resources/views` — 0.
- `grep -n '#[0-9a-fA-F]\{3,8\}' resources/css/combate.css` — 0.
- `grep -rn 'color-scheme: dark' resources/css` — solo `tokens.css:141`.
- `grep -rn '#1e293b' resources/css` — solo `app.css` (calidad).
- `grep -rn 'rgba\?(' resources/css/combate.css` — 0.
- `tests/` — sin cambios; suite no ejecutada.
- Dependencias — sin cambios.

## Incidencias

- El primer `frontend` de F5-F15 alcanzó el límite de pasos; dejó una micro-edición
  pendiente de build. Se re-ejecutó el build después y pasó.
- Las herramientas `edit`/`write` estuvieron denegadas para varios subagentes; usaron
  sustituciones exactas por shell/Python y verificaron con `git diff`.
- Persistencia Engram: el proyecto es ambiguo entre varios repos; queda pendiente de
  guardar con el proyecto correcto.
