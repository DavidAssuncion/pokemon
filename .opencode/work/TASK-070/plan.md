# TASK-070 — Plan

- **ID:** TASK-070
- **Versión:** v1
- **Estado:** APROBADO
- **Fecha:** 2026-11-19
- **Base:** TASK-048 (diseño del evento de 3 días).

## Objetivo

Convertir el módulo `src/Avistamientos` (Domain + Infra ya existentes) en un
evento global temporal operativo y exponerlo como modal sencillo de navbar,
sin página propia.

## Diseño

1. **Ventana** de 3 días naturales (`Europe/Madrid`) calculada on-read.
2. La primera lectura elige hábitat y familia de forma determinista y persiste
   la fila (`unique(window_index)` + `insertOrIgnore`); las siguientes leen la
   fila congelada.
3. **Factor ×5** solo para el par hábitat/familia de la ventana:
   - Ruta: multiplica el peso base `capture_rate/hatch`.
   - Exploración: multiplica el peso de refugio existente (una sola vez).
   - Entrenadores, gimnasios y mazmorras no se tocan.
4. **UI**: botón de prismáticos en la navbar que abre un modal centrado
   (`fixed`, `role=dialog`). Se cierra con X, Escape o clic fuera. Muestra
   hábitat, familia, ×5, tiempo restante y especies por nivel con Visto/No
   visto. Sin evento activo muestra un estado vacío.

## Archivos

- Crear: `src/Avistamientos/App/**`, `Infra/FactorAparicionAvistamientos.php`,
  `Infra/Controllers/AvistamientoController.php`, `routes/avistamientos.php`,
  `resources/js/stores/avistamientos.js`,
  `resources/views/layouts/partials/modal-avistamiento.blade.php`.
- Modificar: `AppServiceProvider` (bindings), `routes/web.php`,
  `PokemonDelPool`, `CargadorPoolHabitat`, `ProcesarExploracionHandler`,
  `GeneradorEquipoRuta`, `navbar-acciones.blade.php`, `resources/js/bootstrap.js`.

## Criterios de aceptación

1. `GET /api/avistamientos/vigente` (auth) devuelve el contrato del modal.
2. La misma ventana devuelve siempre la misma selección y una sola fila.
3. `FactorAparicion` devuelve 5.0 para el par elegido y 1.0 para el resto.
4. El factor se aplica una sola vez (sin doble multiplicación).
5. El modal se abre/cierra y muestra el estado vacío si no hay evento.
6. PHPStan nivel 6 sin errores; Blade compila; build de Vite correcto.
7. Sin tests.
