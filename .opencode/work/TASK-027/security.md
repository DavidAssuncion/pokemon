# TASK-027 — Seguridad

- **Estado:** NO APLICA
- **Motivo:** refactor interno de presentación Livewire. No se tocan autenticación, autorización, entradas de usuario, SQL, cookies, secretos, dependencias ni endpoints.
- **Excepción:** si la fachada mueve `Auth::id()` o la autorización de acciones, se revisará que se conserve exactamente.
