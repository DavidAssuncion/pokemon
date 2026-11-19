# TASK-067 — Petición

- **ID:** TASK-067
- **Fecha:** 2026-11-19
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección de fuga de información en el modal de granjas de hábitats.
- **Estado:** RECEIVED.

## Texto literal

> En habitats granjas no debe mostrarse la familia del pokemon si esta no ha sido desbloqueada/avistada todavia, no debemos dar pistas

## Contexto conocido

- La vista de hábitat (`resources/views/habitats/show.blade.php`) incluye el
  parcial `resources/views/habitats/partials/modal-granja.blade.php`.
- El modal carga `GET /api/habitats/{habitat}/farm`, servido por
  `Src\Granjas\Infra\Controllers\GranjaController::show`, que delega en
  `Src\Granjas\App\ObtenerGranja`.
- `ObtenerGranja` construye las familias del hábitat con
  `Src\Habitats\App\ObtenerFamiliasDisponibles` y las devuelve como
  `FamiliasGranjaCollection`; cada familia incluye `base`, `evolutions` y
  `types` (`Src\Granjas\Domain\DataTransferObjects\FamiliaGranja::toArray`).
- El estado de avistamiento del jugador vive en la tabla `pokedex`
  (`user_id`, `pokemon_id`, `visto`, `atrapado`).
- La columna derecha del hábitat ya oculta con `unknown.webp` los Pokémon no
  avistados (`resources/views/habitats/partials/columna-derecha.blade.php:262-273`),
  pero el modal de granjas no aplica esa regla.
- Existe el flag temporal de QA `App\Support\DesbloqueoPokedex` que permite ver
  todo en local/testing o con `POKEDEX_UNLOCK_ALL=true`.

## Restricciones conocidas

- No modificar tests ni ejecutar la suite.
- No tocar autenticación, autorización ni datos de otros usuarios.
- Respetar `.ai/rules/frontend-minimal-logic.md`: la regla de visibilidad debe
  resolverse en backend; la vista solo presenta.
- Los cambios locales preexistentes del desarrollador no deben sobrescribirse.
- Sin dependencias nuevas.
