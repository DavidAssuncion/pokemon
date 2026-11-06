# TASK-025 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-024 (Ola 1 del frontend completada, commit `0e96b9d`).
- **Petición literal:** «adelante».
- **Interpretación:** ejecutar la Ola 2 del frontend según el diseño de TASK-024: descomponer `app/Livewire/Combate.php` (1.919 líneas) en colaboradores sin estado (3A-3D).

## Contexto

- `TASK-024/architecture.md` §5 define los colaboradores, contratos y criterios.
- `Combate.php` mezcla orquestación Livewire, estado de presentación, grabación de eventos, sincronización de vista, cola de turnos, animación y resolución de acciones.
- Ya se extrajo el JS de combate a un parcial clásico; el contrato JS quedó congelado.
- `ResolutorAccionBatalla` (3E) queda fuera de esta tarea.

## Restricciones

- No tests.
- No dependencias, migraciones, rutas ni endpoints.
- No `git push`.
- No cambiar propiedades públicas ni métodos `wire` de `Combate`.
- No tocar `render()`, el layout de `/combate`, las vistas de combate ni `resources/js`.
- No tocar `SESSION_VERSION`, `BattleSessionService` ni el payload de sesión.
- Cada subola (3A-3D) es secuencial porque comparte `Combate.php`.
