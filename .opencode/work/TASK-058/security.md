# TASK-058 — Seguridad

- **Estado:** **NO APLICA** (revisión acotada incluida en `review.md`).
- **Fecha:** 2026-11-18.

## Motivo

La tarea no añade autenticación, autorización, entradas de usuario, SQL nuevo,
sesiones, cookies, secretos, dependencias ni endpoints públicos.

## Comprobaciones realizadas

- Los endpoints siguen bajo `web` + `Authenticate`.
- `index()` procesa solo las expediciones del usuario autenticado:
  `paraUsuario((int) Auth::id())` filtra por `user_id`.
- `detalle()` mantiene `abort_unless($exploracion->user_id === $userId, 404)`
  antes de procesar; no hay IDOR.
- Las vistas de reclutamiento solo cambian una expresión de `disabled`; no hay
  nuevas entradas ni superficies de inyección.
- No se añadieron endpoints, migraciones ni secretos.
- No se leyó `.env` ni credenciales.

## Riesgo residual

- Procesar en un GET es un efecto secundario idempotente, con throttle y lock.
  Un prefetch o una navegación cross-site solo podría adelantar el cierre de una
  expedición que ya debía cerrarse; no otorga ventaja ni duplica recompensas.
  Aceptado y documentado en el plan.
