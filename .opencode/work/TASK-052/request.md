# TASK-052 — Petición

- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** estudio de viabilidad (solo análisis, sin implementación).
- **Estado:** RECEIVED → ANALYZING

## Texto literal

> https://github.com/josewp808/pokemon-trainer-sprites/tree/main/trainers incluye entrenadores para las rutas, gimnasios y posterior equipos de rivales magma/aqua, teamrocket... Analicemos viabilidad

## Contexto conocido

- Repositorio externo: `josewp808/pokemon-trainer-sprites`.
  - Rama `main`, 2 commits, último push 2025-12-27, ~463 KB.
  - 572 archivos PNG en `trainers/`, 80×80, paleta de 8 bits, fondo transparente.
  - Sin `README` ni `LICENSE` en la raíz.
  - Clon local de referencia: `/tmp/opencode/trainer-sprites/repo/trainers`.
- La aplicación solo tiene un icono genérico de entrenador:
  `public/images/misc/trainer.webp`, usado en
  `resources/views/habitats/partials/columna-izquierda.blade.php:72`.
- Gimnasios: catálogo en código (`src/Gimnasios/Domain/CatalogoGimnasios.php`),
  vista `resources/views/gimnasios/show.blade.php`.
- Combate de ruta: `src/CombateRuta/**`, `app/Support/CombateRuta/**`.
- Entrenadores de hábitat: `src/CombateEntrenadores/**` (generados por fecha/hábitat/nivel).
- No se ha identificado un dominio de facciones/rivales (Magma, Aqua, Rocket) en el código.

## Objetivo

Determinar si los sprites del repositorio son viables para:

1. Entrenadores de ruta.
2. Líderes y etapas de gimnasio.
3. Futuros equipos de rivales (Magma/Aqua/Team Rocket).

## Entregables

- Veredicto de viabilidad por caso de uso (viable / viable con cambios / no listo).
- Inventario y cobertura de sprites frente a las entidades reales del juego.
- Requisitos técnicos, de licencia/procedencia y de esfuerzo.
- Riesgos y recomendación por fases.

## Restricciones

- Solo lectura; no modificar código, vistas, datos ni assets.
- No crear, modificar ni ejecutar tests.
- No leer `.env` ni secretos.
- No implementar nada sin un plan aprobado en una tarea posterior.
- No tocar los cambios locales de TASK-049, TASK-050 ni TASK-051.
