# TASK-009 — Informe final

## Resumen

Refactor visual completo de la app Laravel `pokemon`: capa de tokens centralizada, componentes Blade reutilizables, sprites Pokémon normalizados (128/96/64/32/combate 128), migración de las 44 vistas a tokens, aislamiento de Bootstrap en combate y limpieza de código muerto. Sin cambios de lógica de negocio, dominio, controladores, consultas, migraciones, dependencias ni tests.

- **Estado:** `COMPLETED`
- **Rama:** `refactor/ui-tokens` (desde `master` @ `5917dc5`)
- **Commits:** 14 (`100f836` … `71e2491`, incluido `f30a02b fixes` del desarrollador)
- **Diff:** 55 archivos, +2308/−1919
- **Aprobación:** plan v1, 2026-11-03 (`approval.md`)
- **Revisión:** `APPROVED` en ronda 2 (`review.md`)
- **Seguridad:** `NO APLICA` (`security.md`)

## Fases y commits

| Fase | Contenido | Commit |
|---|---|---|
| F1 | `tokens.css`, `components.css`, `partials/tipos-tokens`, includes en layouts, color de tipos en JS | `100f836` |
| F2 | Bootstrap JS solo en combate; CSS en `layer(bootstrap)`; 3 parches de cascada eliminados | `71c33a3` |
| F3 | `ui.button/input/card/alert`, `sprite`, `tipo-badge`; pilotos welcome/login/reclutado | `6bec4cb` |
| F4a | Layouts, auth, onboarding, welcome | `f58663f` |
| F4b | Sendas, misiones, gimnasios, reclutamiento, reclutado, hábitats index; token `scrim` | `e8a89c6` |
| F4c | Pokédex, exploraciones (6), gimnasios admin/show | `a1952b0` |
| F4d | `habitats/show` (2.282 líneas), 9 sprites, 6 modales | `c0703e9` |
| F4e | `equipos/index` (3.256 líneas) + partial de movimientos | `556cedf` |
| F5 | `combate.css` tokenizado + vistas Livewire de combate | `51bfe93` |
| F6 | Limpieza de 11 componentes muertos, retirada de `TipoBadges::CLASES`, prueba de theme | `e7837a9` |
| Fix | Correcciones de revisión (sprite de combate, ARIA, tamaños) | `25237dc` |
| Fix shiny | `es_shiny` en sprites de equipos, hábitats, gimnasios, ficha y explorador | `199ab67` |
| Fix combate | Restauración de colores pre-F5 y orden de capas para las cards de ataque | `71e2491` |

## Decisiones principales

- Tokens en `resources/css/tokens.css` + `components.css`; `app.css` solo importa y conserva calidad IV.
- Color de tipos desde `TipoPokemon::color()` inyectado como `--type-{slug}`; sin duplicar hex.
- Bootstrap CSS en `@layer bootstrap`; Bootstrap JS solo en `combate.js`.
- Sprites con `width`/`height` 128×128, `object-fit: contain`, `loading`/`decoding` y fallback shiny → normal → `0.webp`.
- `sprite` acepta `fallback` estático; evita depender de Alpine para el error.
- Theme futuro claro/oscuro: `.dark` redefine variables, sin `!important`.
- Modales con `div` + Alpine y `scrim` tokenizado (el `<dialog>`/`ui.modal` se retiró con 0 usos).

## Verificación ejecutada

- `resources/views/**`: **0** `dark:`, **0** hex, **0** `text-[9/10/11px]`, **0** clases de paleta Tailwind, **0** `w-96`, **0** `/images/iconos/` en vistas migradas.
- `resources/css/combate.css`: restaurado al estado pre-F5 por decisión del desarrollador; vuelve a tener 56 hex y 33 `rgba()`, 13 keyframes, `prefers-reduced-motion` y 12 `!important`. El aislamiento de Bootstrap en `layer(bootstrap)` se conserva.
- Bootstrap: `bootstrap.bundle` solo en `combate.js`; `data-bs-*` solo en combate; `layer(bootstrap)` en `combate.css`.
- `npm run build`: OK (67 módulos; `app.css` ~62 kB, `combate.css` ~243 kB).
- `php artisan view:cache` / `view:clear`: OK; 0 tags de componente crudos en `storage/framework/views`.
- Prueba de theme: `--color-primary` `#2563eb → #dc2626 → #2563eb`; `git diff resources/views` vacío; `tokens.css` restaurado con sha256 idéntico.
- `tests/`: **0** archivos tocados o ejecutados.
- `git push`: no ejecutado.

## Corrección post-cierre: sprites shiny

Tras la validación manual, el desarrollador reportó que los Pokémon no se veían shiny en `/equipos` ni en `/habitats/{id}`.

- **Causa:** `iconoDe()` en `equipos/index` ignoraba `es_shiny`; varias vistas de hábitat usaban URLs normales fijas para reclutados.
- **Corrección (`199ab67`):**
  - `equipos/index`: `iconoDe()` devuelve `/shiny/{id}_s.webp`; `x-on:error` en slot, inventario y reemplazo.
  - `habitats/show`: helpers `iconoReclutado`/`onSpriteError`; favoritos, grid de reclutados, formación de equipo y mazmorra; `:shiny` en tarjetas Blade.
  - `gimnasios/show`: mismos helpers y uso en formación.
  - `reclutado/show` + `ReclutadoVistaPresenter`: campo de presentación `es_shiny` y `:shiny`.
  - `exploraciones/_explorador`: `:id` + `:shiny`.
- **Fallback:** shiny → normal → `0.webp` → ocultar.
- **Revisión focalizada:** `APPROVED`; hallazgo LOW de `data-intento` persistente, no bloqueante.
- **Pendiente conocido:** los capturados shiny del resumen y del diario de exploración siguen generando URL normal (`ResultadoExploracionVista`/`EventoExploracionVista`), fuera de este commit.

## Corrección post-cierre 2: colores y espaciado de combate

El desarrollador pidió revertir los cambios de colores de bordes/fondos que F5 introdujo en combate y corregir el espaciado de las cards de ataque.

- **Colores:** `resources/css/combate.css` se restauró exactamente al estado previo a F5 (`556cedf`). Vuelven los hex/rgba originales de `.pokemon-card`, vanguardia, clima, estados, vitales y animaciones. Se conserva el `@import` de Bootstrap en `layer(bootstrap)` y no se reintroducen los parches de F2.
- **Cards de ataque:** el reset `button { padding:0; background:transparent; border:0 }` de Tailwind `base` estaba pisando `.card`/`.card-body` de Bootstrap porque `bootstrap` era la primera capa. Se cambió el orden en `app.css` a `properties, theme, base, bootstrap, components, utilities`; Bootstrap queda por encima de `base` y por debajo de `components`/`utilities`. `.card` recupera fondo/borde y `.card-body` su padding.
- **Commit:** `71e2491 fix(ui): restaurar colores de combate y prioridad de Bootstrap`.
- **Revisión focalizada:** `APPROVED`; LOW de doble fuente de color en combate y Reboot de Bootstrap, no bloqueantes.
- **Decisión:** el criterio F5 de «0 hex en `combate.css`» deja de aplicarse por petición explícita del desarrollador. El theme futuro deberá tocar `tokens.css` y `combate.css`, o re-tokenizar combate.

## Revisión y seguridad

- **Ronda 1:** `CHANGES_REQUESTED` por un CRITICAL real (`@if` dentro de `<x-sprite>` no compilaba y dejaba el combate sin sprites) y dos MEDIUM de ARIA.
- **Ronda 2:** `APPROVED`; CRITICAL y MEDIUM resueltos y verificados con `view:cache` + render aislado del componente.
- **Seguridad:** no aplica; no se tocaron autenticación, autorización, entrada, SQL, sesiones, cookies, secretos, dependencias ni endpoints.

## Riesgos residuales

- `pokedex`/`equipos` usan `x-on:error` de Alpine con `x-bind:src`: posible carrera si la imagen falla antes de que Alpine registre el listener.
- Los modales son `div` + Alpine (sin foco atrapado nativo; `ui.modal` retirado).
- En modo claro, los componentes Bootstrap de combate usan defaults de Bootstrap (`--bs-*` solo se mapea en `.dark`).
- `--quality-*` no alimenta `.pokemon-quality*` (dos fuentes para el color de calidad IV).
- Clases de paleta/`dark:` emitidas por PHP en exploraciones (13 ocurrencias; deuda D12).
- `src/Shared/context.md` sigue mencionando `clases` en la API de `TipoBadges` (documentación desactualizada; fuera de alcance).
- No se ha hecho validación visual en navegador ni en dispositivo real; es el paso manual pendiente.

## Próximos pasos

1. Revisar en `npm run dev` las páginas principales en claro/oscuro: `/`, `/login`, `/pokedex`, `/equipos`, `/reclutamiento`, `/habitats/{id}`, `/exploraciones`, `/gimnasios`, `/sendas`, `/misiones`, `/combate`.
2. Validar especialmente el combate (sprites 128, barras, animaciones, modales) y las dos vistas monolíticas (`equipos`, `habitats/show`).
3. Si la validación visual es correcta, fusionar `refactor/ui-tokens` en `master` (sin push automático; requiere decisión del desarrollador).
4. Abordar el theme definitivo claro/oscuro sobre los tokens ya centralizados.
5. Deuda posterior: clases PHP de exploraciones (D12), `--quality-*` en `app.css`, `src/Shared/context.md`.

## Archivos de coordinación

`.opencode/work/TASK-009/`: `request.md`, `analysis.md`, `architecture.md`, `plan.md`, `approval.md`, `tasks.md`, `execution.md`, `review.md`, `security.md`, `final-report.md`.
