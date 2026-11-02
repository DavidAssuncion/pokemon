# TASK-009 — Tamaños homogéneos de imágenes Pokémon y base de estilos unificada

- **Fecha de recepción:** 2026-11-02
- **Estado:** `RECEIVED`
- **Proyecto:** `/home/david/pokemon` (Laravel 12 / PHP 8.4, rama `master`)
- **Solicitado por:** desarrollador

## Petición literal

> Revisar los tamaños de las imagenes de los pokemon
> -para combate, detalle, reclutamiento o pokedex, 128px, que es su maximo
> -cuando hay que mostrar varios, 96px
> -para miniaturas como exploraciones, 64px
> Debemos unificar tambien todos los estilos de la aplicacion, posteriormente queremos actualizar el theme, todo debe ser lo mas homogeneo posible

## Interpretación operativa inicial

1. **Escala de tamaños de imagen Pokémon:** establecer una escala única y explícita:
   - `128px` para contextos principales de un solo Pokémon (combate, detalle, reclutamiento, Pokédex).
   - `96px` para listados o rejillas donde se muestran varios Pokémon.
   - `64px` para miniaturas (exploraciones y contextos equivalentes).
2. **Aplicación transversal:** localizar y normalizar todos los puntos donde se renderizan imágenes de Pokémon, incluyendo Blade, Alpine y Livewire, evitando tamaños ad hoc (`72px`, `w-96`, `w-12`, estilos inline, etc.).
3. **Base para el theme:** unificar estilos y tokens de la aplicación (tamaños, radios, colores, sombras, tipografía y componentes visuales) para que un futuro cambio de tema se haga en un único punto y no vista por vista.
4. **Sin rediseño funcional:** no se cambia lógica, datos ni comportamiento; el foco es presentación y consistencia visual.

## Contexto mínimo conocido

- Aplicación Laravel 12 con Blade, Alpine.js, Livewire, Chart.js, Tailwind CSS 4 y Bootstrap 5 conviviendo.
- Hojas de estilo principales: `resources/css/app.css` (174 líneas) y `resources/css/combate.css` (506 líneas).
- Vistas relevantes detectadas: `resources/views/livewire/_pokemon-card.blade.php`, `resources/views/livewire/combate.blade.php`, `resources/views/pokedex/index.blade.php`, `resources/views/reclutamiento/index.blade.php`, `resources/views/exploraciones/*`, `resources/views/equipos/index.blade.php`, `resources/views/habitats/show.blade.php`, `resources/views/gimnasios/*`.
- Hay tamaños dispares ya visibles: `72px` en la tarjeta de combate, `w-96` (384px en Tailwind) en `habitats/show.blade.php`, `w-12` (48px) en listados, `96px` para medallas, además de estilos inline y utilidades Tailwind.
- Imágenes servidas desde `public/images/iconos_webp/`, `public/images/iconos_webp/shiny/` y `public/images/type/`.

## Restricciones

- No revertir ni sobrescribir el WIP local preexistente (305 archivos modificados/eliminados al recibir la tarea).
- No modificar migraciones, dependencias, `composer.json`, `package.json` ni configuración de build sin aprobación.
- No crear, modificar ni ejecutar tests; `tests/` queda fuera de alcance.
- Mantener el stack actual (Blade + Alpine + Livewire + Tailwind 4 + Bootstrap 5) salvo decisión explícita.
- No alterar lógica de negocio, contratos backend ni consultas.
- No introducir secretos ni datos sensibles en artefactos.
- La documentación solo se crea si el desarrollador la pide; en principio no aplica.
- El cambio de theme definitivo es una tarea posterior; esta tarea debe dejar la base preparada, no aplicar un tema nuevo.

## Decisiones de alcance (2026-11-02)

1. **Alcance:** refactor visual completo. Se auditan y unifican todas las vistas y componentes de la aplicación, incluida la convivencia Bootstrap/Tailwind, colores, tipografía, botones, formularios, tarjetas, tablas, modales y estados. No es solo la imagen del Pokémon.
2. **Tamaños:** `128/96/64px` son tamaño de visualización CSS. Los archivos de imagen no se redimensionan en esta tarea; se mantienen los assets actuales.
3. **Theme:** el cambio de tema definitivo sigue siendo posterior. Esta tarea debe dejar una capa de tokens/componentes que permita cambiar el theme sin editar vista por vista.
4. **Estrategia Tailwind/Bootstrap:** la propone el `architect` a partir del inventario del `analyst`; se someterá a aprobación dentro del plan.

## Preguntas abiertas

Ninguna bloqueante tras las decisiones anteriores. Cualquier ambigüedad nueva se documentará en `analysis.md`.
