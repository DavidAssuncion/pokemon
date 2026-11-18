# TASK-051 — Tareas

- **Plan:** v1 (aprobado) + correcciones C1/C2 dentro del objetivo
- **Estado:** IMPLEMENTADO — pendiente de revisión

## Fase 1 — Tokens y CSS base (frontend)

- [x] F1 `resources/css/tokens.css` — mapa claro/oscuro, tokens nuevos y `on-scrim`.
- [x] F2 `resources/css/components.css` — `.tipo-badge`, `.type-plate`, puente de compatibilidad.
- [x] F3 `resources/css/app.css` — `.pokemon-card-assigned`, duplicado `color-scheme`.
- [x] F4 `resources/css/combate.css` — hotfix Reboot, puente `--bs-*`, overrides y tokenización.

## Fase 2 — Backend de presentación

- [x] B1 `src/Shared/Tipos/TipoPokemon.php` — `textoSobreColor()`.
- [x] B2 `app/Livewire/Presenters/MovesPreviewPresenter.php` — clases de token.
- [x] B3 `src/Battle/Domain/CombatienteVista.php` — clases de token.
- [x] B4-B6 `src/Exploraciones/Presentation/*` — estados por token, sin `dark:`.

## Fase 3 — Layouts, tipos, gráficos y vistas (frontend)

- [x] F5 `layouts/app.blade.php` — toggle, `theme:cambiado`, `combate:animando`, cosecha visible.
- [x] F6 `layouts/auth.blade.php` — `meta theme-color`.
- [x] F7 `equipos/index.blade.php` — refresco de gráficos por tema.
- [x] F8 `partials/tipos-tokens.blade.php` — `--type-*-on`.
- [x] F9 `livewire/combate.blade.php` — eventos de animación.
- [x] F10 `battle-field`/`_pokemon-card` — `wire:key`.
- [x] F11 `moves-panel.blade.php` — badges, `--type-*-on`, placa de icono, `wire:key`.
- [x] F12 `items-panel.blade.php` — badges de token.
- [x] F13 `_pokemon-card.blade.php` — revisado; sin fills de texto que requieran `text-on-*`.
- [x] F14 `sendas/*` — texto/fondo de tipo legible.

## Fase 4 — Unificación JS

- [x] F15 `vite.config.js`, `resources/js/app.js`, `combate_page.blade.php`, `resources/js/combate.js` — una sola entrada y Bootstrap bajo demanda.

## Correcciones

- [x] C1 — puente CSS para `bg-<estado> text-on-primary`, link hover y ajustes de combate.
- [x] C2 — badges de tipo de gimnasios, `--color-on-scrim`, título de hábitat.
- [x] C3 — guarda `:disabled` en el puente, botones Bootstrap tokenizados, hotfix de enlaces y `wire:key` por slug.
- [x] C5 — `.tipo-badge` sin `::before` y con `padding-left: 0`.
- [x] D3/D4 — ADR y convenciones actualizados tras la revisión.
- [x] D6 — ADR del badge sin punto.

## Fase 5 — Documentación

- [x] D1 ADR `docs/adr/0004-tema-stone-ink-lavender-grey.md`.
- [x] D2 `docs/conventions.md` — convención CSS actualizada.

## Fase 6 — Verificación

- [x] `npm run build`.
- [x] `php -l` de los PHP modificados.
- [x] `vendor/bin/pint --dirty --format agent`.
- [x] Greps de control.
- [x] Revisión manual pendiente del desarrollador (no ejecutada por el agente).

## Fase 7 — Revisión

- [ ] `reviewer` independiente (ronda 2 tras C3/D3/D4).
- [ ] `security` — evaluar si aplica; previsiblemente `no aplica`.
