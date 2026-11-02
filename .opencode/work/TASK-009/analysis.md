# TASK-009 — Análisis

- **Estado:** `COMPLETADO` (con limitaciones de cobertura declaradas)
- **Responsables:** `analyst` (A: imágenes Pokémon; B: estilos globales)
- **Fecha:** 2026-11-02
- **Decisiones previas:** refactor visual completo; 128/96/64 son tamaños de visualización CSS; los assets no se redimensionan; el theme definitivo es posterior.

> **Limitaciones de cobertura declaradas por los analistas:**
> - Análisis A: no se releyó por completo `resources/views/habitats/show.blade.php` (2.282 líneas); sus 16 `<img>` sí quedaron localizadas y contextualizadas.
> - Análisis B: no se revisó en detalle `docs/architecture.md` ni el resto de `.ai/rules/*`; las conclusiones de arquitectura visual no dependen de ello. No se pudo confirmar si Vite deduplica `bootstrap.bundle` entre entradas (no se ejecutó build).
> - Ningún analista modificó archivos ni ejecutó tests.

---

## Análisis A — Imágenes de Pokémon

### Resumen ejecutivo

- Existen **~34 renders de imagen de Pokémon** en 12 vistas; el resto de `<img>` son iconos de tipo, caramelos, medallas, objetos o imágenes de hábitat.
- **Todos los assets** (`public/images/iconos_webp/*.webp` y `shiny/*_s.webp`, 1.200 + 1.137 archivos) son **nativamente 128×128**. Esto valida 128px como tamaño máximo sin pérdida y convierte cualquier `w-96` (384px) en un **realezo 3× borroso**.
- Tamaños actuales dispersos: **384, 128, 96, 72, 64, 48, 40, 32, 20, 18 px** y varios `w-full` sin altura.
- Solo 3 clases propias tocan estas imágenes (`.pokemon-sprite`, `.medalla-icon`, `.icono`), y `.pokemon-sprite` **no define tamaño** (solo animación).
- Hay **dos fallbacks rotos** que apuntan a `/images/iconos/*.png` (directorio inexistente) y **cuatro estrategias distintas de fallback shiny**.
- El único caso de 128px real hoy es `reclutamiento`; el resto de contextos principales está en 72/96.
- Riesgos principales: CLS por `w-full` sin altura, desbordes al fijar 128 en la rejilla de Pokédex (9 columnas), y `loading="lazy"` en sprites above-the-fold de combate.
- La decisión «combate = 128» choca con «varios Pokémon = 96», porque el campo de combate muestra hasta 12 sprites simultáneos.

### Inventario de renders

| Contexto | Archivo:línea | Tamaño actual | Contenedor | Simultáneas | Destino | Notas |
|---|---|---|---|---|---|---|
| Combate · tarjeta de combatiente | `livewire/_pokemon-card.blade.php:110` | `style="width:72px;height:72px"` + `.pokemon-sprite` | `.pokemon-card` (min-height 96px), `.battle-teams` 4 columnas (`col-6 col-md-3`) | 1–12 | **128** | Decisión explícita «combate». `loading="lazy"`; fallback shiny→normal inline |
| Combate · chips de postura | `livewire/partials/preparacion-panel.blade.php:27` | `width=18 height=18` (atributos) | `d-inline-flex` chip + `<select>` | 1–6 | **64** (o excluir) | Micro-icono de fila; sin `loading` |
| Combate · prioridad por Pokémon | `livewire/partials/preparacion-panel.blade.php:50` | `width=32 height=32` | Tarjeta `col-12 col-md-6 col-xl-4` | 1–6 | **64** | Único con `loading="lazy"` en panel |
| Combate · orden de turno | `livewire/partials/turn-bar.blade.php:7` | `style="width:20px;height:20px"` | Badge `rounded-pill` | 2–12 | **32 / excluir** | Icono dentro de badge; 64 rompería la píldora |
| Combate · capturas (modal victoria) | `livewire/combate.blade.php:162-170` | `w-16 h-16` (64) | `relative inline-block` + `flex flex-wrap` | 1–N | **64** | Ya correcto; fallback shiny vía `$captura['onerror']` |
| Exploraciones · evento (encontrado/huida/encuentro) | `exploraciones/_evento.blade.php:28,40,196` | `w-10 h-10` (40) | `<li class="flex items-center gap-3">` | 1 | **64** | Fila de diario; subir a 64 alinea con «miniaturas» |
| Exploraciones · pila de salvajes | `exploraciones/_evento.blade.php:66,100` | `w-8 h-8` (32) | `flex -space-x-2` (avatares apilados) | 2–N | **64** (o 32) | El solapamiento `-space-x-2` se rompe a 64 → caso ambiguo |
| Exploraciones · explorador | `exploraciones/_explorador.blade.php:32-37` | `w-12 h-12` (48) | `relative shrink-0` | 1–3 | **64** | Tiene badge shiny encima |
| Exploraciones · derrotados | `exploraciones/index.blade.php:274` | `w-16 h-16` (64) | `flex flex-wrap gap-3` | 1–N | **64** | `opacity-50 grayscale` |
| Exploraciones · capturados | `exploraciones/index.blade.php:310` | `w-16 h-16` (64) | `flex flex-wrap gap-3` | 1–N | **64** | Ya correcto |
| Equipos · slots de equipo | `equipos/index.blade.php:112-118` | `w-full h-16` (alto 64, ancho fluido) | `grid grid-cols-3 gap-1` | 3 por equipo, N equipos | **96** | Sin `width` fijo → deforma proporción si el contenedor es ancho |
| Equipos · inventario de reclutados | `equipos/index.blade.php:346-352` | contenedor `w-24 h-24` (96) + `w-full h-full` | `grid 2→6 columnas` | 10–100+ | **96** | Ya correcto; asignado va `grayscale opacity-60` |
| Equipos · huevos en incubación | `equipos/index.blade.php:534-537` | contenedor `w-12 h-12` (48) | Fila `flex items-center gap-3` | 1–N | **64** | Miniaturas de lista |
| Equipos · huevos disponibles | `equipos/index.blade.php:584-587` | contenedor `w-16 h-16` (64) | `grid grid-cols-2 sm:grid-cols-3` | 2–N | **96** | Rejilla de selección |
| Equipos · modal reemplazo de slot | `equipos/index.blade.php:825-828` | `w-12 h-12` (48) | Botón fila | 1–3 | **64** | |
| Equipos · detalle (retrato principal) | `equipos/index.blade.php:867-872` | contenedor `w-24 h-24` (96) | `.pokemon-quality` + modal `max-w-90vw` | 1 | **128** | Contexto «detalle» explícito; fallback shiny_s→normal |
| Equipos · opciones de evolución | `equipos/index.blade.php:938` | `w-12 h-12` (48) | `grid grid-cols-2 sm:grid-cols-3` | 1–3 | **96** | Rejilla de selección |
| Pokédex · rejilla | `pokedex/index.blade.php:202-208` | `w-full h-full` dentro de `aspect-square p-2`; grid `3/6/9` | Tarjeta `rounded-xl` | 27+ | **96** ⚠ | Fluido: a 9 col en 1024px el hueco es ~81px → fijar 96 **desborda** |
| Pokédex · modal detalle | `pokedex/index.blade.php:295-301` | `w-24 h-24` (96) | Header modal `max-w-lg` | 1 | **128** | Decisión «Pokédex detalle» |
| Pokédex · familia evolutiva | `pokedex/index.blade.php:388-393` | `w-8 h-8` (32) | `flex flex-wrap gap-2` | 2–4 | **64** | Mini-lista dentro del modal |
| Reclutamiento · rejilla | `reclutamiento/index.blade.php:33-39` | contenedor `w-32 h-32` (128) + `w-full h-full` | `grid 2→9 columnas` | 2–50+ | **128** | Ya en 128; choca con regla «varios→96» |
| Reclutado · detalle | `reclutado/show.blade.php:10-15` | `w-24 h-24` (96) | Card `flex items-center gap-6` | 1 | **128** | `onerror` a `/images/iconos/{id}.png` **roto** |
| Reclutado · próxima evolución | `reclutado/show.blade.php:33` | `w-16 h-16` (64) | `flex items-center gap-4` | 1 | **64** | |
| Gimnasios · formación | `gimnasios/show.blade.php:193-195` | `w-12 h-12` (48) | Fila `flex items-center gap-3` | 1–3 | **64** | |
| Gimnasios admin · selector de especie | `gimnasios/admin.blade.php:223-227` | `w-12 h-12` (48) | `grid 3→6 columnas` | 10–100+ | **96** | Rejilla de selección |
| Hábitat · miembros de equipo en card | `habitats/show.blade.php:238-243` | `w-full` **sin altura** | `grid grid-cols-3 gap-0` | 0–3 | **96** | CLS: la altura la fija la imagen; placeholder mide `h-14` |
| Hábitat · favoritos | `habitats/show.blade.php:319-325` | **`w-96` = 384px** ⚠ | Botón + card | 1–N | **96** | Realezo 3× de un asset 128; rompe la tarjeta |
| Hábitat · lista por nivel | `habitats/show.blade.php:405` | `w-full h-full` en `w-24 h-24` (96) | `flex flex-wrap gap-2` | 1–N | **96** | `unknown.webp` usa el mismo contenedor |
| Hábitat · rejilla de especies | `habitats/show.blade.php:677-682` | contenedor `w-16 h-16` (64) | Botón card | 1–N | **96** | |
| Hábitat · formación de equipo | `habitats/show.blade.php:731` | **`w-96` = 384px** ⚠ | Fila `flex items-center gap-3` | 1–3 | **64** | Degrada fila y desborda |
| Hábitat · familia (rejilla) | `habitats/show.blade.php:847-852` | `w-full h-full` en `aspect-square` | Grid de familias | 1–N | **96** | Fluido estable por `aspect-square` |
| Hábitat · miembros de familia | `habitats/show.blade.php:903-908` | `w-full h-full` en `aspect-square` | Grid | 1–N | **96** | |
| Hábitat · formación mazmorra | `habitats/show.blade.php:1056` | `w-12 h-12` (48) | Fila | 1–3 | **64** | |
| Hábitat · granja (familia) | `habitats/show.blade.php:1144` | **`w-96` (384) en contenedor `w-96`** ⚠ | Cabecera de familia | 1 por familia | **128** | Único w-96 posiblemente intencional (hero), pero borroso |
| Legado · lista reclutados | `reclutados/index.blade.php:82` | `class="icono"` → 170px en CSS no cargado; tamaño real intrínseco | `div.reclutado-item` | N | **excluir** | `src="iconos/{name}.png"` relativo **roto**; vista no enrutada (`routes/web.php:40`) |

**Total:** 34 renders de Pokémon; 3 con 384px; 1 con 72px; 5 con 48px; 3 con 40/32; 2 con 20/18; 2 sin altura explícita; 6 fluidos por `w-full`/`h-full`.

### Clases y estilos implicados

- `.pokemon-sprite` — `resources/css/combate.css:399-400, 481`: **solo animación** (`impacto-sprite`), no fija tamaño. El tamaño vive en el `style` inline de `_pokemon-card:110`.
- `.pokemon-card` — `combate.css:25-30`: `min-height: 96px`, `overflow: hidden`, sin `width`. Crecer a 128px de sprite aumenta la altura de la tarjeta y puede recortar contenido.
- `.battle-teams .pokemon-card` — `combate.css:76-79`: `max-width: 97%`.
- `.medalla-icon` — `resources/css/app.css:12-14`: `width: 96px`. **No es Pokémon** (medalla de gimnasio); usado solo en `gimnasios/index.blade.php:53`.
- `.pokemon-quality*`, `.pokemon-card-quality`, `.pokemon-modal-quality` — `app.css:16-174`: marcos/neón; **no** dimensionan la imagen.
- `.icono` — `public/css/app.css:45`: `width: 170px; float:left`. **CSS muerto**: los layouts solo cargan `@vite(['resources/css/app.css', …])` (`layouts/app.blade.php:8`), no `public/css/app.css`. Solo lo usa la vista legada `reclutados/index`.
- Estilos inline: `_pokemon-card:110` (72px), `preparacion-panel:27,50` (atributos 18/32), `turn-bar:7` (20px), `combate:240` (28px caramelo), `moves-panel:36` (40px tipo).
- `object-contain` presente en casi todos; `img-fluid` solo en combate.
- No existe ningún componente Blade de imagen/sprite reutilizable: cada vista construye `<img>` y URL a mano.

### Casos ambiguos y recomendación

1. **Combate: 128 explícito vs «varios → 96».** El campo muestra 1–12 sprites (`battle-field.blade.php:33-72`) en 4 columnas (`col-6 col-md-3`). A 128px, en móvil cada columna mide ~50% del viewport (~170px) → cabe; en desktop la columna mide ~200px → cabe. **Recomendación:** aplicar 128 (decisión explícita) y compensar con `min-height`/padding; si el architect prioriza densidad, 96 es la alternativa segura.
2. **Pokédex rejilla: 96 fijo desborda.** `pokedex/index.blade.php:179` usa `lg:grid-cols-9`; a 1024px el hueco es ~97px y el `p-2` deja ~81px útiles. **Recomendación:** mantener `w-full h-full` con un **contenedor de 96px** solo desde `sm`, o bajar a `lg:grid-cols-8`; no fijar 96 al `<img>` directamente.
3. **Reclutamiento: 128 (ya) vs regla de rejilla.** `reclutamiento/index.blade.php:33` ya es 128 y está nombrado en el grupo 128. **Recomendación:** dejar 128; a 2xl 9 columnas el hueco es ~158px, cabe.
4. **`w-96` de hábitats (319, 731, 1144).** 384px sobre asset 128 = borroso y desborda en 319/731. **Recomendación:** 319 y 731 → **96**; 1144 (granja, hero por familia) → **128** máximo. Ninguno debe quedar en 384.
5. **Pila de salvajes `-space-x-2` (32px).** Subir a 64 rompe el solapamiento. **Recomendación:** mantener 32–40 para la pila y 64 para el icono principal del evento.
6. **Micro-iconos de badge (turn-bar 20, estados 20, postura 18).** No son «sprites de contexto», son iconos dentro de píldoras. **Recomendación:** **excluirlos** de la escala 128/96/64 y documentarlos como «icono inline ≤32».
7. **Huevos (48/64).** Son Pokémon no revelados; **recomendación:** 64 en listas, 96 en rejilla de selección.

### Archivos candidatos agrupados por contexto

**Combate (128 + ajuste de tarjeta)**
- `resources/views/livewire/_pokemon-card.blade.php`
- `resources/css/combate.css` (min-height / layout de tarjeta)
- `resources/views/livewire/combate.blade.php` (capturas, 64)

**Detalle de un Pokémon (128)**
- `resources/views/equipos/index.blade.php` (modal ~867)
- `resources/views/pokedex/index.blade.php` (modal ~295)
- `resources/views/reclutado/show.blade.php`

**Rejillas y selección (96)**
- `resources/views/pokedex/index.blade.php` (rejilla ~202)
- `resources/views/equipos/index.blade.php` (inventario ~346, huevos disp. ~584, evolución ~938)
- `resources/views/habitats/show.blade.php` (~238, 319, 405, 677, 847, 903, 1144)
- `resources/views/reclutamiento/index.blade.php`
- `resources/views/gimnasios/admin.blade.php`
- `resources/views/equipos/index.blade.php` (slots de equipo ~112)

**Miniaturas / listas / exploraciones (64)**
- `resources/views/exploraciones/_evento.blade.php`, `_explorador.blade.php`, `index.blade.php`
- `resources/views/equipos/index.blade.php` (~534, 825)
- `resources/views/gimnasios/show.blade.php` (~193)
- `resources/views/habitats/show.blade.php` (~731, 1056)
- `resources/views/livewire/partials/preparacion-panel.blade.php`
- `resources/views/reclutado/show.blade.php` (~33)

**Micro-iconos (fuera de escala, solo documentar)**
- `resources/views/livewire/partials/turn-bar.blade.php`
- `resources/views/livewire/partials/preparacion-panel.blade.php:27`
- `resources/views/livewire/_pokemon-card.blade.php:43`

**Muerto / no tocar**
- `resources/views/reclutados/index.blade.php`
- `public/css/app.css` (`.icono`)

### Riesgos y evidencias (imágenes)

| Sev. | Riesgo | Evidencia | Mitigación |
|---|---|---|---|
| **Alto** | Realezo 3× borroso y desbordes | `habitats/show.blade.php:319,731,1144` (`w-96`=384px) sobre assets 128×128 verificados | Bajar a 96/128 |
| **Alto** | Fallbacks a PNG inexistente | `pokedex/index.blade.php:771,778`; `reclutado/show.blade.php:14`; `ReclutadoVistaPresenter.php:61-62,69` → `/images/iconos/…`; `ls public/images/iconos` → *No such file or directory* | Retirar fallback PNG o crear ruta; dejar `onerror` a `0.webp` |
| **Alto** | CLS por imagen sin altura | `habitats/show.blade.php:238-243` (`w-full` sin height); `pokedex:202` fluido | `width`/`height` o `aspect-square` en contenedor |
| **Medio** | 128px rompe rejilla Pokédex | `pokedex/index.blade.php:179` (9 cols) vs `:208` `w-full` | Contenedor 96 / menos columnas |
| **Medio** | Inconsistencia shiny | 4 estrategias: `_pokemon-card:110`, `combate:169`, `equipos:875`, hábitats sin shiny | Unificar helper de URL + fallback |
| **Medio** | `loading="lazy"` en above-the-fold de combate | `_pokemon-card.blade.php:110` | `loading="eager"` + `fetchpriority="high"` en vanguardia |
| **Medio** | `alt=""` en imágenes informativas | `preparacion-panel:27` (`alt=""` con nombre en `title`) | `alt` con nombre salvo decorativas |
| **Bajo** | Altura de tarjeta de combate al crecer el sprite | `combate.css:25` `min-height:96px`; `overflow:hidden` | Ajustar `min-height`/padding, no recortar |
| **Bajo** | Dark mode: imágenes transparentes sobre fondos oscuros | `_evento:72`, `_explorador` contenedores | Verificar contraste del webp (fondo transparente) |
| **Bajo** | Vista legada con `class="icono"` sin CSS cargado | `reclutados/index.blade.php:82` + `public/css/app.css:45` no referenciado | Eliminar vista o migrarla; no incluir en refactor |

---

## Análisis B — Sistema de estilos global

### Resumen ejecutivo

- 44 vistas Blade, 12.143 líneas, 2 hojas CSS (680 líneas efectivas), sin Tailwind config (CSS-first de Tailwind 4) y **sin capa de tokens**: no hay ni una variable de tema de aplicación (`--color-*`, `--radius`, `--font`) fuera de las internas de calidad IV.
- **Convivencia real: Tailwind gobierna 35 de 44 vistas; Bootstrap existe solo en el combate** (`combate.css` importa `bootstrap.min.css` y únicamente `combate_page.blade.php` lo carga). Pero `app.js` importa `bootstrap.bundle.min.js` **en todas las páginas**, así que se envía el JS de Bootstrap sin su CSS.
- El coste está en las vistas, no en el CSS: **1.910 atributos `class`, 1.586 `dark:`, 220 valores arbitrarios, 142 `text-[10px]`, 94 manipulaciones de estilo por JS**. Dos vistas concentran **5.538 líneas (46%)**.
- Ya existe un intento manual de tokenización: `$cardPanelClass` **duplicado literal en 3 vistas** (`habitats/show:8`, `gimnasios/admin:9`, `gimnasios/show:9`).
- **8 de 10 componentes Blade son código muerto** (0 usos): alert, badge, card, header, modal, sidebar, stat-card. El único usado es `<x-tipo-badge>` (1 vez).
- El combate mezcla Bootstrap y Tailwind en el mismo árbol: `card` + `border-blue-200 dark:border-blue-900/60` + `text-[10px]` (`preparacion-panel.blade.php:2-7`). Hay colisión de escala de espaciado (`p-3`, `mb-3`, `p-4`, `mb-4` difieren entre frameworks).
- Recomendación: **híbrido por fases con capa de tokens CSS**; no eliminar Bootstrap ahora (el combate depende de su JS modal y de 103 reglas propias).
- Riesgo dominante: WIP de **305 archivos** (15 vistas modificadas, `components/tipo-badge` y `partials/` sin trackear) y dos vistas de 3.256/2.282 líneas.

### Inventario de vistas/componentes y hotspots

44 archivos, 12.143 líneas. Agrupación por módulo:

| Módulo | Archivos | Líneas | Vistas |
|---|---|---|---|
| Equipos/Reclutamiento | 5 | ~3.800 | `equipos/index` (3.256), `equipos/partials/movimiento-card` (26), `reclutamiento/index` (290), `reclutado/show` (112), `reclutados/index` (89) |
| Hábitats | 2 | 2.406 | `habitats/show` (2.282), `habitats/index` (124) |
| Combate | 9 | ~1.100 | `combate_page` (624), `livewire/combate` (259), `_pokemon-card` (113), `moves-panel` (143), `preparacion-panel` (80), `battle-field` (77), `turn-bar` (53), `battle-log-modal` (21), `battle-log` (11) |
| Pokédex | 1 | 860 | `pokedex/index` |
| Gimnasios | 3 | 1.116 | `admin` (559), `show` (383), `index` (174) |
| Exploraciones | 6 | ~1.000 | `index` (471), `_evento` (281), `_explorador` (113), `_perdidas` (49), `_diario` (41), `_caramelo` (26) |
| Sendas | 2 | 498 | `show` (435), `index` (63) |
| Misiones | 1 | 447 | `misiones/index` |
| Layouts | 2 | 188 | `app` (149), `auth` (39) |
| Auth/Onboarding/Welcome | 4 | 196 | login (68), register (80), welcome (16), equipo-inicial (32) |
| Componentes | 9 | ~290 | 8 sin uso + `tipo-badge` (26) |
| Partials varios | 1 | 50 | `partials/tipos-badge-script` |
| **Legacy** | 1 | 89 | `reclutados/index` — **código muerto**: `/reclutados` redirige a `/equipos` (`routes/web.php:40`) y la vista usa solo estilos inline |

**Top 20 por tamaño/complejidad:**

1. `equipos/index` 3.256 — Alpine `favoritosApp()`, 75 `text-[10px]`, 11 `style=`, 6 modales propios, gráficos Chart.js.
2. `habitats/show` 2.282 — Alpine `habitatShow()`, 6 modales propios, 359 `dark:`.
3. `pokedex/index` 860 — modal de detalle + scroll infinito.
4. `combate_page` 624 — 500+ líneas de JS inline de animación.
5. `gimnasios/admin` 559 — 79 `dark:`, formularios inline.
6. `exploraciones/index` 471.
7. `misiones/index` 447.
8. `sendas/show` 435 — 91 `dark:`, 17 `text-[10px]`.
9. `gimnasios/show` 383.
10. `reclutamiento/index` 290 — 2 modales propios.
11. `exploraciones/_evento` 281.
12. `livewire/combate` 259 — Bootstrap + Tailwind, 60+ líneas de `x-init`.
13. `gimnasios/index` 174.
14. `layouts/app` 149 — nav, ayuda, dark toggle, level bar.
15. `moves-panel` 143 — todo Bootstrap.
16. `habitats/index` 124.
17. `_pokemon-card` 113 — todo Bootstrap.
18. `_explorador` 113 — lógica de color en `@php`.
19. `reclutado/show` 112.
20. `reclutados/index` 89 (legacy) / `livewire/partials/preparacion-panel` 80.

Casi todo el peso es **HTML+Alpine en la misma vista**; no hay vistas parciales reutilizables salvo 6 partials de exploraciones y 6 de combate.

### Evidencia Bootstrap / Tailwind / CSS propio / inline

#### Dónde se carga qué

| Evidencia | Hallazgo |
|---|---|
| `resources/css/app.css:1` | `@import "tailwindcss"` — único CSS global de la app. |
| `resources/css/app.css:2` | `@source "../../src"` — escanea `src/` (necesario por `TipoBadges`), añadido en el WIP (`git diff`: +1 línea). |
| `resources/css/app.css:4` | `@custom-variant dark (&:where(.dark, .dark *))` — dark mode por clase. |
| `resources/css/combate.css:1` | `@import "bootstrap/dist/css/bootstrap.min.css"` — **Bootstrap completo**. |
| `resources/views/combate_page.blade.php:5-6` | `@push('styles') @vite(['resources/css/combate.css'])` — **Bootstrap solo aquí**. |
| `resources/views/layouts/app.blade.php:8` | `@vite(['resources/css/app.css','resources/js/app.js'])` — Tailwind en todas. |
| `resources/js/app.js:2` | `import 'bootstrap/dist/js/bootstrap.bundle.min.js'` — **JS Bootstrap en todas las páginas**. |
| `resources/js/combate.js:3` | Vuelve a importar `bootstrap.bundle` + `bootstrap.js`; la página de combate carga `app.js` **y** `combate.js`. |
| `resources/js/bootstrap.js:13` | `Livewire.start()` — se ejecuta desde dos grafos de módulos distintos. |
| `vite.config.js:5-8` | 4 entradas: `app.css`, `app.js`, `combate.css`, `combate.js`. |

#### Componentes Bootstrap reales (solo combate)

| Archivo:línea | Componente |
|---|---|
| `livewire/combate.blade.php:2` | `container-fluid py-3 battle-container` |
| `livewire/combate.blade.php:127-132` | `modal fade show d-block` + `modal-dialog modal-dialog-centered modal-dialog-scrollable` + `modal-content` |
| `livewire/combate.blade.php:220` | `alert alert-warning text-center py-3 mb-3` |
| `livewire/combate.blade.php:229-238` | `list-group-item d-flex justify-content-between align-items-center` (×3) |
| `livewire/combate.blade.php:251-253` | `btn btn-success w-100 fw-bold` (×2) |
| `livewire/partials/battle-log-modal.blade.php:1-17` | `modal fade`, `modal-dialog ... modal-lg`, `btn-close`, `btn btn-sm btn-secondary`, `data-bs-dismiss` |
| `livewire/partials/battle-field.blade.php:18` | `alert alert-info py-1 px-3 mb-2 d-flex align-items-center gap-2` |
| `livewire/partials/battle-field.blade.php:29-31` | `row g-2`, `col-6 col-md-3 d-flex flex-column` (×4) |
| `livewire/partials/turn-bar.blade.php:1-18` | `card mb-3`, `card-body py-2`, `badge rounded-pill`, `btn btn-sm btn-outline-secondary` |
| `livewire/partials/turn-bar.blade.php:27,36,44` | `btn-outline-warning`, `btn-outline-primary`, `btn-outline-secondary` |
| `livewire/partials/preparacion-panel.blade.php:13,16` | `btn btn-sm btn-outline-secondary`, `btn btn-sm btn-primary` |
| `livewire/partials/preparacion-panel.blade.php:28,55` | `form-select form-select-sm` |
| `livewire/partials/moves-panel.blade.php:6,10` | `badge bg-info text-dark`, `badge bg-primary round-info` |
| `livewire/partials/moves-panel.blade.php:19,134,139` | `spinner-border spinner-border-sm` |
| `livewire/partials/moves-panel.blade.php:24,26` | `row g-2`, `col-6 col-md-4 col-xl-2` |
| `livewire/partials/moves-panel.blade.php:129` | `alert alert-success battle-over` |
| `livewire/_pokemon-card.blade.php:9-61` | `badge bg-purple-100`, `bg-light`, `bg-dark`, `bg-danger`, `bg-warning`, `bg-info`, `bg-secondary` (7 badges) |
| `livewire/_pokemon-card.blade.php:79-104` | `progress`, `progress-bar bg-info/bg-primary`, `progress-bar-trail` |
| `livewire/partials/turn-bar.blade.php:45-46` | `data-bs-toggle="modal"` + `data-bs-target` |

Conteos globales (Bootstrap en vistas): `modal` 59, `alert` 78 (tokens Bootstrap 3), `spinner` 6, `form-select` 4, `list-group` 9, `badge bg-*` 12, `btn btn-*` 9, `progress` 32, `card` 100, `row` 53, `col-*` 24, `d-flex` 35.

**Ninguna vista fuera de combate usa componentes Bootstrap** (el `container` de `reclutados/index:4` es cosmético/inline y esa vista es legacy). No hay `<table>` en toda la app: los listados son divs+grid.

#### Tailwind real (toda la app)

| Evidencia | Hallazgo |
|---|---|
| 1.910 `class=` en 44 vistas | densidad media ~0,16 clases por línea |
| `dark:` ×1.586 | `habitats/show` 359, `equipos/index` 359, `pokedex` 100 |
| `text-[10px]` ×142 | `equipos/index` 75, `habitats/show` 30, `sendas/show` 17 |
| `[..]` arbitrarios ×220 | incluye `w-[24rem]`, `max-h-[70vh]`, `text-[10px]` |
| `rounded-lg` 195 / `rounded-xl` 62 / `rounded-2xl` 33 / `rounded-full` 117 | 5 radios distintos + `rounded` |
| `shadow-sm` 37 / `shadow` 70 / `shadow-md` 7 / `shadow-lg` 4 / `shadow-xl` 2 / `shadow-2xl` 17 | 6 niveles de sombra |
| `grid-cols-*` 73 combinaciones | sin sistema de grid único |
| `sm:` 39 / `md:` 10 / `lg:` 24 / `xl:` 10 / `2xl:` 4 | responsive escaso: 87 prefijos en 12k líneas |

#### CSS propio (680 líneas)

| Archivo:línea | Contenido |
|---|---|
| `app.css:12-14` | `.medalla-icon` — regla suelta de tamaño. |
| `app.css:20-167` | Sistema **«calidad IV»**: 2 variables propias (`--quality-color`, `--quality-rgb`, `--quality-text`) y 5 variantes (normal/good/great/excellent/perfect) + overrides `.dark` (103-123). Es el **único sistema de tokens existente**, pero solo cubre calidad IV. |
| `app.css:8-9` | `.dark body { background-color:#111827 !important; }` — hack con `!important`. |
| `app.css:138-149` | `.pokemon-modal-quality`, `.pokemon-card-quality { border: 2px solid var(--quality-color) !important }`. |
| `app.css:147-158` | `.pokemon-card-assigned` con rgba hardcodeado. |
| `combate.css:4-22` | utilidades propias con paleta Bootstrap hardcodeada: `.bg-hp-low #dc3545`, `.bg-hp-mid #ffc107`, `.bg-hp-high #198754`, `.stage-up/down`, 6 `.weather-*` con hex propios. |
| `combate.css:25-32` | `.pokemon-card` fondo `#fff` / `.dark` `#1f2937`. |
| `combate.css:35-63` | Colores de equipo/vanguardia en `rgb()` crudos. |
| `combate.css:175-206` | **Traducción manual de dark mode a variables de Bootstrap** (`--bs-body-bg:#111827`, `--bs-card-bg:#1f2937`, etc.) — 20+ valores hex. |
| `combate.css:208-220` | Overrides `.dark .modal-content`, `.btn-close` con `filter: invert()`. |
| `combate.css:223-246` | Hack de cascada: `a { color: inherit }` + `.dark nav a { color:#9ca3af }` con comentario explícito de que Bootstrap unlayered gana a las utilidades Tailwind. |
| `combate.css:248-506` | Animaciones de combate (vitales, delta, ataque, hit, KO, barrera, estado, fin de batalla) — **103 reglas propias**, la mayor parte del archivo. |

#### Estilos inline

| Archivo:línea | Hallazgo |
|---|---|
| `reclutados/index.blade.php:8-77` | Vista legacy **sin Tailwind**: `style="color:tomato"`, `display:flex`, `border:1px solid #334` — 8 `style=`. |
| `habitats/show.blade.php:116` (vía `$tipo['color']`), `sendas/index.blade.php:27,45` | `style="background-color: {{ $tipo['color'] }}22"`, `style="color:{{...}}"` — **colores desde PHP**. |
| `gimnasios/index.blade.php:56`, `admin.blade.php:86`, `show.blade.php:47` | `:style="'background-color:' + gym.tipo_color"` — color dinámico desde DTO (`GimnasioResumen.tipo_color` ← `TipoPokemon::color()`). |
| `exploraciones/_explorador.blade.php:86,108` | `style="width: …%"` en barras. |
| `layouts/app.blade.php:126` | `style="width: {{ $progresoNivel }}%"`. |
| `equipos/index.blade.php:1107,1161,1178` | `style="height:180px"` en canvases. |
| `equipos/index.blade.php:1666-1682` | Colores Chart.js `#3b82f6`, `#10b981`, y `calidadIvs()` (1554-1589) **replica la paleta de `app.css`** en JS. |
| `livewire/partials/moves-panel.blade.php:29` | `style="background-color: {{ $move['tipoColor'] }}; color:#fff"`. |
| `livewire/combate.blade.php:130` | `style="background: rgba(0,0,0,0.6)"` en el modal. |

Manipulación de clases/estilos desde JS: 94 ocurrencias (`classList.add('bg-hp-low')` en `combate_page.blade.php:137-138`, `anim-*` en `combate.css`).

### Duplicaciones y colores/tokens

#### Duplicaciones estructurales

| Patrón | Evidencia | Alcance |
|---|---|---|
| **Card/shell** | `$cardPanelClass` idéntico en `habitats/show:8`, `gimnasios/admin:9`, `gimnasios/show:9`; misma cadena inline en `onboarding:12`, `exploraciones:37,150,177,393`, `habitats/index:35` | ~50 usos de `bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700` |
| **Botón primario azul** | `welcome:12`, `habitats/show:116,287,1019,1251`, `onboarding:24` (35 `bg-blue-600`) | 5 variantes de padding (`py-1`, `py-1.5`, `py-2`, `py-2.5`, `py-3`) |
| **Botón verde** | `habitats/show:332,461,630`, `equipos:44,599,758,968`, `reclutado/show:70` (12 `bg-green-600`) | radio `rounded-lg` vs `rounded-xl` |
| **Botón rojo** | 19 `bg-red-600` | misma dispersión |
| **Botón secundario gris** | `px-4 py-2.5 bg-gray-100 dark:bg-gray-700…` **repetido 9 veces** (conteo exacto de cadena) | el más duplicado |
| **Botón icono** | `p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400` ×6 | |
| **Modal** | 17 raíces `fixed inset-0 z-50 flex items-center justify-center p-4` (habitats/show ×5, equipos ×4, gimnasios ×2, pokedex, misiones, reclutamiento ×2, components/modal) | solo 5 con `role="dialog"`, 4 con `aria-modal` |
| **Input** | cadena `w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900…` ×5 | más 4 variantes sin focus |
| **Select** | `w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg…` ×3 | fondo `bg-gray-50` vs `bg-white` en inputs |
| **Empty state** | `text-center py-…` con `text-sm text-gray-500 dark:text-gray-400` en `exploraciones:154,366,394`, `habitats/show:262,419,703`, `equipos:241,468,577`, `gimnasios/index:136`, `admin:66` | 10+ variantes; dos sin clases (`gimnasios/index:136`, `admin:66` → `<p>No hay …</p>` sin estilo) |
| **Badge de tipo** | `TipoBadges` (fuente única) vs `components/badge` (muerto) vs `equipos/index:1899` (`especial: 'bg-blue-100…'`) vs `sendas/show:57` | 2 paletas paralelas |
| **Page header** | `<h1 class="text-2xl font-bold text-gray-900 dark:text-white">` en 12 vistas, con y sin `mb-6` | |
| **Progreso** | `h-2 … bg-gray-100 dark:bg-gray-700 rounded-full` en `sendas/index:42`, `sendas/show:104`; `h-1.5` en `equipos:951,1144,1183` | 3 alturas distintas |
| **Alertas** | `components/alert` (muerto) vs `layouts/app:132,137` inline vs `layouts/auth:19,24,29` (3 duplicados) vs `habitats/show:280,503,657,722,981,1078,1123` (7 copias literales de la misma cadena) | |
| **Estados HP** | `_explorador:17-22` (`bg-red-500/amber-500/green-500/gray-400`) vs `combate.css:7-9` (`#dc3545/#ffc107/#198754`) | 2 semánticas de color para lo mismo |

#### Mapa de tokens actual

**No existen tokens de tema.** Lo que hay:

1. **Variables CSS**: solo `--quality-color`, `--quality-rgb`, `--quality-text` (`app.css:21-23`) y sus overrides `.dark` (`103-123`). No hay `--color-bg`, `--color-surface`, `--color-primary`, `--font-sans`, `--radius-*`, `--shadow-*`.
2. **Variables de Bootstrap**: 20+ en `combate.css:176-206` (`--bs-body-bg`, `--bs-card-bg`, `--bs-link-color`…), definidas **solo bajo `.dark`** y **solo en combate**.
3. **Paleta Tailwind por frecuencia** (sobre 1.910 `class=`): `gray` 1.983, `blue` 355, `white` 341, `red` 273, `green` 154, `amber` 127, `orange` 95, `yellow` 73, `purple` 39, `emerald` 35, `indigo` 25, `black` 21. Cero `slate/zinc/neutral/stone/rose/sky/violet` salvo en `TipoBadges` (que sí usa `sky/violet/rose/lime/slate`).
4. **Hex hardcodeados fuera de Tailwind**: 12 en vistas (`equipos:1557-1679`, `reclutados:22`, `moves-panel:29`), 4 `rgba()`, 18 en `TipoPokemon::color()` (`src/Shared/Tipos/TipoPokemon.php:70-87`), ~40 en `combate.css`, ~8 en `app.css`.
5. **Paleta de tipos**: **3 representaciones distintas** para el mismo dato — hex (`TipoPokemon::color()`), clases Tailwind (`TipoBadges::CLASES`) y `$move['tipoColor']` inline. Un theme nuevo obliga a tocar PHP + Blade + JS.
6. **Dark mode**: clase `.dark` en `<html>`, decidida por script inline duplicado en `layouts/app:12-16` y `layouts/auth:10-14` (y otra copia muerta en `components/header:18-20`), persistida en `localStorage['theme']`; toggle implementado **dos veces** (`layouts/app:115-120` con emojis, `components/header:18-28` con SVG, muerto). `dark:` cubre `bg-white` en el 100% de los archivos que lo usan (verificado archivo a archivo), pero el `_pokemon-card` y 5 partials de combate **no tienen ningún `dark:`** y dependen de overrides en `combate.css`.

### Diagnóstico y recomendación de estrategia

#### Hechos

- Bootstrap **no** está en la app: está en una página. La deuda de «convivencia» es menor de lo que sugiere el encargo, pero es **real y confusa**: `bootstrap.bundle.js` se carga globalmente (`app.js:2`) sin Bootstrap CSS, y en combate se cargan dos grafos con `Livewire.start()` duplicado (`app.js` + `combate.js`). No se verificó si Vite deduplica el chunk en build; en dev son dos ejecuciones.
- La colisión de utilidades es real: `p-3`/`mb-3` (Bootstrap 1rem vs Tailwind 0.75rem), `p-4`/`mb-4` (1.5rem vs 1rem). Ejemplos: `preparacion-panel:4,22,37` mezcla `mb-3`/`mb-2` Bootstrap con `text-xs`/`text-[10px]` Tailwind.
- El mayor coste de un cambio de theme **no es el CSS**: son 142 `text-[10px]`, 220 valores arbitrarios, 220+ colores semánticos escritos a mano en 44 vistas (`bg-green-50 text-green-700 border-green-200` para éxito, etc.) y la lógica de color en JS/PHP.
- Hay 8 componentes preparados y sin usar: la abstracción existe, **nadie la adoptó**. Adoptarlos sin más obligaría a reescribir 12k líneas en un WIP de 305 archivos.

#### Opciones

1. **Mantener ambos con capa de tokens** — tokens en `app.css` (`@theme` de Tailwind 4 + variables CSS) y `.dark` redefiniendo variables en vez de clases. Bootstrap se queda en combate pero leyendo las mismas variables (`--bs-primary`←`--color-primary`). Impacto bajo, no rompe el combate, pero deja dos sistemas de utilidades y no resuelve la duplicación de vistas.
2. **Converger a Tailwind** — elimina Bootstrap del combate: requiere rehacer 9 archivos de combate, 103 reglas de `combate.css`, el modal con `data-bs-*` y el JS de Bootstrap. Es el refactor más limpio a largo plazo, pero **es el mayor riesgo de regresión visual** (animaciones de vitales y cola de eventos acopladas a clases `.progress`/`.progress-bar` de Bootstrap).
3. **Híbrido por fases (recomendada)** — tokens primero (sin cambiar ni una vista), componentes de alto valor después (button/input/card/badge/modal/alert/empty-state), y Bootstrap aislado en combate con su propio conjunto de tokens. Permite paralelizar y convivir con el WIP.

**Recomendación: opción 3**, con estas condiciones:
- Tokens definidos en **un solo sitio** (`resources/css/app.css` con `@theme` para Tailwind + variables semánticas), y `.dark` que **redefina variables**, no clases. `combate.css` consume las mismas variables para sus overrides `--bs-*` en lugar de repetir hex.
- **No eliminar Bootstrap** en esta fase; sí **sacar `bootstrap.bundle` de `app.js`** y cargarlo solo en combate (aprovechando que `combate.js` ya lo importa), eliminando el JS muerto global.
- Los componentes Blade existentes se **reescriben sobre tokens** y se adoptan por sustitución progresiva; no se borran hasta que el último uso migre.
- Unificar las 3 representaciones de color de tipo: `TipoPokemon::color()` como única fuente de hex, `TipoBadges` derivando de ella o exponiendo clases semánticas (`bg-type-fire`).
- Cero valores arbitrarios nuevos: `text-[10px]` → `text-2xs` token; radios → `rounded-card`/`rounded-control`.

### Fases propuestas

**Fase 0 — Baseline y contención (secuencial, bloqueante).**
Propietario: orchestrator. Verificar/cerrar WIP (305 archivos, 15 vistas modificadas, 2 archivos sin trackear que se usan: `components/tipo-badge`, `partials/tipos-badge-script`). Sin esto, cualquier diff visual es atribuible. Criterio: `git status` limpio o WIP commiteado en rama; inventario de capturas por vista.

**Fase 1 — Capa de tokens (secuencial; base de todo).**
Archivos: `resources/css/app.css` (nuevo bloque `@theme` + `:root`/`.dark` con variables semánticas: superficie, fondo, borde, texto, primario, éxito, aviso, peligro, info, radios, sombras, tipografía, espaciado).
Criterio verificable: existe un único lugar donde se define un color; `grep -c '#[0-9a-fA-F]\{6\}' resources/css/app.css` no aumenta; ninguna vista cambia. `combate.css:176-206` pasa a referenciar `var(--color-*)`.
Dependencia: ninguna. **Paralelizable con Fase 2** solo si la 2 no toca CSS.

**Fase 2 — Aislamiento de Bootstrap (paralelizable con 1).**
Archivos: `resources/js/app.js` (quitar línea 2), `resources/js/combate.js` (queda como único importador), `resources/css/combate.css:1` (mantener import), `combate_page.blade.php`.
Criterio: en una página no-combate, `document.querySelectorAll('[class*=modal]')` no aplica y no se descarga Bootstrap JS; el modal del registro sigue abriendo en combate. Riesgo: `data-bs-dismiss` en `battle-log-modal:11,17` depende del bundle → verificar.
Dependencia: independiente.

**Fase 3 — Componentes base (paralelizable por componente, secuencial dentro de cada uno).**
Archivos: `resources/views/components/{button,input,card,badge,alert,modal,empty-state}.blade.php` (nuevos/reemplazo de los muertos); `components/tipo-badge` adaptado a tokens.
Criterio por componente: 0 valores hex/arbitrarios; contrato de props documentado; se adopta en **una** vista piloto y se compara visualmente.
Dependencia: Fase 1.

**Fase 4 — Migración por módulo (paralelizable entre módulos, secuencial por archivo).**
Orden por riesgo creciente: (a) auth + layout + onboarding + welcome (bajo), (b) sendas + misiones + gimnasios/index + reclutamiento (medio), (c) pokedex + exploraciones (medio-alto), (d) hábitats/show (alto), (e) equipos/index (muy alto), (f) combate (aparte, tras Fase 2).
Criterio por módulo: `grep -c 'text-\[10px\]'` = 0 (o tokenizado), 0 hex en la vista, 0 `$cardPanelClass` local, componentes usados ≥1, contraste AA en modo claro/oscuro.
Dependencia: Fase 3. **`equipos/index` y `habitats/show` en secuencia, nunca en paralelo entre sí** (ambos redefinen patrones de card/modal).

**Fase 5 — Combate (secuencial y último antes de theme).**
Archivos: `combate.css` (sustituir hex por variables), `livewire/*` (unificar Bootstrap↔token), `_pokemon-card`, `moves-panel`, `preparacion-panel`, `turn-bar`, `battle-field`, `battle-log*`.
Criterio: animaciones intactas (vitales, delta, hit, KO), `prefers-reduced-motion` respetado, 0 hex propios.
Dependencia: Fases 1 y 2.

**Fase 6 — Theme switch (posterior, fuera de alcance).** Solo redefine variables en `:root`/`.dark`; ningún archivo de vista se toca. Criterio: `git diff --stat resources/views` vacío tras el cambio de theme.

### Riesgos y evidencias (estilos)

- **[alto] WIP preexistente**: `git status` → 305 archivos, 15 vistas modificadas (`equipos/index`, `habitats/show`, `pokedex`, `sendas/*`, `gimnasios/*`, `livewire/combate`, `exploraciones/*`), `app.css` modificado (+`@source`), `components/tipo-badge` y `partials/` **sin trackear**. Impacto: imposible distinguir regresión del refactor de trabajo previo. Mitigación: Fase 0 obligatoria, rama propia.
- **[alto] Vistas monolíticas**: `equipos/index` 3.256 líneas con `favoritosApp()`, 6 modales, Chart.js y 75 `text-[10px]`; `habitats/show` 2.282 con `habitatShow()` y 6 modales. Impacto: cualquier cambio de token toca miles de líneas; conflicto de merge casi seguro. Mitigación: extraer partials **antes** de tokenizar; migrar por secciones.
- **[alto] Dark mode híbrido**: `app.css:8` y `combate.css:230-232` usan `!important` porque Bootstrap (unlayered) gana a Tailwind (layered) — el propio CSS lo documenta en `combate.css:225-229`. Además `_pokemon-card` y 5 partials de combate no tienen `dark:`. Impacto: un cambio de theme deja zonas sin adaptar. Mitigación: tokens + `.dark` que redefine variables; eliminar overrides `!important`.
- **[medio] Colisión Bootstrap/Tailwind**: `p-3`/`mb-3`/`p-4`/`mb-4` con escalas distintas; `card`, `border`, `rounded`, `shadow`, `badge`, `alert` existen en ambos. Evidencia: `preparacion-panel:2-7` usa `card` + `dark:` + `text-[10px]`. Impacto: ajustes visuales impredecibles al reordenar CSS. Mitigación: Fase 2 + tokens con prefijo en combate.
- **[medio] Color de tipo triplicado**: `TipoPokemon::color()` (hex), `TipoBadges::CLASES` (Tailwind), `$move['tipoColor']` (inline). Impacto: el theme exigirá tocar PHP y Blade. Mitigación: una sola fuente + clases semánticas.
- **[medio] Accesibilidad**: 142 `text-[10px]`, 17 modales y solo 5 `role="dialog"`/4 `aria-modal`; 0 `focus-visible`; 1 `sr-only` en 12k líneas; `gimnasios/index:136` y `admin:66` con texto sin clases. Impacto: contraste y foco insuficientes; el refactor puede empeorarlo si solo cambia colores. Mitigación: incluir checklist AA en criterios de cada fase.
- **[medio] JS acoplado al estilo**: `combate_page:137-138` añade `bg-hp-low/mid/high`; `equipos:1540-1589` replica la paleta de `app.css` en JS; 94 manipulaciones de estilo/clase. Impacto: los tokens CSS no llegan a lo que pinta JS. Mitigación: exponer tokens por `getComputedStyle` o clases semánticas.
- **[bajo] Responsive escaso**: 87 prefijos responsive en 12k líneas (`sm:` 39, `md:` 10, `xl:` 10). Impacto: el rediseño puede romper layouts no cubiertos. Mitigación: revisión por breakpoint en Fase 4.
- **[bajo] Build Vite**: 4 entradas, `bootstrap.bundle` importado dos veces (`app.js:2`, `combate.js:3`) y `Livewire.start()` desde dos grafos (`bootstrap.js:13`). Impacto: posible doble ejecución en dev y peso JS innecesario en todas las páginas. Mitigación: Fase 2.

---

## Conclusiones conjuntas

1. **La escala 128/96/64 es viable**, pero requiere un componente/patrón único con contenedores de tamaño fijo y `object-contain`; no basta con cambiar números en cada `<img>`.
2. **El mayor problema de imágenes es `w-96` (384px)** en `habitats/show` y la falta de tamaño en contenedores fluidos; 128px es el máximo nativo de los assets.
3. **El refactor global es de gran magnitud**: 44 vistas, 12.143 líneas, 1.910 `class`, 1.586 `dark:` y 142 `text-[10px]`. No es razonable hacerlo en un solo paso sin tokens, componentes y fases.
4. **La estrategia recomendada es híbrida por fases**: tokens → aislamiento Bootstrap → componentes base → migración por módulos → combate → theme posterior.
5. **El WIP de 305 archivos es un riesgo crítico** para atribuir regresiones; se necesita una decisión explícita sobre cómo aislarlo.

## Decisiones pendientes para arquitectura/plan

1. ¿Se commitea o se aísla el WIP antes de empezar? Sin esto no se puede garantizar la trazabilidad de las regresiones visuales.
2. ¿Bootstrap se queda permanentemente solo en combate o es deuda a eliminar en una fase posterior?
3. ¿El theme futuro es solo claro/oscuro o incluye paletas alternativas/marca?
4. ¿Se usa `@theme` de Tailwind 4 como mecanismo de tokens o un `tokens.css` independiente?
5. ¿Se reescriben los 8 componentes muertos o se crean componentes nuevos?
6. ¿Se elimina `reclutados/index.blade.php` (legacy) o se deja fuera de alcance?
7. ¿Quién posee `layouts/app.blade.php` (dark toggle, nav, alertas) y en qué orden se migra?
