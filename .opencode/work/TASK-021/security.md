# TASK-021 — Seguridad

- **Estado:** NO APLICA
- **Motivo:** refactor estructural de dominio y limpieza de CRUD en desuso. No se abordan autenticación, autorización, entradas de usuario, SQL, sesiones, cookies, secretos, dependencias ni endpoints públicos.
- **Excepción:** si la limpieza del CRUD elimina rutas o vistas, se verificará que no queden enlaces ni accesos rotos, sin cambiar controles de seguridad.
