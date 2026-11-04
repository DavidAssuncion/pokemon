# TASK-010 — Seguridad

- **Estado:** `APPROVED_WITH_FINDINGS`
- **Responsable:** `security`
- **Fecha:** 2026-11-04

## Veredicto

TASK-010 **no introduce vulnerabilidades explotables nuevas**. No hay hallazgos `CRITICAL`. Persisten riesgos `HIGH` preexistentes o de despliegue.

## Hallazgos

| Severidad | Hallazgo | Evidencia | Recomendación |
|---|---|---|---|
| Alto | Sesión de 1 año con cookie sin `Secure` | `config/session.php:174` (`secure=null`) | Forzar `SESSION_SECURE_COOKIE=true` antes de exponer producción por HTTPS |
| Alto | Login sin rate limit | `routes/web.php:13-14` | Añadir `throttle` al login |
| Alto | Usuario demo con contraseña por defecto | seeder de usuarios | Deshabilitar o cambiar la contraseña antes de producción |
| Medio | Payload de sesión sin cifrar | `config/session.php:52` (`encrypt=false`) | Valorar `SESSION_ENCRYPT=true` |
| Medio | Sin invalidación global al cambiar contraseña | `bootstrap/app.php` sin `authenticateSessions()` | Activar `authenticateSessions()` o invalidar sesiones |
| Medio | Crecimiento de `sessions` | `lifetime=525600`; GC 2/100 | Limpieza programada y vigilancia |
| Bajo | Fijación de sesión | Regeneración en login | Ya mitigado |
| Bajo | CSRF | `ValidateCsrfToken` + `@csrf` | Ya mitigado |

## Alcance revisado

- Cambio de `SESSION_LIFETIME` en `config/session.php`, `.env.example` y `docker-compose.yml`.
- Flujo de autenticación (`AuthController`, `routes/web.php`, `bootstrap/app.php`, login Blade).
- Propagación de `es_shiny` en exploraciones/evolución y cambio de `Str::ucfirst` en el seeder: sin superficie de SQLi, XSS, IDOR ni mass assignment nueva.
- No se modificó ningún archivo ni se ejecutaron tests.

## Limitaciones

- No se revisó el `.env` real ni se probó el despliegue en producción.
- El usuario demo y el rate limit son deuda preexistente, no introducida por TASK-010.
