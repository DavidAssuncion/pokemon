# TASK-051 — Arquitectura

- **Estado:** COMPLETADO
- **Responsable:** `architect`
- **Fecha:** 2026-11-17
- **Archivos modificados:** ninguno (solo lectura y cálculo de contraste).

## 1. Resumen ejecutivo

- Se conservan **todos los nombres de token existentes** (`tokens.css:10-154`) y se añaden **8 tokens semánticos + 4 de gráfico + 1 generado por tipo**. No hay renombrado masivo.
- **Claro**: la jerarquía de superficies se invierte respecto al tema actual (el `surface` de la paleta es más oscuro que el `canvas`). Se resuelve con cuatro niveles monótonos y una elevación explícita para modales: `raised #FAF7F2` > `canvas #F2EFE9` > `surface #E9E4DA` > `sunken #DFD8CB`.
- **Oscuro**: se respeta literalmente la paleta del desarrollador; `surface = raised = #2A2733` y se añade `sunken #17141E` para tracks. La elevación se expresa con borde y sombra, no con luminosidad.
- **Accesibilidad**: los colores base que fallan AA (`#A69B8C`, `#A47750`, `#8B88A3`, `#A855F7`, blanco sobre estados) quedan como **referencia decorativa/fill**; el texto usa derivados (`ink-muted`, `primary-ink`, `on-*`). Todos los pares texto/fondo críticos superan 4.5:1 y los de UI 3:1.
- **Combate**: se integra sin dependencias nuevas y **sin reordenar capas**. Hotfix de Reboot con dos reglas sin capa; objetivo con puente `--bs-* → tokens` y tokenización de `combate.css`. La eliminación real de Reboot exigiría SCSS + `sass` (dependencia prohibida) y queda acotada.
- **Tipos**: se conservan los 18 hex. La legibilidad se garantiza por fórmula: badges con tinte bajo + texto `--color-ink` (mínimo 7.95:1); fondos sólidos con `--type-*-on` derivado por luminancia (peor caso 5.62:1).
- **Calidad IV**: se conservan exactamente los cinco colores y `#1e293b`. Solo se toca `.pokemon-card-assigned` (rgba grises fuera de la paleta).

## 2. Decisiones de diseño

| # | Decisión | Motivo | Descartado |
|---|---|---|---|
| D1 | Mantener nombres de token y añadir los mínimos | 27 tokens con cientos de usos; renombrar rompe 59+ archivos | Remapeo con nombres nuevos |
| D2 | `on-primary` oscuro en ambos temas | Blanco sobre `#A47750` = 3.94 y sobre `#A855F7` = 3.96; ambos fallan AA | Mantener blanco y oscurecer el fill (cambiaría la marca) |
| D3 | `primary` = fill/UI; `primary-ink` = texto | `#A47750` sobre fondo = 3.43 y `#A855F7` sobre surface = 3.69; solo válidos como UI ≥3:1 | Usar `primary` como color de texto |
| D4 | `muted` decorativo separado de `ink-muted` legible | `#A69B8C` = 2.16–2.38 y `#8B88A3` = 4.28; no sirven para texto | Un único token `muted` |
| D5 | `surface-raised` más claro que `canvas` en claro | El `surface` dado es más oscuro que el fondo; el modal necesita leerse por encima | `raised = surface` (sin elevación) |
| D6 | En oscuro `raised = surface = #2A2733` | Lo fija el desarrollador (card/modal/dropdown) | Introducir un cuarto tono no pedido |
| D7 | Badge de tipo: texto `ink` + tinte 14% | Garantiza ≥7.9:1 con los 18 colores sin tocar la paleta | Texto `color-mix(tipo 60%, ink)` (falla en claro) |
| D8 | Fondo sólido de tipo: `--type-*-on` calculado | Máx(blanco, negro) siempre ≥4.58; peor caso real 5.62 | Blanco fijo (falla en 12 de 18 tipos) |
| D9 | Combate: puente de variables + overrides en `components` | Bootstrap usa `!important`; las capas no se tocan | Reordenar capas o compilar SCSS (dependencia nueva) |
| D10 | `text-bg-*` se migra en Blade | Es `!important` en capa `bootstrap`; no se puede vencer sin romper la cascada | Override CSS |
| D11 | El tema sigue siendo binario y en `localStorage['theme']` | Cambio mínimo; el modo «system» queda fuera | Módulo JS de tema completo |
| D12 | Gráficos: evento `theme:cambiado` | Chart.js resuelve color al crear; no hay listener | Recrear por polling |

## 3. Mapa claro — «Stone & ink»

Definición en `resources/css/tokens.css` (`@theme static`).

| Token | Valor exacto | Ratio clave | Uso |
|---|---|---|---|
| `--color-canvas` | `#F2EFE9` | — | Fondo de página |
| `--color-surface` | `#E9E4DA` | — | Cards, inputs, navbar |
| `--color-surface-raised` | `#FAF7F2` | — | Modales, dropdowns |
| `--color-surface-sunken` | `#DFD8CB` | — | Tracks, hover, pozos |
| `--color-line` | `#D8D0C3` | 1.21 sobre surface (decorativo) | Bordes/divisores |
| `--color-line-strong` | `#C2B7A5` | 1.56 sobre surface (decorativo) | Separadores reforzados |
| `--color-ink` | `#302E2A` | **11.81** canvas / **10.69** surface | Texto base |
| `--color-ink-muted` | `#665E52` | **5.04** surface / **4.51** sunken | Texto secundario (352 usos) |
| `--color-ink-subtle` | `#6B6357` | **4.67** surface / 4.18 sunken | Placeholder. No usar sobre `sunken` |
| `--color-ink-inverse` | `#F5F1E8` | 12.02 sobre `ink` | Texto sobre `bg-ink` |
| `--color-primary` | `#A47750` | **3.11** surface / 3.43 canvas (UI) | Fill, borde, nav, foco |
| `--color-primary-hover` | `#B0855F` | on-primary **5.53** | Hover (aclarado) |
| `--color-primary-soft` | `color-mix(in oklab, var(--color-primary) 14%, var(--color-surface))` ≈ `#E0D4C6` | primary-ink **5.38** | Nav activo, chips |
| `--color-primary-ink` | `#6E4A2A` | **6.19** surface / 6.84 canvas | Texto/enlace primario |
| `--color-on-primary` | `#1A140E` | **4.64** sobre primary | Texto de botón/badge primario |
| `--color-success` | `#3F6B4B` | blanco **6.15** | Fill de éxito |
| `--color-success-soft` | `color-mix(... 12%, surface)` ≈ `#D4D5C8` | success-ink **5.65** | Alertas/badges |
| `--color-success-ink` | `#2E5638` | 5.65 soft | Texto de éxito |
| `--color-warning` | `#7C5410` | blanco **6.70** | Fill de aviso |
| `--color-warning-soft` | `color-mix(... 12%, surface)` ≈ `#DCD2C2` | warning-ink **5.38** | Alertas |
| `--color-warning-ink` | `#6B4A0F` | 5.38 soft | Texto de aviso |
| `--color-danger` | `#9E3B31` | blanco **6.72** | Fill de error |
| `--color-danger-soft` | `color-mix(... 12%, surface)` ≈ `#E2D0C5` | danger-ink **6.18** | Alertas/KO |
| `--color-danger-ink` | `#7C2E26` | 6.18 soft | Texto de error |
| `--color-info` | `#36617E` | blanco **6.63** | Fill informativo |
| `--color-info-soft` | `color-mix(... 12%, surface)` ≈ `#D3D4CF` | info-ink **5.84** | Toasts/alertas |
| `--color-info-ink` | `#274F6A` | 5.84 soft | Texto informativo |
| `--color-secondary` *(nuevo)* | `#6E665A` | blanco **5.66** | Neutral interactivo |
| `--color-secondary-soft` | `color-mix(... 12%, surface)` ≈ `#D9D4CA` | secondary-ink **6.03** | Chips neutros |
| `--color-secondary-ink` | `#4F4940` | 6.03 soft | Texto neutro |
| `--color-on-secondary` | `#FFFFFF` | 5.66 | Texto sobre secondary |
| `--color-on-success` | `#FFFFFF` | 6.15 | Texto sobre success |
| `--color-on-warning` | `#FFFFFF` | 6.70 | Texto sobre warning |
| `--color-on-danger` | `#FFFFFF` | 6.72 | Texto sobre danger |
| `--color-on-info` | `#FFFFFF` | 6.63 | Texto sobre info |
| `--color-control-bg` | `#E9E4DA` (`var(--color-surface)`) | — | Fondo de input/select |
| `--color-control-border` | `#8A7F6F` | **3.10** surface (UI) | Borde de control |
| `--color-focus` | `#A47750` | **3.11** surface (UI) | Outline de foco |
| `--color-muted` | `#A69B8C` | 2.16 (decorativo) | Iconos/disabled, nunca texto |
| `--color-scrim` | `rgb(48 46 42 / 0.55)` | — | Overlay (scrim cálido) |
| `--color-chart-grid` | `var(--color-line)` | — | Rejilla Chart.js |
| `--color-chart-label` | `var(--color-ink-muted)` | 5.04 | Etiquetas Chart.js |
| `--color-chart-primary` | `var(--color-primary)` | — | Serie 1 |
| `--color-chart-success` | `var(--color-success)` | — | Serie 2 |
| Radios | `control .5rem`, `card .75rem`, `panel 1rem` | — | Sin cambios |
| `--shadow-card` | `0 1px 2px rgb(48 46 42 / 0.08)` | — | Card |
| `--shadow-modal` | `0 12px 32px rgb(48 46 42 / 0.18)` | — | Modal |
| `--quality-*` | **sin cambios** (`#94a3b8/#4ade80/#38bdf8/#c084fc/#facc15`) | — | Calidad IV |
| `--color-type-*` | **sin cambios** (18 hex) | — | Tipos |

**Justificación de la jerarquía clara.** `canvas` es el muro (más claro), `surface` es papel kraft (más oscuro), `raised` es la hoja que flota sobre el scrim (más clara que todo), `sunken` es el hueco. La elevación no se deduce de la luminosidad sola: se refuerza con `--shadow-modal` y borde.

## 4. Mapa oscuro — «Lavender Grey — Dark»

Definido en `.dark` de `tokens.css` (solo variables, sin capa).

| Token | Valor exacto | Ratio clave | Nota |
|---|---|---|---|
| `--color-canvas` | `#1E1B24` | — | `$body-bg` |
| `--color-surface` | `#2A2733` | — | card/modal/dropdown |
| `--color-surface-raised` | `#2A2733` | — | Igual por decisión del desarrollador |
| `--color-surface-sunken` | `#17141E` | — | Tracks/pozos, más oscuro que canvas |
| `--color-line` | `#4A4458` | 1.57 surface (decorativo) | `$border-color` |
| `--color-line-strong` | `#5E5670` | 2.12 | Divisores reforzados |
| `--color-ink` | `#EAE6F8` | **13.88** canvas / **11.95** surface | `$body-color` |
| `--color-ink-muted` | `#9A97B0` | **5.17** surface / 6.00 canvas | Derivado legible del `$text-muted` |
| `--color-ink-subtle` | `#9491AC` | **4.81** surface | Placeholder |
| `--color-ink-inverse` | `#1E1B24` | 13.88 sobre `ink` | Texto sobre `bg-ink` |
| `--color-primary` | `#A855F7` | **3.69** surface / 4.29 canvas (UI) | `$primary` |
| `--color-primary-hover` | `#C084FC` | on-primary **6.95** | Hover aclarado |
| `--color-primary-soft` | `color-mix(... 16%, surface)` ≈ `#3C304F` | primary-ink **5.76** | Nav/chips |
| `--color-primary-ink` | `#C9A0FF` | **6.93** surface | Texto/enlace morado |
| `--color-on-primary` | `#17121F` | **4.64** sobre primary | Texto oscuro |
| `--color-success` | `#7CA982` | on-success **6.35** | `$success` |
| `--color-success-soft` | `color-mix(... 18%, surface)` ≈ `#383C41` | success-ink **6.26** | Alertas |
| `--color-success-ink` | `#A6CCA9` | 6.26 soft | Derivado para texto |
| `--color-warning` | `#FBBF24` | on-warning **10.17** | `$warning` |
| `--color-warning-soft` | `... 18%` ≈ `#4B403B` | warning-ink **6.95** | Alertas |
| `--color-warning-ink` | `#FCD34D` | 6.95 soft | Texto |
| `--color-danger` | `#F87171` | on-danger **6.14** | `$danger` |
| `--color-danger-soft` | `... 18%` ≈ `#4C353F` | danger-ink **5.86** | Alertas |
| `--color-danger-ink` | `#FCA5A5` | 5.86 soft | Texto |
| `--color-info` | `#60A5FA` | on-info **6.67** | `$info` |
| `--color-info-soft` | `... 18%` ≈ `#353C53` | info-ink **6.06** | Alertas |
| `--color-info-ink` | `#93C5FD` | 6.06 soft | Texto |
| `--color-secondary` | `#8B88A3` | on-secondary **5.47** | `$secondary` |
| `--color-secondary-soft` | `... 18%` ≈ `#3A3745` | secondary-ink **5.51** | Chips |
| `--color-secondary-ink` | `#B4B0C8` | 5.51 soft | Texto |
| `--color-on-secondary` | `#14111A` | 5.47 | Texto oscuro |
| `--color-on-success` | `#17121F` | 6.35 | |
| `--color-on-warning` | `#17121F` | 10.17 | |
| `--color-on-danger` | `#17121F` | 6.14 | |
| `--color-on-info` | `#17121F` | 6.67 | |
| `--color-control-bg` | `#2A2733` | — | `$input-bg` |
| `--color-control-border` | `#7D7595` | **3.38** surface (UI) | `$input-border-color` derivado |
| `--color-focus` | `#A855F7` | **3.69** surface (UI) | Anillo de foco |
| `--color-muted` | `#8B88A3` | 4.28 (decorativo) | `$text-muted` como decoración |
| `--color-scrim` | `rgb(0 0 0 / 0.7)` | — | Se conserva |
| `--shadow-modal` | `0 12px 32px rgb(0 0 0 / 0.55)` | — | |
| Radios | `.5 / .75 / 1rem` | — | `$border-radius*` |
| `--quality-*` / `--color-type-*` | **sin cambios** | — | No se retematizan |

**Excepción documentada:** `$text-muted: #8B88A3` no alcanza AA sobre `#2A2733` (4.28). Por eso el token legible es `#9A97B0` y `#8B88A3` queda como `--color-muted` decorativo.

## 5. Tokens nuevos, valores y consumidores que deben migrar

| Token nuevo | Claro | Oscuro | Consumidores actuales que migran |
|---|---|---|---|
| `--color-secondary` + `-soft` + `-ink` + `on-secondary` | `#6E665A` / mix 12% / `#4F4940` / `#FFF` | `#8B88A3` / mix 18% / `#B4B0C8` / `#14111A` | `MovesPreviewPresenter::categoriaColor` (`:425-431`, categoría ESTADO); `--bs-secondary`; badges neutros |
| `--color-on-success` | `#FFFFFF` | `#17121F` | `ui/button.blade.php:31`; `combate.blade.php:173` |
| `--color-on-warning` | `#FFFFFF` | `#17121F` | Cualquier `bg-warning` con texto; preparado |
| `--color-on-danger` | `#FFFFFF` | `#17121F` | `ui/button.blade.php:30`; `moves-panel:77,89` |
| `--color-on-info` | `#FFFFFF` | `#17121F` | Badges/avisos con fill `info` |
| `--color-control-bg` | `var(--color-surface)` | idem | `ui/input.blade.php:36`, `.form-select`, textareas |
| `--color-control-border` | `#8A7F6F` | `#7D7595` | `ui/input.blade.php:37`; `.form-select` |
| `--color-focus` (ya existe) | `#A47750` | `#A855F7` | `.focus-ring` (`components.css:60-63`) |
| `--color-chart-grid` | `var(--color-line)` | idem | `equipos/index.blade.php:527-528` |
| `--color-chart-label` | `var(--color-ink-muted)` | idem | `equipos/index.blade.php:526` |
| `--color-chart-primary` | `var(--color-primary)` | idem | `equipos/index.blade.php:584-585` |
| `--color-chart-success` | `var(--color-success)` | idem | `equipos/index.blade.php:592-593` |
| `--color-muted` | `#A69B8C` | `#8B88A3` | `debug-tooltip__key`; iconos disabled |
| `--type-<slug>-on` (generado) | `#17120C` o `#FFFFFF` | idem | `moves-panel.blade.php:38`; `sendas/index.blade.php:27,39,43`; `sendas/show.blade.php:10,101,104` |

`--type-<slug>-on` **no vive en `tokens.css`**: lo emite `partials/tipos-tokens.blade.php` junto a `--type-*`, calculado por `TipoPokemon::textoSobreColor()` (luminancia relativa WCAG; umbral 0.179).

## 6. Accesibilidad

### Pares críticos (WCAG 2.x, sRGB)

| Combinación | Claro | Oscuro | Umbral |
|---|---|---|---|
| `ink` / `canvas` | 11.81 | 13.88 | 4.5 ✅ |
| `ink` / `surface` | 10.69 | 11.95 | 4.5 ✅ |
| `ink-muted` / `surface` | 5.04 | 5.17 | 4.5 ✅ |
| `ink-muted` / `sunken` | 4.51 | ≥5.8 | 4.5 ✅ (claro justo) |
| `ink-subtle` / `surface` | 4.67 | 4.81 | 4.5 ✅ |
| `primary-ink` / `surface` | 6.19 | 6.93 | 4.5 ✅ |
| `primary-ink` / `primary-soft` | 5.38 | 5.76 | 4.5 ✅ |
| `on-primary` / `primary` | 4.64 | 4.64 | 4.5 ✅ |
| `on-primary` / `primary-hover` | 5.53 | 6.95 | 4.5 ✅ |
| blanco / `success` | 6.15 | — | 4.5 ✅ |
| `on-success` / `success` | — | 6.35 | 4.5 ✅ |
| blanco / `warning` | 6.70 | — | 4.5 ✅ |
| `on-warning` / `warning` | — | 10.17 | 4.5 ✅ |
| blanco / `danger` | 6.72 | — | 4.5 ✅ |
| `on-danger` / `danger` | — | 6.14 | 4.5 ✅ |
| blanco / `info` | 6.63 | — | 4.5 ✅ |
| `on-info` / `info` | — | 6.67 | 4.5 ✅ |
| `control-border` / `surface` | 3.10 | 3.38 | 3.0 ✅ |
| `primary` / `surface` (UI) | 3.11 | 3.69 | 3.0 ✅ |
| `focus` / `surface` | 3.11 | 3.69 | 3.0 ✅ |

### Colores base que NO pueden cumplir y regla derivada

| Color base | Ratio que falla | Regla |
|---|---|---|
| `#A69B8C` | 2.16–2.38 | Solo `--color-muted` decorativo. Nunca texto. |
| `#A47750` | texto 3.43; blanco encima 3.94 | `--color-primary` solo fill/UI ≥3:1. Texto → `#6E4A2A`. Texto encima → `#1A140E`. |
| `#8B88A3` | 4.28 sobre `#2A2733` | `--color-muted` decorativo. Texto → `#9A97B0`. |
| `#A855F7` | texto 3.69; blanco encima 3.96 | `--color-primary` solo fill/UI. Texto → `#C9A0FF`. Texto encima → `#17121F`. |
| Blanco sobre `#7CA982/#60A5FA/#F87171/#FBBF24` | 2.54–2.77 | Prohibido. Encima → `#17121F`. |
| `#D8D0C3` / `#4A4458` como borde de control | 1.21 / 1.57 | `--color-line` solo decorativo. Controles → `--color-control-border`. |

## 7. Estados del tema claro

El desarrollador solo dio estados para oscuro. Se proponen estados claros propios (tierras, verdes y azules desaturados), todos con blanco encima ≥6.1.

| Estado | Fill claro | Soft | Ink | On |
|---|---|---|---|---|
| success | `#3F6B4B` | mix 12% ≈ `#D4D5C8` | `#2E5638` | `#FFFFFF` |
| warning | `#7C5410` | mix 12% ≈ `#DCD2C2` | `#6B4A0F` | `#FFFFFF` |
| danger | `#9E3B31` | mix 12% ≈ `#E2D0C5` | `#7C2E26` | `#FFFFFF` |
| info | `#36617E` | mix 12% ≈ `#D3D4CF` | `#274F6A` | `#FFFFFF` |
| secondary | `#6E665A` | mix 12% ≈ `#D9D4CA` | `#4F4940` | `#FFFFFF` |

En oscuro se conservan los fills del desarrollador; solo los `-ink` se aclaran para texto sobre soft (`#A6CCA9`, `#FCD34D`, `#FCA5A5`, `#93C5FD`), porque el fill base sobre su propio soft cae a 4.0–4.3.

## 8. Calidad IV

- **Se conservan exactamente**: `tokens.css:100-111` (`#94a3b8`, `#4ade80`, `#38bdf8`, `#c084fc`, `#facc15`) y todo `app.css:25-128`, incluido el fondo `#1e293b`. No se retematiza.
- **No tocar** `--quality-color` ni las sombras neón: el panel es autocontenido y funciona igual en claro y oscuro.
- **Sí tocar (fuera de la paleta de calidad)**:
  - `app.css:180-186` `.pokemon-card-assigned`: sustituir `rgba(243,244,246,.65)` / `.dark rgba(17,24,39,.45)` por `color-mix(in oklab, var(--color-surface-sunken) 65%, transparent)`. Elimina la segunda regla `.dark`.
  - `app.css:14` `.dark { color-scheme: dark; }` es duplicado de `tokens.css:116`; se puede eliminar sin efecto.
- **Deuda preexistente detectada**: en claro, `--quality-text` (`#64748b`, `#15803d`, `#0284c7`, `#9333ea`) sobre el panel fijo `#1e293b` da 2.7–3.6:1 (el valor de IV es pequeño). No lo provoca el nuevo tema. Se conserva por instrucción; queda como deuda documentada.

## 9. Badges de tipo y fondos sólidos

### Badge (nunca fondo sólido)

```css
.tipo-badge {
  background-color: color-mix(in oklab, var(--type-color) 14%, var(--color-surface));
  border: 1px solid color-mix(in oklab, var(--type-color) 45%, var(--color-line));
  color: var(--color-ink);
}
.tipo-badge::before {
  content: "";
  width: .5rem; height: .5rem;
  border-radius: var(--radius-full);
  background: var(--type-color);
}
```

- Claro: mínimo **9.05:1** (siniestro, `#DAD2C7`).
- Oscuro: mínimo **7.95:1** (eléctrico, `#4A433C`).
- La identidad del tipo la aportan borde + punto sólido, no el texto. Se elimina el `color-mix(tipo 60%, ink)` de `components.css:94`.

### Fondo sólido del tipo (tarjeta de ataque, filas de senda)

```blade
style="background-color: var(--type-{{ $slug }}); color: var(--type-{{ $slug }}-on)"
```

`--type-*-on` se elige por luminancia: `#17120C` si `L > 0.179`, `#FFFFFF` si no. Peor caso: veneno `#A040A0` → blanco 5.62. **Ningún tipo baja de 5.62:1.**

### Texto de color de tipo sobre superficie

`sendas/index.blade.php:27,39,43` y `sendas/show.blade.php:10,101,104` usan `color: var(--type-*)` como texto. Migrar a `color: var(--color-ink)` + borde/punto de tipo o a `--type-*-on` solo si hay fondo sólido.

## 10. Combate: arquitectura de integración

### Restricciones respetadas

- No se reordena `@layer properties, theme, base, bootstrap, components, utilities;`.
- No se añade `sass` ni se compila Bootstrap por SCSS.
- `combate.css` sigue siendo la única entrada que carga Bootstrap.

### Hotfix mínimo (P0, independiente del tema)

Al final de `combate.css`, **sin capa**:

```css
a { text-decoration: none; }
body { font-family: inherit; }
```

### Objetivo: puente `--bs-* → tokens` (una sola definición)

En `combate.css`, un único bloque `:root` que referencia tokens + un bloque `.dark` **solo con tripletas RGB** (Bootstrap las exige numéricas):

```css
:root {
  --bs-body-bg: var(--color-canvas);
  --bs-body-color: var(--color-ink);
  --bs-body-color-rgb: 48, 46, 42;
  --bs-emphasis-color: var(--color-ink);
  --bs-secondary-color: var(--color-ink-muted);
  --bs-secondary-bg: var(--color-surface-sunken);
  --bs-tertiary-bg: var(--color-surface);
  --bs-border-color: var(--color-line);
  --bs-border-color-translucent: color-mix(in oklab, var(--color-line) 70%, transparent);
  --bs-card-bg: var(--color-surface);
  --bs-card-color: var(--color-ink);
  --bs-card-border-color: var(--color-line);
  --bs-card-border-radius: var(--radius-card);
  --bs-list-group-bg: var(--color-surface);
  --bs-list-group-color: var(--color-ink);
  --bs-list-group-border-color: var(--color-line);
  --bs-link-color: var(--color-primary-ink);
  --bs-link-hover-color: var(--color-primary-hover);
  --bs-primary: var(--color-primary);
  --bs-secondary: var(--color-secondary);
  --bs-success: var(--color-success);
  --bs-info: var(--color-info);
  --bs-warning: var(--color-warning);
  --bs-danger: var(--color-danger);
  --bs-primary-rgb: 164, 119, 80;
  --bs-secondary-rgb: 110, 102, 90;
  --bs-success-rgb: 63, 107, 75;
  --bs-info-rgb: 54, 97, 126;
  --bs-warning-rgb: 124, 84, 16;
  --bs-danger-rgb: 158, 59, 49;
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
```

Todo lo demás resuelve `var()` en tiempo de uso: no hay una segunda definición de la paleta oscura. El bloque `.dark` de `combate.css:383-429` se elimina.

### Conflictos concretos y resolución

| Conflicto | Causa | Resolución |
|---|---|---|
| `.border` gana a `border-line` | `.border{...!important}` en capa `bootstrap` | `--bs-border-color: var(--color-line)`; `.border` sale con el color del tema |
| `.card` pinta texto `#212529` | `_card.scss` usa `--bs-body-color` | `--bs-card-color: var(--color-ink)` |
| `.alert-*` con paleta claro de Bootstrap | colores compilados, no `!important` | En `@layer components`: redefinir `--bs-alert-*` para info/success/warning/danger/secondary |
| `.form-select` fondo/arrow Bootstrap | usa `--bs-body-bg` y SVG `#343a40` | `--bs-form-select-bg-img` con data-URI por tema, o `background-image` en `components`; usar `--color-control-*` |
| `text-muted` | `color: var(--bs-secondary-color)!important` | Resuelto por el puente: `--bs-secondary-color: var(--color-ink-muted)` |
| `bg-primary` / `bg-info` / `bg-success` | utilidades `!important` con `--bs-*-rgb` | Puente RGB + `text-on-*` en el markup |
| `text-bg-*` | `!important`, no se puede vencer | Migrar markup: presentadores devuelven clases de token |
| `.btn-outline-secondary` | colores compilados | En `components`: redefinir `--bs-btn-*` con `ink`, `control-border`, `surface-sunken` |
| `.progress` track | `--bs-progress-bg` | `var(--color-surface-sunken)` |
| `.modal-content` | `.dark .modal-content` hardcodeado | `--bs-modal-bg: var(--color-surface-raised)`; borrar el bloque `.dark` |
| `.btn-close` | flecha negra en oscuro | Mantener solo `filter: invert(1) ...` bajo `.dark` |

### Tokenización del resto de `combate.css`

| Grupo (líneas) | Hex actuales | Sustitución |
|---|---|---|
| HP (12-22) | `#dc3545/#ffc107/#198754` | `--color-danger/-warning/-success` |
| stage (24-32) | verdes/rojos Bootstrap | `--color-success-soft/-ink`, `--color-danger-soft/-ink` |
| climas (34-68) | 6 tríos | `color-mix(tipo semántico 18-22%, surface)` + `-ink` + borde 45% |
| turno activo (70-74,244-247) | amarillos | `--color-warning` + `--color-warning-soft` |
| turnos/vanguardia (77-87,222-234) | azul/rojo fijos | aliado = `info`; rival = `danger` |
| targeted (89-92) | `#ff000033/#dc3545` | `color-mix(danger 20%, transparent)` + `--color-danger` |
| pokemon-card (95-103) | `#fff/#1f2937` | `background-color: var(--color-surface)` (sin `.dark`) |
| debug tooltip (112-219) | slate fijos | `surface-raised/ink/ink-muted/line/warning` |
| vital-label (327-341) | `#495057/#cbd5e1` | `--color-ink-muted` |
| move-best/focus (365-380) | `#0d6efd/#60a5fa` | `--color-primary` / `--color-focus` |
| trails/deltas (461-550) | rojo/cian/azul | `--color-danger/-info/-primary` con `color-mix` |
| animaciones (580,612-616,655,689-697,767,796) | rgba fijos | `color-mix(estado 45-85%, transparent)` |

Resultado: `combate.css` sin hex de tema.

### Hotfix vs objetivo

- **Hotfix P0**: 2 reglas sin capa (subrayado + tipografía). Se puede aplicar antes del tema.
- **Objetivo P1**: puente + tokenización + migración de `text-bg-*`. Cabe en esta tarea sin dependencias.
- **Fuera de alcance justificado**: quitar Reboot de raíz requiere `$enable-reboot:false` y compilar SCSS (`sass`). Prohibido por «no añadir dependencias». El aislamiento por capas + puente cubre el síntoma y el 95% del color.

### Iconos

- **Navbar `fa-seedling`**: no es CSS; `app.blade.php:30-44` no lo emite en `/combate*`. **Decisión pendiente.**
- **Icono de tipo en la tarjeta de ataque** (`moves-panel.blade.php:46`): el disco del WebP se funde con el fondo sólido del mismo tipo. Solución: placa de superficie alrededor del `<img>`:

```css
.type-plate {
  display: inline-flex;
  padding: 2px;
  border-radius: var(--radius-full);
  background: color-mix(in oklab, var(--color-surface-raised) 88%, transparent);
  box-shadow: 0 0 0 1px var(--color-line);
}
```

## 11. Livewire — interfaz pendiente

- No hay evidencia de que Livewire cause el subrayado ni el icono: el subrayado es cascada y el icono es Blade.
- El toggle es Alpine inline (`app.blade.php:86-91`); no hay store ni listener.
- **Interfaz propuesta, agnóstica al resultado de la investigación**:

```js
window.dispatchEvent(new CustomEvent('theme:cambiado', { detail: { tema: 'light'|'dark' } }));
```

```js
this._onTheme = () => { if (this.detailPokemon) this.renderDetailCharts(); };
window.addEventListener('theme:cambiado', this._onTheme);
// destroy(): window.removeEventListener('theme:cambiado', this._onTheme);
```

- Si el morph de Livewire destruye el canvas, el enganche será `Livewire.hook('morph.updated', ...)` o `livewire:navigated`; no se implementa a ciegas. El contrato `theme:cambiado` se mantiene.

## 12. Plan por archivos

| # | Archivo | Cambio | Responsable |
|---|---|---|---|
| 1 | `resources/css/tokens.css` | Sustituir valores claro (`:10-112`) y oscuro (`:115-154`); añadir `secondary*`, `on-*`, `control-*`, `muted`, `chart-*`; conservar nombres y `quality/type` | frontend |
| 2 | `resources/css/components.css` | `.tipo-badge` nueva fórmula (`:84-98`); añadir `.type-plate`; `.focus-ring` sin cambios | frontend |
| 3 | `resources/css/app.css` | Sin retematizar calidad; `.pokemon-card-assigned` → tokens (`:180-186`); eliminar `.dark{color-scheme}` duplicado (`:14`) | frontend |
| 4 | `resources/css/combate.css` | Hotfix sin capa; puente `--bs-*`; overrides alert/select/btn; tokenizar hex; borrar `.dark` (`:383-429`) | frontend |
| 5 | `resources/views/layouts/app.blade.php` | Toggle: inicializar icono desde `classList` real y despachar `theme:cambiado`; opcional `meta theme-color` | frontend |
| 6 | `resources/views/layouts/auth.blade.php` | `meta theme-color`; inicialización coherente | frontend |
| 7 | `resources/views/equipos/index.blade.php` | Listener de tema, `destroy()`; usar `--color-chart-*` | frontend |
| 8 | `src/Shared/Tipos/TipoPokemon.php` | Añadir `textoSobreColor(): string` (luminancia WCAG) | backend |
| 9 | `resources/views/partials/tipos-tokens.blade.php` | Emitir `--type-<slug>-on` además de `--type-<slug>` | frontend |
| 10 | `resources/views/livewire/partials/moves-panel.blade.php` | `style` con `--type-*-on`; `text-bg-*` → clase de token; placa del icono | frontend |
| 11 | `resources/views/livewire/partials/items-panel.blade.php` | `text-bg-primary` → `bg-primary text-on-primary` | frontend |
| 12 | `preparacion-panel.blade.php`, `battle-field.blade.php`, `turn-bar.blade.php`, `battle-log.blade.php` | `.form-select`, `.alert-*`, `text-muted` resueltos por CSS; retirar color redundante | frontend |
| 13 | `resources/views/livewire/_pokemon-card.blade.php` | `bg-primary/bg-info/bg-success` con texto → añadir `text-on-*` | frontend |
| 14 | `app/Livewire/Presenters/MovesPreviewPresenter.php` | `categoriaColor`/`colorEfectividad` devuelven clases de token | backend |
| 15 | `src/Battle/Domain/CombatienteVista.php` | `posturaClase` y clases de partes a tokens | backend |
| 16 | `src/Exploraciones/Presentation/*` | Sustituir clases Tailwind fijas + `dark:` por tokens de estado | backend |
| 17 | `resources/views/sendas/*` | Texto de tipo → ink + borde/punto; fondos sólidos → `--type-*-on` | frontend |
| 18 | `docs/adr/0004-tema-stone-ink-lavender-grey.md` | ADR de la decisión y del puente Bootstrap | documenter |
| 19 | `docs/conventions.md` (`:164`) | Actualizar «Sin CSS personalizado» → tokens + componentes permitidos | documenter |

**No tocar:** `resources/views/habitats/partials/modal-formacion.blade.php`, `resources/views/partials/leyenda-ivs-evs.blade.php`, `resources/views/gimnasios/show.blade.php`, `tests/`, `.env`, `TASK-049/`, `TASK-050/`.

## 13. Verificación estática y criterios de aceptación

### Comandos

```bash
npm run build
php -l src/Shared/Tipos/TipoPokemon.php
php -l src/Battle/Domain/CombatienteVista.php
php -l app/Livewire/Presenters/MovesPreviewPresenter.php
php -l src/Exploraciones/Presentation/ResultadoExploracionVista.php
php -l src/Exploraciones/Presentation/PresentadorExploraciones.php
php -l src/Exploraciones/Presentation/ExploradorVista.php
vendor/bin/pint --dirty --format agent
```

### Greps de control

```bash
head -1 resources/css/app.css
grep -n '#[0-9a-fA-F]\{3,8\}' resources/css/combate.css
grep -rn 'text-bg-' resources/views
grep -rn 'text-white\|bg-emerald\|bg-green-\|bg-amber-\|bg-red-\|bg-gray-' src app resources/views
grep -rn 'dark:' src app
grep -c '^\.dark' resources/css/tokens.css
grep -n 'color-scheme: dark' resources/css
grep -rn '#1e293b' resources/css
grep -rn 'fa-seedling' resources/views
```

### Criterios de aceptación

1. Los 27 nombres de token existentes siguen existiendo; ningún consumidor cambia de nombre.
2. Todo texto normal (`ink`, `ink-muted`, `ink-subtle`, `-ink`) ≥4.5:1 sobre `canvas`/`surface`/`soft`; `ink-subtle` solo sobre `surface`/`canvas`.
3. `on-primary` y `on-{success,warning,danger,info}` ≥4.5:1 sobre su fill.
4. Bordes de control y anillo de foco ≥3:1.
5. Ningún `text-primary`/`text-secondary` para texto normal; solo `primary-ink`/`secondary-ink`.
6. `/combate`: sin subrayado en enlaces, tipografía heredada, alerts/cards/selects/modal con tokens en claro y oscuro.
7. `combate.css` no redefine la paleta oscura; único bloque `.dark` es el de tripletas RGB.
8. `text-bg-*` eliminado del markup.
9. Badge de tipo ≥7:1 en ambos temas; fondo sólido ≥5.6:1.
10. Los cinco colores de calidad y `#1e293b` permanecen idénticos.
11. Gráficos se reconstruyen al cambiar tema y no dejan listeners tras `destroy()`.
12. Cero archivos de `tests/`, cero dependencias nuevas, cero cambios en los tres archivos con cambios locales.
13. `npm run build` termina sin errores y `pint` no reporta cambios pendientes.

## 14. Riesgos y rollback

| Riesgo | Prob. | Impacto | Mitigación / rollback |
|---|---|---|---|
| Inversión `surface` < `canvas` percibida como error de elevación | media | medio | Documentar en ADR; `raised` más claro para modales; revisión visual |
| `on-primary` oscuro cambia el look de 83 botones/badges | alta | medio | Aprobación de diseño; rollback = revertir `tokens.css` |
| Tripletas RGB del puente se desincronizan de los tokens | media | bajo | Comentario «derivado de…» en `combate.css`; grep de contraste manual |
| Overrides de `.alert`/`.btn-outline` pierden si cambia Bootstrap | baja | medio | Están en `components`, posterior a `bootstrap`; verificación visual |
| Quedan `text-bg-*` o `bg-*` sin migrar | media | bajo | Greps de control |
| Gráficos no se refrescan o fuga de listener | media | bajo | `destroy()` + `removeEventListener`; prueba manual |
| `--quality-text` claro ilegible (deuda previa) | alta | bajo | Se conserva por instrucción; deuda documentada |
| Los 3 archivos con cambios locales cambian de render | alta | bajo | No editarlos; avisar a TASK-049/050 |
| Livewire invalida el DOM del combate | desconocida | medio | Interfaz `theme:cambiado` desacoplada |

**Rollback global:** revertir `tokens.css`, `components.css`, `app.css` y `combate.css` restaura el tema anterior; los cambios PHP de presentadores son independientes y reversibles por archivo. No hay migraciones ni datos.

## 15. Preguntas abiertas

1. **Botón de cosecha en `/combate`**: ¿se mantiene oculto (recomendado) o se muestra deshabilitado durante la animación?
2. **`on-primary` oscuro**: ¿se aprueba texto oscuro sobre `#A47750`/`#A855F7`? Es la única vía AA sin oscurecer el fill.
3. **Hover del primario**: se propone aclarar (`#B0855F` / `#C084FC`). ¿Se acepta?
4. **`secondary` claro**: se propone `#6E665A` (tierra neutra). ¿Se aprueba?
5. **`--quality-text` en claro**: se conserva exactamente (deuda 2.7–3.6:1) por instrucción.
6. **`surface` más oscuro que `canvas`**: se confirma la inversión editorial como definitiva.
7. **Livewire**: pendiente; el diseño no depende de él.
8. **Icono de tipo**: ¿se aprueba la placa `.type-plate` para los 18 tipos?
