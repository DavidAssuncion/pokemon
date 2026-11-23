# Animaciones de Pokémon Showdown

Este directorio y `resources/js/battle-animations/` contienen código y recursos
extraídos de [smogon/pokemon-showdown-client](https://github.com/smogon/pokemon-showdown-client),
commit `36ba1106bbb0e95b457e29a07b5f9e9db29ac85a` (2026-11-23).

## Código

- `play.pokemonshowdown.com/src/battle-animations.ts` — cabecera MIT; la mayor
  parte del archivo es CC0.
- `play.pokemonshowdown.com/src/battle-animations-moves.ts` — cabecera CC0.
- El repositorio del cliente en su conjunto es AGPL-3.0. El código de animación
  se ha aislado en un bundle propio para no incorporar el resto del cliente.
  Si se redistribuye el proyecto, revisar el cumplimiento de las licencias
  originales y conservar este aviso.

## Imágenes `fx/`

Las imágenes de `public/showdown/fx/` provienen de
`play.pokemonshowdown.com/fx/`. La licencia CC0 del archivo
`battle-animations.ts` cubre la mayoría de estas imágenes, con estas excepciones:

- `icicle.png` y `lightning.png`: Clint Bellanger, triple licencia
  GPLv2/GPLv3/CC-BY-SA-3.0.
- `bone.png`: misma licencia que las anteriores según la cabecera del archivo.
- `rocks.png`, `rock1.png`, `rock2.png`: PO user "Gilad", GPLv3.

Las imágenes de `sprites/` no se han copiado y no están cubiertas por estas
licencias. Los clips `weather-gen6-*.webm` y `weather-gen6-*.mp4` también
provienen de `fx/`; la cabecera de `battle-animations.ts` habla expresamente de
imágenes, así que su cobertura CC0 debe revisarse antes de redistribuirlos.
Los nombres y personajes de Pokémon son marcas de Nintendo, Game Freak y
The Pokémon Company; este aviso no concede derechos sobre ellos.

## Adaptador

`resources/js/battle-animations/runtime.js` es código propio de este proyecto:
traduce las llamadas de Showdown (`showEffect`, `anim`, `backgroundEffect`,
`wait`) a Web Animations API sobre el campo de batalla de Laravel.
