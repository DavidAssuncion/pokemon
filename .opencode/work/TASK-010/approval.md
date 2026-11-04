# TASK-010 — Aprobación

- **Estado:** `APROBADO`
- **ID de tarea:** TASK-010
- **Versión del plan:** `v2`
- **Fecha:** 2026-11-04
- **Solicitado por:** desarrollador

## Texto literal de la aprobación

Selección en la herramienta de preguntas: **«Aprobar plan v2»**.

## Alcance autorizado

- Cambiar `PokemonSeeder` para capitalizar nombres con `Str::ucfirst`, sin backfill ni migración de datos existentes.
- Ampliar la sesión a 1 año de inactividad (`SESSION_LIFETIME=525600`) en configuración, `.env.example` y Docker.
- Corregir la propagación de `es_shiny` en exploraciones y en la evolución, usando `x-sprite`.
- Verificación estática y manual; seguridad obligatoria.
- Revisión independiente solo si el desarrollador la solicita.
- **Fuera de alcance:** generación de los 59 assets shiny (documentados en `missing-shiny.md`), backfill de nombres, CSV, eventos de exploración, generación de shiny salvaje en exploraciones, tests y dependencias.

## Restricciones confirmadas

- Sin `git push`.
- Sin tests: no se crean, modifican ni ejecutan.
- No tocar archivos de TASK-009 ni su WIP.
- No leer `.env` ni exponer secretos.
- No modificar la base de datos salvo la verificación de solo lectura.
- No generar assets shiny en esta versión.
