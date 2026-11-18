# TASK-049 — Petición

- **Fecha:** 2026-11-16
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** implementación de UI (Blade + Tailwind), sin backend.
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> Aumenta el tamaño del modal en combates de ruta, IV/EV del rival debe mostrarse como colapsable para ocupar menos, los pokemon rival deben estar en 3 columnas en lugar de 3 filas como esta ahora

## Interpretación de trabajo

1. **Modal:** el popup de "Configurar Formación" que se abre antes del combate de ruta
   (`resources/views/habitats/partials/modal-formacion.blade.php`), hoy `max-w-md`.
2. **IV/EV colapsable:** la leyenda `resources/views/partials/leyenda-ivs-evs.blade.php`
   debe poder plegarse para reducir la altura del modal.
3. **Rivales en 3 columnas:** el desglose por Pokémon del rival dentro de la leyenda
   (hoy 3 bloques apilados en vertical) debe mostrarse en 3 columnas en escritorio.
4. Mantener responsive, accesibilidad por teclado y el comportamiento actual de los datos.

## Entregables

- Aumento del ancho del modal de formación en ruta.
- Leyenda IV/EV colapsable (cerrada por defecto), con `<summary>` accesible.
- Rejilla de 3 columnas para los Pokémon del rival en el modal de formación.
- Sin cambios de backend, contratos, datos ni tests.

## Restricciones

- No implementar antes de la aprobación del plan.
- No crear, modificar ni ejecutar tests.
- No tocar `src/`, `app/`, rutas, migraciones ni datos.
- Respetar la leyenda compartida con gimnasios: el cambio de 3 columnas se limita al
  modal de formación; el modal de gimnasio no debe quedar roto.
- No leer `.env` ni secretos.
