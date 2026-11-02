# TASK-009 — Arquitectura

- **Estado:** `COMPLETADO` (diseño)
- **Responsable:** `architect`
- **Fecha:** 2026-11-02
- **Base:** `analysis.md` (análisis A + B)
- **Alcance:** refactor visual completo + sistema de sprites 128/96/64.

> El arquitecto no modificó archivos ni ejecutó builds/tests.

## Decisión

Refactor **híbrido por fases** con una **capa de tokens semánticos** como única fuente de color/tipografía/radios/sombras, un **componente `sprite`** con contenedor de tamaño fijo y `object-contain`, y **Bootstrap confinado a la página de combate** (CSS en una capa de cascada propia + JS fuera de `app.js`). Las vistas se migran por módulos con archivos disjuntos: tokens → aislamiento Bootstrap → componentes → módulos de riesgo creciente → combate → limpieza. El theme futuro solo redefinirá `resources/css/tokens.css`.

## Contexto verificado

| Hecho | Evidencia |
|---|---|
| 44 vistas, 12.143 líneas; dos monolíticas: `equipos/index` 3.256 (359 `dark:`) y `habitats/show` 2.282 (359 `dark:`) | `wc -l` por vista |
| 142 `text-[10px]`, 1.586 `dark:`, 1.910 `class=` | recuento propio + análisis |
| Tailwind 4.3.3 (CSS-first, sin config), Bootstrap 5.3.8, Vite 7, 4 entradas | `node_modules/*/package.json`, `vite.config.js` |
| Bootstrap **solo** se carga en combate (`combate.css` → `bootstrap.min.css`, `combate_page:5-6`); **su JS sí es global** (`app.js:2`) | grep |
| `combate.js` reimporta `./bootstrap` + `bootstrap.bundle`; `app.js` y `combate.js` comparten el módulo `bootstrap.js` (`Livewire.start()`) | `resources/js/*` |
| No existe capa de tokens: solo `--quality-*` en `app.css:20-167` | `app.css` |
| 8 componentes Blade con 0 usos; único usado `<x-tipo-badge>` (1 vez, `exploraciones/_explorador:63`) | grep `<x-` |
| `components/tipo-badge.blade.php` y `partials/tipos-badge-script.blade.php` están **sin trackear** (WIP) | `git status` |
| Assets Pokémon nativos 128×128 (`iconos_webp/*.webp`, `shiny/*_s.webp`); `public/images/iconos/` **no existe**; `0.webp` sí existe | `ls public/images` |
| 34 renders de Pokémon; 3 a 384px (`habitats/show:319,731,1144`); 2 fallbacks a PNG inexistente (`pokedex:771,778`, `reclutado/show:14`) | análisis A |
| Colores de tipo en 3 representaciones: hex (`TipoPokemon::color()`), clases Tailwind (`TipoBadges::CLASES`), inline (`$move['tipoColor']`, `$tipo['color']`, `gym.tipo_color`) | grep |
| PHP emite **clases de estilo** al frontend: `hpClass`, `posturaClase`, `claseCarta`, `stage-up/down`, `bg-*` (`CombatienteVista:53,137,184,216`, `BatallaVistaPresenter:158`) | grep |
| `$cardPanelClass` duplicado literal en `habitats/show:8`, `gimnasios/admin:9`, `gimnasios/show:9` | grep |
| 17 modales `div fixed inset-0`; solo 5 con `role="dialog"`; 0 `focus-visible` | análisis B |

## Opciones evaluadas

1. **Tokens en un `tokens.css` separado + `@theme` en `app.css` + Bootstrap en capa** *(elegida)*. Una sola fuente, dark mode por redefinición de variables, Bootstrap sigue vivo pero subordinado, componentes adoptables por sustitución. Coste: 2 archivos CSS nuevos y 1 partial Blade.
2. **Todo dentro de `app.css`** (`@theme` + `:root` + `.dark`). Menos archivos, pero el archivo pasa de 174 a ~400 líneas mezclando tema, calidad IV y overrides de Bootstrap; el theme futuro se vuelve un diff grande en un archivo compartido por todos los módulos (conflicto en WIP).
3. **Converger a Tailwind y eliminar Bootstrap**. Más limpio a largo plazo, pero exige rehacer 9 vistas de combate, el modal `data-bs-*`, el `progress` animado y 103 reglas de `combate.css`. **Descartada en esta tarea** por riesgo de regresión y por restricción explícita.
4. **Reescribir las 12k líneas de una vez sobre componentes nuevos**. Descartada: WIP de 305 archivos, 2 vistas monolíticas, imposible atribuir regresiones.
5. **Fuente de color de tipos en CSS estático y deprecar `TipoPokemon::color()`**. Duplicaría hex hasta limpiar backend (`ArbolSendas`, `MovesPreviewPresenter`, `GimnasioResumen/Detalle`) y rompe contratos DTO. **Descartada ahora** (queda como decisión pendiente).

## 1. Resumen de la solución

- **Un tema, tres consumidores:** `resources/css/tokens.css` define valores en `:root` y `.dark`; Tailwind los expone como utilidades (`bg-surface`, `text-ink`, `border-line`, `text-2xs`, `rounded-card`); Bootstrap los consume vía el puente `--bs-* → var(--color-*)`; el CSS propio (`app.css`, `combate.css`, `components.css`) los consume directamente.
- **Dark mode deja de ser clases:** `.dark` redefine variables, no utilidades. Los `1.586 dark:` desaparecen por módulo al migrar; el criterio estático de cada fase es `dark: == 0` (con excepciones justificadas).
- **Sprite único:** `<x-sprite>` con `aspect-ratio: 1`, `width: var(--sprite-size)`, `max-width: 100%`, `img` interno `object-contain`, `width/height="128"` intrínsecos y fallback `shiny → normal → 0.webp`. La escala 128/96/64 es un **token**, no un número por vista; los casos dudosos se resuelven con tokens (`--sprite-combat`, `--sprite-inline`) o con el degradado natural `max-width:100%` (Pokédex 9 columnas).
- **Bootstrap aislado:** CSS en `@layer bootstrap` (declarada antes de Tailwind ⇒ pierde frente a utilidades), JS fuera de `app.js`, y regla de vocabulario: ningún elemento nuevo mezcla `p-*`/`mb-*` de Bootstrap con Tailwind. En Fase 5 se elimina la mezcla existente.
- **Color de tipos:** `TipoPokemon::color()` sigue siendo la única fuente de hex; un partial emite `--type-*` en `:root`; CSS deriva `soft/ink` con `color-mix()`. `TipoBadges::CLASES` se mantiene durante la migración y se retira al final.
- **Trabajo por archivos disjuntos:** cada fase declara archivos exactos y owner único; las dos vistas monolíticas son exclusivas y se migran con «extraer al tocar».

## 2. Arquitectura de tokens

### 2.1 Ubicación y mecánica

```text
resources/css/
├─ tokens.css        (NUEVO)  ← ÚNICA fuente de valores de tema
│   ├─ @theme static { ... }        valores claros + mapeos estáticos (--color-type-*: var(--type-*))
│   ├─ :root { --type-*: ... }      NO: los --type-* los emite Blade (ver 2.4)
│   ├─ .dark { ... }                overrides de modo oscuro
│   └─ @theme { --text-2xs; --radius-*; --shadow-*; --spacing-*; --font-* }
├─ components.css    (NUEVO)  @layer components → .sprite, .tipo-badge, .focus-ring, .card-surface, .sprite-stack
├─ app.css           (MOD)    @layer bootstrap; @import tailwindcss; @import tokens; @import components; @source; calidad IV
└─ combate.css       (MOD)    @import bootstrap ... layer(bootstrap); puente --bs-*; animaciones
```

**Por qué `@theme static`:** garantiza que *todas* las variables se emitan aunque una utilidad no las use todavía; evita que un override `.dark` apunte a una variable no generada.

**Por qué `.dark` va sin capa:** una declaración normal sin capa gana a cualquier declaración normal en capa (`@theme` emite en `@layer theme`). Así `.dark { --color-surface: … }` sobrescribe sin `!important` ni especificidad extra.

**Por qué NO se toca `--spacing`:** los 1.910 `class=` dependen de la escala numérica de Tailwind. Solo se **añaden** claves semánticas (`--spacing-gutter`), que generan utilidades nuevas (`gap-gutter`, `p-gutter`) sin alterar `p-4`.

### 2.2 Taxonomía

| Token (`tokens.css`) | Rol | Claro | Oscuro | Consumidor Tailwind | Consumidor CSS/Bootstrap |
|---|---|---|---|---|---|
| `--color-canvas` | fondo de página | `#f9fafb` | `#111827` | `bg-canvas` | `--bs-body-bg`, `body` |
| `--color-surface` | tarjeta/panel | `#ffffff` | `#1f2937` | `bg-surface` | `--bs-card-bg`, `--bs-tertiary-bg`, `.sprite` bg opcional |
| `--color-surface-raised` | modal/popover | `#ffffff` | `#1f2937` | `bg-surface-raised` | `--bs-modal-bg`, `--bs-body-bg` de modal |
| `--color-surface-sunken` | pozo/input/hueco | `#f3f4f6` | `#111827` | `bg-surface-sunken` | `--bs-secondary-bg`, `--bs-list-group-bg` |
| `--color-line` | borde por defecto | `#e5e7eb` | `#374151` | `border-line` | `--bs-border-color`, `--bs-card-border-color` |
| `--color-line-strong` | borde énfasis/hover | `#d1d5db` | `#4b5563` | `border-line-strong` | `--bs-border-color-translucent` |
| `--color-ink` | texto primario | `#111827` | `#f9fafb` | `text-ink` | `--bs-body-color`, `--bs-card-color` |
| `--color-ink-muted` | texto secundario | `#6b7280` | `#9ca3af` | `text-ink-muted` | `--bs-secondary-color` |
| `--color-ink-subtle` | placeholder/terciario (**no AA para texto esencial**) | `#9ca3af` | `#6b7280` | `text-ink-subtle` | `::placeholder` |
| `--color-ink-inverse` | texto sobre superficie oscura | `#ffffff` | `#111827` | `text-ink-inverse` | — |
| `--color-primary` | relleno de acción | `#2563eb` | `#2563eb` | `bg-primary` | `--bs-primary`, `--bs-link-color` |
| `--color-primary-hover` | hover del relleno | `#1d4ed8` | `#3b82f6` | `hover:bg-primary-hover` | `--bs-primary-hover` |
| `--color-primary-soft` | tinte de fondo | `color-mix(in oklab, var(--color-primary) 12%, var(--color-surface))` | ídem | `bg-primary-soft` | `--bs-primary-bg-subtle` |
| `--color-primary-ink` | texto azul sobre canvas | `#1d4ed8` | `#93c5fd` | `text-primary-ink` | `--bs-link-color`, `--bs-link-hover-color` |
| `--color-on-primary` | texto sobre `primary` | `#ffffff` | `#ffffff` | `text-on-primary` | `--bs-btn-color` |
| `--color-success` / `-soft` / `-ink` | éxito | `#16a34a` / mix 12% / `#15803d` | `#22c55e` / mix / `#86efac` | `bg-success`, `text-success-ink`, `bg-success-soft` | `--bs-success`, `--bs-alert-*` de `.alert-success` |
| `--color-warning` / `-soft` / `-ink` | aviso | `#d97706` / mix / `#b45309` | `#f59e0b` / mix / `#fcd34d` | `bg-warning`, `text-warning-ink` | `--bs-warning`, `.alert-warning` |
| `--color-danger` / `-soft` / `-ink` | peligro/error | `#dc2626` / mix / `#b91c1c` | `#ef4444` / mix / `#fca5a5` | `bg-danger`, `text-danger-ink` | `--bs-danger`, `.alert-danger`, `hp-low` |
| `--color-info` / `-soft` / `-ink` | información | `#0284c7` / mix / `#0369a1` | `#38bdf8` / mix / `#7dd3fc` | `bg-info`, `text-info-ink` | `--bs-info`, `.alert-info` |
| `--color-focus` | anillo de foco | `var(--color-primary)` | `var(--color-primary)` | `outline-focus`, `ring-focus` | `.focus-ring`, `:focus-visible` |
| `--color-type-{18}` | color de tipo (mapeo) | `var(--type-{slug})` | ídem | `bg-type-fire`, `text-type-fire`, `border-type-fire` | `.tipo-badge` |
| `--text-2xs` + `--text-2xs--line-height` | 10px / 0.875rem | fijo | fijo | `text-2xs` (sustituye 142 `text-[10px]`) | — |
| `--text-3xs` | 11px (uso puntual badges) | fijo | fijo | `text-3xs` | `.tipo-badge` |
| `--radius-control` | input/botón | `0.5rem` | fijo | `rounded-control` | `--bs-border-radius` |
| `--radius-card` | tarjeta/panel | `0.75rem` | fijo | `rounded-card` | `--bs-card-border-radius` |
| `--radius-panel` | modal/sección | `1rem` | fijo | `rounded-panel` | `--bs-modal-border-radius` |
| `--radius-pill` | píldora | `9999px` | fijo | `rounded-pill`* | `.badge` |
| `--shadow-card` | sombra tarjeta | `0 1px 2px rgb(0 0 0/.05)` | igual + borde | `shadow-card` | `--bs-box-shadow-sm` |
| `--shadow-modal` | sombra modal | `0 10px 30px rgb(0 0 0/.12)` | `0 10px 30px rgb(0 0 0/.5)` | `shadow-modal` | `--bs-modal-box-shadow` |
| `--spacing-gutter` | separación de rejilla | `0.75rem` | fijo | `gap-gutter`, `p-gutter` | `--bs-gutter-x` (combate) |
| `--spacing-section` | separación entre bloques | `1.5rem` | fijo | `space-y-section` | — |
| `--sprite-128/96/64/inline` | tamaños de visualización | `8rem/6rem/4rem/2rem` | fijo | — (solo CSS) | `.sprite--128…` |
| `--sprite-combat` | densidad de combate | `8rem` | fijo | — | `.sprite--combat` |

\* `--radius-pill` colisiona con el namespace de Tailwind; se declara como `--radius-pill: 9999px` y se usa `rounded-pill` (Tailwind ya lo genera). Si se prefiere evitar solapamiento, usar `--radius-full`.

**Regla de oro:** ningún archivo de vista define un color. Un `#hex` en `resources/views/**` es deuda (excepto `style="width: …%"` de progreso).

### 2.3 Modo oscuro

```css
/* tokens.css — forma, no implementación completa */
@theme static { --color-canvas: #f9fafb; --color-surface: #ffffff; /* … claros … */ }
.dark        { --color-canvas: #111827; --color-surface: #1f2937; /* … oscuros … */ }
```

- Sin `!important`, sin `.dark body { … }`, sin duplicar la lista de tokens.
- `color-scheme: dark` se mantiene en `@custom-variant`/`.dark`.
- El toggle (`layouts/app:115-120`) se conserva; solo se elimina la duplicación muerta en `components/header:18-28` cuando ese componente se retire.
- **Theme alternativo futuro (F6):** se añade `[data-theme="x"] { --color-*: … }` en `tokens.css`; `git diff --stat resources/views` debe quedar vacío. Ese es el criterio de aceptación del theme.

### 2.4 Colores de tipo (fuente única)

Flujo elegido, sin duplicar hex:

```text
TipoPokemon::color()  (PHP, única fuente de hex)
        │
        ├─ partials/tipos-tokens.blade.php  →  <style>:root{--type-normal:#A8A878;…}</style>  (1 inclusión por layout)
        │
        ├─ TipoBadges::paraJs()  →  window.TIPOS_BADGE[slug].color  (Alpine: --type-color inline)
        │
        └─ tokens.css  @theme { --color-type-normal: var(--type-normal); … }   (mapeo estático de 18 líneas)

CSS: .tipo-badge { background: color-mix(in oklab, var(--type-color) 14%, var(--color-surface));
                   color: var(--type-color); border-color: color-mix(...); }
```

- `partials/tipos-tokens.blade.php` se incluye en `layouts/app` y `layouts/auth` (una línea en `<head>`), por lo que toda vista (incluida combate) recibe las variables.
- `TipoBadges` **no** cambia de firma pública; se **añade** `color` al array y se mantiene `clases` (deprecado) hasta F6. `@source "../../src"` se mantiene hasta F6 porque las clases de `TipoBadges::CLASES` se generan desde PHP.
- `$move['tipoColor']` (`moves-panel:29`) pasa a `style="--type-color: {{ $move['tipoColor'] }}"` + `.tipo-badge` (F5).
- `$tipo['color']` de `sendas/index:27,39,43` y `gym.tipo_color` de `gimnasios/*` se sustituyen por `var(--type-<slug>)` vía clase/`style="--type-color: …"` **sin tocar los DTOs** (los DTOs ya llevan `tipo_slug`). `ArbolSendas` y `GimnasioResumen` no se modifican en esta tarea.
- `calidadIvs()` de `equipos/index:1554-1589` deja de replicar hex: devuelve solo la `clave` y lee colores con `getComputedStyle(document.documentElement).getPropertyValue('--quality-color-<clave>')` (tokens de calidad en `:root`). Chart.js no acepta `var()`, por eso se leen resueltos.

## 3. Contratos de componentes Blade

**Ubicación propuesta:** `resources/views/components/ui/` para los 8 base + `components/sprite.blade.php` + `components/tipo-badge.blade.php` (existente). Los 8 componentes muertos actuales se **reutilizan como punto de partida** (se reescriben sobre tokens) para no duplicar namespaces.

### 3.1 Tabla de contratos

| Componente | Props (`@props`) | Variantes / estados | Accesibilidad (reglas duras) |
|---|---|---|---|
| `ui.button` | `variant='primary'`, `size='md'`, `type='button'`, `href=null`, `disabled=false`, `loading=false`, `icon=null` | `primary`/`secondary`/`ghost`/`danger`/`success`; `sm`/`md`/`lg`; `loading` | `<button type>` explícito (nunca submit implícito); si `href` → `<a role>`; `disabled` + `aria-disabled` en `<a>`; `loading` ⇒ `aria-busy="true"` y ancho estable; icono solo ⇒ `aria-label` obligatorio; `focus-visible` visible |
| `ui.input` | `name`, `label=null`, `type='text'`, `value=null`, `hint=null`, `error=null`, `required=false` | texto/email/password/number/search; `error` | `<label for>`; `id` autogenerado; `aria-describedby` a hint/error; `aria-invalid="true"` si error; `required` + `aria-required`; el error se anuncia (`role="alert"`) |
| `ui.select` | `name`, `label=null`, `error=null`, `required=false`, `placeholder=null` | nativo; opciones por **slot** (sin array como contrato) | Igual que `input`; `aria-describedby`; foco visible; sin `form-select` de Bootstrap |
| `ui.card` | `title=null`, `subtitle=null`, `padding='md'`, `as='div'` | `none`/`sm`/`md`; slots `default` y `footer` | Si `title` ⇒ `aria-labelledby` con id autogenerado; `as="section"` para regiones; sin `role` decorativo |
| `ui.badge` | `variant='neutral'`, `size='sm'`, `type=null` | `neutral`/`primary`/`success`/`warning`/`danger`/`info`; `type` delega en `tipo-badge` | Texto, nunca solo color (WCAG 1.4.1); contraste AA sobre `-soft`; `title` opcional no sustituye al texto |
| `ui.alert` | `variant='info'`, `title=null`, `dismissible=false` | info/success/warning/danger | `role="alert"` solo danger/warning; `role="status"` + `aria-live="polite"` en el resto; botón cerrar con `aria-label="Cerrar"`; foco no se roba |
| `ui.modal` | `name` (id Alpine), `title`, `size='md'`, `open=false` | `sm`/`md`/`lg`/`xl`/`full` | `<dialog>` nativo (recomendado) ⇒ foco atrapado + Escape gratis; `aria-labelledby` al título; `aria-modal`; retorno de foco al disparador; `::backdrop` tokenizado; si se rechaza `<dialog>`, trap manual sin dependencias |
| `ui.empty-state` | `title`, `description=null`, `icon=null` | con/sin acción (slot `action`) | Texto real, no `text-[10px]`; icono `aria-hidden`; contenedor `role="status"` si sustituye a una lista cargada |
| `tipo-badge` (existente) | `tipo`, `slug`, `label`, `size='md'` | `xs`/`sm`/`md` | Icono `alt=""` (decorativo, el texto da el nombre); color **no** es el único portador de información; contraste del texto sobre `-soft` |
| `sprite` | ver §4 | ver §4 | ver §4 |

### 3.2 Reglas transversales de accesibilidad

1. **Foco:** todo interactivo usa `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus` (clase `.focus-ring`). Prohibido `outline-none` sin reemplazo.
2. **Contraste AA (objetivo, validar con herramienta al implementar):** `ink`/`surface` ≈16.5:1; `ink-muted`/`surface` ≈4.6:1; `primary-ink`/`surface` ≈4.5:1; `on-primary`/`primary` ≈5.2:1; `ink-subtle`/`surface` ≈2.7:1 ⇒ **solo placeholders y texto no esencial**.
3. **Modales:** `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, Escape, foco inicial dentro, foco devuelto al disparador, scroll de fondo bloqueado.
4. **Iconos:** decorativos `aria-hidden="true"` + `alt=""`; informativos con `alt`/`aria-label`.
5. **Movimiento:** transiciones bajo `@media (prefers-reduced-motion: reduce)`.
6. **Tamaño de texto:** `text-2xs` (10px) se conserva por paridad visual, pero **no se usa para información esencial ni para acciones**; se documenta como deuda de accesibilidad (F6/theme).

### 3.3 Ejemplo de firma (no implementación)

```blade
{{-- resources/views/components/ui/button.blade.php --}}
@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'href' => null, 'disabled' => false, 'loading' => false])
{{-- match($variant) → clases de token; match($size) → padding/texto; sin hex, sin text-[..] --}}
```

## 4. Componente `sprite` y mapeo de tamaños

### 4.1 Contrato

```blade
{{-- resources/views/components/sprite.blade.php --}}
@props([
    'id'         => null,   // int|string: especie (construye la URL)
    'src'        => null,   // URL explícita; tiene prioridad sobre id
    'name'       => '',     // alt/title
    'shiny'      => false,  // usa /shiny/{id}_s.webp con fallback a normal
    'suffix'     => '',     // formas regionales (id + sufijo)
    'size'       => '96',   // 128 | 96 | 64 | inline | combat
    'loading'    => 'lazy', // eager | lazy
    'priority'   => false,  // fetchpriority="high"
    'decorative' => false,  // alt="" + aria-hidden
])
```

**Markup (contrato):**

```html
<span class="sprite sprite--{size}" style="--sprite-size: …">
  <img src="…" alt="{name|''}" width="128" height="128"
       loading="{loading}" decoding="async" fetchpriority="{high|auto}"
       class="sprite__img" onerror="…fallback…">
</span>
```

**CSS (contrato):**

```css
.sprite { display:inline-block; aspect-ratio:1; width:var(--sprite-size); max-width:100%; flex-shrink:0; }
.sprite__img { display:block; width:100%; height:100%; object-fit:contain; }
.sprite--128{--sprite-size:var(--sprite-128)} .sprite--96{--sprite-size:var(--sprite-96)}
.sprite--64{--sprite-size:var(--sprite-64)}   .sprite--inline{--sprite-size:var(--sprite-inline)}
.sprite--combat{--sprite-size:var(--sprite-combat)}
```

- `width/height="128"` = intrínsecos del asset: reservan proporción y evitan CLS; el tamaño visual lo fija el CSS.
- `max-width:100%` + `aspect-ratio` resuelven la rejilla Pokédex sin variante extra.
- **Fallback único:** `shiny_s → {id}.webp → 0.webp`; nunca `/images/iconos/*.png` (directorio inexistente). En PHP se calcula la cadena `onerror`; no se aceptan 4 estrategias distintas.
- **Lazy/eager:** `eager` + `priority` en la vanguardia de cada lado del combate y en el retrato principal de un detalle; `lazy` en rejillas, listas y modales cerrados.
- **Alt:** por defecto `name`; `decorative` cuando el nombre ya está visible adyacente (evita duplicado en lector de pantalla). En Pokédex, si el nombre está oculto, `alt` obligatorio.
- `object-contain` siempre; prohibido `object-cover` y `w-full h-full` sin contenedor de proporción.

### 4.2 Mapeo contexto → tamaño

| Contexto | Archivo:línea | Hoy | Destino | Modo / nota |
|---|---|---|---|---|
| Combate · tarjeta | `livewire/_pokemon-card:110` | 72 inline | **128** | `.sprite--combat`; `eager`+`priority` solo vanguardia; resto `lazy` |
| Combate · chips postura | `partials/preparacion-panel:27` | 18 | `inline` (32) | Micro-icono de fila; `alt` con nombre (hoy `alt=""` con `title`) |
| Combate · prioridad | `partials/preparacion-panel:50` | 32 | **64** | — |
| Combate · orden de turno | `partials/turn-bar:7` | 20 | `inline` | Dentro de píldora; no escala |
| Combate · capturas victoria | `livewire/combate:162-170` | 64 | **64** | Ya correcto; unificar fallback |
| Exploraciones · evento | `_evento:28,40,196` | 40 | **64** | Fila de diario |
| Exploraciones · pila salvajes | `_evento:66,100` | 32 | `inline` (32) | `.sprite-stack` conserva `-space-x-2` y añade `ring-2 ring-surface`; no subir a 64 |
| Exploraciones · explorador | `_explorador:32-37` | 48 | **64** | Badge shiny encima |
| Exploraciones · derrotados | `index:274` | 64 | **64** | `grayscale opacity-50` |
| Exploraciones · capturados | `index:310` | 64 | **64** | — |
| Equipos · slots | `equipos/index:112-118` | `w-full h-16` | **96** | `aspect-ratio` sube la altura de 64 a 96: cambio visual aceptado |
| Equipos · inventario | `equipos/index:346-352` | 96 | **96** | Contenedor 96 |
| Equipos · huevos incubando | `equipos/index:534` | 48 | **64** | Lista |
| Equipos · huevos disponibles | `equipos/index:584` | 64 | **96** | Rejilla de selección |
| Equipos · modal reemplazo | `equipos/index:825` | 48 | **64** | Fila botón |
| Equipos · detalle | `equipos/index:867` | 96 | **128** | Retrato principal; `eager` |
| Equipos · evolución | `equipos/index:938` | 48 | **96** | Rejilla |
| Pokédex · rejilla | `pokedex/index:202-208` | fluido | **96** | `max-width:100%` lo reduce a ~81px en `lg:grid-cols-9`; sin desborde ni cambio de columnas |
| Pokédex · modal | `pokedex/index:295` | 96 | **128** | `eager` al abrir |
| Pokédex · familia | `pokedex/index:388` | 32 | **64** | Mini-lista |
| Reclutamiento · rejilla | `reclutamiento/index:33` | 128 | **128** | 9 columnas a 2xl ≈158px: cabe |
| Reclutado · detalle | `reclutado/show:10` | 96 | **128** | Corregir fallback PNG |
| Reclutado · evolución | `reclutado/show:33` | 64 | **64** | — |
| Gimnasios · formación | `gimnasios/show:193` | 48 | **64** | — |
| Gimnasios admin · selector | `gimnasios/admin:223` | 48 | **96** | Rejilla de selección |
| Hábitat · miembros en card | `habitats/show:238` | `w-full` sin alto | **96** | Elimina CLS (hoy la altura la fija la imagen) |
| Hábitat · favoritos | `habitats/show:319` | **384** | **96** | Realezo 3× eliminado |
| Hábitat · lista por nivel | `habitats/show:405` | 96 | **96** | — |
| Hábitat · rejilla especies | `habitats/show:677` | 64 | **96** | — |
| Hábitat · formación equipo | `habitats/show:731` | **384** | **64** | Fila |
| Hábitat · familia | `habitats/show:847` | fluido `aspect-square` | **96** | — |
| Hábitat · miembros familia | `habitats/show:903` | fluido `aspect-square` | **96** | — |
| Hábitat · mazmorra | `habitats/show:1056` | 48 | **64** | — |
| Hábitat · granja (hero) | `habitats/show:1144` | **contenedor 384** | **128** | Único 384 legítimo como hero; se baja a 128 (máximo nativo) |
| Legado | `reclutados/index:82` | CSS muerto | **excluir** | Vista no enrutada; decisión pendiente |

**Casos ambiguos resueltos:** combate 128 con token `--sprite-combat` (cambiar a 96 es una línea en `tokens.css`, sin vistas); Pokédex 9 columnas con `max-width:100%`; pila de salvajes en 32 (`inline`); micro-iconos ≤32 fuera de escala; huevos 64/96.

## 5. Bootstrap/Tailwind y color de tipos

### 5.1 Estrategia de convivencia

**Regla de vocabulario (obligatoria a partir de F3):** una superficie usa *un* sistema de utilidades.
- Vistas normales: solo Tailwind + tokens.
- Combate: Bootstrap para estructura/componentes (`card`, `row/col`, `modal`, `progress`, `badge bg-*`) y Tailwind solo para lo que Bootstrap no cubre, **sin repetir la misma propiedad** (`p-3` Bootstrap + `p-4` Tailwind en el mismo nodo = prohibido).
- Componentes Blade nuevos: **nunca** emiten clases Bootstrap.

**Aislamiento CSS por capas:**

```css
/* app.css (primera hoja cargada en el documento) */
@layer bootstrap;              /* declara el orden: bootstrap < theme < base < components < utilities */
@import "tailwindcss";
@import "./tokens.css";
@import "./components.css";

/* combate.css (hoja cargada solo en combate, después de app.css) */
@import "bootstrap/dist/css/bootstrap.min.css" layer(bootstrap);
```

- Con Bootstrap en la primera capa, **las utilidades Tailwind ganan** a Reboot y a las utilidades Bootstrap. Esto elimina los parches `a { color: inherit }`, `.dark header { … !important }` y `.dark nav a { … }`.
- **Límite conocido:** las utilidades Bootstrap llevan `!important`; en `!important` el orden de capas se invierte, así que `.bg-white{…!important}` seguiría ganando a `dark:bg-gray-900`. Por eso la solución no es la capa sola: es **quitar la mezcla** en F5. Mientras exista, se mantiene una lista de excepciones documentada en `combate.css`.
- **Riesgo de Reboot vs Preflight:** al capar Reboot, Preflight gana (márgenes de `<p>`/`<ul>`). Es el cambio más sensible de F2b; se prueba visualmente en combate y el rollback es una línea (volver a `@import "bootstrap/…"` sin `layer()` y restaurar los 3 parches). **Requiere aprobación.**
- **Verificación técnica pendiente:** confirmar que `@tailwindcss/vite` preserva `layer(bootstrap)` en el `@import`. Si lo elimina, se cae al plan B (sin capa + parches explícitos) y se documenta.

**Aislamiento JS:**

| Acción | Archivo | Efecto |
|---|---|---|
| Quitar `import 'bootstrap/dist/js/bootstrap.bundle.min.js'` | `resources/js/app.js:2` | Deja de enviarse Bootstrap JS en 43 vistas |
| Mantener el import | `resources/js/combate.js:3` | Único consumidor; `bootstrap.js` (Livewire+Alpine) se comparte por dedupe ESM |
| No tocar | `resources/js/bootstrap.js` | `Livewire.start()` sigue una vez por página (mismo módulo) |

- `bootstrap.js` importa `Livewire`/`Alpine`; `combate.js` y `app.js` lo importan → **mismo URL de módulo**, el navegador lo evalúa una vez. En build, Rollup emite chunk compartido. Se verifica en F2 comprobando `window.bootstrap` indefinido en página no-combate y modal `data-bs-*` operativo en combate.
- Regla nueva: `data-bs-*` solo puede aparecer en `resources/views/livewire/**` y `combate_page.blade.php`.

### 5.2 Puente de tokens a Bootstrap (`combate.css`)

Bloque **único**, válido para claro y oscuro (resuelve solo al cambiar las variables):

```css
:root, .dark {
  --bs-body-bg: var(--color-canvas);
  --bs-body-color: var(--color-ink);
  --bs-secondary-bg: var(--color-surface-sunken);
  --bs-tertiary-bg: var(--color-surface-sunken);
  --bs-secondary-color: var(--color-ink-muted);
  --bs-card-bg: var(--color-surface);
  --bs-card-color: var(--color-ink);
  --bs-card-border-color: var(--color-line);
  --bs-list-group-bg: var(--color-surface);
  --bs-list-group-color: var(--color-ink);
  --bs-list-group-border-color: var(--color-line);
  --bs-border-color: var(--color-line);
  --bs-link-color: var(--color-primary-ink);
  --bs-link-hover-color: var(--color-primary-hover);
}
```

Se elimina el bloque `.dark { --bs-*: #hex }` de `combate.css:176-206` (20+ hex) y los overrides `.dark .modal-content/.modal-header/.btn-close` se reescriben con tokens. Las alertas Bootstrap (`.alert-warning`, `.alert-success`) reciben mapeo por variante (`--bs-alert-bg/-color/-border-color`) porque Bootstrap las compila con valores fijos.

**Hex propios de `combate.css` (≈40) → tokens:** `.bg-hp-low/mid/high` → `--color-danger/warning/success`; `.stage-up/down` → `success-soft/danger-soft`; `.weather-*` → nueva familia `--color-weather-*` (6 tokens en `tokens.css`, mismo tratamiento que los tipos); `.pokemon-card` fondos → `surface`; vanguardia/rival → `--color-team-player` / `--color-team-rival` (2 tokens); `.targeted-card` → `color-mix` de `danger`. Las animaciones (103 reglas) **no se tocan**.

### 5.3 Estado de colores dinámicos PHP/JS

| Fuente | Hoy | Destino | Riesgo |
|---|---|---|---|
| `$p['hpClass']` (`CombatienteVista:53`) | `bg-hp-high`… | Mantener **nombre de clase estable** `hp-high`… definido en CSS con tokens | Cambio coordinado PHP↔CSS en F5 |
| `$p['posturaClase']`, `$p['claseCarta']`, `$p['stage.clase']` | clases Bootstrap/Tailwind | Clases semánticas nuevas (`posture-*`, `team-*`, `stage-up`) | F5, con tabla de mapeo por presentador |
| `$tipo['color']`, `gym.tipo_color` | hex inline | `style="--type-color: var(--type-<slug>)"` o clase | F4b/F4c |
| `calidadIvs()` JS | hex duplicados | `getComputedStyle` de `--quality-color-*` | F4e |
| Chart.js `#3b82f6`/`#10b981` | hex | `var()` resuelto al render | F4e |

**Alternativa descartada ahora:** cambiar los presentadores para emitir estados (`hp: 'low'`) en lugar de clases. Es más limpio, pero toca `src/Battle/Domain` y `src/Exploraciones/Presentation` en pleno WIP. Se propone como deuda posterior al theme.

## 6. Fases de migración

Estructura de commits: **un commit por fase y por módulo**, mensajes `refactor(ui): fase X — <archivo/módulo>`. Nada de `git push`.

| Fase | Archivos exactos | Owner | Depende de | Paraleliza con | Criterio estático |
|---|---|---|---|---|---|
| **F0 · Baseline y contención** (S, bloqueante) | — (solo git) | `orchestrator` | — | — | `git status --porcelain` vacío; commit WIP propio; `components/tipo-badge` y `partials/` trackeados; rama de trabajo creada desde ese commit |
| **F1 · Tokens** (M, serial) | crear `resources/css/tokens.css`, `resources/css/components.css`, `resources/views/partials/tipos-tokens.blade.php`; mod `resources/css/app.css` (imports + `@layer bootstrap;` + quitar `.dark body !important`); mod `layouts/app:8-11` y `layouts/auth:8-11` (include del partial) | `frontend-tokens` | F0 | F2a, F3 (solo si no tocan CSS) | 0 cambios de clase en vistas (excepto 2 includes); `grep -c "#[0-9a-fA-F]\{6\}" resources/css/tokens.css` = solo valores de `:root/.dark`; `grep -n "!important" resources/css/app.css` sin el hack de body |
| **F2a · Bootstrap JS** (S) | mod `resources/js/app.js` (quitar línea 2) | `frontend-js` | F0 | F1, F3 | `grep -c "bootstrap.bundle" resources/js/app.js` = 0; `combate.js` = 1; `grep -rn "data-bs-" resources/views` solo en `livewire/**` y `combate_page` |
| **F2b · Bootstrap CSS en capa** (S, tras F1) | mod `resources/css/combate.css:1` (`layer(bootstrap)`); eliminar `:223` (`a{}`), `:230-232` (`.dark header`), `:237-246` (`.dark nav a`) | `frontend-js` | F1 | F3 | `grep -n "layer(bootstrap)"` en `app.css` y `combate.css`; 0 `!important` en los 3 parches eliminados; Bootstrap solo importado en `combate.css` |
| **F3 · Componentes base** (M, paralelo por componente) | crear `components/ui/{button,input,select,card,badge,alert,modal,empty-state}.blade.php`, `components/sprite.blade.php`; mod `components/tipo-badge.blade.php`, `resources/css/components.css`, `app.js` solo si el modal lo exige | `frontend-components` | F1 | F4a | por componente: 0 hex, 0 `text-[10px]`, 0 `[..]` arbitrario en el archivo; `grep "bg-white dark:bg-gray-800" components/ui` = 0; usado en ≥1 vista piloto (`welcome`, `auth/login`, `pokedex` modal) |
| **F4a · Layout/auth/onboarding** (S) | `layouts/app`, `layouts/auth`, `auth/login`, `auth/register`, `welcome`, `onboarding/equipo-inicial` | `view-auth` | F3 | F4b, F4c | `grep -c "dark:"` = 0 en los 6; `grep "text-\[10px\]"` = 0; alertas de sesión vía `ui.alert` |
| **F4b · Módulos medios** (M, paralelo entre módulos) | `sendas/index`, `sendas/show`, `misiones/index`, `gimnasios/index`, `reclutamiento/index`, `reclutado/show`, `habitats/index` | `view-sendas`, `view-misiones`, `view-gimnasios`, `view-reclutamiento` (1 owner por módulo) | F3, F4a (para patrones) | entre sí | por archivo: `dark:` = 0 (excepciones listadas), `text-[10px]` = 0, `$tipo['color']` = 0, `/images/iconos/` = 0, usa `x-sprite` |
| **F4c · Pokédex + exploraciones + gimnasios internos** (M/L) | `pokedex/index`; `exploraciones/index`, `_evento`, `_explorador`, `_perdidas`, `_diario`, `_caramelo`; `gimnasios/admin`, `gimnasios/show` | `view-pokedex`, `view-exploraciones`, `view-gimnasios` | F3, F4a | entre sí | `iconUrl()/onIconError()` sin `/images/iconos/`; `text-[10px]` = 0; `dark:` = 0; `x-sprite` en los 3 renders de Pokédex y en `_evento`/`_explorador` |
| **F4d · `habitats/show`** (L, **exclusiva**) | `habitats/show` (extraer partials por sección **al tocar**: cabecera/modos, equipos, especies, granja, modales) | `view-habitats` | F4c | **nada** (no paralela con F4e) | `w-96` = 0; `.sprite` en los 16 `<img>`; `$cardPanelClass` = 0; `dark:` = 0; `text-[10px]` = 0 |
| **F4e · `equipos/index`** (L/XL, **exclusiva**) | `equipos/index` (extraer partials al tocar), `equipos/partials/movimiento-card` | `view-equipos` | F4d (patrones ya estables) | **nada** | `text-[10px]` = 0; `dark:` = 0; `$cardPanelClass` = 0; `calidadIvs()` sin hex; `Chart.js` usa tokens resueltos; 6 modales vía `ui.modal` |
| **F5 · Combate** (M/L, serial) | `combate.css` (hex→tokens, puente `--bs-*`, hp/postura/weather), `livewire/combate`, `_pokemon-card`, `partials/{moves-panel,preparacion-panel,turn-bar,battle-field,battle-log,battle-log-modal}`, `combate_page` (solo hooks de clase) | `view-combate` | F1, F2a, F2b, F3 | — | 0 hex en `combate.css` (excepto animaciones sin color); `bg-hp-*` sustituido por clases semánticas; `prefers-reduced-motion` presente; `data-bs-*` intacto; animaciones con las mismas clases |
| **F6 · Limpieza y theme** (S) | `tokens.css` (theme alternativo de prueba); eliminar `TipoBadges::CLASES`, `@source` si procede, `components/header|sidebar|stat-card` si siguen muertos, `reclutados/index`, `public/css/app.css`; opcional mover `Chart.js` fuera de `app.js` | `orchestrator` + `frontend-*` | F4a-e, F5 | — | `git diff --stat resources/views` vacío tras cambiar un token de tema; 0 usos de componentes muertos; 0 `/images/iconos/` en `src app resources` |

### Realismo y paralelización

- **F0–F3 son el 80% del valor con ~10% del riesgo**: no reescriben vistas, son aditivas y reversibles.
- **F4b y F4c** admiten 3–4 owners en paralelo porque los conjuntos de archivos son disjuntos. `layouts/app` queda cerrado por F4a antes de abrir módulos, para que nadie más lo toque.
- **F4d y F4e son cuellos de botella** (5.538 líneas, 359 `dark:` cada una). Se ejecutan en serie, con «extraer partials al tocar»: cada commit migra una sección (p. ej. «inventario», «huevos», «modales») y deja la vista compilable. Nunca se paralelizan entre sí porque ambas redefinen card/modal.
- Si el WIP de esas dos vistas cambia durante la migración, el conflicto se resuelve **a favor del WIP** y se re-aplica el commit de la sección.
- F5 es el único punto que puede alterar animaciones: se hace al final, con la página de combate como única superficie.

## 7. Verificación (estática, sin tests)

Comandos de solo lectura a ejecutar por fase y adjuntar al informe:

```bash
# 1. Deuda de estilo por archivo migrado
grep -c 'dark:'            resources/views/<archivo>      # objetivo 0 (excepciones justificadas)
grep -c 'text-\[10px\]'    resources/views/<archivo>      # objetivo 0
grep -nE '#[0-9a-fA-F]{6}' resources/views/<archivo>      # objetivo 0
grep -n  '\$cardPanelClass' resources/views/<archivo>     # objetivo 0
grep -n  '/images/iconos/' resources/views src app        # objetivo 0
# 2. Contratos
grep -c 'bootstrap.bundle' resources/js/app.js            # 0
grep -rn 'data-bs-'        resources/views               # solo combate/livewire
grep -rn 'bootstrap'       resources/css                 # solo combate.css
# 3. Adopción
grep -rc '<x-sprite'       resources/views | grep -v ':0' # renders migrados
grep -rc '<x-ui\.'         resources/views               # componentes usados
# 4. Alcance del diff
git diff --stat resources/views resources/css resources/js
```

- **No** se ejecutan tests ni `npm run build` (restricción). La comprobación visual en claro/oscuro la hace el desarrollador con `npm run dev`.
- Opcional (requiere escritura en `storage/`, pedir permiso): `php artisan view:cache` para detectar errores de compilación Blade, seguido de `view:clear`.
- La revisión independiente (`reviewer`) valida: contratos de props, 0 hex, foco/aria, y que no se tocó lógica de negocio ni Livewire/Chart.js.

## 8. Riesgos, mitigaciones y rollback

| Riesgo | Sev. | Mitigación | Rollback |
|---|---|---|---|
| WIP de 305 archivos impide atribuir regresiones | **Alto** | F0: commit del WIP en rama propia (sin revertir) + rama de trabajo | volver al commit WIP |
| Bootstrap en capa invierte Reboot/Preflight en combate | **Alto** | F2b aislada, comparación visual de combate; lista de excepciones | revertir `layer(bootstrap)` y restaurar 3 parches (1 commit) |
| `@tailwindcss/vite` no preserva `layer()` | Medio | Verificar CSS emitido en F2b | plan B: sin capa + parches explícitos |
| Vistas monolíticas y conflictos de merge | **Alto** | owner exclusivo, extraer partials al tocar, commits por sección | revertir la sección, no la fase |
| `TipoBadges::CLASES` retirado antes de migrar deja badges sin estilo (Tailwind no genera clases de `src`) | Medio | retirar solo en F6; mantener `@source "../../src"` | restaurar const + `@source` |
| Dark mode con tokens cambia matices (359 `dark:` por vista) | Medio | tabla de equivalencias `gray-* → token`; revisión en ambos modos por módulo | tokens son aditivos: revertir el módulo |
| `text-2xs` = 10px mantiene deuda AA | Medio | no usar en acciones/info esencial; documentar | — |
| Chart.js no acepta `var()` | Bajo | `getComputedStyle` al render; re-render al abrir modal | mantener hex temporal en JS |
| `<dialog>` nativo + Livewire morph | Medio | componente probado en 1 modal piloto; si falla, div+Alpine sin trap | usar el patrón div actual |
| Dedupe de `bootstrap.bundle` no confirmado en dev | Bajo | comprobar `window.bootstrap` y modal en combate tras F2a | reimportar en `app.js` |
| Nuevo código usa `data-bs-*` fuera de combate | Bajo | regla de vocabulario + grep en revisión | — |
| Transparencia de webp sobre fondos oscuros | Bajo | `.sprite` sin fondo por defecto; fondo opcional `bg-surface` por contexto | — |

**Rollback global:** cada fase es un commit independiente y las fases 1–3 son **aditivas** (no cambian ninguna vista), por lo que `git revert` deja la app exactamente como el WIP. No se reescribe historia ni se hace `reset --hard`.

## 9. Decisiones que requieren aprobación explícita

1. **Ubicación de tokens:** `resources/css/tokens.css` + `components.css` (recomendado) vs todo en `app.css`.
2. **Fuente del color de tipos:** `TipoPokemon::color()` + partial `--type-*` en runtime (recomendado) vs hex estático en CSS y deprecar `color()`.
3. **Bootstrap en `@layer bootstrap`** (acepta Preflight sobre Reboot en combate) vs mantener sin capa y conservar parches.
4. **Quitar `bootstrap.bundle` de `app.js`** (JS de Bootstrap solo en combate).
5. **Namespace `resources/views/components/ui/`** vs componentes planos en `components/`.
6. **Reescribir los 8 componentes muertos** (recomendado) vs crear un set nuevo y dejar los muertos.
7. **`<dialog>` nativo** para `ui.modal` vs div+Alpine con trap manual.
8. **Sprite de combate a 128** (token `--sprite-combat`) vs 96 por densidad.
9. **Eliminar** `reclutados/index.blade.php` y `public/css/app.css` (legacy no enrutado).
10. **Mover `Chart.js` fuera del `app.js` global** (rendimiento, toca entrada de Vite) — fuera de alcance salvo aprobación.
11. **Alcance del theme futuro:** solo claro/oscuro (recomendado) vs paletas de marca/alternativas.
12. **Deuda posterior:** sustituir clases de estilo emitidas por PHP (`hpClass`, `posturaClase`, `claseCarta`) por estados + mapeo en Blade. No necesario para el theme; recomendado después de F6.

## Borrador de ADR

- **Título:** ADR-009 — Capa de tokens y componentes para el refactor visual; Bootstrap confinado a combate.
- **Estado:** Propuesto (pendiente de aprobación del plan).
- **Contexto:** 44 vistas/12.143 líneas sin tokens, 1.586 `dark:`, 142 `text-[10px]`, 1.910 `class=`, Bootstrap solo en combate pero con JS global, escala de sprites dispersa (18–384px), color de tipos triplicado (PHP/Blade/JS) y assets nativos de 128px.
- **Decisión:** tokens semánticos en `resources/css/tokens.css` (`@theme static` + `.dark`), utilidades Tailwind como vocabulario principal, Bootstrap en `@layer bootstrap` y su JS solo en combate, `<x-sprite>` con tamaños 128/96/64 (+`inline`/`combat`) y `object-contain` con dimensiones intrínsecas, y color de tipos con `TipoPokemon::color()` como única fuente emitida a variables CSS.
- **Consecuencias:** el theme futuro se limita a `tokens.css`; desaparecen los parches `!important`; se elimina la mezcla de escalas Bootstrap/Tailwind en combate (F5); se mantiene una regla de vocabulario y una lista de excepciones; los componentes actuales se reescriben en vez de duplicarse; se abre deuda explícita sobre clases de estilo emitidas por PHP.
