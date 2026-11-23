# TASK-069 — Revisión

- **Revisor:** auto-revisión del agente (no hay revisor independiente en esta sesión).
- **Fecha:** 2026-11-19.

## Comprobaciones

- [x] `php -l` de todos los archivos modificados.
- [x] `vendor/bin/pint --format agent` sobre los archivos tocados.
- [x] `vendor/bin/phpstan analyse` (nivel 6): 0 errores.
- [x] `vendor/bin/deptrac analyse`: 118 violaciones (antes 128).
- [x] `php artisan route:list --path=api/admin -v`: `Authorize:admin` presente.
- [x] Sin cambios en `tests/`.
- [x] Sin cambios en los archivos de TASK-068 (`MovesPreviewPresenter`,
  `ArbolSendas`, `PresentadorDetalleEspecie`, `ReclutadoSerializer`,
  `ObjetivoMovimiento`, Blade).

## Riesgos residuales

1. **Migraciones nuevas**: requieren `php artisan migrate`; el flag `is_admin`
   queda a `false` para todos los usuarios existentes. Hay que marcar al
   administrador manualmente.
2. **Datos duplicados**: la migración de invariantes borra exploraciones
   activas duplicadas (conserva la más reciente) y favoritos duplicados
   (conserva el más antiguo). Es una limpieza destructiva deliberada y
   aprobada en el plan.
3. **PostgreSQL**: los índices parciales usan sintaxis estándar soportada por
   PostgreSQL y SQLite.
4. **Hardener (nivel 8)**: sigue teniendo deuda preexistente; no se regeneró su
   baseline en esta tarea.
5. **No se ejecutaron tests** (política del proyecto).

## Elementos diferidos

- `src/Avistamientos/**`: sin cablear; pendiente de decisión.
- `ReclutadoSerializer`: `auth()->id()` (archivo en edición por TASK-068).
- Split de `ReclutadoController`, `BatallaVistaPresenter`,
  `BattleEffectServiceProvider` y `PresentadorExploraciones`.
- Mover DTOs de `Habitats\Presentation` y `Battle\Presentation` a `Domain`.
- Mover reglas de `app/Support` a `src/`.
- FormRequests en los controladores con validación inline.
