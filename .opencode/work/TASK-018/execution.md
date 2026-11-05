# TASK-018 — Ejecución

## Estado

`COMPLETED` — commit `c2c22e1` en `task-011`. Sin `reviewer` ni `security` por continuidad.

## Preflight

- Fecha: 2026-11-05
- Proyecto: `/home/david/pokemon`
- Rama: `task-011`
- `resources/views/pokedex/index.blade.php`: limpio en HEAD (`b3cb0b2`).
- Cambio local ajeno detectado: `resources/views/habitats/index.blade.php` (`gap-3` → `gap-6`). No se tocará.
- `routes/habitats.php` conserva cambios preexistentes; no se tocará.

## Implementación

- `resources/views/pokedex/index.blade.php`:
  - Modal: `w-[90vw] max-w-[90vw] max-h-[94vh]`; se eliminaron `max-w-3xl` y `lg:max-w-4xl`.
  - Cabecera: `flex flex-col gap-4 lg:flex-row lg:items-start lg:gap-6`.
  - Ubicación: tarjeta a la derecha en `lg` (`lg:w-72`), con miniatura `w-24 max-w-32 sm:w-28 lg:w-32` (`aspect-[4/3] object-cover`), provincia, nombre y botón “Ir al hábitat”. La miniatura es el último elemento de la fila, a la derecha.
  - Móvil: la tarjeta se apila debajo de la identidad.
  - Getter `ubicacionesDetalle` con fallback a `location`.
  - Eliminado el bloque “Ubicación” del cuerpo; comentario actualizado a “stats y especie”.
  - Estado vacío “Sin hábitat asignado.” en la cabecera.

## Verificación ejecutada

- `php artisan view:cache` / `view:clear`: OK.
- `npm run build`: OK (Vite 7.3.6, 67 módulos).
- `grep`: `w-[90vw]`, `max-w-[90vw]`, `max-h-[94vh]`, `ubicacionesDetalle`, `max-w-32`, `Ir al hábitat` presentes.
- `grep`: sin `max-w-3xl` ni `lg:max-w-4xl`.
- Solo un `<!-- Ubicación -->`, en la cabecera.
- GETTER `ubicacionesDetalle` presente; bloque del cuerpo eliminado.
- `git diff --check`: sin errores.
- Sin tests.

## Commit

- Rama: `task-011`.
- Commit: `c2c22e1 feat(pokedex): miniatura de ubicación en cabecera y modal ancho`.
- Archivo: `resources/views/pokedex/index.blade.php`.
- No se stagearon `resources/views/habitats/index.blade.php` (`gap-6` local) ni `routes/habitats.php`.
- Sin push.

## Notas

- La miniatura usa `object-cover`; recorta las imágenes más panorámicas o verticales, aceptado para una miniatura de 128 px.
- En móvil la cabecera crece al apilar la tarjeta; el cuerpo mantiene scroll.
- Verificación visual en navegador pendiente: 360/375/768/1024/1280/1920 px, con 0, 1 y varias ubicaciones.
