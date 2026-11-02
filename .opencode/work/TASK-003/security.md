# TASK-003 — Seguridad

- **Estado:** `NO APLICA`
- **Decisión:** el desarrollador indicó el 2026-11-01 que la auditoría de seguridad no era
  necesaria para esta tarea.
- **Auditoría realizada:** ninguna. No se debe interpretar este archivo como un visto bueno de
  seguridad.

## Puntos que quedan sin verificación de seguridad

Aunque no bloquean TASK-003 por decisión del desarrollador, conviene revisarlos en una tarea
posterior:

- `GET /exploraciones` tiene efectos secundarios (procesa expediciones). Requiere valorar CSRF,
  prefetch y repetición de peticiones.
- Confirmar anti-IDOR/BOLA en `preview`, `store-individual`, `recoger`, `cancelar` y `cerrar`
  (route-model binding, global scope de `BelongsToUser` y reglas `exists` con `user_id`).
- Validar que `ProcesarExploracionesPendientes::paraUsuario()` no puede procesar expediciones de
  otro usuario en ningún contexto HTTP.
- Revisar el lock `exploracion:procesar:{id}` como posible vector de denegación de servicio y su
  comportamiento con el driver de cache de producción.
- Revisar XSS/escapado en las nuevas vistas (`explorador`, `diario`, `evento`, `equipos`,
  `habitats`) y el uso de `@json`.
- Revisar CSRF en las acciones POST y la semántica del `GET` con efectos secundarios.
- Confirmar que logs y mensajes de error no filtran datos personales ni secretos.

## Riesgo residual

El módulo se entrega sin auditoría de seguridad independiente. Cualquier despliegue debe asumir
este riesgo o encargar la auditoría antes de exponerlo a usuarios reales.
