# TASK-057 — Informe final

- **Estado:** COMPLETED
- **Fecha:** 2026-11-18
- **Aprobación:** instrucción directa del desarrollador («No crees... Solo corrige los
  registros necesarios para que seguarde bien en bbdd»).

## Resumen

Se corrigieron las evoluciones regionales que quedaban colgadas de la forma normal:
Perrserker (863) ahora depende de Meowth de Galar (10161) y cadena 12022; Obstagoon
(862) de Linoone de Galar (10175) y cadena 12134; y los 7 casos análogos (Cursola,
Sirfetch'd, Mr. Rime, Runerigus, Clodsire, Sneasler, Overqwil). También se corrigieron
7 niveles de evolución corrompidos por el índice «última fila gana».

## Archivos

- **Modificado:** `database/seeders/PokemonSeeder.php`.
- **Creados:** ninguno.
- **CSV:** sin cambios (`storage/data/**` intacto).
- **Tests:** sin cambios; no se ejecutó la suite.

## Cambios

- `REGION_RANGES` + `regionById()`: detecta las 58 formas regionales por ID (Alola 18,
  Galar 20, Hisui 16, Paldea 4), sin depender del sufijo del nombre.
- Evoluciones agrupadas por `evolved_species_id`; si no hay fila propia, se usa
  `base_form_id` como padre y se hereda su `evolution_chain_id`.
- `minimum_level` = mínimo de niveles no nulos; 40 si todos son nulos.
- Sin guards, borrados, logs ni cambios de esquema. Se conserva el re-parenting regional
  y el fallback de Basculegion 902 (padre 550, cadena 280).

## Verificación ejecutada

- `php -l database/seeders/PokemonSeeder.php`: sin errores.
- `vendor/bin/pint database/seeders/PokemonSeeder.php --format agent`: `pass`.
- `php artisan db:seed --class=PokemonSeeder --no-interaction`: ejecutado sin errores.
- BD tras el seed:
  - 1083 pokémon, 514 evoluciones, 0 huérfanas, 13 aristas cross-chain.
  - 9 chains/padres/niveles: 862→12134/10175/35, 863→12022/10161/28,
    864→12113/10173/38, 865→12035/10166/40, 866→12057/10168/42,
    867→12287/10179/40, 903→13109/10235/40, 904→13106/10234/40,
    980→14096/10253/20.
  - 7 niveles: 28→22, 53→28, 80→37, 101→30, 555→35, 156→14, 724→34.
  - Meowth 52 solo evoluciona a Persian 53; Meowth-galar 10161 solo a Perrserker 863;
    Zigzagoon-galar 10174 → Linoone-galar 10175 → Obstagoon 862.
  - Nombres sin sufijos nuevos: 10161 `meowth`, 10175 `linoone`, 10177
    `darmanitan-standard`.
  - Sin cambios en `pokemon_habitat` (1006), `shelters` (4), `player_inventory`
    `familia:` (121), `huevos` (1) y `nest_plantings` (2).
- Revisión independiente (`reviewer`): **APPROVED**, solo hallazgos BAJOS/NIT.
- No se ejecutaron tests (política del proyecto).

## Decisiones

- No se restauraron sufijos; la región se resuelve por ID.
- Una fila por evolucionado (se respeta `unique(evolved_species_id)`); Overqwil colapsa
  sus 3 métodos.
- Basculegion 902 queda con padre normal 550 (su base 10247 no se siembra), documentado.
- `melmetal`/`meltan`, iconos de caramelo, claves `familia:`, `PokemonHabitatSeeder` y
  `ReclutadosSeeder`/`BuildShowdownMovesets` quedan fuera de alcance.

## Riesgos residuales

- Los rangos de ID son estáticos: una futura forma regional fuera de los rangos se
  omitiría hasta actualizar `REGION_RANGES`.
- Nombres duplicados (36 claves compartidas por 73 Pokémon) sin unique; la identidad
  debe ser `id`, nunca `name`.
- El seed no es transaccional y sobrescribe los 1083 nombres; no se creó backup. Un
  re-seed con el código anterior no restaura exactamente los nombres previos.
- `nests` = 6 y `reclutados` = 40 son valores preexistentes ajenos; el seed no los tocó.
- PHPStan excluye `database/`; la verificación estática efectiva es `php -l` + Pint +
  consultas de BD.
- No se re-ejecutó el seed para probar idempotencia (la revisión fue de solo lectura).
- `PokemonHabitatSeeder` no debe re-ejecutarse sobre la BD actual: duplicaría 204 pares
  de hábitat.

## Próximos pasos opcionales

- Abrir tarea de assets para los 8 iconos de caramelo de las cadenas nuevas.
- Revisar consumidores que resuelvan Pokémon por nombre (hoy no hay ninguno en
  `app/`, `src/` ni `routes/`).
- Si se desea rollback de nombres, restaurar desde un backup previo; no existe en esta
  ejecución.
