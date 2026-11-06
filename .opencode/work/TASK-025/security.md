# TASK-025 — Seguridad

- **Estado:** NO APLICA
- **Motivo:** refactor interno de presentación Livewire. No se tocan autenticación, autorización, entradas de usuario, SQL, sesiones, cookies, secretos, dependencias ni endpoints.
- **Excepción:** si la extracción toca `Auth::id()`, autorización de acciones o payload de sesión, se revisará que no se alteren los controles existentes.
