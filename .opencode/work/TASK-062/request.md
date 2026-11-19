# TASK-062 — Petición

- **ID:** TASK-062
- **Fecha:** 2026-11-18
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** configuración de infraestructura (Dockerfile / PHP).
- **Estado:** RECEIVED.

## Texto literal

> In Creator.php line 249:
>
>   Allowed memory size of 134217728 bytes exhausted (tried to allocate 2097152
>   bytes)
>
> añade a dockerfile RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini

## Interpretación inicial

El desarrollador encontró un agotamiento del límite de memoria de PHP (128 MB,
`134217728` bytes) y pide fijar `memory_limit = 512M` en la imagen mediante un
`.ini` adicional en `conf.d` del PHP embebido en la imagen final.

La línea solicitada se añadiría a la etapa `app` (`php:8.4-apache`) del
`Dockerfile`. Queda por verificar si el error pudo originarse en la etapa de
build `composer` (`composer:2`) y, en ese caso, si además hace falta ajustar esa
etapa (`COMPOSER_MEMORY_LIMIT`), y si la ruta literal equivale a `$PHP_INI_DIR`
usada ya por el proyecto.

## Contexto conocido (pre-análisis del orquestador)

- `Dockerfile` multietapa:
  1. `composer:2` → `composer install --no-dev --optimize-autoloader`.
  2. `node:22-alpine` → build de Vite.
  3. `php:8.4-apache` → imagen final; copia `docker/php/opcache.ini` a
     `$PHP_INI_DIR/conf.d/opcache.ini` y arranca con `entrypoint.sh`.
- No existe configuración de `memory_limit` en `Dockerfile`, `docker/` ni
  `docker-compose*.yml`.
- `docker-compose.yml` usa la imagen `pokemon-app:latest` para `app`, `worker` y
  el servicio de scheduler; los tres comparten la misma imagen y por tanto el
  mismo `php.ini`.
- El proyecto es Laravel 12 sobre PHP 8.4.
- `composer` del host es 2.4.4 y `vendor/` existe.

## Restricciones

- Sin tests: no crear, modificar ni ejecutar la suite.
- Sin dependencias nuevas ni cambios en `composer.json`/`package.json`.
- No tocar `.env`, `.env.testing` ni secretos.
- No revertir ni sobrescribir los cambios locales preexistentes del
  desarrollador.
- Sin operaciones git destructivas ni `git push`.
- Cambio acotado a la configuración de la imagen.
