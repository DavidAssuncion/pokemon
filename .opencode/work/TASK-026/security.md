# TASK-026 — Seguridad

- **Estado:** NO APLICA
- **Motivo:** refactor interno de presentación Livewire. No se tocan autenticación, autorización, entradas de usuario, SQL, cookies, secretos, dependencias ni endpoints.
- **Excepción:** `resolverObjeto` usa `Auth::id()` y `UsarObjetoEnCombate`; se verificará que el usuario y la autorización se resuelvan igual que antes.
