# TASK-063 — Seguridad

- **ID:** TASK-063
- **Estado:** no aplica
- **Fecha:** 2026-11-19

El cambio elimina una regla de juego en el motor de turnos. No toca
autenticación, autorización, entrada de usuario, SQL, archivos, sesiones, cookies,
secretos, dependencias ni endpoints públicos. No se manejan datos personales.

No se ha delegado auditoría de `security`. Se revaluará si la futura habilidad de
turno extra añade entrada de usuario o efectos persistentes.
