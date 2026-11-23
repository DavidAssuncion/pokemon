# TASK-062 — Análisis

- **ID:** TASK-062
- **Fecha:** 2026-11-21
- **Analista:** `analyst`
- **Estado:** COMPLETADO

## Resultado del `analyst`

### 1) `Creator.php:249` — no es Composer

- Composer 2.4.4 (`composer/composer`, tag `2.4.4`, 1113 rutas) **no contiene ningún
  `Creator.php`**: verificado sobre el árbol del tag.
- El único `Creator.php` del proyecto es
  `vendor/nesbot/carbon/src/Carbon/Traits/Creator.php:249` (Carbon 3.13.2) →
  `return new static(null, $timezone);` dentro de `Carbon::now()`.
- `In <archivo> line <n>:` es el formato de error de Symfony Console ⇒ proceso **PHP de
  consola** (artisan/entrypoint/seeders), no el instalador de Composer. Inferido, no
  comprobado.

### 2) ¿Build o runtime?

Comprobado:

- La imagen oficial `composer:2` escribe `memory_limit=-1` en `$PHP_INI_DIR/php-cli.ini`
  (Dockerfile de `composer/docker`, 2.4) y Composer además eleva su límite a 1.5G. La etapa
  `composer` **no** está limitada a 128M.
- `134217728` = 128M es el **default compilado de PHP** (sin `php.ini` que lo fije).
- El PHP del host (`/etc/php/8.4/cli/php.ini`) tiene `memory_limit=-1` ⇒ no es el host.
- `composer.lock:11500` → `plugin-api-version 2.3.0`, coherente con Composer 2.4.x del host.

Inferido (alta confianza): OOM en **runtime de contenedor** con imagen construida **sin**
`memory-limit.ini`. No verificable aquí: `docker image ls` solo muestra `alpine:latest`
(sin `pokemon-app:latest`) y no hay contenedores activos.

### 3) ¿Corrige la etapa `app`?

Sí para runtime: `app`, `worker` y `scheduler` comparten `pokemon-app:latest`
(`docker-compose.yml:34,73,103`) ⇒ un solo `.ini` cubre los tres roles, incluidos
`migrate`/`db:seed` del entrypoint. No afecta al build, pero el build **no lo necesita**.
`COMPOSER_MEMORY_LIMIT` **no hace falta** (redundante; solo afecta al propio Composer, no a
procesos hijos).

### 4) Ruta

`/usr/local/etc/php/conf.d/` es válida en `php:8.4-apache`: es exactamente `$PHP_INI_DIR`
(`ENV PHP_INI_DIR=/usr/local/etc/php`) y es el `--with-config-file-scan-dir` común a
**Apache (mod_php) y CLI**. Equivale a `$PHP_INI_DIR/conf.d`. Preferible `$PHP_INI_DIR`
(portable a Alpine).

### 5) Diff recomendado: NINGUNO — ya está aplicado

`Dockerfile:82-83` ya contiene
`RUN echo "memory_limit = 512M" > $PHP_INI_DIR/conf.d/memory-limit.ini`, en `HEAD`
(commit `06c93ec`, 2026-11-18 19:13, único commit que introduce `memory_limit`).
`request.md` (19:12) afirmaba que no existía: quedó desfasado. Añadir la línea literal
duplicaría configuración.

Acción pendiente real: **reconstruir y recrear**. Riesgos operativos:

- **[alto] Imagen obsoleta:** `worker`/`scheduler` usan `pull_policy: never` y mismo tag;
  `restart: unless-stopped` puede dejar contenedores con el `.ini` antiguo → verificar con
  `php -r 'echo ini_get("memory_limit");'` en cada servicio.
- **[medio] `opcache.validate_timestamps=0`:** requiere recrear el contenedor, no copiar
  archivos.
- **[medio] 512M × workers Apache prefork:** presión de RAM; vigilar consumo.
- **[medio] 512M enmascara la causa raíz** (posible consulta/leak); un pico mayor acabará
  en OOM-kill del contenedor.

**Ambigüedad bloqueante para el diagnóstico fino:** falta el comando/URL y las primeras
líneas del stack; sin ellas no se puede confirmar HTTP vs consola ni el módulo que consume
los 2 MB.

## Conclusión de coordinación

La modificación solicitada **ya existe en el repositorio** (confirmado en `Dockerfile:82-83`
y commit `06c93ec`). No procede duplicarla. El fallo persiste porque la imagen/contenedores
en ejecución se construyeron antes de ese commit. La acción útil es reconstruir la imagen y
recrear los servicios, y verificar `memory_limit` en cada rol.
