# TASK-069 — Seguridad

- **Fecha:** 2026-11-19.

## Cambios de seguridad

1. **Autorización de administración**
   - Nueva columna `users.is_admin` (migración
     `2026_11_19_000001_add_is_admin_to_users_table.php`).
   - Gate `admin` en `AppServiceProvider::boot()`.
   - Rutas `/api/admin/gyms` bajo `can:admin`; verificado con
     `route:list -v` (`Authorize:admin`).
   - Antes: cualquier usuario autenticado podía crear/editar gimnasios.

2. **Invariantes de datos**
   - Índices parciales únicos para favoritos y exploraciones activas.
   - `toggleFavorito` serializa por usuario; `darCaramelo`, `darEvs` y
     `evolucionar` bloquean filas antes de leer/escribir.

3. **Errores visibles**
   - Un gimnasio inexistente en la meta de batalla ya no pierde la medalla en
     silencio: se registra en `Log::error` y se informa en el log de la vista.

## No modificado

- No se han leído ni tocado `.env`, credenciales ni secretos.
- No se ha desactivado ninguna validación, policy, CSRF ni middleware.
- `BattleSessionService::unserialize()` sobre datos de sesión sigue igual; se
  documenta como deuda (no se amplió la superficie).
