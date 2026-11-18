# TASK-051 — Análisis

- **Estado:** COMPLETADO
- **Responsable:** `analyst`
- **Fecha:** 2026-11-17
- **Nota:** el agente alcanzó el límite de pasos durante la verificación. El análisis se basa en la evidencia leída y en los greps ejecutados. No se modificó ningún archivo ni se ejecutó build, servidor o tests. Quedaron sin verificar: revisión línea a línea de las ~60 vistas consumidoras (se usó grep), consumo exacto de `window.TIPOS_COLOR` en JS y generación de correo/PDF fuera de `resources/views`.

## Resumen

- La app usa **dos entradas CSS distintas**: `resources/css/app.css` (Tailwind v4 + tokens + componentes, para toda la app) y `resources/css/combate.css` (Bootstrap 5 + estilos propios, solo en la página de combate). `resources/views/combate_page.blade.php:1,6` extiende `layouts.app` y añade `combate.css`, por lo que conviven ambos sistemas.
- El tema se implementa con la clase `.dark` en `<html>`, persistida en `localStorage['theme']` y con fallback a `prefers-color-scheme`. El toggle es un `x-data` inline en el botón del navbar. No hay store/módulo JS de tema ni sincronización entre componentes.
- El cuerpo principal está bien tokenizado: en `resources/views` no hay hex propios ni clases de la paleta Tailwind por defecto. **Los puntos sucios están fuera de las vistas**: `resources/css/combate.css` (83 hex), `resources/css/app.css` (calidad IV neón), tres presentadores de `src/Exploraciones/Presentation`, `src/Battle/Domain/CombatienteVista.php` y `app/Livewire/Presenters/MovesPreviewPresenter.php`.
- Las dos paletas nuevas no encajan solo cambiando valores: faltan tokens (`secondary`, `on-success/on-danger/...`, borde de control, foco desacoplado, tokens de gráfico) y los valores `muted`/`accent`/`primary` propuestos **no alcanzan AA** en texto normal (ratios calculados abajo).
- Archivos modificados: **ninguno**.

## Requisitos

- **Funcionales:**
  - Sustituir la paleta clara actual por «Stone & ink» (6 colores base).
  - Sustituir la paleta oscura actual por «Lavender Grey — Dark».
  - Mantener el mecanismo claro/oscuro, su persistencia y el toggle existente.
  - Mantener coherencia en estados, hover, focus, tablas, formularios, badges, gráficos y modales.
  - No cambiar lógica de negocio, backend ni contratos de datos.
- **No funcionales:**
  - Laravel 12 + Blade + Tailwind CSS v4 (`package.json:13`), Vite 7, sin dependencias nuevas.
  - Accesibilidad AA razonable (contraste texto/fondo y controles).
  - No tocar ni tests, ni `.env`, ni los cambios locales de TASK-049/TASK-050.
  - Presentación separada de dominio (`resources/**` solo presenta).
- **Criterios de aceptación:** inventario verificable de tokens y consumidores; lista priorizada de hardcodes; necesidades de mapeo y conflictos identificados; decisiones abiertas explícitas; cero cambios en el repositorio.
- **Ambigüedades:** rol exacto del acento (¿sustituye al azul en enlaces/foco/botón primario?); si el `muted` claro es texto o decorativo; si combate/Bootstrap entra en alcance; si se conserva la paleta neón de calidad IV; si se añade `secondary`; si se conserva la paleta oficial de tipos Pokémon.

## Alcance afectado

- **Módulos:** UI compartida/layouts; Exploraciones (presentación); Battle (dominio/presentación de combate); Livewire combate; Equipos (Chart.js); Sendas/Gimnasios (colores de tipo); Hábitats/Pokédex (consumidores de tokens).
- **Archivos (candidatos a modificar):**
  - `resources/css/tokens.css`, `resources/css/app.css`, `resources/css/components.css`, `resources/css/combate.css`.
  - `resources/views/layouts/app.blade.php`, `resources/views/layouts/auth.blade.php`.
  - `resources/views/equipos/index.blade.php` (refresco de gráficos al cambiar tema).
  - `src/Exploraciones/Presentation/ResultadoExploracionVista.php`, `PresentadorExploraciones.php`, `ExploradorVista.php`.
  - `src/Battle/Domain/CombatienteVista.php`, `app/Livewire/Presenters/MovesPreviewPresenter.php`.
  - Partials Livewire de combate que usan Bootstrap (`resources/views/livewire/partials/*`), solo si combate entra en alcance.
  - Nuevo ADR en `docs/adr/` (documentación de la decisión).
- **Solo lectura / no tocar:** `resources/views/habitats/partials/modal-formacion.blade.php` y `resources/views/partials/leyenda-ivs-evs.blade.php` (cambios locales ajenos), `.opencode/work/TASK-049/`, `TASK-050/`, `tests/`, `.env`.
- **Tablas/contratos:** variables CSS `--color-*`, `--quality-*`, `--type-*`, `--bs-*`; nombres de utilidades Tailwind (`bg-surface`, `text-ink-muted`, etc.); arrays de presentadores con cadenas de clase; `window.TIPOS_COLOR`.
- **Vistas/endpoints:** todos los layouts y vistas que consumen tokens; página `/combate*`.

## Evidencia

### Arranque y versiones

- `package.json:13` — `tailwindcss ^4.0.0`; `:18` `chart.js ^4.6.1`; `:17` `bootstrap ^5.3.8`; `:16` `alpinejs ^3.16.3`.
- `vite.config.js:8-13` — entradas `app.css`, `app.js`, `combate.css`, `combate.js`.
- `resources/css/app.css:1-9` — orden de capas, imports de Tailwind/tokens/components/FontAwesome y `@source "../../src"`.
- `resources/css/app.css:11` — `@custom-variant dark (&:where(.dark, .dark *))`.

### Definición y activación del tema

- `resources/views/layouts/app.blade.php:13-17` — script inline: lee `localStorage['theme']`; si no existe, usa `prefers-color-scheme`; añade `.dark` a `<html>`.
- `resources/views/layouts/app.blade.php:86-91` — botón `x-data="{ oscuro: localStorage.getItem('theme') === 'dark' }"`; en click alterna, guarda `theme` y hace `classList.toggle('dark', oscuro)`. **No hay listener de sistema ni sincronización entre pestañas.**
- `resources/views/layouts/auth.blade.php:11-15` — misma inicialización, sin toggle.
- `resources/js/` — sin referencias a `theme`/`dark`/`oscuro` (grep vacío); el tema no vive en un store Alpine.
- `resources/css/tokens.css:115-154` — `.dark` redefine tokens; `resources/css/app.css:14` repite `color-scheme: dark`; `resources/css/combate.css:387-427` define otra capa oscura para Bootstrap.

### Inventario de tokens (claro → oscuro) y consumidores principales

| Token | Claro | Oscuro | Consumidores principales |
|---|---|---|---|
| `--color-canvas` | `#f9fafb` | `#111827` | `layouts/app.blade.php:19`, `layouts/auth.blade.php:17` |
| `--color-surface` | `#ffffff` | `#1f2937` | header `app.blade.php:23`, cards/inputs, `equipos/index.blade.php` |
| `--color-surface-raised` | `#ffffff` | `#1f2937` | modales (`components/ui/modal.blade.php:50`), hábitats/equipos |
| `--color-surface-sunken` | `#f3f4f6` | `#111827` | hover de nav/botones, tracks (`app.blade.php:39,62,78,88`) |
| `--color-line` | `#e5e7eb` | `#374151` | bordes de cards, inputs, separadores |
| `--color-line-strong` | `#d1d5db` | `#4b5563` | bordes reforzados |
| `--color-ink` | `#111827` | `#f9fafb` | texto base, headings |
| `--color-ink-muted` | `#6b7280` | `#9ca3af` | texto secundario (59 archivos) |
| `--color-ink-subtle` | `#9ca3af` | `#6b7280` | placeholders/terciario |
| `--color-ink-inverse` | `#ffffff` | `#111827` | texto sobre `bg-ink` |
| `--color-primary` | `#2563eb` | `#2563eb` | botones, nav activo, progreso |
| `--color-primary-hover` | `#1d4ed8` | `#3b82f6` | hover de botón primario |
| `--color-primary-soft` | `color-mix(primary 12%, surface)` | misma fórmula | nav activo, chips |
| `--color-primary-ink` | `#1d4ed8` | `#93c5fd` | texto sobre `primary-soft` |
| `--color-on-primary` | `#ffffff` | `#ffffff` | texto de botones/badges (83 usos) |
| `--color-success` | `#16a34a` | `#22c55e` | alertas, barras, estados |
| `--color-success-soft` / `-ink` | mix 12% / `#15803d` | mix 12% / `#86efac` | alerts, badges |
| `--color-warning` | `#d97706` | `#f59e0b` | avisos |
| `--color-warning-soft` / `-ink` | mix 12% / `#b45309` | mix 12% / `#fcd34d` | alerts, badges |
| `--color-danger` | `#dc2626` | `#ef4444` | errores, KO |
| `--color-danger-soft` / `-ink` | mix 12% / `#b91c1c` | mix 12% / `#fca5a5` | alerts, badges |
| `--color-info` | `#0284c7` | `#38bdf8` | toasts, información |
| `--color-info-soft` / `-ink` | mix 12% / `#0369a1` | mix 12% / `#7dd3fc` | alerts, badges |
| `--color-focus` | `var(--color-primary)` | `var(--color-primary)` | `.focus-ring` (`components.css:60-63`) |
| `--color-scrim` | `rgb(0 0 0/.6)` | `rgb(0 0 0/.7)` | `.scrim`, modales |
| `--quality-*` | `#94a3b8/#4ade80/#38bdf8/#c084fc/#facc15` | **sin override** | `.pokemon-quality`, JS Chart.js |
| Radios | `control .5rem`, `card .75rem`, `panel 1rem` | iguales | `components.css`, `ui/modal` |
| Sombras | `card`, `modal` (rgb fijos) | `modal` más opaca | `card-surface`, `ui/modal` |

- Conteos de uso (utilidades + `var()`, en `resources/views` + `src` + `app` + `resources/css`): `ink` 772, `ink-muted` 352, `surface` 317, `line` 266, `primary` 231, `surface-sunken` 210, `danger` 188, `warning` 168, `success` 129, `ink-subtle` 123, `on-primary` 83, `primary-ink` 67, `danger-ink` 67, `warning-ink` 64, `info` 56, `warning-soft` 50, `success-ink` 46, `danger-soft` 42, `primary-soft` 38, `line-strong` 25, `surface-raised` 19, `primary-hover` 18, `info-soft` 18, `ink-inverse` 6, `scrim` 5, `canvas` 2, `focus` 0 (solo vía `.focus-ring`).

### Componentes base tokenizados

- `resources/css/components.css:60-63` — `.focus-ring` usa `--color-focus`.
- `resources/css/components.css:66-71` — `.card-surface` usa surface/line/radius/shadow.
- `resources/css/components.css:79-81` — backdrop de modal con `color-mix(--color-ink 50%, transparent)`.
- `resources/css/components.css:84-98` — `.tipo-badge`: fondo 14% tipo + surface, borde 40% tipo + line, texto 60% tipo + ink.
- `resources/views/components/ui/alert.blade.php:18-24` — variantes info/success/warning/danger sobre tokens.
- `resources/views/components/ui/button.blade.php:27-31` — primary/secondary/ghost/danger/success; **danger y success usan `text-on-primary` (blanco)**.
- `resources/views/components/ui/input.blade.php:36-37` — input usa `bg-surface text-ink border-line`.
- `resources/views/components/ui/modal.blade.php:50-52` — `bg-surface-raised`, borde `line`.

### Calidad IV neón (hardcode)

- `resources/css/tokens.css:100-111` — paleta `--quality-*` estática y alias `--quality-color-*` para JS.
- `resources/css/app.css:25-47` — `.pokemon-quality` con `--quality-color: #94a3b8`, `--quality-text: #64748b`, fondo `#1e293b`, sombras `rgba`.
- `resources/css/app.css:50-82` — cinco variantes con hex duplicados.
- `resources/css/app.css:94-106` — `perfect` con gradiente `rgba(250,204,21,...)` y `#1e293b`.
- `resources/css/app.css:108-128` — overrides `.dark` solo de `--quality-text`.
- `resources/css/app.css:180-186` — `.pokemon-card-assigned` con `rgba(243,244,246,.65)` y `.dark` `rgba(17,24,39,.45)`.

### Tipos Pokémon

- `src/Shared/Tipos/TipoPokemon.php:67-89` — 18 hex fijos por tipo.
- `resources/views/partials/tipos-tokens.blade.php:9-15` — Blade emite `:root { --type-* }`; `:18` emite `window.TIPOS_COLOR`.
- `resources/css/tokens.css:79-97` — alias `--color-type-*`; **no cambian en `.dark`** (mismos valores).
- `resources/views/sendas/index.blade.php:27,39,43` y `sendas/show.blade.php:10,101,104` — color de tipo como fondo y como **texto** (`color: var(--type-*)`).
- `resources/views/gimnasios/index.blade.php:56,77`, `admin.blade.php:86`, `show.blade.php:46` — color de tipo como fondo de progreso/medalla.
- `resources/views/livewire/partials/moves-panel.blade.php:38` — fondo de tipo + `color: var(--color-on-primary)` (blanco).

### Gráficos y color resuelto

- `resources/views/equipos/index.blade.php:310-313` — `tokenCss()` con `getComputedStyle(document.documentElement).getPropertyValue(...)`.
- `resources/views/equipos/index.blade.php:486-492` — decide `normal` en oscuro leyendo `classList.contains('dark')`; usa `--quality-color-*`.
- `resources/views/equipos/index.blade.php:522-560` — Chart.js radar: `--color-ink-muted`, `--color-line`, `--color-primary`, `--color-success`.
- `resources/views/equipos/index.blade.php:579-595` — tres gráficos (calculado/base/IV).
- **No hay listener de cambio de tema** que destruya/recrea los gráficos; al alternar quedan con los colores resueltos al crearse.

### Colores hardcodeados fuera del tema

- `resources/css/combate.css:1` — importa Bootstrap completo en capa `bootstrap`.
- `combate.css:12-22` — HP bajo/medio/alto `#dc3545/#ffc107/#198754`.
- `combate.css:24-32` — stage-up/down `#d1e7dd/#0f5132`, `#f8d7da/#842029`.
- `combate.css:34-68` — 6 climas con fondo/texto/borde hex.
- `combate.css:70-74` y `245-251` — turno activo con gradiente `#fde047/#facc15/#ca8a04`.
- `combate.css:77-87` — turnos aliado/rival con `rgb(22,78,103)`, `rgb(39,200,237)`, `rgb(112,42,60)`, `rgb(255,99,123)`.
- `combate.css:90-97` — `targeted-card #ff000033`, `#dc3545`; `.pokemon-card` `#fff`.
- `combate.css:100-102` — `.dark .pokemon-card` `#1f2937`.
- `combate.css:124-158` — tooltip debug y vanguardia con hex/rgb.
- `combate.css:165` — etiquetas vitales `#e5e7eb`; `:333,340` — `#495057` / `#cbd5e1`.
- `combate.css:366,370` — `move-best`/focus con `#0d6efd`; `:379` dark `#60a5fa`.
- `combate.css:387-427` — variables Bootstrap oscuras (`--bs-body-bg #111827`, `--bs-body-color #f9fafb`, cards/list-group/modal/link) y `.dark .modal-content`.
- `combate.css:462-470` — trails `rgba(220,53,69,.45)`, `rgba(13,202,240,.45)`, `rgba(13,110,253,.45)`.
- `combate.css:491-549` — píldoras de delta (daño/cura/barrera/estado/bloqueo) con hex y rgba, más overrides `.dark`.
- `combate.css:580,612-616,655,689-697,767,796` — sombras/animaciones con amarillo, verde, rojo y azul fijos.
- `src/Exploraciones/Presentation/ResultadoExploracionVista.php:136-140` — clases `bg-emerald-400`, `bg-green-100`, `bg-amber-100`, `bg-red-100`, `bg-gray-200` (+ `dark:`).
- `src/Exploraciones/Presentation/PresentadorExploraciones.php:796-801` — clases `bg-blue-100`, `bg-orange-100`, `bg-amber-100`, `bg-green-100`, `bg-gray-200`, `bg-red-100` (+ `dark:`).
- `src/Exploraciones/Presentation/ExploradorVista.php:162-165` — clases `bg-red-100`, `bg-amber-100`, `bg-green-100`, `bg-gray-200` (+ `dark:`).
- `src/Battle/Domain/CombatienteVista.php:138-140` — `'bg-primary text-white'`, `'bg-success text-white'`, `'bg-light border text-secondary'`.
- `src/Battle/Domain/CombatienteVista.php:216` — `'bg-secondary text-decoration-line-through'` / `'bg-dark'`.
- `app/Livewire/Presenters/MovesPreviewPresenter.php:425-431` — `categoriaColor` devuelve `danger/primary/secondary`.
- `app/Livewire/Presenters/MovesPreviewPresenter.php:441-455` — `colorEfectividad` devuelve `dark/warning/light border/success`.
- Consumo de esos nombres: `resources/views/livewire/partials/moves-panel.blade.php:50,75,87` — `badge text-bg-{{ ... }}` (Bootstrap).
- Consumo de postura: `resources/views/livewire/_pokemon-card.blade.php:80` — `badge {{ $p['posturaClase'] }}`.

### Vistas limpias

- `grep -rn '#[0-9a-fA-F]{3,8}' resources/views` → **0 coincidencias**.
- `grep` de clases `(bg|text|border|...)-(slate|gray|red|...)-[0-9]{2,3}` en `resources/views` → **0 coincidencias**.
- `grep` de `rgba?(` en `resources/views` → solo `equipos/index.blade.php:315` (comentario) y `:528,537` (construcción JS a partir de tokens).
- Los `style="..."` con color usan `var(--type-*)`, `var(--color-scrim)` o `color-mix` (`habitats/partials/columna-izquierda.blade.php:29`, `sendas/index.blade.php:27`, etc.).

### Reglas `.ai`

- `.ai/rules/index.md:12` — `resources/**` → `frontend-minimal-logic.md`.
- `.ai/rules/index.md:13` — `src/**`, `app/**`, `resources/**` → `arrays-dto-collections.md`.
- `.ai/rules/frontend-minimal-logic.md:5-7` — backend dueño de lógica y orden; frontend solo presenta.
- `.ai/rules/arrays-dto-collections.md:44-52` — `resources/**` no calcula reglas; las vistas reciben datos listos.
- `.ai/rules/arrays-dto-collections.md:63-71` — checklist de revisión.
- `.ai/rules/model-policy.md` — modelo fijo `opencode-go/deepseek-v4.1-flash`.
- `docs/conventions.md:164` — «Sin CSS personalizado (todo utility-first)», en contradicción con `tokens.css`, `components.css` y `combate.css`; el cambio de tema exige CSS y debe actualizar/justificar esta convención.

### Estado Git

- `git status --short`: `M resources/views/habitats/partials/modal-formacion.blade.php`, `M resources/views/partials/leyenda-ivs-evs.blade.php`; sin commitear `TASK-049/`, `TASK-050/`, `TASK-051/`. Rama `master`.

## Dependencias

- Tailwind CSS v4, Vite 7, `@tailwindcss/vite`, Laravel Vite Plugin.
- Chart.js 4.6.1 (gráficos radar en Equipos).
- Bootstrap 5.3.8 solo para combate (CSS y JS bundle).
- Alpine 3.16 (vía Livewire) para el toggle y estados UI.
- Font Awesome 6.7.2 (iconos; no afecta color).
- `--type-*` depende de `TipoPokemon::color()` y del partial Blade; si se toca la paleta de tipos hay dependencia dominio→presentación.

## Duplicidades y deuda técnica

- **Dos definiciones de oscuro**: `tokens.css:115-154` (Tailwind) y `combate.css:387-427` (Bootstrap), más `app.css:14`.
- **Paleta de calidad duplicada**: `tokens.css:100-111` y `app.css:26-82` repiten los mismos hex; `app.css` no consume los tokens de calidad.
- **`--color-primary` idéntico en claro y oscuro** (`tokens.css:26,130`), sin variante oscura real.
- **`surface` = `surface-raised`** en ambos temas; **`canvas` = `surface-sunken`** en oscuro; no hay jerarquía de elevación.
- **Bootstrap sin tema claro**: solo hay overrides `.dark`; en claro el combate usa azul/blanco por defecto (#0d6efd, #fff), ajeno a Stone & ink.
- **Presentadores devuelven arrays con cadenas de clase** (`ResultadoExploracionVista:78`, `PresentadorExploraciones:674,763`, `CombatienteVista:138-140`), deuda ya existente frente a `arrays-dto-collections.md`.
- **Dominio emitiendo clases CSS** (`src/Battle/Domain/CombatienteVista.php`), acoplamiento de dominio a presentación.
- **`dark:` en `src`** (15 líneas) depende de que Tailwind escanee `src`; `app.css:9` lo declara explícitamente, pero mover clases a `app/` o constantes requiere verificar el escaneo.
- **Sin módulo de tema**: el toggle está duplicado conceptualmente en dos layouts y no notifica a Chart.js.

## Riesgos

- **[alto] Contraste de `muted` claro.** `#A69B8C` sobre `#F2EFE9` = **2.38:1**; sobre `#E9E4DA` = **2.16:1**. `text-ink-muted` se usa en 59 archivos. Impacto: texto secundario ilegible. Mitigación: definir `--color-ink-muted` más oscuro (derivado) o separar `muted` decorativo de `muted-text`.
- **[alto] `accent` como primario y `on-primary` blanco.** Blanco sobre `#A47750` = **3.94:1**; `#A47750` sobre fondo = **3.43:1**. Falla AA de texto normal en botones (83 usos de `on-primary`) y en enlaces. Mitigación: token `on-primary` oscuro o un `accent-ink` más oscuro; decidir si el acento es solo decorativo/UI.
- **[alto] Isla Bootstrap de combate.** `combate.css` tiene 83 hex y 988 líneas; el claro no está tematizado. Impacto: combate se verá con otra paleta. Mitigación: puente `--bs-*` desde tokens en ambos temas, o declarar combate fuera de alcance.
- **[alto] Estados con `on-primary` blanco sobre success/danger/info oscuros.** Blanco sobre `#7CA982` = **2.67:1**, sobre `#60A5FA` = **2.54:1**, sobre `#F87171` = **2.77:1**. `ui/button.blade.php:30-31` ya usa `text-on-primary` para danger/success. Mitigación: tokens `on-success/on-danger/on-info` o texto oscuro.
- **[medio] `muted` oscuro justo por debajo de AA.** `#8B88A3` sobre card `#2A2733` = **4.28:1** (texto normal); sobre fondo = **4.97:1**. Mitigación: aclarar ligeramente para superficies.
- **[medio] Primario oscuro como texto.** `#A855F7` sobre card = **3.69:1**; sobre fondo = **4.29:1**; blanco sobre primario = **3.96:1**. Sirve para UI/large, no para texto normal.
- **[medio] Badges de tipo en claro.** La fórmula actual (texto 60% tipo + 40% ink) ya falla en varios tipos; con `surface #E9E4DA` e `ink #302E2A` empeora: estimación eléctrico **1.79:1**, hielo **1.89:1**, tierra **2.05:1**, acero **2.22:1**, planta **2.33:1**. En oscuro mejora (~4.78-5.22:1). Mitigación: texto del badge desde `--color-ink` o `--type-*-ink` por tipo; revisar `sendas` y `moves-panel:38`.
- **[medio] Presentadores con paleta Tailwind fija** (15 clases en 3 archivos): el cambio de tokens no los afecta; seguirán con verdes/rojos/azules ajenos a Stone & ink.
- **[medio] Gráficos obsoletos al alternar tema.** Chart.js resuelve colores al crear; no hay listener. Mitigación: hook de re-render en el toggle o exponer evento `theme:changed`.
- **[medio] Calidad IV neón sobre tema claro.** `.pokemon-quality` fija fondo `#1e293b` incluso en claro (`app.css:37`); sobre Stone & ink será un bloque oscuro. Decisión pendiente.
- **[bajo] Icono del toggle desincronizado.** `app.blade.php:86` inicializa `oscuro` solo desde `localStorage`; si el tema viene de `prefers-color-scheme`, el icono muestra luna aunque esté oscuro.
- **[bajo] Sin `meta name="theme-color"`** en los layouts (head revisado en `app.blade.php:3-18` y `auth.blade.php:3-16`); la barra del navegador móvil no acompaña.
- **[bajo] Conflicto con cambios locales.** Los dos archivos modificados consumen tokens/calidad; cambiar valores alterará su render aunque no se editen. No hay conflicto de archivo, sí de revisión visual.

## Opciones

1. **Remapeo mínimo**: cambiar solo valores en `tokens.css`/`.dark` y ajustar `app.css`; mantener nombres. Rápido, pero deja los fallos de contraste, la isla Bootstrap y los presentadores hardcodeados.
2. **Remapeo + tokens faltantes + migración de hardcodes** (recomendada): añadir `secondary`, `on-*`, `input/control`, `focus`, tokens de gráfico; puentear Bootstrap; sustituir clases de presentadores y combate por tokens; hook de re-render de Chart.js. Coherente y verificable.
3. **Capa de tema completa**: opción 2 + módulo JS de tema (eventos, `system`, `meta theme-color`) y ADR de theming. Mayor alcance y más riesgo de tocar más vistas.

## Suposiciones

- Los ratios se calcularon con luminancia relativa WCAG en sRGB; los mixes `oklab` de `.tipo-badge` se aproximaron con mezcla en RGB lineal (estimación, no medida en navegador).
- «Stone & ink» se aplica a los tokens semánticos existentes, no a una paleta paralela nueva.
- El modo oscuro sigue siendo conmutable y persistente; no se introduce un tercer modo «system» salvo decisión.
- La paleta de tipos Pokémon es un contrato visual de dominio y no se cambia salvo autorización.
- No se ejecutó build ni se renderizó la app; los hallazgos son estáticos.

## Preguntas bloqueantes

1. ¿El **combate/Bootstrap** entra en el alcance del rediseño o se deja para otra tarea? (afecta a `combate.css`, partials Livewire y `MovesPreviewPresenter`).
2. ¿El **acento `#A47750`** sustituye al azul como color primario (botones, enlaces, nav activo, foco) o solo se usa como acento decorativo? Si sustituye, ¿se acepta `on-primary` oscuro o un `accent-ink` más oscuro?
3. ¿Se **conserva la paleta neón de calidad IV** (incluido el fondo `#1e293b`) o se retematiza para Stone & ink?
4. ¿El **`muted #A69B8C`** es para texto secundario (obliga a oscurecerlo) o solo para bordes/decoración?
5. ¿Se añade un token **`secondary`** (`#8B88A3`) y se mapea a la `--bs-secondary` de Bootstrap?
6. ¿Se autoriza modificar **`src/.../Presentation`** y `src/Battle/Domain/CombatienteVista.php` para quitar clases Tailwind fijas?
7. ¿Se **retoca la paleta de tipos** (`TipoPokemon::color()`) para contraste en claro, o se mantiene y se cambia solo la fórmula del badge/texto?
8. ¿Se mantiene la clave `localStorage['theme']` y el comportamiento binario actual, o se añade modo «system» y sincronización?

## Recomendación

Adoptar la **opción 2** por fases: primero tokens y contraste (decidir `muted`, `on-primary`, `secondary`, foco e inputs), después puente Bootstrap/combate y limpieza de presentadores, y por último el hook de gráficos. Antes de diseñar valores definitivos, resolver las preguntas 1-5: condicionan el mapa completo y evitan un rediseño que siga fallando AA o que deje media app con la paleta antigua. No tocar los dos archivos con cambios locales; si sus tokens cambian, coordinar revisión visual con TASK-049/TASK-050.

---

# Subanálisis A — Página de combate (2026-11-17)

- **Responsable:** `analyst` (segunda pasada, solo lectura).
- **Origen:** reporte del desarrollador: «Combate se comporta de forma extraña... los enlaces pasan a tener underline, o desaparece el icono de plantas».

## Resumen

Dos síntomas, dos causas distintas:

- **Subrayado:** colisión de capas CSS entre Tailwind Preflight (`@layer base`) y Bootstrap Reboot (`@layer bootstrap`). Bootstrap se declara después de `base` y su regla `a { text-decoration: underline }` gana. Solo ocurre en combate porque `combate.css` —único portador de Bootstrap— se carga únicamente en `combate_page`.
- **Icono de plantas:** no hay ninguna regla CSS que oculte un icono de planta en combate. La explicación verificable es **markup**: el botón de cosecha (`fa-seedling`) no se renderiza en `/combate*` por `@if(!request()->is('combate*'))`. Existe una segunda candidata visual (el icono de tipo `planta` sobre una tarjeta del mismo color) que encaja solo si el desarrollador se refiere a los ataques.

## Causa raíz — subrayado

1. `resources/css/app.css:1` declara el orden: `@layer properties, theme, base, bootstrap, components, utilities;`
2. `resources/css/app.css:3` importa Tailwind, cuyo preflight vive en `base`: `node_modules/tailwindcss/index.css:633-636` → `a { color: inherit; text-decoration: inherit; }`.
3. `resources/css/combate.css:1` importa Bootstrap completo en la capa `bootstrap`: `@import "bootstrap/dist/css/bootstrap.min.css" layer(bootstrap);`
4. Bootstrap Reboot define `a { text-decoration: underline }` — `node_modules/bootstrap/scss/_reboot.scss:244-246`, con `$link-decoration: underline` (`_variables.scss:454`). En el artefacto: `bootstrap.min.css` contiene `a{color:...;text-decoration:underline}`.
5. Ambos selectores son `a` (misma especificidad). **Decide la capa**: `bootstrap` está después de `base` → gana Reboot.

Por qué **no** ocurre en el resto de la app: solo `resources/views/combate_page.blade.php:5-7` hace `@vite(['resources/css/combate.css'])`; es el único punto de entrada que carga Bootstrap. Elementos afectados: los 5 enlaces del navbar (`resources/views/layouts/app.blade.php:58-64`), que no llevan `no-underline`. Los `<a class="btn ...">` del modal de victoria (`resources/views/livewire/combate.blade.php:251,253`) no se subrayan porque `.btn` fija `text-decoration: none`.

## Causa raíz — icono de plantas

No existe en `combate.css` ninguna regla que oculte imágenes o iconos. Candidatos:

**A) Botón de cosecha `fa-seedling` del navbar — más probable.**
- `resources/views/layouts/app.blade.php:30` → `@if(!request()->is('combate*'))` envuelve el bloque 30-44; icono en `:41`.
- En `/combate` (`routes/web.php:28`) el botón **no se emite**: no es CSS, es Blade.
- Es el único icono de «plantas» fuera de combate. `git blame` lo sitúa en el commit `0317179` (2026-11-05), anterior al trabajo de tema: decisión preexistente y deliberada.
- Se descarta problema de glifo: `fa-seedling` existe en FA 6.7.2 y FontAwesome se importa sin capa.

**B) Icono de tipo `planta` en el panel de ataques.**
- `resources/views/livewire/partials/moves-panel.blade.php:38` pinta la tarjeta con `background-color: var(--type-...)` y `:46` inserta `<img src="/images/type/planta.webp" style="width:40px;height:40px">`.
- `--type-planta` = `#78C850` (`src/Shared/Tipos/TipoPokemon.php:81`); el asset es una ficha verde con hoja blanca: el disco verde se funde con el fondo de la tarjeta. No hay regla que lo oculte; es contraste color-sobre-color.
- Afecta a todos los tipos, no solo planta; visible solo en combate porque en Sendas/Equipos el icono va sobre superficie clara.

**C) Descartados:** sprites `x-sprite`, iconos de estado, iconos de objetos/bayas y barra de recompensa.

**Evidencia que falta:** confirmar si el icono ausente está en el navbar o en el panel de ataques, y si «desaparece» significa ausente del DOM o fundido con el fondo.

## Otras colisiones Bootstrap/Tailwind verificables

- **Tipografía:** Reboot fija `body { font-family: var(--bs-body-font-family) }` y gana al `font-family` de Preflight; solo en combate el stack empieza por `system-ui`.
- **`.card` impone color:** `_card.scss:33` → `color: var(--bs-body-color)`; en claro es `#212529` y solo se remapea en `.dark`.
- **`.border`:** `.border{border:…!important}` de Bootstrap gana a `border-line`; casos en `items-panel.blade.php:89,110` y `moves-panel.blade.php:51,116`. `.dark` no remapea `--bs-border-color`.
- **`.rounded`:** `preparacion-panel.blade.php:10` usa radio Bootstrap.
- **`text-muted`:** utilidad Bootstrap en `moves-panel.blade.php:27,150,155` y `battle-log.blade.php:8`.
- **Paleta semántica Bootstrap sin remapear:** `bg-primary` (`moves-panel.blade.php:19`, `_pokemon-card.blade.php:164`), `bg-info` (`:154`), `bg-success` (`combate.blade.php:173`); `.dark` no define `--bs-primary/--bs-info/--bs-success`.
- **Badges de presentadores:** `text-bg-*` y `text-decoration-line-through` dependen de Bootstrap.
- **Alerts:** no hay reglas `.alert` en `combate.css`; se pintan con la paleta clara de Bootstrap incluso en oscuro.
- **Formularios:** `.form-select` en `preparacion-panel.blade.php:13,24,68,91`.
- **Utilidades `!important`:** las de Bootstrap ganan a variantes responsivas de Tailwind (latente).

## Causa raíz vs síntoma

- Subrayado: **capas** (`bootstrap` tras `base`). El orden de `<link>` no es la causa.
- Icono: **markup** (condición Blade), no CSS. Si se confirma la candidata B, sería **color sobre color** (diseño).

## Opciones de corrección

1. **Hotfix CSS mínimo (recomendado para el subrayado ya):** añadir al final de `combate.css`, fuera de capas, `a { text-decoration: none; }`. Una declaración normal sin capa vence a cualquier capa. Alcance 1 regla; riesgo bajo. Efecto: en combate no habrá enlaces subrayados nunca.
2. **Hotfix markup:** añadir `no-underline` a los 5 `<a>` de `app.blade.php:58-64`. Cubre solo esos enlaces.
3. **Estructural:** compilar Bootstrap por SCSS con `$enable-reboot: false` (nueva entrada `combate.scss` + `sass` como dependencia) o importar solo componentes/utilidades. Elimina de raíz la competición de Reboot; requiere verificar `.btn`, `.form-select`, `.modal`, `.progress`. Riesgo medio. Encaja con el puente `--bs-*`→tokens de `combate.css:383-413`.
4. **No recomendado:** reordenar capas (`bootstrap` antes de `base` rompería componentes; después de `utilities`, las utilidades `!important` de Bootstrap pisarían Tailwind).
5. **Icono A (navbar):** decidir si el botón de cosecha debe existir en combate. Si sí, quitar `@if(!request()->is('combate*'))` y verificar que el `fetch` no interfiere con la cola de animación. Si debe seguir oculto, documentarlo.
6. **Icono B (tipo):** placa/anillo de superficie detrás del `<img>` o fondo de tarjeta mezclado (`color-mix`). Decisión de diseño que afecta a todos los tipos.

## Recomendación de secuencia

- **Antes del cambio de tema:** opción 1 (o 2) para el subrayado y decisión sobre el icono (5/6). Son independientes de los tokens y restauran el aspecto previo.
- **Junto al cambio de tema:** opción 3 (Reboot fuera / aislamiento) y puente `--bs-*`→tokens; tocan el mismo archivo, la misma declaración de capas y el mismo bloque `combate.css:383-413`.

## Evidencia

- `resources/css/app.css:1` — orden de capas con `bootstrap` entre `base` y `components`.
- `resources/css/combate.css:1` — Bootstrap completo importado en `layer(bootstrap)`.
- `resources/views/combate_page.blade.php:5-7` — único `@vite` de `combate.css`.
- `resources/views/layouts/app.blade.php:8,10` — `app.css` y luego `@stack('styles')`.
- `resources/views/layouts/app.blade.php:58-64` — enlaces del navbar sin `no-underline`.
- `node_modules/tailwindcss/index.css:633-636` — `a { text-decoration: inherit }` en `@layer base`.
- `node_modules/bootstrap/scss/_reboot.scss:244-246` — `a { text-decoration: underline }`.
- `node_modules/bootstrap/scss/_variables.scss:454,457` — `$link-decoration: underline`, `$link-hover-decoration: null`.
- `node_modules/bootstrap/dist/css/bootstrap.min.css` — regla `a` con `text-decoration:underline`.
- `node_modules/bootstrap/scss/_buttons.scss:32` — `.btn` fija `text-decoration: none`.
- `resources/views/layouts/app.blade.php:30-44,41` — bloque de cosecha excluido en `combate*`, icono `fa-seedling`.
- `routes/web.php:28` — ruta `/combate`.
- `resources/views/livewire/partials/moves-panel.blade.php:38,46` — fondo por tipo + `<img>` de tipo.
- `src/Shared/Tipos/TipoPokemon.php:81` — `PLANTA => '#78C850'`.
- `resources/views/livewire/partials/items-panel.blade.php:89,110,120` / `moves-panel.blade.php:51,116` — `border border-line`.
- `resources/css/combate.css:383-413` — dark `--bs-*` sin `--bs-border-color`, `--bs-primary`, `--bs-info`.
- `node_modules/bootstrap/scss/_card.scss:33` — `.card { color: var(--bs-body-color) }`.
- `resources/views/livewire/partials/preparacion-panel.blade.php:10,13,24,68,91` — `rounded`, `border-0`, `form-select`.
- `resources/views/livewire/partials/battle-field.blade.php:9` — `.alert-info` sin override oscuro.
- `resources/views/livewire/combate.blade.php:146,173,219,251,253` — `alert-success`, `bg-success`, `alert-warning`, `<a class="btn">`.

## Estado Git observado

Además de los dos archivos indicados, `git status` muestra `M resources/views/gimnasios/show.blade.php`. No lo ha tocado el análisis; conviene confirmar con el desarrollador antes de planificar.

---

# Subanálisis B — Livewire, CSS y JS de combate (2026-11-17)

- **Responsable:** `analyst` (tercera pasada, solo lectura).
- **Origen:** sospecha del desarrollador de que el comportamiento extraño de combate «era por Livewire».
- **Archivos modificados:** ninguno.

## Veredicto

| Síntoma | Livewire | Causa real |
|---|---|---|
| **Subrayado de enlaces** | **Irrelevante** | Colisión de capas CSS ya probada en Subanálisis A: Tailwind Preflight en `@layer base` vs Bootstrap Reboot en `@layer bootstrap` (`app.css:1`). Livewire no emite ningún CSS con reglas `a`. |
| **Desaparece icono de plantas** | **Irrelevante** | Markup condicional: `resources/views/layouts/app.blade.php:30` envuelve el bloque en `@if(!request()->is('combate*'))`. El navbar está fuera del componente Livewire; Livewire nunca lo morpha. |
| Comportamiento «extraño» general | **Coadyuvante menor** | Ausencia de `wire:key` en bucles filtrados de tarjetas/ataques + `wire:ignore.self` en modales que congela su cabecera. No explica ninguno de los dos síntomas reportados. |

**No hay doble inicialización de Livewire/Alpine.** `public/build/manifest.json` muestra que `app.js` y `combate.js` importan el mismo chunk compartido `_bootstrap-APkKWR_4.js`; un módulo ES se evalúa una sola vez por documento, por lo que `Livewire.start()` (`resources/js/bootstrap.js:23`) corre una única vez.

## Montaje de la página

- `routes/web.php:28` → closure que devuelve `view('combate_page')`. **No es un page component de Livewire.**
- `resources/views/combate_page.blade.php:1,14-16` — `@extends('layouts.app')`, `@section('content')` con `@livewire('combate')`. Página estática + **una isla Livewire** (`App\Livewire\Combate`).
- `resources/views/livewire/combate.blade.php:1-3` — raíz `<div>`; `wire:key="battle-{{ $battleId }}"` en el contenedor interno. Subvistas por `@include`.
- Assets:
  - `layouts/app.blade.php:8` → `@vite(['resources/css/app.css','resources/js/app.js'])` en `<head>`.
  - `layouts/app.blade.php:10` → `@stack('styles')`; `combate_page.blade.php:5-7` inyecta `combate.css`.
  - `layouts/app.blade.php:155` → `@stack('scripts')`; `combate_page.blade.php:9-12` inyecta `combate.js` y un `<script>` inline de 768 líneas (`partials/combate-scripts.blade.php`).
- **Islas Livewire:** `wire:ignore.self` en `battle-log-modal.blade.php:6` y `preparacion-modal.blade.php:6`. **No hay** `wire:navigate`, `wire:replace`, `@script` ni `@assets` en vistas. `wire:key` solo en raíz, `preparacion-panel` (5) y `reward-progreso`.
- `app/Livewire/Combate.php:77-81`: `render()` hace `->extends('layouts.app')->section('content')`; como componente anidado, es configuración muerta que duplica el layout, no causa doble `<html>` hoy.

## Ciclo de vida CSS/JS con Livewire

- CSS: `@vite` estático; Livewire no recarga CSS ni inyecta `<link>`. El CSS de combate se carga una vez por navegación completa, no por morph.
- JS: `app.js:1` importa `./bootstrap` (Livewire+Alpine) y Chart.js; `combate.js:1-3` importa `./bootstrap` y `bootstrap/dist/js/bootstrap.bundle.min.js`. El chunk compartido evita doble `Livewire.start()`.
- `<script>` inline de `combate-scripts.blade.php` se ejecuta al parsear `@stack('scripts')`; define funciones globales que consume `x-init` de `combate.blade.php:4-101`. Al no usar `wire:navigate`, nunca se re-ejecuta; el `x-init` de la raíz no se repite tras morph.
- `document.addEventListener('shown.bs.modal', ...)` en `combate-scripts.blade.php:759-767` — listener global único, sobrevive a morphs.

## Livewire y los dos síntomas

- **Icono:** el `fa-seedling` del navbar está fuera de Livewire; el bloque se emite solo si la ruta no es `combate*`. El segundo `fa-seedling` (`app.blade.php:123`) está en el toast, oculto por `x-show`. No participa.
- Riesgos Livewire reales no relacionados: bucles sin `wire:key` en `battle-field.blade.php:24,35,46,57` y `moves-panel.blade.php:34`; `wire:ignore.self` impide actualizar el título del modal de preparación; `reward-progreso` se reemplaza por `wire:key` md5, intencional.
- **Subrayado:** Livewire solo aporta un `<style>` inline con reglas `[wire:*]`, `[x-cloak]` y `:root`, sin `a`. La hipótesis «era por Livewire» es **falsa**; probablemente se asoció al navegar a `/combate`, que sí carga Bootstrap.

## Bootstrap JS

- Se carga solo en combate: `resources/js/combate.js:3`.
- Uso real: modales por atributos declarativos (`turn-bar.blade.php:27-28,53-54`, `battle-log-modal.blade.php:11,17`, `preparacion-modal.blade.php:11,21,23,26`) y listener `shown.bs.modal` (`combate-scripts.blade.php:759`).
- Bootstrap instala listeners delegados en `document`; sobreviven a morphs. No hay `new bootstrap.Modal(...)` ni `bootstrap.Dropdown` a mano.
- El modal de victoria (`combate.blade.php:125-128`) no usa el plugin: replica clases y controla visibilidad con `.modal-pendiente` (`combate.css:830-832`). Deuda: dos mecanismos de modal.
- `@popperjs/core` (`package.json:19`) es redundante: `bootstrap.bundle` ya incluye Popper.

## Duplicidades CSS/JS

- Bootstrap Reboot completo en combate: tipografía, `a`, `body`, headings, listas.
- Doble sistema de color: tokens Tailwind vs 83 hex en `combate.css` y `--bs-*` solo en `.dark` (`combate.css:383-413`); en claro Bootstrap usa su paleta por defecto.
- `.dark` declarado en tres sitios: `app.css:14`, `tokens.css:115-154`, `combate.css:383-384`.
- Específico de combate: `.pokemon-card`, `.battle-teams*`, `.vital-*`, `.move-card`, `.debug-tooltip*`, `.turn-ally/enemy`, `.active-turn`, `.weather-*`, animaciones, `.reward-progreso*`, `.modal-pendiente`.
- Genérico/movible a `components.css`: `.cursor-pointer`, `.cursor-not-allowed`, `.stage-up/.stage-down`, `.fin-batalla`, `.round-pulso`; `bg-hp-*` debería pasar a tokens.
- JS: `combate-scripts.blade.php` mezcla motor de animación, helper Alpine y listener Bootstrap. `combate.js` es trivial (2 imports); no hay duplicación con `app.js` salvo el import compartido.

## Arquitectura de unificación propuesta

**Objetivo:** una sola entrada JS (`app.js`) y una sola fuente de tokens, manteniendo el aislamiento de Bootstrap CSS mientras exista.

- **Fase 0 — hotfix independiente:** regla sin capa `a { text-decoration: none; }` o `no-underline` en los 5 enlaces del navbar. Cierra el síntoma ya.
- **Fase 1 — unificar JS sin dependencias nuevas:** eliminar `resources/js/combate.js` de `vite.config.js:12` y del `@push('scripts')`; en `app.js`, cargar Bootstrap bajo demanda si existe `.battle-container` con import dinámico; mover `combate-scripts.blade.php` a módulo ES (o mantenerlo inline como paso intermedio). Resultado: una sola entrada JS, un solo `Livewire.start()`, Bootstrap solo donde se usa.
- **Fase 2 — unificar CSS por tokens, sin tocar Bootstrap:** sustituir hex por tokens; puente completo `--bs-*` en ambos temas; mover lo genérico a `components.css`.
- **Fase 3 — eliminar Bootstrap:** requiere reescribir grid/utilidades/componentes. Inventario medido: `badge` 35, `d-flex` 43, `btn*` 12, `gap-*` 43, `mb-*` 27, `col-*` 12, `alert*` 9, `modal*` 15, `list-group*` 9, `progress*` 12, `form-select` 4, `spinner-border` 3, `small` 15, `text-muted` 6. Migración grande.

**Bloqueo:** unificar en una sola entrada CSS no es viable sin añadir `sass` (para compilar Bootstrap sin Reboot) o migrar los ~250 usos de clases Bootstrap. La unificación viable a corto plazo es **JS + tokens**; la CSS única exige decisión de producto.

## Riesgos de quitar Bootstrap de combate

- **[alto]** Reescritura incompleta de grid/utilidades: rotura del layout de 4 columnas (`battle-field.blade.php:19-64`), tarjetas y modales.
- **[alto]** Modales dependen de JS de Bootstrap (`data-bs-toggle`/`data-bs-dismiss`, `shown.bs.modal`); sin Bootstrap hay que migrar a `<dialog>` o `ui/modal`.
- **[medio]** `alert`, `progress`, `spinner-border`, `form-select`: hay equivalentes tokenizados pero el markup no los usa.
- **[medio]** `text-muted`, `text-truncate`, `visually-hidden`, `small`, `fw-*`: sustituibles, 15+ usos.
- **[bajo]** `text-bg-*` de badges generados por presentadores: hay que cambiar también el backend de presentación.

## Evidencia faltante

1. Consola del navegador en `/combate`: advertencias de Livewire, errores de morph, duplicados Alpine.
2. HTML tras morph: confirmar que el `@if` del navbar no se reevalúa.
3. Confirmación de qué icono es el ausente (cosecha del navbar vs tipo en panel de ataques).
4. Red/DevTools: un solo `<script>` de Livewire, sin auto-inyección adicional.
5. Confirmar que el manifest del entorno real coincide con el build leído.

## Rutas clave

- `resources/views/combate_page.blade.php:5-12` — assets por página.
- `resources/views/layouts/app.blade.php:8,10-11,30-44,58-64,155,157` — assets, condición del icono, enlaces, stacks.
- `resources/views/livewire/combate.blade.php:3-101,125-128` — raíz, `x-init`, modal victoria.
- `resources/css/app.css:1` — orden de capas; `combate.css:1` — Bootstrap en `layer(bootstrap)`.
- `resources/js/bootstrap.js:23` — `Livewire.start()`; `combate.js:2-3` — import de Bootstrap JS.
- `resources/views/livewire/partials/battle-field.blade.php:24,35,46,57` — bucles sin `wire:key`.
- `resources/views/livewire/partials/battle-log-modal.blade.php:6` — `wire:ignore.self`.
- `app/Livewire/Combate.php:77-81` — `extends()` inerte.
- `public/build/manifest.json` — chunk compartido `_bootstrap-*`.

## Recomendación

1. **Cerrar el diagnóstico:** Livewire **no** causa ni el subrayado ni la desaparición del icono; ambos tienen causa verificada (capas CSS y `@if` de ruta).
2. **Hotfix inmediato** del subrayado y **decisión de producto** sobre el icono de cosecha en combate.
3. **Unificar JS ya** (Fase 1): una sola entrada, import dinámico de Bootstrap, inline script → módulo. Bajo riesgo, sin dependencias.
4. **Unificar CSS solo hasta tokens y puente `--bs-*`** (Fase 2). La entrada CSS única queda bloqueada hasta decidir si se añade `sass` o se migra Bootstrap a Tailwind (Fase 3, alcance grande).
5. **Añadir `wire:key`** a tarjetas de `battle-field` y ataques de `moves-panel` como higiene Livewire independiente del rediseño.
