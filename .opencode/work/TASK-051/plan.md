# TASK-051 — Plan v1

- **ID:** TASK-051
- **Versión:** v1
- **Fecha:** 2026-11-17
- **Estado:** AWAITING_APPROVAL
- **Base:** `analysis.md` (incluye Subanálisis A y B) y `architecture.md`.

## 1. Objetivo

Sustituir el tema claro actual por «Stone & ink» y el oscuro por «Lavender Grey — Dark», manteniendo los nombres de token existentes, garantizando contraste AA en texto y controles, y dejando combate integrado con el mismo sistema visual. Incluye corregir el subrayado de enlaces, mostrar el botón de cosecha en combate con bloqueo durante la animación, mejorar la visibilidad de los iconos de tipo y unificar la entrada JS de combate.

## 2. Alcance

- Tokens de color claros y oscuros, estados, controles, foco, gráficos y superficies.
- Componentes CSS compartidos: badges de tipo, placa de icono de tipo, foco, tarjetas.
- Layouts: sincronización del icono del toggle, evento de cambio de tema, `meta theme-color`, botón de cosecha en combate.
- Gráficos de Equipos: colores desde tokens y refresco al cambiar tema.
- Combate: hotfix de Reboot, puente `--bs-*` a tokens, tokenización de `combate.css`, overrides de alertas/selects/botones, bloqueo de cosecha durante la animación.
- Limpieza de colores fijos en presentadores PHP (`src/Exploraciones/Presentation`, `src/Battle/Domain/CombatienteVista.php`, `app/Livewire/Presenters/MovesPreviewPresenter.php`).
- ADR de la decisión y actualización de la convención CSS.
- Verificación estática y build.

## 3. Fuera de alcance

- Eliminar Bootstrap por completo o migrar sus ~250 usos a Tailwind (Fase 3 del Subanálisis B). Requiere decisión de producto y un alcance propio.
- Retematizar la paleta neón de calidad IV o cambiar su fondo `#1e293b`. Se conserva exactamente.
- Cambiar los 18 colores de tipo de `TipoPokemon::color()`.
- Añadir modo «system», sincronización entre pestañas o un módulo de tema completo.
- Añadir dependencias, incluida `sass`.
- Crear, modificar o ejecutar tests. `tests/` queda fuera de alcance.
- Modificar los cambios locales ajenos:
  - `resources/views/habitats/partials/modal-formacion.blade.php`
  - `resources/views/partials/leyenda-ivs-evs.blade.php`
  - `resources/views/gimnasios/show.blade.php`

## 4. Decisiones incorporadas

- Acento `#A47750` como primario interactivo; texto oscuro `on-primary` para cumplir AA.
- Calidad IV neón intacta.
- Se autoriza limpiar hardcodes en `src/` y `app/`.
- Livewire no es la causa del subrayado ni del icono ausente; el subrayado es cascada CSS y el icono era una condición Blade.
- El botón de cosecha se muestra en combate y se deshabilita mientras la cola de animación está activa.
- Unificación de combate: entrada JS única + puente CSS de tokens; Bootstrap CSS sigue aislado en combate.

## 5. Arquitectura y contratos

- **Tokens:** se conservan los 27 nombres existentes. Se añaden `--color-secondary*`, `--color-on-{primary,secondary,success,warning,danger,info}`, `--color-control-{bg,border}`, `--color-muted`, `--color-chart-*`, `--color-primary-ink` (ya existente) y `--color-focus`. Mapa exacto en `architecture.md` §3-§5.
- **Superficies claras:** `raised #FAF7F2` > `canvas #F2EFE9` > `surface #E9E4DA` > `sunken #DFD8CB`.
- **Superficies oscuras:** `surface = raised = #2A2733`, `canvas #1E1B24`, `sunken #17141E`.
- **Contraste:** texto normal ≥4.5:1; UI y foco ≥3:1. `#A69B8C` y `#8B88A3` solo como `--color-muted` decorativo; el texto usa `ink-muted`/`-ink` derivados.
- **Tipos:** se conservan los 18 hex. `TipoPokemon::textoSobreColor()` calcula `--type-<slug>-on` por luminancia WCAG; los badges usan tinte 14% + texto `ink`, y los fondos sólidos usan `--type-*-on`.
- **Calidad:** sin cambios en `--quality-*`, `app.css:25-128` ni `#1e293b`. Solo se tokeniza `.pokemon-card-assigned`.
- **Tema:** se mantiene `localStorage['theme']` y el toggle binario. Al alternar se despacha `theme:cambiado` con `{ tema: 'light'|'dark' }`.
- **Combate:** `combate.css` gana un hotfix sin capa (`a { text-decoration: none; }`, `body { font-family: inherit; }`) y un puente `:root`/`.dark` de `--bs-*` a tokens. Los overrides de alertas/selects/botones viven en `@layer components` para ganar a `bootstrap` sin reordenar capas.
- **Cosecha:** el botón del navbar se muestra también en `/combate`. El componente despacha `combate:animando` al empezar y terminar `resolverAccion`; el layout escucha el evento y deshabilita el botón mientras `animando` sea verdadero.
- **JS:** `resources/js/app.js` carga Bootstrap con import dinámico solo si existe `.battle-container`. Se elimina la entrada `resources/js/combate.js` de Vite y de `combate_page`; el script inline de combate se mantiene en esta tarea.
- **Livewire:** se añaden `wire:key` a las tarjetas de `battle-field` y a los ataques de `moves-panel` para higiene de morph. No se atribuye el rediseño a Livewire.

## 6. Archivos y responsables

### Frontend — CSS y presentación

| ID | Archivo | Cambio |
|---|---|---|
| F1 | `resources/css/tokens.css` | Sustituir valores claro/oscuro; añadir tokens nuevos; conservar `quality-*` y `type-*` |
| F2 | `resources/css/components.css` | Nueva fórmula `.tipo-badge`; añadir `.type-plate`; mantener `.focus-ring` |
| F3 | `resources/css/app.css` | Tokenizar `.pokemon-card-assigned`; eliminar `.dark{color-scheme}` duplicado; no tocar calidad |
| F4 | `resources/css/combate.css` | Hotfix Reboot; puente `--bs-*`; overrides `components`; tokenizar hex; eliminar bloque `.dark` antiguo |
| F5 | `resources/views/layouts/app.blade.php` | Toggle sincronizado con el tema real; despachar `theme:cambiado`; escuchar `combate:animando`; mostrar cosecha en combate; `meta theme-color` |
| F6 | `resources/views/layouts/auth.blade.php` | `meta theme-color`; inicialización coherente sin toggle |
| F7 | `resources/views/equipos/index.blade.php` | Escuchar `theme:cambiado`; reconstruir gráficos; usar `--color-chart-*`; limpiar listener en `destroy()` |
| F8 | `resources/views/partials/tipos-tokens.blade.php` | Emitir `--type-<slug>-on` además de `--type-<slug>` |
| F9 | `resources/views/livewire/combate.blade.php` | Despachar `combate:animando` en `resolverAccion` (inicio/fin); opcional `wire:key` en raíz ya existe |
| F10 | `resources/views/livewire/partials/battle-field.blade.php` | `wire:key` en bucles de tarjetas |
| F11 | `resources/views/livewire/partials/moves-panel.blade.php` | `wire:key` en ataques; `--type-*-on`; badges de token; `.type-plate` en el icono |
| F12 | `resources/views/livewire/partials/items-panel.blade.php` | `text-bg-primary` → `bg-primary text-on-primary`; resto de badges a tokens |
| F13 | `resources/views/livewire/_pokemon-card.blade.php` | Añadir `text-on-*` a fills `bg-primary/info/success` |
| F14 | `resources/views/sendas/index.blade.php`, `sendas/show.blade.php` | Texto de tipo → `ink` + borde/punto; fondos sólidos → `--type-*-on` |
| F15 | `vite.config.js`, `resources/js/app.js`, `resources/views/combate_page.blade.php`, `resources/js/combate.js` | Unificar JS: import dinámico de Bootstrap en app.js; quitar entrada `combate.js`; eliminar archivo si no queda referencia |

### Backend — PHP de presentación

| ID | Archivo | Cambio |
|---|---|---|
| B1 | `src/Shared/Tipos/TipoPokemon.php` | Añadir `textoSobreColor(): string` con luminancia WCAG |
| B2 | `app/Livewire/Presenters/MovesPreviewPresenter.php` | `categoriaColor` y `colorEfectividad` devuelven clases de token |
| B3 | `src/Battle/Domain/CombatienteVista.php` | `posturaClase` y clases de partes a tokens (`bg-*-soft text-*-ink`, `text-on-*`) |
| B4 | `src/Exploraciones/Presentation/ResultadoExploracionVista.php` | Sustituir `bg-emerald/green/amber/red/gray` y `dark:` por tokens |
| B5 | `src/Exploraciones/Presentation/PresentadorExploraciones.php` | Igual que B4 |
| B6 | `src/Exploraciones/Presentation/ExploradorVista.php` | Igual que B4 |

### Documentación

| ID | Archivo | Cambio |
|---|---|---|
| D1 | `docs/adr/0004-tema-stone-ink-lavender-grey.md` | Nuevo ADR: paletas, contraste, puente Bootstrap, decisiones y deuda |
| D2 | `docs/conventions.md` | Actualizar la regla «Sin CSS personalizado» para admitir tokens/componentes |

### No se toca

`tests/`, `.env`, `TASK-049/`, `TASK-050/` y los tres archivos con cambios locales indicados.

## 7. Orden de ejecución y dependencias

1. **Fase 1 — Tokens y CSS base (F1-F4).** Frontend. Sin dependencias externas.
2. **Fase 2 — Backend de presentación (B1-B6).** Puede ejecutarse en paralelo con Fase 1: no comparte archivos. B1 debe preceder a F8/F11.
3. **Fase 3 — Layouts, tipos, gráficos y vistas (F5-F14).** Frontend, después de F1-F4 y B1-B6. F5 depende de F9 para el evento `combate:animando`; se implementan en secuencia dentro del mismo lote.
4. **Fase 4 — Unificación JS (F15).** Frontend, después de F5/F9 para no dejar referencias al evento o al bundle antiguo.
5. **Fase 5 — Documentación (D1-D2).** Documenter, en paralelo con Fase 4 si el diseño ya está cerrado.
6. **Fase 6 — Verificación estática y build.** Orquestador/specialist según corresponda.
7. **Fase 7 — Revisión independiente (`reviewer`).** Después de Fase 6.

Reglas de concurrencia:

- Dos agentes no editan el mismo archivo.
- Fase 1 y Fase 2 pueden ir en paralelo.
- F5-F14 se ejecutan en secuencia dentro de un único lote de frontend para evitar conflictos en Blade.
- F15 se ejecuta después de F5/F9.
- `documenter` no toca código.

## 8. Criterios de aceptación

1. El tema claro usa Stone & ink y el oscuro Lavender Grey; el toggle cambia ambos sin recargar.
2. Los 27 nombres de token existentes siguen existiendo y ningún consumidor tiene que renombrarse.
3. Todo texto normal ≥4.5:1 sobre `canvas`/`surface`/`soft`; controles y foco ≥3:1.
4. `on-primary` y `on-{success,warning,danger,info}` ≥4.5:1 sobre su fill.
5. Los cinco colores de calidad y `#1e293b` permanecen idénticos.
6. Los 18 colores de tipo permanecen idénticos; badges y fondos sólidos son legibles en ambos temas.
7. `/combate`: sin subrayado en enlaces, tipografía heredada, alerts/cards/selects/modal tokenizados en claro y oscuro.
8. `combate.css` no redefine la paleta oscura; el único `.dark` es el de tripletas RGB del puente.
9. `text-bg-*` no aparece en el markup; los badges usan tokens.
10. El botón de cosecha aparece en combate y queda deshabilitado mientras la animación está activa; fuera de combate sigue funcionando igual.
11. Los gráficos de Equipos se reconstruyen al cambiar de tema y no dejan listeners tras `destroy()`.
12. Una sola entrada JS en Vite; Bootstrap se carga dinámicamente solo en páginas con `.battle-container`.
13. No se modifican `tests/`, `.env`, dependencias ni los tres archivos con cambios locales.
14. `npm run build` termina sin errores; `php -l` y `pint --dirty` pasan en los PHP modificados.
15. Cero `dark:` en `src/`/`app/` y cero clases de paleta Tailwind fija (`bg-emerald`, `bg-green-*`, `bg-amber-*`, `bg-red-*`, `bg-gray-*`) en el alcance.

## 9. Verificación requerida

- `npm run build`.
- `php -l` sobre cada PHP modificado.
- `vendor/bin/pint --dirty --format agent`.
- `git diff --check`.
- Greps de control:
  - `grep -rn 'text-bg-' resources/views` → 0.
  - `grep -rn 'text-white\|bg-emerald\|bg-green-\|bg-amber-\|bg-red-\|bg-gray-' src app resources/views` → 0.
  - `grep -rn 'dark:' src app` → 0.
  - `grep -n '#[0-9a-fA-F]\{3,8\}' resources/css/combate.css` → 0 (salvo data-URI de select).
  - `grep -n 'color-scheme: dark' resources/css` → 1 (tokens.css).
  - `grep -rn '#1e293b' resources/css` → solo `app.css` (calidad).
  - `grep -rn 'fa-seedling' resources/views` → navbar y toast; confirmar que el navbar ya no tiene la condición de ruta.
- Revisión manual de rutas: `/`, `/equipos`, `/combate`, `/sendas`, `/gimnasios`; toggle claro/oscuro; modales; enlaces; cosecha; iconos de tipo; responsive.
- No se ejecuta la suite de tests.

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| `on-primary` oscuro cambia el aspecto de botones/badges | Aprobado en la decisión de diseño; rollback en `tokens.css` |
| `surface` más oscuro que `canvas` se percibe como error | `raised` para modales; ADR y revisión visual |
| El puente `--bs-*` no cubre algún componente Bootstrap | Overrides en `components`; grep de hex y revisión de combate |
| El select pierde el chevron al tokenizar | Data-URI por tema o `background-image` con SVG; verificación visual |
| El botón de cosecha interfiere con la animación | Evento `combate:animando` + `:disabled`; prueba manual de cosecha en combate |
| El import dinámico de Bootstrap llega tarde | Se carga al inicio del módulo si existe `.battle-container`; los modales se abren por interacción posterior |
| Chart.js conserva colores antiguos | Evento `theme:cambiado` + reconstrucción + `removeEventListener` en `destroy()` |
| Cambios locales ajenos se rompen | No se editan; revisión visual coordinada con TASK-049/050 |
| Livewire morph reutiliza nodos | `wire:key` en tarjetas/ataques; no se atribuye la causa a Livewire |

## 11. Rollback

- Revertir los cuatro CSS (`tokens.css`, `components.css`, `app.css`, `combate.css`) restaura el tema anterior.
- Los cambios de layouts/vistas se revierten por archivo.
- Los cambios PHP de presentadores son independientes y reversibles por archivo.
- La unificación JS se revierte restaurando `vite.config.js`, `app.js`, `combate_page.blade.php` y `combate.js`.
- No hay migraciones ni cambios de datos.
