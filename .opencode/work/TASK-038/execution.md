# TASK-038 — Ejecución

- **Estado:** COMPLETED — diagnóstico operativo, sin defecto de código

## Preflight (2026-11-09)

- `git branch --show-current`: `task-011`.
- `git status`: `M bugs.txt` y cambios de TASK-035 ajenos; no tocar.
- TASK-033, TASK-034, TASK-036 y TASK-037 cerradas y commiteadas.
- Base de trabajo: `/home/david/pokemon`.
- Alcance: investigar por qué Riolu/Machoke no atacan; sin tests, dependencias ni migraciones.

## Diagnóstico de `battle_ruta_6af1a5ea3b50c`

Inspección de solo lectura de la sesión `9G8cqazHljrHi7h1Pn3cD3cGXqKrj9NrtDhDXKyt` (usuario 2) y del agregado `Src\Battle\Domain\AgregadoBatalla`:

- `SESSION_VERSION` = 21.
- `ROUND` = 8.
- `RONDA_PREPARADA` = 7 → **la preparación de la ronda 8 no está confirmada**.
- `ACTOR_ACTUAL` = `jugador_22` (mewtwo).
- `NEXT_ACTOR` = `jugador_22` (mewtwo).
- `ACCION_PENDIENTE` = `null`.
- `HAY_ACCION_PENDIENTE` = sí.
- Todos los combatientes vivos tienen `ACTUADO=0`: nadie ha actuado todavía en la ronda 8.
- Equipo 1 «Test» (jugador): tyranitar (retaguardia), giratina-altered (vanguardia), mewtwo (retaguardia).
- Equipo 2 «Pokémon salvajes» (rival): riolu (retaguardia), medicham (vanguardia, debilitado), machoke (vanguardia, paralizado).
- Riolu y Machoke están en el **equipo rival**, no en el del jugador, en esta batalla concreta.
- Mewtwo tiene `presion` y `leftovers`; Giratina tiene `presion`, `telepatia` y `bucle_arena` con 4 turnos restantes.
- El log del agregado está vacío (`LOG_ENTRIES=0`) porque `SincronizadorVistaBatalla` lo consume al proyectar la vista.

## Interpretación

El fragmento pegado («--- Ronda 7 ---», presión, tormenta de arena) es el **cierre de la ronda 6 / transición a la ronda 7**, no la ronda completa. Las acciones de la ronda aparecen después de esas líneas. En el momento de la inspección, la batalla está al inicio de la ronda 8 esperando que el jugador confirme u omita la preparación; por eso Riolu/Machoke aún no han actuado. Una vez confirmada la preparación y jugados los turnos del jugador, la IA rival actúa con normalidad.

No se ha identificado ningún defecto de código en el flujo de turnos, en la IA ni en las condiciones. El comportamiento observado es el esperado para la fase de preparación.

## Registro

- 2026-11-09: incidencia recibida con el fragmento de la ronda 7.
- 2026-11-09: inspección de la sesión de `battle_ruta_6af1a5ea3b50c`; ronda 8 con preparación pendiente y todos los actores sin actuar.
- 2026-11-09: se descarta defecto de código; se cierra como diagnóstico operativo.
