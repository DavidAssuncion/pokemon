# TASK-032 — Seguridad

- **Estado:** NO APLICA.
- **Motivo:** cambio de presentación (estructura Blade + CSS) en el modal de detalle de `/equipos`. No hay entrada de usuario, autenticación, autorización, SQL, manejo de archivos, sesiones, cookies, secretos, dependencias ni endpoints públicos nuevos.
- **Comprobaciones:** el diff solo afecta a clases de layout y a una regla de scrollbar; no se modifican atributos ARIA, manejadores de eventos, rutas ni lógica.
- **Revisión:** confirmado tras el análisis y el diff del commit `37f665b`. Si el alcance cambia, se solicitará auditoría a `security`.
