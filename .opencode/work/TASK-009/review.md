# TASK-009 — Revisión independiente

## Ronda 1 — 2026-11-03

**VEREDICTO:** `CHANGES_REQUESTED`

### Hallazgos

- **[CRITICAL] `resources/views/livewire/_pokemon-card.blade.php:110-121`** — `@if` dentro de la etiqueta `<x-sprite>` no compila; el tag se renderiza literal y el combate pierde el sprite. Evidencia: `php artisan view:cache` + `grep '<x-sprite' storage/framework/views/*.php`; reproducido con `Blade::render`. Corrección: sacar la condicional de la etiqueta o añadir una prop de fallback al componente.
- **[MEDIUM] `resources/views/reclutamiento/index.blade.php:80,115`** — dos modales sin `role="dialog"`, `aria-modal` ni `aria-labelledby`.
- **[MEDIUM] `resources/views/livewire/combate.blade.php:130`** — modal de victoria sin `aria-labelledby`.
- **[LOW] `resources/views/livewire/partials/preparacion-panel.blade.php:27`** — chip de postura con `<img>` de 18px en lugar de `x-sprite size="inline"`.
- **[LOW] PHP con paleta/`dark:` (13 ocurrencias en `src/`)** — deuda D12 asumida (`estado_clase`, `hp_clase`, `clase`); fuera de alcance.
- **[NIT] `resources/css/app.css`** — `--quality-*` duplicados entre tokens y `.pokemon-quality*`; el theme no controla las tarjetas de calidad.
- **[NIT] `resources/views/exploraciones/_evento.blade.php`** — `alt` redundante cuando el nombre ya está en el texto.

### Criterios

| # | Criterio | Estado |
|---|---|---|
| 1 | Sprites 128/96/64, sin `w-96`, intrínsecos | PASS |
| 2 | Tokens: 0 hex/`dark:`/`text-[9-11px]`/paleta en vistas | PASS |
| 3 | Componentes con props, sin Bootstrap, muertos retirados | PASS |
| 4 | Bootstrap aislado, sin parches | PASS |
| 5 | Theme centralizado + prueba y reversión | PASS |
| 6 | Accesibilidad | FAIL (modales sin ARIA) |
| 7 | Sin regresiones funcionales | FAIL (sprite de combate) |
| 8 | Sin tests | PASS |
| 9 | Sin secretos/dependencias/config | PASS |

### Evidencia

- Rama `refactor/ui-tokens`, 10 commits, 51 archivos, +2329/−2006; `tests/` = 0.
- `resources/views`: 0 `dark:`, 0 hex, 0 `text-[9/10/11px]`, 0 paleta, 0 `w-96`.
- Bootstrap: solo `combate.js` importa el bundle; solo `combate.css` importa Bootstrap en `layer(bootstrap)`; `!important` 15→12.
- Tokens presentes en el CSS compilado; `prefers-reduced-motion` conservado.
- `view:cache` OK; el fallo del sprite se reproduce en el compilado.

### Riesgos residuales

- `x-on:error` de Alpine puede perder la carrera de inicialización; se recomienda `onerror` estático.
- `ui.modal` retirado; los modales usan `div` + Alpine (focalización nativa no disponible).
- Modo claro de combate usa `--bs-*` por defecto de Bootstrap, no tokens.
- Deuda D12 en clases PHP de exploraciones.
- `--quality-*` duplicados.

### Confirmación

El reviewer no modificó archivos. No ejecutó tests. No hizo commit ni push.

## Ronda 2 — 2026-11-03 (commit `25237dc`)

**VEREDICTO FINAL:** `APPROVED`

### Estado de los hallazgos

| Hallazgo | Estado | Evidencia |
|---|---|---|
| CRITICAL: `<x-sprite>` con `@if` no compilaba | **Resuelto** | `@if` eliminado; `:fallback` en `sprite`; `grep '<x-sprite' storage/framework/views/*.php` = 0 |
| MEDIUM: 2 modales sin ARIA en `reclutamiento` | **Resuelto** | `role="dialog"`, `aria-modal`, `aria-labelledby` e `id` en títulos |
| MEDIUM: modal de victoria sin `aria-labelledby` | **Resuelto** | `aria-labelledby="victory-modal-title"` + `id` |
| LOW: chip de postura a 18px | **Resuelto** | `x-sprite size="inline"` |
| NIT: `alt` duplicado en `_evento` | **Resuelto** | `decorative` en los dos sprites con nombre adyacente |
| NIT: `--quality-*` duplicados | **Pendiente, no bloqueante** | Fuera de alcance (D1/D12) |

### Evidencia de la re-revisión

- `git show --stat 25237dc` → 6 archivos, +19/−13; `git show --check` sin errores.
- `php artisan view:cache` OK; 0 tags `<x-sprite>`/`<x-ui.*>`/`<x-tipo-badge>` crudos en `storage/framework/views`.
- Render aislado de `sprite` en 5 casos: shiny+fallback → normal → `0.webp`; no-shiny → `0.webp`; `id`+shiny → normal → `0.webp`; `width/height` 128 y `sprite__img` presentes.
- ARIA: 18 `aria-modal`; ningún `div.fixed.inset-0` sin `role="dialog"`; 15 `aria-labelledby` apuntan a ids existentes.
- 37 `<x-sprite>`, 0 `w-96`, 0 `/images/iconos/` en vistas; 0 `dark:`/hex/`text-[9-11px]`/paleta.
- `bootstrap.bundle` 0 en `app.js`; `combate.css:1` con `layer(bootstrap)`.
- `npm run build` OK (67 módulos).

### Riesgos residuales aceptados

- `pokedex`/`equipos` usan `x-on:error` de Alpine con `x-bind:src`: posible carrera si la imagen falla antes de que Alpine registre el listener.
- Modales con `div` + Alpine (sin foco atrapado nativo; `ui.modal` retirado).
- Modo claro de combate usa defaults de Bootstrap (`--bs-*` solo en `.dark`).
- `--quality-*` no alimenta `.pokemon-quality*`.
- Clases PHP con paleta/`dark:` en exploraciones (deuda D12).

### Confirmación

El reviewer no modificó archivos; no ejecutó tests; no hizo commit ni push.

## Ronda 3 — 2026-11-03 (post-cierre, commit `199ab67`)

**Motivo:** el usuario reportó que los Pokémon no se veían shiny en `/equipos` ni en `/habitats/{id}`.

**VEREDICTO:** `APPROVED`

### Cambios revisados

- `equipos/index`: `iconoDe()` shiny-aware y `x-on:error` en slot, inventario y reemplazo.
- `habitats/show`: helpers `iconoReclutado`/`onSpriteError`; 4 usos (favoritos, `allReclutados`, formación de equipo, mazmorra) y `:shiny` en la tarjeta Blade de equipo.
- `gimnasios/show`: mismos helpers y uso en formación.
- `ReclutadoVistaPresenter`: `es_shiny` en el contrato `reclutado`.
- `reclutado/show`: `:shiny` en el sprite principal.
- `exploraciones/_explorador`: `:id` + `:shiny`.

### Hallazgos

- **[LOW] `data-intento` persistente** en `onSpriteError` (equipos, habitats, gimnasios): si un nodo reutilizado falla con otra URL, puede ocultarse en vez de reintentar normal. No afecta al caso reportado.
- Sin bucles de fallback: shiny → normal → `0.webp` → ocultar.
- Contratos `es_shiny` verificados en `ReclutadoSerializer`, `TeamMember->reclutado`, `ReclutadoVistaPresenter` y `ExploradorVista`.

### Riesgos residuales

- Capturados shiny en el resumen de exploración (`ResultadoExploracionVista`) y en el diario (`EventoExploracionVista`) siguen generando URL normal; no forman parte de este commit.
- Huevos: usan el icono de especie; puede ser intencional para no revelar el shiny antes de eclosionar.
- `x-on:error` depende de que Alpine registre el listener; el sprite de combate ya usa `onerror` estático.
- Helpers `iconoReclutado`/`onSpriteError` duplicados en 3 vistas; candidato a módulo JS compartido.

### Evidencia

- `git show --stat 199ab67` → 6 archivos, +79/−9; solo los autorizados.
- `view:cache` OK; 0 tags `<x-*>` crudos; `npm run build` OK (67 módulos).
- `grep` de URLs fijas de reclutados: 0 en las vistas corregidas.
- `tests/` = 0.

### Confirmación

El reviewer no modificó archivos; no ejecutó tests; no hizo commit ni push.
## Ronda 4 — 2026-11-03 (post-cierre, commit `71e2491`)

**Motivo:** el desarrollador pidió revertir los colores de bordes/fondos de combate introducidos por F5 y corregir el espaciado de las cards de ataque.

**VEREDICTO:** `APPROVED`

### Cambios revisados

- `resources/css/combate.css` restaurado exactamente a `556cedf` (pre-F5): vuelven los hex/rgba originales, se conserva `layer(bootstrap)` y no se reintroducen los parches de F2.
- `resources/css/app.css`: orden de capas `properties → theme → base → bootstrap → components → utilities`, para que Bootstrap gane a `base` y siga perdiendo ante `components`/`utilities`.

### Hallazgos

- **[LOW] `combate.css` vuelve a tener 56 hex y 33 `rgba()`**: es una decisión explícita del desarrollador; el criterio F5 de «0 hex en combate» deja de cumplirse. Documentado.
- **[LOW] Reboot de Bootstrap vuelve a ganar a Tailwind Preflight en `/combate`**: afecta a `body`, márgenes de `p`/`h*`/`ul` y color de `a` sin utilidades. El markup revisado no lo nota; validar visualmente.
- Sin hallazgos HIGH/MEDIUM. `.card`/`.card-body` recuperan fondo/borde/padding; `.move-card` conserva hover, `move-best` y `focus-visible`.

### Evidencia

- `git diff 556cedf 71e2491 -- resources/css/combate.css` → 0 líneas.
- `git show --stat 71e2491` → 2 archivos, +97/−104; solo `app.css` y `combate.css`.
- Orden compilado: `properties(0) → theme(1575) → base(8276) → bootstrap(11865) → components(11882) → utilities(13767)`.
- `npm run build` OK (67 módulos); `tests/` = 0.

### Riesgos residuales

- Doble fuente de color en combate (`tokens.css` + hex de `combate.css`); un theme futuro deberá tocar ambos o re-tokenizar.
- El orden de capas depende de que `app.css` se cargue antes que `combate.css` (cierto en el layout actual).
- Validación visual de `/combate` en claro/oscuro pendiente.

### Confirmación

El reviewer no modificó archivos; no ejecutó tests; no hizo commit ni push.
