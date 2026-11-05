# TASK-016 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-016
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Recomendado)»
- **Fecha:** 2026-11-04
- **Alcance autorizado:** Modificar `resources/views/habitats/index.blade.php` (marco 3:2, imagen completa, limpieza de favoritos) y `app/Http/Controllers/HabitatsController.php` (docblock `@deprecated` de `toggleFavorito()`). Commit scoped en `task-011`, sin push.
- **Restricciones:** sin tests; sin `reviewer` ni `security` por continuidad. No tocar `routes/habitats.php`, `/habitats/{id}`, `/pokedex`, `/equipos`, CSS compartido ni migraciones. Conservar las 6 columnas y `gap-3` del desarrollador.
