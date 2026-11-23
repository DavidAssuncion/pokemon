# TASK-062 — Informe (BLOQUEADO)

- **ID:** TASK-062
- **Estado:** BLOCKED
- **Fecha:** 2026-11-21

## Resumen

La directiva solicitada (`memory_limit = 512M` en `conf.d`) **ya existe** en el
`Dockerfile:82-83` desde el commit `06c93ec` (2026-11-18 19:13), usando `$PHP_INI_DIR`, que
equivale a `/usr/local/etc/php`. No se ha modificado ningún archivo.

## Evidencia

- `Dockerfile:82-83`:
  `RUN echo "memory_limit = 512M" > $PHP_INI_DIR/conf.d/memory-limit.ini`.
- `git log -1 -- Dockerfile` → `06c93ec 2026-11-18 19:13:29 +0100 subir cache`.
- `Creator.php:249` → `vendor/nesbot/carbon/src/Carbon/Traits/Creator.php:249`
  (`Carbon::now()`), no Composer.
- El host tiene `memory_limit=-1`; el OOM de 128 MB corresponde a un contenedor construido
  antes de `06c93ec`.

## Decisión pendiente

Reconstruir `pokemon-app:latest` y recrear `app`, `worker` y `scheduler`, o cerrar como
verificación sin tocar contenedores.

## Verificación ejecutada

- Inspección estática del `Dockerfile`, `docker/php/opcache.ini`, `docker-compose.yml`,
  `composer.lock` y `vendor/`.
- Comprobación de `git log`/`git show` sobre el `Dockerfile`.
- Sin tests, sin builds y sin modificar archivos.

## Bloqueo

`question` falló por reinicio del servidor. Se requiere aprobación explícita:
`APROBADO plan v1`, `Solo confirmar, no reconstruir` o `Cancelar tarea`.
