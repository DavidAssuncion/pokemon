# TASK-017 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-017
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Recomendado)»
- **Fecha:** 2026-11-04
- **Alcance autorizado:** Modificar `resources/views/habitats/index.blade.php` para estirar las imágenes (`object-fill`, sin padding), uniformar las tarjetas con banda de nombre `h-11` + `line-clamp-2` y retirar el zoom de hover. Commit scoped en `task-011`, sin push.
- **Restricciones:** sin tests; sin `reviewer` ni `security` por continuidad. No tocar `/habitats/{id}`, `/pokedex`, `/equipos`, CSS compartido, rutas, controladores ni migraciones. Mantener 6 columnas y `gap-3`.
