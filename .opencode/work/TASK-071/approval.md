# TASK-071 — Aprobación

- **ID de tarea:** TASK-071
- **Estado:** APROBADO
- **Plan:** v3
- **Fecha:** 2026-11-23

## Texto literal de la aprobación

> public\images\rivales aqui tienes las imagenes, los equipo pokemon luego los confirmo, de momento puedes ir implementando

## Interpretación

El desarrollador autoriza la implementación del plan v3 vigente y aporta las imágenes
de las facciones en `public/images/rivales/`. Los equipos Pokémon quedan como
propuesta provisional hasta su confirmación posterior; se implementan los rosters del
catálogo v3 y se podrán ajustar sin cambiar la estructura.

## Alcance aprobado

1. Retirar Torres (código, rutas, vistas, modelos, seeder y migraciones no
   trackeadas); sin limpiar las tablas de la BD local.
2. Provincia virtual «Temporal» en `/habitats`, con 5 facciones.
3. 3 oleadas por facción × 4 combates (3 reclutas + 1 jefe).
4. Jefes secundarios en oleadas 1-2; capitán principal en la oleada 3.
5. Desbloqueo por medallas 2/3/4/5/6; oleadas 2 y 3 a los 9 y 15.
6. Nivel rival = nivel exacto del jugador.
7. IV/EV: oleada 1 TopUno/TopDos; oleada 2 TopDos/Completo; oleada 3 Completo.
8. Recompensa EXP ×3 + caramelo de tipo; sin reclutamiento.
9. Progreso con `oleada_actual` y `combate_actual`.
10. Selector de equipo simple en v1.
