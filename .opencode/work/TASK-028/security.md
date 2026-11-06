# TASK-028 — Seguridad

- **Estado:** NO APLICA
- **Motivo:** verificación en `/tmp` con datos mock; no se tocan autenticación, autorización, datos reales ni secretos.
- **Excepción:** si el arnés necesita un usuario falso, se usará un id ficticio y sesión array; nunca credenciales reales.
