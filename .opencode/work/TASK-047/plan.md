# TASK-047 — Plan v1: seeder de mazmorras

- **Estado:** EJECUTADO (aprobado y verificado)
- **Versión:** v1
- **Fecha:** 2026-11-16
- **Tipo:** implementación de datos (seeder), sin cambios de esquema.

## 1. Objetivo

Poblar `habitats.mazmorra` con un piso por Pokémon del pool real de cada hábitat,
agrupados por nivel 1 → 2 → 3 y ordenados por suma de stats base (BST) ascendente.
El resultado debe ser determinista, idempotente y consumible por el módulo de
mazmorras existente.

## 2. Alcance

- Crear `database/seeders/MazmorraSeeder.php`.
- Registrar el seeder en `database/seeders/CatalogoSeeder.php`, después de
  `PokemonHabitatSeeder`.
- Ejecutar el seeder sobre la BD local de desarrollo y verificar los datos.

## 3. Fuera de alcance

- Migraciones o cambios de esquema.
- Cambios en `src/Mazmorras`, `app/`, rutas, vistas o UI.
- Re-sembrar `pokemon_habitat` desde el CSV o conciliar la divergencia BD/CSV.
- Tests (no se crean, no se ejecutan).
- Cambios en `habitats.mazmorra` ya configurados manualmente.
- Hábitat 49 `Legendarios`.

## 4. Decisiones vinculantes

1. **Fuente:** BD actual (`pokemon_habitat` + `pokemon` + `pokemon_stats`).
2. **Unidad:** un piso por `species_id`; las formas regionales son pisos propios.
3. **Orden:** nivel 1 → 2 → 3; dentro de cada nivel, BST ascendente; desempate por
   `species_id` ascendente.
4. **Exclusiones:** hábitat 49 y especies 132 (Ditto), 201 (Unown), 235 (Smeargle).
5. **Idempotencia:** escribir solo donde `habitats.mazmorra IS NULL`.
6. **Contrato:** `{"pisos":[{"piso":1,"species_id":25}, ...]}`, numeración contigua 1..N.
7. **Sin migración:** la columna `json nullable` ya existe y el modelo la castea a array.

## 5. Archivos y responsables

| Archivo | Acción | Responsable |
|---|---|---|
| `database/seeders/MazmorraSeeder.php` | Crear | `database` |
| `database/seeders/CatalogoSeeder.php` | Añadir `MazmorraSeeder::class` tras `PokemonHabitatSeeder::class` | `database` |

No se autoriza tocar ningún otro archivo.

## 6. Algoritmo

Para cada hábitat con `id <> 49` y `mazmorra IS NULL`:

1. Cargar la relación `pokemon` (pivote `level`) con `stats`, excluyendo
   `pokemon.id IN (132, 201, 235)`.
2. Agrupar por `pivot.level` (1, 2, 3) y ordenar los grupos por nivel ascendente.
3. Dentro de cada grupo, ordenar por `SUM(base_stat)` ascendente y, en empate, por
   `species_id` ascendente.
4. Aplanar los grupos en ese orden y asignar `piso` consecutivo desde 1.
5. Si el hábitat no produce ningún piso, no escribir nada y registrarlo.
6. Guardar `mazmorra = ['pisos' => $pisos]` mediante el modelo `Habitat`
   (el cast `array` serializa el JSON).
7. Informar por consola de hábitats y pisos generados.

El seeder debe ser `declare(strict_types=1);`, sin dependencias nuevas y sin lógica
de presentación.

## 7. Orden de ejecución y dependencias

1. `database`: crear el seeder y registrarlo.
2. Verificación estática: `php -l` y Pint sobre el archivo nuevo.
3. Ejecutar `php artisan db:seed --class=Database\\Seeders\\MazmorraSeeder`.
4. Verificación de datos con las consultas de §9.
5. Re-ejecutar el seeder para comprobar que es no-op.

Dependencia: `PokemonHabitatSeeder` debe haberse ejecutado antes (el seeder se
registra después en `CatalogoSeeder`).

## 8. Criterios de aceptación

1. Existe `MazmorraSeeder.php` con `declare(strict_types=1);` y sin tests.
2. `CatalogoSeeder` lo invoca después de `PokemonHabitatSeeder`.
3. `php -l` pasa y Pint no reporta cambios en el archivo nuevo.
4. Tras ejecutarlo: **55 hábitats** con `mazmorra` no nulo y **887 pisos** en total
   (corrección verificada: las 3 filas de 132/201/235 están dentro del hábitat 49,
   que ya se excluye; las 887 filas restantes son elegibles).
5. El hábitat 49 sigue con `mazmorra = NULL`.
6. Ningún piso referencia las especies 132/201/235.
7. La numeración de pisos es contigua 1..N en cada hábitat.
8. Todos los `species_id` de los pisos existen en `pokemon`.
9. En cada hábitat, los niveles no decrecen y, dentro de un nivel, el BST no decrece.
10. Una segunda ejecución no modifica ningún registro.
11. No se modificó ningún archivo fuera de los dos autorizados.

## 9. Verificación requerida

### Estática
```bash
php -l database/seeders/MazmorraSeeder.php
vendor/bin/pint --test database/seeders/MazmorraSeeder.php
```
PHPStan excluye `database/` en `phpstan.neon`, así que no se ejecuta sobre el seeder.

### Ejecución
```bash
php artisan db:seed --class=Database\\Seeders\\MazmorraSeeder
```

### Datos (solo lectura)
```sql
SELECT count(*) FROM habitats WHERE mazmorra IS NOT NULL;                 -- 55
SELECT sum(json_array_length(mazmorra->'pisos')) FROM habitats WHERE mazmorra IS NOT NULL; -- 887
SELECT count(*) FROM habitats WHERE id = 49 AND mazmorra IS NULL;         -- 1

SELECT h.id FROM habitats h, json_array_elements(h.mazmorra->'pisos') WITH ORDINALITY t(p,o)
WHERE (p->>'piso')::int <> t.o;                                           -- 0 filas

SELECT count(*) FROM habitats h, json_array_elements(h.mazmorra->'pisos') p
WHERE NOT EXISTS (SELECT 1 FROM pokemon pk WHERE pk.species_id = (p->>'species_id')::int); -- 0

SELECT count(*) FROM habitats h, json_array_elements(h.mazmorra->'pisos') p
WHERE (p->>'species_id')::int IN (132,201,235);                           -- 0
```

Además, comprobar el orden nivel/BST con la consulta de ventana de
`analysis.md` (Parte C.6) y una muestra legible de 2-3 hábitats.

### Idempotencia
Re-ejecutar el seeder y comprobar que los conteos no cambian.

Sin tests.

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| La BD y el CSV divergen | Fuente = BD (decidido); no se toca el pivote |
| Quedan 3 filas de 132/201/235 en el pivote | Exclusión explícita en el seeder |
| `species_id` sin índice único | Se resuelve desde la BD; deuda documentada |
| Columna `json` (no `jsonb`) | `IS NULL` y `json_array_length`; sin comparaciones con `'null'` |
| Orden de seeders | Registrar después de `PokemonHabitatSeeder` |
| 20+ pisos secuenciales | La UI solo muestra hasta el piso actual; riesgo de producto aceptado |
| Formas regionales como pisos | Aceptado por producto |

## 10 bis. Corrección post-ejecución (2026-11-16)

El dato de 884 pisos era una estimación incorrecta: las 3 filas de las especies
132/201/235 están **dentro del hábitat 49**, que ya se excluye. Las 887 filas fuera
del 49 son todas elegibles. El criterio funcional (un piso por especie elegible) se
mantiene; el resultado real es **55 hábitats / 887 pisos**. No cambia el alcance.

## 11. Rollback

Al no haber mazmorras previas y escribir solo en `NULL`, el rollback es:

```sql
UPDATE habitats SET mazmorra = NULL WHERE id <> 49;
```

No se ejecutará salvo que el desarrollador lo solicite.

## 12. Aprobación

Este plan no autoriza implementación hasta que el desarrollador responda
explícitamente `Aprobar plan v1` (o equivalente que mencione la versión).
