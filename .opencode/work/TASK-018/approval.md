# TASK-018 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-018
- **Versión aprobada:** v2
- **Texto literal:** «Aprobar plan v2 (Recomendado)»
- **Fecha:** 2026-11-05
- **Alcance autorizado:** Modificar `resources/views/pokedex/index.blade.php` para mostrar la ubicación en la cabecera con miniatura de hábitat (máx. 128 px) a la derecha y botón “Ir al hábitat”; eliminar el bloque de ubicación del cuerpo; ampliar el modal a `w-[90vw] max-w-[90vw] max-h-[94vh]`. Commit scoped en `task-011`, sin push.
- **Restricciones:** sin tests; sin `reviewer` ni `security` por continuidad. No tocar backend, rutas, contrato, `/equipos`, `/habitats`, CSS compartido ni migraciones. Mantener el modal solo lectura.
