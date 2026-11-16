# TASK-047 — Revisión

- **Revisor:** `reviewer` independiente.
- **Fecha:** 2026-11-16.
- **Veredicto:** **APPROVED**.
- **Alcance revisado:** `database/seeders/MazmorraSeeder.php` (nuevo) y
  `database/seeders/CatalogoSeeder.php` (una línea).

## Criterios de aceptación

| # | Criterio | Resultado |
|---|---|---|
| 1 | Seeder con `declare(strict_types=1)`, sin tests | PASS |
| 2 | Registrado después de `PokemonHabitatSeeder` | PASS |
| 3 | `php -l` y Pint | PASS |
| 4 | 55 hábitats / 887 pisos | PASS |
| 5 | Hábitat 49 NULL | PASS |
| 6 | Sin 132/201/235 | PASS |
| 7 | Numeración contigua | PASS |
| 8 | `species_id` existentes | PASS |
| 9 | Orden nivel/BST y desempate | PASS |
| 10 | Segunda ejecución no-op | PASS (por `whereNull` y estado de BD) |
| 11 | Sin archivos fuera del alcance | PASS |

## Hallazgos

- **[LOW] Filtro por `pokemon.id` en vez de `species_id`.** Hoy equivalente
  (`id == species_id` en las 1083 filas); latente si se introduce una forma con
  `id` distinto. No bloquea.
- **[LOW] Niveles fuera de 1..3 o stats ausentes se descartan en silencio.** Hoy
  no ocurre (1006 filas solo con niveles 1/2/3; 0 Pokémon sin 6 stats). No bloquea.
- **[LOW] Lista de especies no salvajes duplicada** entre `MazmorraSeeder` y
  `PokemonHabitatSeeder`; unificar en el futuro.
- **[LOW] Comentario `-- 884` obsoleto en `analysis.md` C.6**; corregido a 887.
- **[NIT] `isset($this->command)` repetido; `execution.md` pendiente de cierre.**
  `execution.md` queda cerrado.

## Evidencia de datos

- 56 hábitats; 55 con `mazmorra`; 1 NULL (id 49).
- 887 pisos; mínimo 9, máximo 25, mediana 16.
- 0 arrays vacíos; claves JSON solo `piso` y `species_id`.
- 0 huérfanos; 0 duplicados de especie por hábitat; 0 pisos fuera del pool.
- Comparación exacta contra la secuencia esperada por ventana: 0 diferencias.
- Pivote intacto: 1006 filas; 119 en el hábitat 49.

## Riesgos residuales

- Solo se rellena `mazmorra IS NULL`: cambios futuros del pivote no regeneran
  mazmorras ya configuradas.
- El hábitat 49 no tiene mazmorra (decisión de producto).
- Columna `json` (no `jsonb`) y `species_id` sin índice único: deuda preexistente.
- 25 pisos máximo y formas regionales como pisos propios: aceptado en el plan.
