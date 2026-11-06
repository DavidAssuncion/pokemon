# TASK-028 — Petición

- **Fecha:** 2026-11-06
- **Origen:** cierre de TASK-027 (commit `8cc7fc0`).
- **Petición literal:** «Adelante» (tras explicar 3F).
- **Interpretación:** ejecutar la verificación diferencial 3F entre el `Combate.php` original (`fad7c65`) y la fachada actual, sin modificar código de aplicación.

## Contexto

- `TASK-027` convirtió `Livewire/Combate.php` en una fachada de 149 líneas con 14 colaboradores.
- La revisión independiente verificó contrato, 31/31 `guardar()` y 20/20 `nextActor()`, pero **no comparó payloads en ejecución**.
- 3F ejecuta los mismos escenarios contra ambos árboles y compara estado, log, eventos, sesión y recompensas.

## Restricciones

- No modificar código de aplicación, vistas, JS, rutas, tests, migraciones ni configuración.
- No `git push`.
- El worktree base va en `/tmp/opencode/task-028/base`; los scripts en `/tmp/opencode/task-028`.
- No ejecutar la suite de tests del proyecto.
- Si un escenario no puede reproducirse sin BD, se documenta y se marca como no cubierto.
