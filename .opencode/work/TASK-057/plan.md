# TASK-057 — Plan v5 (corrección directa)

- **ID:** TASK-057
- **Versión:** v5
- **Fecha:** 2026-11-18
- **Estado:** APROBADO por instrucción directa del desarrollador.
- **Base:** `analysis.md` + `architecture.md`, acotado a lo pedido.

## Instrucción literal

> No crees, ya esta todo lo necesario creado. Solo corrige los registros necesarios
> para que seguarde bien en bbdd

## Alcance

**Solo se modifica `database/seeders/PokemonSeeder.php`.** No se crean archivos ni se
tocan los CSV. Después se ejecuta `PokemonSeeder` para corregir la BD.

### Cambios en el seeder

1. **Región por ID** (constante interna, sin archivos nuevos): Alola
   `10091,10092,10100–10115`; Galar `10161–10180`; Hisui `10229–10244`; Paldea
   `10250–10253`. Sustituye `regionOf($name)`; así las 38 formas de Alola/Galar se
   siembran aunque su nombre ya no lleve sufijo.
2. **Padre por `base_form_id`:** si un evolucionado solo tiene filas con
   `base_form_id` y esa forma está sembrada, usar esa forma como
   `evolves_from_species_id` y darle su misma `evolution_chain_id`. Si tiene fila
   propia, se mantiene el comportamiento actual.
3. **Nivel mínimo:** `minimum_level = MIN(niveles no nulos) ?? 40`.

Sin borrados, guards, logs ni cambios de esquema.

## Aplicación a BD

- `php artisan db:seed --class=PokemonSeeder --no-interaction`.
- No se ejecuta `PokemonHabitatSeeder` ni `DatabaseSeeder`.
- Efecto esperado: 9 chains y 15 aristas corregidas; 1083 pokémon / 514 evoluciones;
  nombres sin sufijos y capitalizados por el `Str::ucfirst` existente.

## Fuera de alcance

- `storage/data/*.csv` (no se tocan).
- Cualquier archivo nuevo.
- `PokemonHabitatSeeder`, `ReclutadosSeeder`, `BuildShowdownMovesets`, iconos,
  Basculegion 902, `melmetal`/`meltan`, claves `familia:`, UI.
- Tests y migraciones.

## Criterios de aceptación

- 862→12134/10175/35, 863→12022/10161/28, 864→12113/10173/38,
  865→12035/10166/40, 866→12057/10168/42, 867→12287/10179/40,
  903→13109/10235/40, 904→13106/10234/40, 980→14096/10253/20.
- 7 niveles: 28→22, 53→28, 80→37, 101→30, 555→35, 156→14, 724→34.
- BD: 1083 pokémon, 514 evoluciones, 0 huérfanas, 13 cross-chain.
- Meowth 52 solo a Persian 53; Meowth-galar 10161 solo a Perrserker 863;
  Zigzagoon-galar 10174 → Linoone-galar 10175 → Obstagoon 862.
- `pokemon_habitat`, `nests`, `shelters`, `player_inventory`, `reclutados`, `huevos`
  y `nest_plantings` sin cambios.

## Verificación (sin tests)

- `php -l database/seeders/PokemonSeeder.php`.
- `vendor/bin/pint --dirty --format agent`.
- Tras el seed, consultas de solo lectura en `tinker` para comprobar los 9 padres/
  chains, los 7 niveles, los conteos y las aristas.
- No se ejecuta la suite de tests.

## Rollback

- Código: `git restore --source=HEAD -- database/seeders/PokemonSeeder.php`.
- Datos: el seed es idempotente; revertir el código y volver a sembrar devuelve el
  estado anterior de evoluciones/chains (los nombres quedarían sin sufijo, que es lo
  deseado). No se crea backup.
