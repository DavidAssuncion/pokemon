# TASK-064 — Petición

- **ID:** TASK-064
- **Fecha:** 2026-11-19
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** funcionalidad de QA/debug (vista `/debug` + acciones en Pokédex).
- **Estado:** RECEIVED.

## Texto literal

> En la pokedex tenemos algo similar a un modo admin/test, debemos ampliar esto,
> se crea una vista, /debug que no aparece en el menu, desde aqui tendremos varias
> filas y botones para experiencia y caramelos de tipo y de stats, podran añadir y
> quitarse de su inventario
> +1000exp
> +10000exp
> +100000exp
> +1000000exp... Tambien para restar
>
> En pokedex con este modo, podras añadir a reclutar un pokemon asi como darte
> tambien caramelos de esta familia

## Contexto conocido

- Ya existe un flag temporal de QA `POKEDEX_UNLOCK_ALL` / `?unlock=1` en
  local/testing (`app/Support/DesbloqueoPokedex.php`, `config/pokedex.php`).
- La Pokédex se sirve desde `PlayerController::pokedex` y
  `resources/views/pokedex/index.blade.php`.
- El inventario usa `player_inventory` con claves `familia:{chain_id}`,
  `ev:{1..6}` y `tipo:{slug}` (`app/Support/ItemCatalogo.php`).
- El reclutamiento actual pasa por `ReclutamientoController::recruit` y
  `CrearReclutado`; requiere un `Reclutable` propiedad del usuario.

## Interpretación inicial

1. Nueva vista `/debug`, fuera del menú de navegación, accesible solo en el mismo
   contexto de QA que el flag actual (local/testing o flag explícito).
2. La vista muestra filas de experiencia de la cuenta con botones para sumar y
   restar `1.000`, `10.000`, `100.000` y `1.000.000`.
3. La vista muestra filas de caramelos de tipo y de estadísticas (EV) con botones
   para añadir y quitar unidades del inventario del jugador.
4. En `/pokedex`, cuando el modo QA está activo, cada Pokémon permite:
   - reclutarlo directamente, y
   - obtener caramelos de su familia evolutiva.

## Decisiones del desarrollador (2026-11-19)

- **Experiencia:** cuenta del jugador (`users.experiencia`), no de reclutados.
- **Cantidades de caramelos:** botones de `+/-100`, `+/-255` y `+/-3072`.
- **Acceso a `/debug`:** solo `local`/`testing`, por el mismo criterio que el
  flag actual; 404 fuera de esos entornos, sin depender de `APP_DEBUG`.

## Restricciones conocidas

- No se crean, actualizan ni ejecutan tests.
- No se sobrescriben los cambios locales preexistentes (TASK-061/062/063 en
  curso y modificaciones sin commitear en `master`).
- Sin `git push` ni operaciones destructivas.
- La vista de debug no debe aparecer en el menú.
- Toda acción de otorgar/retirar debe ser idempotente y validada en servidor.
- Al afectar a inventario, experiencia y reclutamiento, requiere auditoría de
  seguridad antes de cerrar.
