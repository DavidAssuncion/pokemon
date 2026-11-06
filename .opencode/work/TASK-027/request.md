# TASK-027 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-026 (commit `fad7c65`).
- **Petición literal:** «1500 lineas siguen siendo demasiadas, idealmente deben ser menos de 100» + decisión del desarrollador: «Fachada ~120 líneas (Recomendado)».
- **Interpretación:** convertir `app/Livewire/Combate.php` (1.491 líneas) en una fachada de ~120 líneas que conserve las 32 propiedades públicas, los 18 métodos públicos y todo el comportamiento, delegando la lógica en colaboradores.

## Contexto

- `Combate.php` ya tiene extraídos `GrabadorEventosBatalla`, `SincronizadorVistaBatalla`, `ColaTurnosBatalla`, `EstadoAnimacionBatalla`, `ResolutorAccionBatalla` y `ResolutorPosicionCombatiente`.
- Quedan en el componente: lifecycle, ciclo de turno, preparación, prioridades, interacciones del jugador, helpers y orquestación de eventos.
- El contrato público no se puede cambiar: 32 propiedades y 18 métodos usados por Blade/JS.
- `SESSION_VERSION = 21` y `BattleSessionService` no se tocan.

## Restricciones

- No tests.
- No dependencias, migraciones, rutas ni endpoints.
- No `git push`.
- No cambiar propiedades públicas, métodos públicos, `render()`, vistas, JS ni layout.
- No tocar `SESSION_VERSION` ni `BattleSessionService`.
- Cada colaborador sin estado de dominio propio; la sesión y el estado Livewire siguen en el componente.
- Objetivo: ~120 líneas; se acepta un rango 100-150 si el contrato lo exige.
