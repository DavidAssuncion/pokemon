# TASK-026 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-025 (Ola 2 del frontend completada, commit `77ad147`).
- **Petición literal:** «Adelante, sigue con la division en componentes o clases mas reutilizables.»
- **Interpretación:** completar 3E (`ResolutorAccionBatalla`) y extraer clases reutilizables que eliminen duplicidades detectadas en la revisión de TASK-025.

## Contexto

- `Combate.php` quedó en 1.695 líneas tras 3A-3D.
- `resolverAccion` (~162 líneas) y `resolverPrioridades` (~144 líneas) duplican la secuencia de aplicar un movimiento.
- `resolverAccionObjeto` (~74 líneas) sigue en el componente.
- `ColaTurnosBatalla::posicion` duplica `Combate::findPokemonViewData`.
- `AnimacionBatalla::tick` no se lee.

## Restricciones

- No tests.
- No dependencias, migraciones, rutas ni endpoints.
- No `git push`.
- No cambiar propiedades públicas ni métodos `wire`.
- No tocar `render()`, vistas, JS, `SESSION_VERSION` ni `BattleSessionService`.
- Cada extracción debe conservar secuencia, logs, eventos y timing de sesión.
