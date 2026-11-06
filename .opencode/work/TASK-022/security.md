# TASK-022 — Seguridad

- **Estado:** NO APLICA
- **Motivo:** refactor de dominio de batalla y limpieza documental. No se tocan autenticación, autorización, entradas de usuario, SQL, sesiones, cookies, secretos, dependencias ni endpoints públicos.
- **Excepción:** si el refactor de `ServicioEjecucionBatalla` altera efectos o estados, se revisará que no se desactiven validaciones ni controles de seguridad.
