# TASK-048 — Seguridad

- **Estado:** NO APLICA (preliminar)
- **Motivo:** la tarea añade un endpoint autenticado de solo lectura (`GET /avistamientos`) sin entrada de usuario, sin subida de archivos, sin SQL crudo y sin cambios de autenticación/autorización. El evento es global; el único dato personal es el estado «visto», que se filtra por el usuario autenticado.

## Comprobaciones que debe mantener la implementación

- Ruta dentro del grupo `auth` de `routes/web.php`.
- `Pokedex` consultado con `user_id = Auth::id()` explícito, sin confiar solo en el global scope.
- Sin IDs de usuario por URL ni parámetros de escritura.
- Sin secretos ni datos de `.env` en la vista o en los DTOs.
- La migración no expone datos personales; `habitat_id` y `evolution_chain_id` son datos de catálogo.

Si durante la implementación se añade entrada de usuario, escritura o un endpoint público, se reabrirá la auditoría.
