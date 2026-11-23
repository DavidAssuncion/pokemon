# TASK-062 — Plan v1

- **ID:** TASK-062
- **Versión:** v1
- **Fecha:** 2026-11-21
- **Proyecto:** `/home/david/pokemon`
- **Estado:** ESPERANDO APROBACIÓN

## 1. Objetivo

Confirmar que el `Dockerfile` fija `memory_limit = 512M` y dejar el runtime de los
contenedores alineado con esa configuración, sin duplicar la directiva ya existente.

## 2. Hallazgo principal

La línea pedida ya está en `Dockerfile:82-83` (commit `06c93ec`, 2026-11-18):

```dockerfile
# Límite de memoria PHP
RUN echo "memory_limit = 512M" > $PHP_INI_DIR/conf.d/memory-limit.ini
```

`$PHP_INI_DIR` equivale a `/usr/local/etc/php` en `php:8.4-apache`, por lo que el efecto es
idéntico al de la ruta literal solicitada. El error de 128 MB es de runtime
(`Carbon::now()` en un proceso de consola) y la imagen en ejecución es anterior a ese commit.

## 3. Alcance

### Dentro del alcance

1. Verificación estática del `Dockerfile` y de la ausencia de configuraciones
   `memory_limit` contradictorias.
2. Reconstrucción de la imagen `pokemon-app:latest` con el `Dockerfile` actual.
3. Recreación de los servicios `app`, `worker` y `scheduler`.
4. Verificación en cada contenedor de `ini_get('memory_limit')` = `512M` y del estado de
   salud.

### Fuera de alcance

- Modificar el `Dockerfile` (la línea ya existe; no se duplica).
- Cambiar el valor a `-1` u otro distinto de `512M`.
- Optimizar `Carbon`, consultas o seeders, o investigar la causa raíz del consumo.
- Tests.
- Tocar los cambios locales de TASK-061 y `.githooks/pre-commit`.
- `git commit`, `git push` ni operaciones destructivas.

## 4. Archivos

No se crea ni modifica ningún archivo de aplicación. Solo se actualizan los artefactos de
coordinación de `.opencode/work/TASK-062/`.

## 5. Orden de ejecución

1. **Verificación estática** (orquestador): `git show 06c93ec -- Dockerfile`,
   `grep -n memory_limit Dockerfile`, comprobar que no hay otra directiva que lo pise.
2. **Reconstrucción** (solo si se aprueba): `docker compose build app`.
3. **Recreación** (solo si se aprueba): `docker compose up -d --force-recreate app worker scheduler`.
4. **Verificación runtime**:
   `docker compose exec -T app php -r 'echo ini_get("memory_limit"), PHP_EOL;'`
   y el equivalente en `worker` y `scheduler`; comprobar healthchecks.
5. **Reintento** de la operación que produjo el OOM (comando exacto pendiente de que el
   desarrollador lo aporte).

## 6. Criterios de aceptación

- `Dockerfile` contiene exactamente una directiva `memory_limit = 512M` en la etapa `app`.
- La imagen `pokemon-app:latest` se reconstruye sin errores.
- `ini_get('memory_limit')` devuelve `512M` en `app`, `worker` y `scheduler`.
- Los contenedores quedan `healthy` y el error de memoria no se reproduce en la operación
  afectada.

## 7. Verificación requerida

Análisis estático y comprobación manual de los criterios. Sin tests.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Downtime durante la recreación | Reconstruir primero; recrear en una ventana breve. |
| `worker`/`scheduler` con `pull_policy: never` mantienen imagen vieja | `--force-recreate` y verificar `ini_get` en cada servicio. |
| `opcache.validate_timestamps=0` | Recrear el contenedor; no basta con copiar archivos. |
| 512 MB × procesos concurrentes | Vigilar RAM del host; ajustar si hay presión. |
| El límite enmascara la causa raíz | Registrar el comando y el stack; si reaparece, analizar consumo real. |

## 9. Rollback

No hay cambios de archivos. Si la recreación falla, los contenedores anteriores pueden
volver a levantarse con la imagen previa; no se elimina ninguna imagen ni volumen.
