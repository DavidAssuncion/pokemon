# TASK-024 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-023 (Ola 3 completada, commit `dd83ca9`).
- **Petición literal:** «Haz el frontend».
- **Interpretación:** abordar la deuda de frontend identificada en TASK-023: `Livewire/Combate.php` (1.919 líneas), JS inline de combate y vistas gigantes (`equipos/index` 3.284 líneas, `habitats/show` 3.007 líneas).

## Contexto

- Backend de dominio ya descompuesto en las olas 1-3.
- `Livewire/Combate.php` mezcla orquestación, estado de presentación, resolución de acciones, grabación de animación y sincronización de vista.
- `combate_page.blade.php` (619 líneas) concentra 22 funciones JS globales en `@push('scripts')`.
- `livewire/combate.blade.php` (255 líneas) contiene un `x-init` Alpine de ~95 líneas acoplado a esas funciones.
- `equipos/index.blade.php` y `habitats/show.blade.php` superan las 3.000 líneas.
- `resources/js/combate.js` solo importa bootstrap.

## Restricciones

- No tests.
- No dependencias nuevas sin autorización.
- No migraciones, rutas ni esquema.
- No `git push`.
- No cambiar el contrato público de Livewire sin compatibilidad.
- No romper sesiones de batalla (`SESSION_VERSION` intacto en esta tarea).
- Implementación paralela solo con archivos disjuntos.
- Cada ola requiere plan y aprobación explícitos.

## Decisión de alcance

El frontend es demasiado grande para una sola ola segura. El plan propondrá olas con archivos disjuntos y aprobación por ola. La primera ola priorizará `Livewire/Combate.php` y la extracción del JS de combate; las vistas gigantes irán en una ola separada.
