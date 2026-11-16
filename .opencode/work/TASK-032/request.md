# TASK-032 — Petición

- **Fecha:** 2026-11-09
- **Origen:** desarrollador, sesión de OpenCode.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> en /equipos ver el detalle del pokemon el scroll bar mata la experiencia del dorado, como podemos hacerlo?

## Interpretación inicial

En la vista `/equipos`, al abrir el modal de detalle de un Pokémon con calidad IV perfecta (marco dorado, `.pokemon-modal-quality` + `.pokemon-quality--perfect`), la barra de scroll nativa del contenedor del modal rompe visualmente el marco dorado: se dibuja dentro del borde, se pega al borde derecho y tapa/corta el glow y las esquinas.

La petición es **investigar y proponer cómo resolverlo**, no implementar todavía.

## Contexto conocido

- Proyecto: `/home/david/pokemon` (Laravel 12 + Blade + Alpine + Tailwind CSS 4 + Vite).
- Vista: `resources/views/equipos/partials/modal-detalle-pokemon.blade.php`, línea 5:
  `class="relative bg-surface rounded-panel shadow-modal w-[90vw] max-w-[90vw] max-h-[94vh] overflow-y-auto pokemon-modal-quality"`
- Estilos de calidad: `resources/css/app.css` (`.pokemon-quality`, `.pokemon-modal-quality`, `.pokemon-quality--perfect`).
- Tokens: `resources/css/tokens.css` (`--quality-color-perfect: #facc15`).
- El contenedor que pinta el marco dorado es el mismo que hace scroll (`overflow-y-auto`).
- `color-scheme: dark` en `.dark` afecta a la barra nativa en modo oscuro.
- No hay estilos globales de scrollbar en `resources/css/` (búsqueda inicial sin resultados).

## Restricciones

- Sin tests.
- Sin cambios en dependencias.
- Sin `git push`.
- No tocar `bugs.txt` (cambio local preexistente del desarrollador).
- La solución debe conservar accesibilidad: scroll con teclado, foco, `aria-modal`, cierre con Escape y clic en backdrop.
- Debe funcionar en claro y oscuro, desktop y móvil.
- No ocultar la barra de scroll a costa de perder usabilidad.
- Respetar `.ai/rules/frontend-minimal-logic.md`: el frontend solo presenta; no introducir lógica de negocio.

## Preguntas a responder por el análisis

1. ¿Cuál es la causa exacta del conflicto entre scrollbar y marco dorado?
2. ¿Qué opciones hay (marco exterior + scroll interior, scrollbar temática, gutter, etc.) y qué trade-offs tienen?
3. ¿Cuál es la opción mínima recomendada y qué archivos habría que tocar?
4. ¿Afecta también a otros modales o calidades?
5. ¿Qué verificación estática y manual se necesita?
