# TASK-002 — Seguridad

## Estado

`no aplica`.

## Justificación

- No se modifican autenticación, autorización, sesiones, cookies, CSRF, contraseñas ni secretos.
- No se añaden endpoints públicos, consultas SQL, migraciones ni entradas de usuario.
- Los cambios de producción se limitan a contratos de dominio (`aplicarEstado(): bool`), tope de
  rondas/observador y presentación del log de estados en Livewire.
- Los tests del arnés no usan datos reales, no escriben en BD/storage y no leen `.env`.
- El único log nuevo se deriva de estados internos de la batalla, no de datos externos.

## Recomendación

Si en una fase posterior se expone la simulación como comando/endpoint o se aceptan equipos
arbitrarios de usuario, solicitar auditoría de `security` antes de exponerlo.
