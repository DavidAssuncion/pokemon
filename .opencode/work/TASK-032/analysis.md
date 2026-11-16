# TASK-032 — Análisis

- **Estado:** COMPLETADO por `analyst` (sesión `ses_e40e3334dffekY40zeqaPGN8VH`), 2026-11-09.
- **Alcance:** investigación de causa raíz y opciones; sin modificaciones.

## Resumen

El modal de detalle de `/equipos` es el único contenedor del proyecto que combina en el **mismo elemento** el marco decorativo de calidad (`pokemon-modal-quality`: borde 2px + glow) y el scroll (`overflow-y-auto`). La scrollbar nativa se asigna dentro del border-box, pegada al borde dorado, y se pinta como una barra rectangular sin tematizar ni redondear: en las esquinas superiores/inferiores derechas rompe la continuidad del arco dorado y, en modo claro, además introduce una barra gris/blanca sobre el marco. No es un problema solo de "perfect": afecta a todas las calidades, pero se percibe mucho más en `perfect` por el borde de 2px y el glow intenso. El resto de modales del proyecto ya usan el patrón correcto (marco exterior `overflow-hidden` + contenedor interior `overflow-y-auto`), por lo que la solución robusta es alinear este modal con ese patrón.

## Requisitos

- Funcionales:
  - El marco/glow dorado (y el de cualquier calidad) debe verse continuo en las cuatro esquinas mientras el contenido hace scroll.
  - El scroll del contenido debe seguir funcionando con rueda, trackpad, teclado y táctil.
  - Cabecera (título, tipos, nivel/CP, Liberar y Cerrar) y cuerpo deben conservar el comportamiento actual salvo la mejora visual.
  - Cierre con Escape y clic en backdrop intactos; `role="dialog"`, `aria-modal`, `aria-labelledby` intactos.
- No funcionales:
  - Compatibilidad con tema claro y oscuro (`document.documentElement.classList.toggle('dark')`, `layouts/app.blade.php:14` y `:83-87`; `.dark { color-scheme: dark; }` en `app.css:14`).
  - Responsive: 90vw, `max-h-[94vh]`, móvil 320–390px, tablet y escritorio; zoom 200%.
  - Chrome/Edge, Firefox y, si es posible, Safari. Sin dependencias nuevas (restricción explícita).
  - Estilos propios en `resources/css/app.css`; no introducir lógica de negocio en Blade.
  - No ocultar la scrollbar de forma que se pierda la affordance de scroll.
- Criterios de aceptación:
  - Con un Pokémon de IV perfectos y contenido que desborde, el borde dorado y el glow se ven continuos en las 4 esquinas, en claro y oscuro.
  - Existe un único scroll vertical (no aparecen dos barras ni scroll horizontal accidental).
  - La barra visible es coherente con el tema (no gris/blanca sobre panel oscuro) o, si se decide ocultarla visualmente, sigue habiendo indicación de scroll.
  - Verificación estática/manual (sin tests, prohibido ejecutarlos):
    - `npm run build` (Vite 7 + Tailwind 4) y `npm run dev` para comprobar el resultado real.
    - Revisión manual en `/equipos` con un Pokémon perfect y con uno normal, claro/oscuro, 360px/768px/1440px, zoom 200%.
    - Chrome + Firefox (Safari opcional), comprobando barra y esquinas.
    - Tab/Shift+Tab por los botones del modal (`focus-ring` en `components.css:60`), Escape y backdrop.
    - Regresión visual en tarjetas de `panel-pokemon.blade.php:60-61` (`.pokemon-card-quality`) y en el retrato `pokemon-quality` de `modal-detalle-pokemon.blade.php:8`.
- Ambigüedades:
  - No está definido si el desarrollador quiere conservar una scrollbar visible tematizada o prefiere ocultarla estéticamente (afecta a accesibilidad).
  - No está definido si la cabecera debe quedar fija (sticky) o seguir desplazándose con el contenido.
  - Se desconoce el navegador/SO habitual del desarrollador (Windows/Linux con scrollbar clásica vs macOS con overlay), lo que cambia la percepción del defecto.

## Alcance afectado

- Módulos:
  - UI de Equipos (modal de detalle de Pokémon).
  - Sistema de estilos de calidad IV (`resources/css/app.css`).
- Archivos (solo lectura ahora; cambios propuestos, no ejecutados):
  - `resources/views/equipos/partials/modal-detalle-pokemon.blade.php:5` — separar marco exterior (sin scroll) del contenedor interior con scroll; opcionalmente extraer cabecera como hermano fijo. Tipo: cambio estructural Blade (envoltorio + mover cierre de div), sin lógica nueva.
  - `resources/css/app.css` (después de `:149`, junto a `.pokemon-modal-quality`) — opcional: clase nueva `.pokemon-modal-scroll` con `scrollbar-width/scrollbar-color` y `::-webkit-scrollbar-*` tematizados. Tipo: CSS aditivo y encapsulado.
  - No se toca `tokens.css` (los tokens `--quality-*` ya existen y sirven).
  - No se toca `index.blade.php` (JS `calidadClase` sigue igual).
- Tablas/contratos:
  - Ninguna tabla ni contrato backend. No hay cambios de datos ni de API.
- Vistas/endpoints:
  - Solo `/equipos` (render del partial). No hay endpoint nuevo.

## Evidencia

- `resources/views/equipos/partials/modal-detalle-pokemon.blade.php:5` — el mismo div acumula `bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-y-auto pokemon-modal-quality` y `:class="calidadClase(detailPokemon)"`. Marco y scroll comparten caja.
- `resources/views/equipos/partials/modal-detalle-pokemon.blade.php:7` — cabecera con `border-b border-line p-6` dentro del contenedor que scrollea (se desplaza y la scrollbar la atraviesa).
- `resources/views/equipos/partials/modal-detalle-pokemon.blade.php:56,58,329` — cierre de cabecera, apertura del cuerpo `p-6` y cierre del cuerpo: puntos exactos donde envolver con el nuevo scroller.
- `resources/css/app.css:143-149` — `.pokemon-modal-quality { border: 2px solid var(--quality-color); box-shadow: 0 0 10px … , 0 0 30px …; }`. Define marco y glow, pero **no** define radio ni scrollbar.
- `resources/css/app.css:78-82` y `:94-106` — `--quality-color/--quality-rgb` para `perfect` (`#facc15`, `250, 204, 21`) y glow reforzado. Las reglas con doble clase (`:94`) exigen también `.pokemon-quality`, por lo que **no** se aplican al modal (el modal solo lleva `pokemon-modal-quality` + `pokemon-quality--perfect`).
- `resources/css/app.css:14` y `resources/css/tokens.css:115-116` — `.dark { color-scheme: dark; }`: en oscuro el navegador pinta la scrollbar nativa en modo oscuro; en claro no se declara `color-scheme: light`, así que la barra es clara. No hay ninguna regla `scrollbar`/`::-webkit-scrollbar` en `resources/css/` (búsqueda sin resultados).
- `resources/css/tokens.css:104,111` — `--quality-perfect: #facc15` y alias `--quality-color-perfect`; confirma el "dorado".
- `resources/views/equipos/index.blade.php:469-496` — `calidadIvs()` / `calidadClase()`: la calidad se calcula por media de IV y `perfect` exige 15 en los seis. No hay nada que tocar aquí.
- `resources/views/pokedex/index.blade.php:271` — patrón correcto ya existente: contenedor `overflow-hidden rounded-panel` (marco fijo).
- `resources/views/pokedex/index.blade.php:284` — cabecera `shrink-0` fuera del scroll.
- `resources/views/pokedex/index.blade.php:388` — cuerpo `min-h-0 flex-1 overflow-y-auto`: el scroll vive en el hijo, no en el marco.
- `resources/views/habitats/partials/modal-granja.blade.php:5` y `:18` — mismo patrón: `overflow-hidden flex flex-col` + `flex-1 min-h-0 overflow-y-auto`.
- `resources/views/habitats/partials/modal-baya.blade.php:10` — mismo patrón (marco sin scroll).
- `resources/views/equipos/partials/panel-pokemon.blade.php:60-61` — las tarjetas usan `pokemon-card-quality` + `calidadClase(...)`; no son contenedores de scroll, por lo que no sufren este defecto, pero comparten las clases de calidad (riesgo de regresión si se toca `.pokemon-quality`).
- `public/build/assets/app-CM6kxF-K.css` — CSS compilado: `@layer utilities` empieza en el byte 13806 y las reglas `.pokemon-modal-quality` en el 113162 (fuera de capas). Las reglas propias sin capa ganan a las utilidades de Tailwind; por eso `.pokemon-modal-quality` sustituye a `shadow-modal`. Consecuencia práctica: `overflow-hidden` (propiedad distinta) sí funciona como utilidad, pero un futuro `border-*`/`shadow-*` de Tailwind en ese div sería anulado por `.pokemon-modal-quality`.
- `package.json:9-23` y `vite.config.js` — Tailwind 4 vía `@tailwindcss/vite`, Vite 7, Alpine 3.16; no hace falta dependencia nueva.
- Git: rama `task-011`; `bugs.txt` modificado (preexistente, no tocar); `.opencode/work/TASK-032/` sin seguimiento. Nada de este análisis procede de cambios locales no revisados.
- Nota de render (comportamiento esperado, a confirmar manualmente): la scrollbar se asigna dentro del border-box, junto al borde; el track/thumb se pintan rectangulares y no se redondean al radio del marco, por lo que en las esquinas derechas el arco dorado se ve interrumpido. El `box-shadow` de glow está fuera del border-box y **no** lo recorta `overflow`, así que el glow sobrevive; lo que se rompe visualmente es la línea dorada y la estética del borde derecho.

## Dependencias

- Tailwind 4 + Vite 7: los cambios de clases Blade requieren `npm run build`/`dev` para reflejarse.
- Cascada de estilos: `.pokemon-modal-quality` vive sin capa en `app.css` y prevalece sobre utilidades Tailwind; conviene no intentar corregir borde/sombra con utilidades en el mismo div.
- Herencia de custom properties: `--quality-color/--quality-rgb` se definen en las clases `pokemon-quality--*` del div exterior; si el scroll se mueve a un hijo, el hijo hereda esas variables (las custom properties se heredan), lo que permite tematizar la scrollbar interior sin duplicar la clase de calidad.
- Tokens de tema y toggle de oscuro existentes (`layouts/app.blade.php:14`, `:83-87`).
- Sin dependencias externas nuevas.

## Duplicidades y deuda técnica

- El modal de detalle es el único que no sigue el patrón `overflow-hidden` + hijo `overflow-y-auto` ya usado en Pokedex, granja, baya, mazmorra, gestión admin/favoritos y gimnasios. Es una desviación puntual, no una duplicidad.
- No existe ningún estilo global de scrollbar: cualquier solución tematizada será la primera del proyecto; conviene encapsularla en una clase específica (`.pokemon-modal-scroll`) para no afectar a otros scrolls (dropdowns, listas de cosecha, etc.).
- `w-[90vw] max-w-[90vw]` es redundante (deuda menor, fuera de alcance).
- `bg-surface` convive con `pokemon-modal-quality`; en claro el modal es blanco y en oscuro `#1f2937`, mientras el retrato interior (`pokemon-quality`, línea 8) tiene fondo oscuro fijo por diseño. No es deuda a corregir aquí.
- Cabecera sin fondo propio dentro del scroller: si se opta por sticky, habrá que darle fondo opaco; actualmente hereda el `bg-surface` del padre.

## Riesgos

- [medio] Duplicar scroll: si se añade un hijo con `overflow-y-auto` y se olvida quitar `overflow-y-auto` del exterior, aparecen dos barras y se agrava el problema. Mitigación: exterior `overflow-hidden` + interior único scroller.
- [medio] El modal crece más de `94vh` si el hijo no lleva `min-h-0` (mínimo automático de flex item). Mitigación: `flex flex-col` en el exterior y `flex-1 min-h-0` en el scroller.
- [bajo-medio] Regresión en tarjetas/retrato si se aplican reglas de scrollbar a `.pokemon-quality` (clase compartida). Mitigación: clase nueva y selectores acotados al modal.
- [bajo] `::-webkit-scrollbar` fuerza scrollbar clásica en Chrome/Safari (pierde la overlay de macOS) y en Chrome ≥121 las propiedades estándar `scrollbar-width/scrollbar-color` tienen precedencia sobre los pseudo-elementos WebKit. Mitigación: decidir una sola estrategia y aceptar que Firefox solo admite `thin` + color (sin radio en el thumb).
- [bajo] `scrollbar-gutter: stable` introduce un canal permanente y reduce el ancho útil; no es necesario si se separa el marco.
- [bajo] `overflow-hidden` en el marco exterior recorta el glow exterior de elementos internos; el retrato está a 24px del borde y su glow máximo es 22px, por lo que en principio no se recorta, pero conviene verificarlo visualmente.
- [bajo] El bug solo se manifiesta cuando el contenido desborda (Pokémon con muchas evoluciones/movimientos); una verificación con un Pokémon corto puede dar falso negativo.
- [bajo] Scroll horizontal accidental: hoy `overflow-y-auto` convierte el eje X a `auto`; un hijo ancho podría añadir barra inferior dentro del marco. Mitigación opcional: `overflow-x-hidden` en el scroller interior.

## Opciones

1. **A — Separar marco y scroll (recomendada).**
   - Exterior: `relative bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-hidden flex flex-col pokemon-modal-quality` + `:class="calidadClase(...)"`.
   - Interior: `<div class="flex-1 min-h-0 overflow-y-auto">` envolviendo cabecera + cuerpo.
   - Ventajas: elimina la causa raíz; el marco nunca lo pinta la scrollbar; el `overflow-hidden` + `border-radius` del padre recorta las esquinas cuadradas del hijo; alinea el código con Pokedex/granja/baya; permite sticky header sin esfuerzo extra; sin dependencias.
   - Desventajas: un div adicional y mover un cierre; hay que recordar `min-h-0`.
   - Riesgo: bajo-medio (layout), mitigable con la verificación.
   - Compatibilidad: total (Chrome, Firefox, Safari).
   - Accesibilidad: conserva scroll nativo y barra visible; no cambia el foco ni el teclado.
   - Esfuerzo: bajo (~15 min + build).
   - Archivos: `modal-detalle-pokemon.blade.php:5` y cierre en `:330`.
2. **A+bis — A + cabecera fija (hermana del scroller).**
   - Extraer el bloque de cabecera (`:7-56`) como hijo `shrink-0` del exterior y dejar el cuerpo (`:58-329`) dentro del scroller `flex-1 min-h-0 overflow-y-auto`.
   - Ventajas: además del marco intacto, el botón Cerrar/Liberar y el título siempre visibles; la scrollbar no cruza la cabecera (esquina superior derecha perfecta); UX claramente mejor en móvil.
   - Desventajas: la cabecera consume altura fija; en pantallas muy bajas reduce el área de contenido; hay que asegurar fondo opaco si en el futuro se superpone.
   - Riesgo: bajo. Compatibilidad: total. Accesibilidad: mejora (acciones siempre alcanzables). Esfuerzo: bajo-medio. Archivos: mismo partial.
3. **B — Scrollbar tematizada fina sobre el contenedor actual.**
   - CSS: `scrollbar-width: thin; scrollbar-color: rgba(var(--quality-rgb), .45) transparent;` + `::-webkit-scrollbar { width: 8px }`, track transparente, thumb `rgba(var(--quality-rgb), .5)` con `border-radius: 9999px`; opcional `scrollbar-gutter: stable`.
   - Ventajas: solo CSS, sin tocar Blade; la barra pasa a ser dorada/verde/azul según calidad (las custom properties se heredan); reduce mucho el impacto visual.
   - Desventajas: no elimina el problema de las esquinas (el track sigue siendo rectangular y pegado al borde); en Chrome ≥121 los pseudo-elementos WebKit se ignoran si se usan las propiedades estándar; en macOS se pierde la overlay y queda una barra permanente; `scrollbar-gutter` reduce ancho.
   - Riesgo: bajo. Compatibilidad: buena con degradación elegante. Accesibilidad: buena (sigue visible). Esfuerzo: bajo. Archivos: `app.css` tras `:149`.
4. **C — Gutter/padding compensado manteniendo el scroll actual.**
   - **No resuelve la causa**: la scrollbar se coloca en el borde interior del border-box y el `padding` no la desplaza hacia dentro; añadir `padding-right` solo separa el contenido de la barra, no la aleja del marco. `scrollbar-gutter: stable` reserva espacio pero no redondea ni recolorea.
   - Ventajas: cambio mínimo.
   - Desventajas: cosmético e insuficiente por sí solo; puede crear una franja vacía visible.
   - Riesgo: bajo pero con expectativa incumplida. Esfuerzo: muy bajo. Archivos: `app.css`/clases del partial.
5. **D — Ocultar la scrollbar (`scrollbar-width: none` + `::-webkit-scrollbar { display:none }`).**
   - Ventajas: marco perfecto, sin barra que lo rompa.
   - Desventajas: pierde la affordance de scroll; en desktop puede parecer que el modal está cortado; mala accesibilidad si no se añade otro indicador (sombra/fade). No recomendada en solitario.
   - Riesgo: medio (usabilidad). Esfuerzo: muy bajo. Archivos: `app.css`.
6. **E — Marco pintado por pseudo-elemento por encima de la scrollbar (`::after` con `border` y `pointer-events:none`).**
   - Ventajas: CSS-only, mantiene un solo contenedor.
   - Desventajas: depende del orden de pintado de scrollbar vs pseudo-elementos (varía por motor); frágil; el thumb sigue ocupando el borde derecho; complica el mantenimiento.
   - Riesgo: medio-alto de no funcionar igual en todos los navegadores. No recomendada.
7. **F — Librería de scrollbar overlay (OverlayScrollbars, etc.).** Descartada: instala dependencia y añade JS, prohibido por restricciones.

## Suposiciones

- El desarrollador percibe el defecto principalmente en modo oscuro y con scrollbar clásica (Windows/Linux), donde la barra es ancha y contrasta con el dorado.
- "Dorado" = borde/glow de `pokemon-quality--perfect` (`#facc15`), no el fondo del retrato.
- Se acepta tocar la estructura del partial (envoltorio) sin cambiar su contenido ni su lógica.
- El contenido del modal puede desbordar en Pokémon con muchas evoluciones/movimientos; en los cortos no hay barra y el bug no se ve.
- No se requiere conservar la overlay scrollbar de macOS; una barra fina permanente es aceptable si se tematiza.
- `npm run build` está disponible en el entorno del desarrollador (Node/Vite ya instalados).

## Preguntas bloqueantes

- Ninguna bloquea el análisis; sí condicionan la implementación:
  1. ¿Se prefiere conservar una barra visible tematizada (opción A+B) o prima el marco impoluto aunque la barra sea menos visible?
  2. ¿Se quiere cabecera fija con acciones siempre visibles (A+bis) o mantener el scroll de todo el modal (A)?
  3. ¿Navegador/SO de referencia para validar (Chrome/Firefox, Windows/macOS)? Cambia la percepción y la estrategia de tematización.

## Recomendación

Implementar **A+bis** (marco exterior fijo + cabecera `shrink-0` + cuerpo scroller) y, opcionalmente, **B acotada** al scroller interior para que la barra sea fina y del color de la calidad. Es la opción mínima y robusta: ataca la causa raíz, reutiliza el patrón ya aceptado en el proyecto y no añade dependencias.

Cambios concretos (sin ejecutarlos):

1. `resources/views/equipos/partials/modal-detalle-pokemon.blade.php:5`
   - Sustituir `overflow-y-auto` por `overflow-hidden flex flex-col` en el div exterior; mantener `max-h-[94vh]`, `rounded-panel`, `bg-surface`, `pokemon-modal-quality` y `:class="calidadClase(detailPokemon)"`.
2. Mismo archivo, `:7-56` (cabecera)
   - Sacarla del scroller y dejarla como hijo directo `shrink-0` del exterior (ya tiene `border-b border-line p-6`).
3. Mismo archivo, `:58-329` (cuerpo)
   - Envolverlo en `<div class="flex-1 min-h-0 overflow-y-auto">…</div>` (opcional `overflow-x-hidden overscroll-contain`).
   - Cerrar el nuevo div justo antes del cierre del exterior (`:330`).
4. Opcional, `resources/css/app.css` después de `:149`
   - Nueva clase, por ejemplo `.pokemon-modal-scroll { scrollbar-width: thin; scrollbar-color: rgba(var(--quality-rgb), .45) transparent; }` y reglas `::-webkit-scrollbar`/`::-webkit-scrollbar-thumb` con `border-radius: 9999px` y thumb `rgba(var(--quality-rgb), .5)`. Añadir la clase al scroller interior.
   - No aplicar scrollbar a `.pokemon-quality` ni a `.pokemon-card-quality` (compartidas con tarjetas/retrato).
5. No tocar `tokens.css`, `index.blade.php` ni el backend.

Resultado esperado: el borde dorado se dibuja en el contenedor exterior, que no scrollea y recorta a su radio; la scrollbar queda dentro del hijo, separada del marco y, si se aplica el punto 4, fina y tintada. El glow exterior no se recorta porque `box-shadow` no lo afecta `overflow`.
