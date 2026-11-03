# TASK-009 — Ejecución

## Preflight inicial (2026-11-02)

- **Proyecto:** `/home/david/pokemon`.
- **Rama:** `master`, upstream `origin/master`.
- **Worktrees:** solo `/home/david/pokemon`.
- **Staged:** vacío.
- **Unstaged (tracked):** 176 archivos, `10043 insertions`, `4086 deletions`.
- **Untracked:** 258 archivos.
- **`git status --short`:** 305 entradas totales.
- **Conclusión inicial:** WIP local extenso y preexistente. No se revierte, no se hace `checkout`, `reset` ni `clean`.

## Preflight actualizado (2026-11-02, tras respuesta del desarrollador)

- El desarrollador indicó que el árbol «ya está limpio».
- Verificación: `git status --short` → **0 entradas**.
- **HEAD:** `5917dc5 updates` en `master`.
- **Conclusión:** árbol limpio. Se puede implementar sin mezclar WIP ajeno. Aun así, no se hará `push` ni operaciones destructivas.
- **Baseline:** commit `5917dc5`.
- **Rama propuesta:** `refactor/ui-tokens` desde `master` (sujeta a aprobación del plan). Sin `push`.

## Aprobación

- Plan v1 aprobado por el desarrollador el 2026-11-03: «Aprobar plan v1» + «Adelante plan v1».
- Registro: `approval.md`.
- Rama creada: `refactor/ui-tokens` desde `master` (`5917dc5`).
- Nota: permanecen 3 borrados preexistentes de `Zone.Identifier` ajenos a esta tarea; no se incluirán en los commits y no se tocarán.

## F1 — Tokens y base CSS (completada)

- Creados: `resources/css/tokens.css`, `resources/css/components.css`, `resources/views/partials/tipos-tokens.blade.php`.
- Modificados: `resources/css/app.css`, `layouts/app.blade.php`, `layouts/auth.blade.php`, `partials/tipos-badge-script.blade.php`.
- `npm run build`: OK (67 módulos; CSS app 87.31 kB; combate 241.00 kB).
- Commit: `100f836 refactor(ui): fase F1 — tokens y base CSS`.
- Verificación: 18 tokens de tipo, 5 de calidad, sin hex en `components.css`; sin el hack `.dark body !important`.
- Pendiente de F3: contraste AA del texto de `.tipo-badge` sobre fondos claros (usar mezcla con `--color-ink` o tokens `-ink`).

## F2 — Aislamiento de Bootstrap (completada)

- `resources/js/app.js`: eliminado `bootstrap.bundle`; `combate.js` es el único importador.
- `resources/css/combate.css`: Bootstrap en `layer(bootstrap)`; eliminados los 3 parches de cascada.
- `npm run build`: OK; chunk de Bootstrap solo en combate.
- Commit: `71c33a3 refactor(ui): fase F2 — aislar Bootstrap en combate`.

## F3 — Componentes base y sprite (completada)

- Creados 8 componentes `ui.*` + `sprite`; `tipo-badge` migrado a tokens; `.tipo-badge` con contraste AA.
- Pilotos: `welcome`, `auth/login`, `reclutado/show` (sprite 128).
- Corrección: `id` de `ui.input`/`ui.select` ya no se duplica.
- `npm run build` y `php artisan view:cache`: OK.
- Commit: `6bec4cb refactor(ui): fase F3 — componentes base y sprite`.
- Riesgos: `<dialog>` y sprite dinámico sin validar en navegador; se probarán en F4/F5.

## F4a — Layout/auth/onboarding (completada)

- Migrados: `layouts/app`, `layouts/auth`, `auth/login`, `auth/register`, `welcome`, `onboarding/equipo-inicial`.
- 0 `dark:`, 0 `text-[10px]`, 0 hex en los 6 archivos.
- `php artisan view:cache` y `npm run build`: OK.
- Commit: `f58663f refactor(ui): fase F4a — layout auth onboarding`.

## F4b — Módulos medios (completada)

- Migrados: `sendas/index`, `sendas/show`, `misiones/index`, `gimnasios/index`, `reclutamiento/index`, `reclutado/show`, `habitats/index`.
- 0 `dark:`, 0 `text-[9/10/11px]`, 0 hex, 0 colores de paleta y 0 `bg-black` en los 7 archivos.
- Añadido token `--color-scrim` (0.6 claro / 0.7 oscuro) y clase `.scrim`; modales F4b tokenizados.
- `php artisan view:cache` y `npm run build`: OK.
- Commit: `e8a89c6 refactor(ui): fase F4b — módulos medios`.
- Nota: `hover:bg-line/70` en `sendas/show` como hover de pozo (no hay token específico); documentado.

## F4c — Pokédex, exploraciones y gimnasios (completada)

- Migrados: `pokedex/index`, 6 vistas de `exploraciones/`, `gimnasios/admin`, `gimnasios/show`.
- 0 `dark:`, 0 `text-[9/10/11px]`, 0 hex, 0 paleta Tailwind en los 9 archivos; 3 sprites en Pokédex.
- `php artisan view:cache` y `npm run build`: OK.
- Commit: `a1952b0 refactor(ui): fase F4c — Pokédex, exploraciones y gimnasios`.

## F4d — habitats/show (completada)

- Vista de 2.282 líneas migrada; 9 `<x-sprite>` (128/96/64); 6 modales con `role="dialog"`.
- Eliminados `$cardPanelClass`/`$constructionButtonClass`/`$familyCardClass` y los 3 `w-96`.
- Commit: `c0703e9 refactor(ui): fase F4d — habitats/show`.

## F4e — equipos/index + movimiento-card (completada)

- Migrada la vista de 3.256 líneas y el partial; 7 `<x-sprite>`; `calidadIvs` y Chart.js leen tokens con `getComputedStyle`.
- Incidencia corregida: `:class` con expresión Alpine dentro de `<x-sprite>` se compilaba como PHP y producía `Call to undefined function pokemonTeamId()`. Sustituido por `x-bind:class`; auditoría de las 7 etiquetas `<x-*>` del archivo: 0 atributos de un solo `:`.
- Ternarios redundantes del inventario limpiados (`text-ink-muted`, `text-info-ink`).
- `php artisan view:cache` y `npm run build`: OK.
- Commit: `556cedf refactor(ui): fase F4e — equipos y partial de movimientos`.
- Deuda menor: los modales de `equipos/index` usan `div` + Alpine con tokens, no `ui.modal` (el criterio del plan admitía fallback; `<dialog>` sigue sin validar en navegador).

## F5 — Combate (completada)

- `resources/css/combate.css`: 0 hex, 0 `rgba()`, 13 keyframes y 12 `!important` (sin cambios respecto a HEAD); Bootstrap en `layer(bootstrap)`.
- Vistas `livewire/*`: 0 `dark:`, 0 `text-[9/10/11px]`, 0 hex, 0 paleta; 4 `<x-sprite>` (combate 128, turn-bar inline, preparación inline, captura 64).
- `moves-panel` usa `var(--type-{slug})` en lugar de `$move['tipoColor']`.
- Fallback shiny→normal restaurado en `_pokemon-card` (`x-on:error`) y capturas (`:id` + `:shiny` en `x-sprite`).
- `php artisan view:cache` y `npm run build`: OK.
- Commit: `51bfe93 refactor(ui): fase F5 — combate tokenizado`.

## F6 — Limpieza y prueba de theme (completada)

- Eliminados 7 componentes legacy sin uso (`alert`, `badge`, `card`, `header`, `modal`, `sidebar`, `stat-card`) y 4 `ui.*` sin uso (`select`, `badge`, `modal`, `empty-state`).
- `TipoBadges`: retiradas `CLASES`, la clave `clases` y el accesor `clases()`; `paraJs()` ya no emite `clases`. `@source "../../src"` se conserva por las clases PHP restantes.
- Prueba de theme: `--color-primary` `#2563eb` → `#dc2626` → build → reversión byte a byte (sha256 idéntico); `git diff resources/views` vacío durante la prueba.
- `grep` global en `resources/views`: 0 `dark:`, 0 hex, 0 `text-[9/10/11px]`, 0 clases de paleta.
- Commit: `e7837a9 refactor(ui): fase F6 — limpieza y prueba de theme`.

## Revisión y cierre

- Ronda 1 (`reviewer`): `CHANGES_REQUESTED` — CRITICAL: `@if` dentro de `<x-sprite>` en `_pokemon-card` no compilaba; 2 MEDIUM de ARIA; 1 LOW de tamaño; 1 NIT de `alt`.
- Correcciones: commit `25237dc` (prop `fallback` en `sprite`, ARIA en modales, chip inline, `decorative`).
- Ronda 2: `APPROVED`, sin hallazgos nuevos; riesgos residuales aceptados.
- Seguridad: `NO APLICA`.

## Corrección post-cierre — sprites shiny (2026-11-03)

- Reporte del desarrollador: los Pokémon no se veían shiny en `/equipos` ni en `/habitats/{id}`.
- Causa: `iconoDe()` ignoraba `es_shiny` y varias vistas usaban URLs normales fijas para reclutados.
- Corregido en: `equipos/index`, `habitats/show`, `gimnasios/show`, `reclutado/show`, `exploraciones/_explorador` y `ReclutadoVistaPresenter` (campo de presentación `es_shiny`).
- Fallback unificado: shiny → normal → `0.webp` → ocultar.
- Verificación: `view:cache` OK, 0 tags crudos, `npm run build` OK, 0 URLs fijas de reclutados en las vistas corregidas.
- Revisión focalizada: `APPROVED`; hallazgo LOW de `data-intento` persistente, no bloqueante.
- Commit: `199ab67 fix(ui): respetar es_shiny en sprites de reclutados`.
- Pendiente conocido: capturados shiny en el resumen/diario de exploración siguen usando URL normal (fuera de este commit).

## Corrección post-cierre 2 — colores y espaciado de combate (2026-11-03)

- El desarrollador pidió revertir los colores de bordes/fondos que F5 introdujo en combate y corregir el espaciado de las cards de ataque.
- `resources/css/combate.css` restaurado exactamente a `556cedf` (pre-F5): vuelven los hex/rgba originales, se conserva `layer(bootstrap)` y no se reintroducen los parches de F2.
- `resources/css/app.css`: orden de capas `properties, theme, base, bootstrap, components, utilities`; Bootstrap queda por encima de `base`, por lo que `.card`/`.card-body` recuperan fondo/borde/padding frente al reset `button` de Tailwind.
- Verificación: `git diff 556cedf -- combate.css` vacío; orden compilado correcto; `npm run build` OK.
- Revisión focalizada: `APPROVED`; LOW de doble fuente de color en combate y Reboot de Bootstrap, no bloqueantes.
- Commit: `71e2491 fix(ui): restaurar colores de combate y prioridad de Bootstrap`.
- Se preserva el commit del desarrollador `f30a02b fixes` (tamaños 128, nota de combate, badges, Zone.Identifier).
- Decisión: el criterio F5 de «0 hex en `combate.css`» deja de aplicarse por petición explícita del desarrollador; los colores de combate vuelven a ser fijos.

## Estado

`COMPLETED` — rama `refactor/ui-tokens`, 14 commits, 55 archivos. Pendiente validación visual manual y decisión de fusión (sin push).
