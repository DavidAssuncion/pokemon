# ADR-0004: Tema «Stone & ink» / «Lavender Grey — Dark» y contrato CSS de temas

- **Estado:** Aceptado
- **Fecha:** 2026-11-17
- **Ámbito:** `resources/css`, `resources/views`, `resources/js`, `src/Shared/Tipos`,
  `src/Battle`, `src/Exploraciones`, `app/Livewire`
- **Tarea:** TASK-051 — plan v1
- **Implementación:** TASK-051 (fases 1–5) y correcciones posteriores a revisión: **C1**
  (puente de compatibilidad `bg-<estado> text-on-primary` y `--bs-link-hover-color`), **C2**
  (`--color-on-scrim` y badges de tipo) y **C3** (overrides de botones Bootstrap, guarda
  `:disabled` del puente y ajustes de estados deshabilitados, hotfix de enlaces y `wire:key` por
  slug). Este ADR registra la decisión y el diseño; la verificación estática y el build están en
  [`TASK-051/execution.md`](../../.opencode/work/TASK-051/execution.md).
- **Estado de verificación (2026-11-17):** estática y build OK. La comprobación **visual** en
  navegador —`/combate`, ambos temas y las vistas de gimnasios y hábitats— queda pendiente del
  desarrollador; por eso el ADR sigue «Aceptado» y no se declara cerrada la validación visual.

## Contexto

El tema anterior era neutro (grises y azul `#2563eb`) y no representaba la identidad pedida:
un claro terroso y un oscuro lavanda. La auditoría previa a la tarea detectó, además, fallos de
contraste en los colores de marca y de estado que se querían usar:

- `#A47750` como texto queda en 3.43:1 y blanco encima en 3.94:1; ambos fallan AA.
- `#A855F7` como texto queda en 3.69:1 y blanco encima en 3.96:1.
- `#8B88A3` sobre `#2A2733` queda en 4.28:1 y `#A69B8C` entre 2.16 y 2.38:1.
- Blanco sobre los fills oscuros de estado queda entre 2.54 y 2.77:1.

En paralelo, el frontend tenía dos entradas CSS con capas distintas: `app.css` (Tailwind,
tokens y componentes) y `combate.css` (Bootstrap). La colisión entre Preflight y Reboot producía
enlaces subrayados y tipografía distinta en `/combate`; no era un efecto de Livewire. El icono
de cosecha del navbar tampoco era un problema CSS: una condición Blade lo ocultaba en
`/combate*`.

Restricciones de la tarea: sin dependencias nuevas (incluida `sass`), sin tests, sin eliminar
Bootstrap por completo, sin tocar la paleta neón de calidad IV ni los 18 colores de tipo, y sin
cambiar los nombres de token existentes.

## Decisión

| ID | Decisión | Motivo |
|---|---|---|
| **D1** | Conservar los 27 nombres de token existentes y añadir solo tokens semánticos | Hay cientos de usos en 59+ archivos; renombrar obligaría a migrar todos los consumidores sin beneficio visual. |
| **D2** | Claro «Stone & ink» con cuatro superficies monótonas y elevación explícita | El `surface` de la paleta es más oscuro que el `canvas`; el modal necesita leerse por encima del fondo. |
| **D3** | Oscuro «Lavender Grey» literal del desarrollador: `surface = raised = #2A2733`, `canvas #1E1B24`, `sunken #17141E` | Se respeta la paleta aprobada; la elevación se expresa con borde y sombra, no con un cuarto tono. |
| **D4** | `primary` es fill/UI; `primary-ink` es texto; `on-primary` es oscuro en ambos temas | Es la única vía AA sin oscurecer el color de marca (`on-primary` 4.64:1 en claro y oscuro). |
| **D5** | `muted` queda como decorativo; el texto usa `ink-muted`/`ink-subtle`; controles y foco usan tokens propios ≥3:1 | `#A69B8C` y `#8B88A3` no alcanzan AA como texto; los bordes de línea tampoco sirven para controles. |
| **D6** | Los 18 colores de tipo no cambian; badges con tinte 14% + texto `ink` y fondos sólidos con `--type-*-on` calculado | Conserva la identidad del tipo y garantiza legibilidad en ambos temas sin retematizar la paleta. |
| **D7** | La paleta neón de calidad IV y su fondo `#1e293b` se conservan exactamente | El panel es autocontenido y funciona igual en claro y oscuro; retematizarlo estaba fuera de alcance. |
| **D8** | Combate se integra con hotfix de Reboot, puente `--bs-* → tokens` y overrides en `@layer components`, sin reordenar capas | Elimina las colisiones visibles sin compilar SCSS ni añadir dependencias. |
| **D9** | Una sola entrada JS en Vite; Bootstrap se carga con import dinámico solo si existe `.battle-container` | Evita cargar Bootstrap en páginas que no lo usan y elimina el bundle paralelo de combate. |
| **D10** | El tema sigue siendo binario y persiste en `localStorage['theme']`; al alternar se emite `theme:cambiado` | Cambio mínimo sobre el toggle existente; los gráficos necesitan un contrato para reconstruirse. |

### Paletas y jerarquía de superficies

**Claro — «Stone & ink».** La elevación se lee de más claro a más oscuro, salvo el modal, que
flota sobre el scrim:

| Nivel | Token | Valor | Uso |
|---|---|---|---|
| 1 | `--color-surface-raised` | `#FAF7F2` | Modales y dropdowns |
| 2 | `--color-canvas` | `#F2EFE9` | Fondo de página |
| 3 | `--color-surface` | `#E9E4DA` | Cards, inputs y navbar |
| 4 | `--color-surface-sunken` | `#DFD8CB` | Tracks, hover y pozos |

`canvas` es el muro, `surface` es papel kraft, `raised` es la hoja que flota sobre el scrim y
`sunken` es el hueco. La elevación no se deduce solo de la luminosidad: se refuerza con
`--shadow-modal` y borde.

| Token | Claro | Nota |
|---|---|---|
| `--color-line` | `#D8D0C3` | Solo decorativo (1.21 sobre surface) |
| `--color-line-strong` | `#C2B7A5` | Divisores reforzados |
| `--color-ink` | `#302E2A` | 11.81 sobre canvas y 10.69 sobre surface |
| `--color-ink-muted` | `#665E52` | 5.04 sobre surface y 4.51 sobre sunken |
| `--color-ink-subtle` | `#6B6357` | 4.67 sobre surface; no usar sobre sunken (4.18) |
| `--color-primary` | `#A47750` | Solo fill, borde y UI (3.11 sobre surface) |
| `--color-primary-hover` | `#B0855F` | Hover aclarado |
| `--color-primary-ink` | `#6E4A2A` | Texto y enlaces (6.19 sobre surface) |
| `--color-on-primary` | `#1A140E` | Texto sobre el fill primario (4.64) |
| `--color-control-border` | `#8A7F6F` | Borde de input/select (3.10) |
| `--color-focus` | `#A47750` | Anillo de foco (3.11) |
| `--color-muted` | `#A69B8C` | Solo iconos y disabled (2.16) |
| `--color-scrim` | `rgb(48 46 42 / 0.55)` | Overlay cálido |
| `--color-on-scrim` | `#FFFFFF` | Texto sobre el scrim (título de hábitat) |

**Oscuro — «Lavender Grey — Dark».** `surface` y `raised` comparten valor por decisión del
desarrollador; `sunken` es más oscuro que `canvas` para tracks.

| Nivel | Token | Valor | Uso |
|---|---|---|---|
| — | `--color-surface` y `--color-surface-raised` | `#2A2733` | Cards, modales y dropdowns |
| — | `--color-canvas` | `#1E1B24` | Fondo de página |
| — | `--color-surface-sunken` | `#17141E` | Tracks y pozos |

| Token | Oscuro | Nota |
|---|---|---|
| `--color-line` | `#4A4458` | Solo decorativo (1.57 sobre surface) |
| `--color-line-strong` | `#5E5670` | Divisores reforzados (2.12) |
| `--color-ink` | `#EAE6F8` | 13.88 sobre canvas y 11.95 sobre surface |
| `--color-ink-muted` | `#9A97B0` | 5.17 sobre surface y 6.00 sobre canvas |
| `--color-ink-subtle` | `#9491AC` | 4.81 sobre surface |
| `--color-primary` | `#A855F7` | Solo fill, borde y UI (3.69 sobre surface) |
| `--color-primary-hover` | `#C084FC` | Hover aclarado |
| `--color-primary-ink` | `#C9A0FF` | Texto y enlaces (6.93 sobre surface) |
| `--color-on-primary` | `#17121F` | Texto oscuro sobre el fill (4.64) |
| `--color-control-border` | `#7D7595` | Borde de control derivado (3.38) |
| `--color-focus` | `#A855F7` | Anillo de foco (3.69) |
| `--color-muted` | `#8B88A3` | Decorativo; el `$text-muted` original no alcanza AA |
| `--color-scrim` | `rgb(0 0 0 / 0.7)` | Overlay del modal en oscuro |
| `--color-on-scrim` | `#FFFFFF` | Texto sobre el scrim (título de hábitat) |

**Excepción documentada:** `#8B88A3` sobre `#2A2733` da 4.28:1. Por eso el token legible de texto
secundario es `#9A97B0` y `#8B88A3` queda como `--color-muted` decorativo.

### Reglas de contraste y derivados

- Texto normal ≥4.5:1; componentes de UI, bordes de control y foco ≥3:1.
- `--color-primary` y `--color-secondary` nunca se usan como color de texto; para eso existen
  `primary-ink` y `secondary-ink`.
- `--color-muted` nunca se usa como texto.
- `--color-line` y `--color-line-strong` no sirven como borde de control; para eso está
  `--color-control-border`.
- `--color-ink-subtle` no se usa sobre `sunken` (4.18 en claro).

Pares críticos verificados por el arquitecto:

| Combinación | Claro | Oscuro |
|---|---|---|
| `ink` / `canvas` | 11.81 | 13.88 |
| `ink` / `surface` | 10.69 | 11.95 |
| `ink-muted` / `surface` | 5.04 | 5.17 |
| `ink-muted` / `sunken` | 4.51 | ≥5.8 |
| `ink-subtle` / `surface` | 4.67 | 4.81 |
| `primary-ink` / `surface` | 6.19 | 6.93 |
| `primary-ink` / `primary-soft` | 5.38 | 5.76 |
| `on-primary` / `primary` | 4.64 | 4.64 |
| `on-primary` / `primary-hover` | 5.53 | 6.95 |
| `control-border` / `surface` | 3.10 | 3.38 |
| `focus` / `surface` | 3.11 | 3.69 |

Estados (fill y texto encima):

| Estado | Claro | Oscuro |
|---|---|---|
| success | `#3F6B4B` / blanco 6.15 | `#7CA982` / `#17121F` 6.35 |
| warning | `#7C5410` / blanco 6.70 | `#FBBF24` / `#17121F` 10.17 |
| danger | `#9E3B31` / blanco 6.72 | `#F87171` / `#17121F` 6.14 |
| info | `#36617E` / blanco 6.63 | `#60A5FA` / `#17121F` 6.67 |
| secondary | `#6E665A` / blanco 5.66 | `#8B88A3` / `#14111A` 5.47 |

Los `-soft` se derivan con `color-mix` sobre `surface` (12% en claro, 18% en oscuro) y su texto
usa el `-ink` correspondiente. `primary-soft` usa 14% en claro y 16% en oscuro; el texto es
`primary-ink` (5.38 y 5.76).

### Tipos y calidad IV

**Tipos.** Los 18 hex de `TipoPokemon::color()` no cambian. La legibilidad se resuelve por
fórmula, no retematizando:

```css
.tipo-badge {
  background-color: color-mix(in oklab, var(--type-color) 14%, var(--color-surface));
  border: 1px solid color-mix(in oklab, var(--type-color) 45%, var(--color-line));
  color: var(--color-ink);
  padding-left: 0;
}
```

- La identidad cromática la dan el tinte de fondo y el borde; el badge no usa
  pseudo-elemento decorativo. `padding-left: 0` elimina el hueco reservado a la izquierda y el
  `gap` se conserva para el icono opcional de `x-tipo-badge`.
- Mínimo del badge: **9.05:1** en claro (siniestro) y **7.95:1** en oscuro (eléctrico).
- Se elimina el `color-mix(tipo 60%, ink)` anterior; el texto usa `ink` fijo.
- Los fondos sólidos usan `--type-<slug>-on`, emitido por
  `resources/views/partials/tipos-tokens.blade.php` y calculado por
  `TipoPokemon::textoSobreColor()` con luminancia WCAG: `#17120C` si `L > 0.179`, `#FFFFFF` si no.
  Peor caso: veneno `#A040A0` con blanco, **5.62:1**.
- `color: var(--type-*)` no se usa como texto sobre superficie; se usa `ink` + borde y tinte de tipo.

**Calidad IV.** Se conservan los cinco colores (`#94a3b8`, `#4ade80`, `#38bdf8`, `#c084fc`,
`#facc15`), sus sombras neón y el fondo `#1e293b` de `resources/css/app.css`. Solo se tokeniza
`.pokemon-card-assigned`, que usaba grises ajenos a la paleta, y se elimina el
`.dark { color-scheme: dark; }` duplicado de `app.css` (la fuente es `tokens.css`).

### Combate: capas, hotfix y puente

No se reordena la declaración de capas de `app.css`:

```css
@layer properties, theme, base, bootstrap, components, utilities;
```

**Hotfix de Reboot (sin capa).** Al final de `resources/css/combate.css`:

```css
a:not([class*="underline"]) { text-decoration: none; }
body { font-family: inherit; }
```

Se puede aplicar antes del tema y resuelve el subrayado y la tipografía sin tocar Bootstrap. El
selector deja intactos los subrayados explícitos del markup (`text-decoration-underline` y
similares). La regla `body` anula además la pila tipográfica que Reboot impone.

**Puente `--bs-* → tokens`.** Un único bloque `:root` traduce las variables de Bootstrap a
tokens; las variables que Bootstrap exige numéricas (`--bs-*-rgb`) se declaran como tripletas.
El único bloque `.dark` de `combate.css` redefine esas tripletas, no la paleta. Ambos bloques
viven **dentro de `@layer components`** —posterior a `bootstrap` en el orden de capas—, no sin
capa: así sustituyen las variables que Bootstrap declara en su propia capa sin reordenar nada.

```css
@layer components {
  /* Extracto: el mapa completo está en combate.css. */
  :root {
    --bs-body-bg: var(--color-canvas);
    --bs-body-color: var(--color-ink);
    --bs-body-color-rgb: 48, 46, 42;
    --bs-secondary-color: var(--color-ink-muted);
    --bs-border-color: var(--color-line);
    --bs-card-bg: var(--color-surface);
    --bs-card-color: var(--color-ink);
    --bs-link-color: var(--color-primary-ink);
    --bs-link-hover-color: var(--color-primary-ink);
    --bs-primary: var(--color-primary);
    --bs-danger: var(--color-danger);
    --bs-primary-rgb: 164, 119, 80;
    --bs-danger-rgb: 158, 59, 49;
    /* …secondary/success/info/warning y sus tripletas */
  }

  .dark {
    --bs-body-color-rgb: 234, 230, 248;
    --bs-primary-rgb: 168, 85, 247;
    --bs-secondary-rgb: 139, 136, 163;
    --bs-success-rgb: 124, 169, 130;
    --bs-info-rgb: 96, 165, 250;
    --bs-warning-rgb: 251, 191, 36;
    --bs-danger-rgb: 248, 113, 113;
  }
}
```

**Enlaces: `primary-ink` tanto en reposo como en hover.** `--bs-link-hover-color` resuelve al
mismo token que `--bs-link-color`, no a `--color-primary-hover`. `primary-hover` es el paso de
*fill* —el aclarado del fondo de botones y controles—; como color de texto no sirve: sobre
`surface` queda en 2.6:1 en claro (calculado), frente a los 6.19:1 de `primary-ink`. En oscuro
`primary-hover` llegaría a 5.53:1, pero el contrato se mantiene idéntico en ambos temas y no se
acepta un valor que solo cumpla en uno. El realce del hover queda a cargo del componente
(subrayado explícito, fondo o borde), no del color del texto.

El mapa completo de variables vive en `combate.css`. Los casos que Bootstrap compila y no expone
como variable (`.alert-*`, `.form-select`, `.btn-*`) se redefinen en `@layer components`,
posterior a `bootstrap`, para ganar la cascada sin reordenar capas. El resto de `combate.css`
(tokenización de HP, climas, turnos, tooltip, animaciones) sustituye hex fijos por tokens y
`color-mix`.

**Botones Bootstrap.** Los botones que usa el markup de combate se reasignan por variables
`--bs-btn-*`, sin `!important`:

| Selector | Texto | Fondo / borde | Hover y activo |
|---|---|---|---|
| `.btn-primary` | `on-primary` | `primary` | `primary-hover` con `on-primary` |
| `.btn-success` | `on-success` | `success` | `success` mezclado 85% con `ink` |
| `.btn-outline-primary` | `primary-ink` | borde `primary` | relleno `primary` con `on-primary` |
| `.btn-outline-warning` | `warning-ink` | borde `warning` | relleno `warning` con `on-warning` |
| `.btn-outline-secondary` | `ink` | borde `control-border` | `surface-sunken` con `ink` |

**Estados deshabilitados.** No los cubre una sola regla: conviven tres familias y cada una
resuelve el estado de forma distinta.

- **`.btn-*` de combate:** reasignan `--bs-btn-disabled-color: ink-muted`,
  `--bs-btn-disabled-bg: surface-sunken` y `--bs-btn-disabled-border-color: line`; los outline
  conservan el fondo transparente. Bootstrap sigue aplicando su `--bs-btn-disabled-opacity` por
  defecto (0.65 en 5.3.8): el proyecto no la sobrescribe, así que la señal de no interactivo
  combina un color propio con la atenuación del componente.
- **Fills semánticos heredados (`bg-<estado> text-on-primary`):** las cinco reglas del puente en
  `resources/css/components.css` llevan la guarda
  `:not(:disabled):not(.disabled):not([aria-disabled="true"])`, de modo que un consumidor
  deshabilitado **no** recibe el `on-*` del fill. Si mantiene el fill, conserva el
  `text-on-primary` declarado en el markup y solo se atenúa con `disabled:opacity-*`; si cambia
  a `disabled:bg-surface-sunken`, declara además `disabled:text-ink-muted` (o
  `disabled:text-ink-subtle` en algunas vistas).
- **Controles con borde (sendas):** `disabled:border-line` junto a `disabled:text-ink-subtle`.

Así un botón deshabilitado se lee como no interactivo y no como un fill semántico atenuado. La
contrapartida es que el color legible del segundo grupo depende del puente y de la opacidad (ver
deuda).

**Entrada JS única.** `resources/js/app.js` carga Bootstrap con import dinámico solo si la página
tiene `.battle-container`. Se elimina `resources/js/combate.js` de `vite.config.js` y de
`combate_page.blade.php`; el script inline de combate se mantiene.

**Livewire.** No se atribuye el rediseño a Livewire: el subrayado era cascada y el icono era una
condición Blade. Se añaden `wire:key` en las tarjetas de `battle-field` y en los ataques de
`moves-panel` como higiene de morph, no como causa raíz.

### Tema y contrato de UI

- Persistencia: `localStorage['theme']` con `light`/`dark`; sin modo «system».
- El toggle aplica/retira `.dark` y despacha
  `theme:cambiado` con `{ tema: 'light'|'dark' }`.
- Los gráficos de Equipos escuchan el evento, reconstruyen con `--color-chart-*` y eliminan el
  listener en `destroy()`.
- Los layouts emiten `meta theme-color` coherente con el tema activo.

## Alternativas descartadas

| Alternativa | Motivo del descarte |
|---|---|
| Eliminar Bootstrap por completo y migrar sus ~250 usos a Tailwind | Requiere decisión de producto, un alcance propio y reescribir componentes estables; el riesgo supera el objetivo de tema. Queda como deuda futura. |
| Reordenar `@layer` para que `components` gane a `bootstrap` | Rompe la precedencia de utilidades y el orden que Tailwind espera; el puente de variables y los overrides en `components` resuelven lo mismo sin efectos laterales. |
| Añadir `sass` y compilar Bootstrap con `$enable-reboot: false` | Es una dependencia nueva prohibida por la tarea; además obliga a mantener un build SCSS paralelo. El hotfix y el aislamiento por capas cubren el síntoma. |
| Remapear los tokens a nombres nuevos | Rompe 59+ archivos y no aporta valor visual; los nombres actuales ya son semánticos. |
| Un único token `muted` para decoración y texto | `#A69B8C` y `#8B88A3` no alcanzan AA como texto; se necesitan `ink-muted`/`ink-subtle`. |
| Mantener blanco sobre `primary` y sobre los estados oscuros | Falla AA (3.94–3.96 y 2.54–2.77); oscurecer el fill cambiaría la marca aprobada. |
| Blanco fijo sobre los fondos sólidos de tipo | Falla en 12 de 18 tipos; el `-on` por luminancia garantiza ≥5.62:1. |
| Módulo JS de tema completo con «system» y sincronización entre pestañas | Fuera de alcance; el toggle binario y `localStorage` ya existen y bastan. |
| Override CSS de `text-bg-*` | Es `!important` en la capa `bootstrap`; se migra el markup a clases de token. |

## Consecuencias

**Positivas**

- El tema claro y el oscuro comparten la misma semántica de tokens; ningún consumidor renombra
  variables.
- Todos los pares críticos de texto y UI cumplen AA, con reglas explícitas para los colores que
  no pueden usarse como texto.
- Los badges y fondos de tipo son legibles en ambos temas sin alterar los 18 colores originales.
- La calidad IV neón se mantiene idéntica, incluido su fondo.
- `/combate` deja de tener enlaces subrayados y tipografía ajena, y sus componentes Bootstrap
  siguen el tema por variables.
- Bootstrap deja de cargarse en páginas sin combate y desaparece la segunda entrada JS.

**Negativas / costes**

- El texto oscuro sobre el primario (`on-primary`) cambia el aspecto de botones y badges
  primarios en ambos temas.
- En claro, `surface` es más oscuro que `canvas`; sin la elevación de `raised` y las sombras puede
  percibirse como un error de jerarquía.
- El puente duplica algunas tripletas RGB que deben mantenerse sincronizadas con los tokens.
- El puente de compatibilidad `.bg-<estado>.text-on-primary` es deuda temporal: cinco reglas sin
  capa que existen solo para el markup heredado y deben desaparecer al migrarlo.
- Los overrides de componentes Bootstrap dependen de la versión instalada; un cambio de Bootstrap
  puede exigir revisarlos.
- Se pospone la eliminación real de Reboot, que sigue presente en combate.

## Deuda y seguimiento

- **`--quality-text` en claro:** los valores `#64748b`, `#15803d`, `#0284c7` y `#9333ea` sobre el
  panel fijo `#1e293b` dan 2.7–3.6:1. Es deuda preexistente y se conserva por instrucción; para
  corregirla habría que retematizar el panel o definir tokens de texto por calidad.
- **Migración futura de Bootstrap:** quedan ~250 usos. La fase 3 del Subanálisis B propone
  eliminarlo; requiere alcance propio, decisión de producto y, probablemente, un build SCSS.
- **`wire:key` en combate:** se añade en tarjetas y ataques como higiene de morph. Verificar en
  fase 6 que no hay reutilización de nodos; si el morph destruye el canvas de gráficos, enganchar
  `Livewire.hook('morph.updated', ...)` sin cambiar el contrato `theme:cambiado`.
- **Tripletas RGB del puente:** mantenerlas sincronizadas con `tokens.css`; dejarlas comentadas
  como «derivado de…» y revisarlas al cambiar la paleta.
- **Placa de icono de tipo (`.type-plate`):** el disco del WebP se funde con el fondo sólido del
  mismo tipo. La placa de superficie lo separa; revisar si en el futuro se sustituye el asset.
- **`--color-chart-*` y ciclo de vida:** los gráficos se reconstruyen al cambiar de tema; falta
  verificar que no queden listeners tras `destroy()` en todas las rutas de Equipos.
- **Puente `.bg-{success,warning,danger,info,secondary}.text-on-primary`:** cinco reglas en
  `resources/css/components.css` **fuera de `@layer`**, con la guarda
  `:not(:disabled):not(.disabled):not([aria-disabled="true"])`. Existen porque el markup heredado
  combina un fill semántico con `text-on-primary`, y el `on-primary` oscuro de este tema no
  contrasta sobre `success`, `warning`, `danger`, `info` ni `secondary`. La guarda impide que un
  botón deshabilitado reciba el color `on-*` y pierda la señal de no interactivo. Al estar sin
  capa ganan a las utilidades de Tailwind —que es lo que permite corregir el color heredado—,
  pero quedan fuera de la cascada normal: una utilidad de color posterior no las desplazará.
- **Consumidores heredados que solo atenúan al deshabilitarse:** hay botones con
  `bg-<estado> text-on-primary` cuyo único tratamiento de `disabled` es `disabled:opacity-*`.
  Mantienen el fill y el `text-on-primary` del markup, así que su color legible depende por
  completo del puente. La revisión anotó **15**; el conteo estático de esta corrección
  (2026-11-17, atributos `class`/`:class` con `bg-<estado>`, `text-on-primary` y
  `disabled:opacity-*` a la vez) deja **17 atributos en 10 vistas**, cifra inestable mientras
  TASK-052/TASK-053 reescriben vistas. Migrarlos a `text-on-<estado>` más un `disabled:`
  explícito (`disabled:bg-surface-sunken disabled:text-ink-muted`) sigue pendiente y es
  requisito para retirar el puente.
- **Tarea pendiente — retirar el puente:** migrar cada `bg-<estado> text-on-primary` a su
  `text-on-<estado>` real (`text-on-success`, `text-on-warning`, `text-on-danger`,
  `text-on-info`, `text-on-secondary`) y borrar las cinco reglas. El recuento es inestable
  mientras TASK-052/TASK-053 reescriben vistas: la revisión anotó **47** usos y el conteo
  estático de esta corrección (atributos `class`/`:class` que contienen a la vez `bg-<estado>` y
  `text-on-primary`, 2026-11-17) deja **44 atributos en 25 vistas**: 22 `danger`, 14 `success`,
  7 `warning`, 1 `info` y 0 `secondary`. La regla de `secondary` no tiene consumidores y se
  conserva solo por simetría del contrato.

### Archivos concurrentes (TASK-052 / TASK-053)

TASK-052 y TASK-053 trabajan sobre el mismo árbol en gimnasios, hábitats y equipos. Dos archivos
quedaron tocados por ambas partes:

- `resources/views/gimnasios/show.blade.php` — el badge de tipo dejó de usar `text-on-primary` y
  toma el color de `--type-<slug>-on`; es un cambio de una línea de TASK-051 que convive con la
  reescritura de la vista por TASK-052.
- `resources/views/habitats/partials/columna-izquierda.blade.php` — el título sobre la imagen pasó
  de `text-on-primary` a `text-on-scrim` (`--color-on-scrim`, blanco en ambos temas); una línea de
  TASK-051 junto a los cambios de sprite de TASK-052.

Ambas ediciones son de una línea y no reformatean ni sustituyen el resto del archivo, pero deben
revisarse de forma coordinada: un `git diff` por archivo mezcla los dos trabajos y no permite
atribuir líneas sin leer el contexto. Durante esta corrección, `components.css` y `combate.css`
siguieron cambiando (puente y overrides de botones), así que este ADR describe el estado de los
archivos en ese momento, no una versión congelada.

## Referencias

- Plan: [`TASK-051/plan.md`](../../.opencode/work/TASK-051/plan.md)
- Diseño y cálculos de contraste: [`TASK-051/architecture.md`](../../.opencode/work/TASK-051/architecture.md)
- Análisis: [`TASK-051/analysis.md`](../../.opencode/work/TASK-051/analysis.md)
- Ejecución y verificación estática: [`TASK-051/execution.md`](../../.opencode/work/TASK-051/execution.md)
- Convención CSS: [`docs/conventions.md`](../conventions.md)
- Código: `resources/css/tokens.css`, `resources/css/components.css`, `resources/css/app.css`,
  `resources/css/combate.css`, `resources/js/app.js`, `resources/views/layouts/app.blade.php`,
  `resources/views/partials/tipos-tokens.blade.php`, `src/Shared/Tipos/TipoPokemon.php`.
