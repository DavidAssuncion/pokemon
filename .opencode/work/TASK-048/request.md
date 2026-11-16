# TASK-048 — Petición

- **Fecha:** 2026-11-16
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** implementación de funcionalidad.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Añade al navbar un fa icon de prismaticos para los avistamientos, cada 3 dias, en un habitat aletorio, una familia aleatoria de pokemon aumentara considerable su posibilidad de aparicion

## Interpretación de trabajo

1. **Navbar:** añadir un icono Font Awesome de prismáticos (`fa-binoculars`) que lleve a la sección de avistamientos.
2. **Avistamientos:** una vista/página que muestre el evento activo: hábitat, familia de Pokémon, multiplicador de aparición y tiempo restante.
3. **Evento de 3 días:** cada 3 días se elige un hábitat aleatorio y una familia aleatoria de ese hábitat; esa familia tendrá una probabilidad de aparición considerablemente mayor durante la ventana.
4. **Sin cron obligatorio:** el evento debe resolverse de forma determinista y estable aunque no haya scheduler.
5. **Respetar el árbol de trabajo sucio:** hay cambios locales preexistentes que no se deben sobrescribir.

## Entregables

- Análisis de los puntos de integración reales (navbar, rutas, generación de pools, familia evolutiva, vistas).
- Diseño de la solución mínima y segura.
- Plan de implementación con archivos, criterios de aceptación y verificación.
- Implementación solo tras aprobación explícita.

## Restricciones

- No implementar antes de la aprobación del plan.
- No crear, modificar ni ejecutar tests.
- No leer `.env`, credenciales ni secretos.
- No revertir ni sobrescribir cambios locales preexistentes.
- No introducir moneda, misiones ni facciones en esta tarea.
- Mantener el alcance: icono + página de avistamientos + evento de aparición.
