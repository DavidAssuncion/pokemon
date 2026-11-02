# TASK-006 — QA

- **QA:** `qa`
- **Fecha:** 2026-11-02
- **Plan:** `plan.md` v1
- **Veredicto:** `PASS WITH KNOWN ISSUES`

## Criterios

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | `nombre=null` + Pokémon existente → nombre del Pokémon | Cumple | Test de hábitat con `rhyperior`; `assertDontSee('Reclutado eliminado')` |
| 2 | Fallback solo si la relación es nula | Cumple | `filled()` + `?->`; esquema FK/NOT NULL |
| 3 | Capturas 1/3 + caramelos 2/3 con 3 filas | Cumple | `lg:grid-cols-3`, `lg:col-span-1/2`, tres bloques familia/EV/tipo |
| 4 | Sin capturas → caramelos full width; sin caramelos → capturas full width | Cumple | Tests Livewire con/sin capturas; nuevo test de solo capturas |
| 5 | Rama entrenador/gimnasio intacta | Cumple | Diff limitado a `layout='ruta'` |
| 6 | Sin cambios de recompensas/migraciones/dependencias | Cumple | Solo presentación y tests |

## Pruebas ejecutadas

- `vendor/bin/phpunit --filter 'HabitatsControllerTest|HabitatsViewTest|CombateLivewireTest' --no-coverage` → **OK (19 tests, 54 aserciones)**.
- `vendor/bin/phpunit --filter 'CombateLivewireTest' --no-coverage` → OK (6 tests, 16 aserciones).
- `vendor/bin/phpunit --filter 'Habitats|Combate' --no-coverage` → 143 tests, 558 aserciones, 2 errores en `PokemonHabitatSeederTest` (ajenos).
- PHPStan: 0 errores en TASK-006; 8 preexistentes en `SendasController`.
- Pint: pass.
- Sin migraciones ni dependencias nuevas.

## Fallos conocidos

- `PokemonHabitatSeederTest` (2 errores): FK `habitats.province_id=8` ausente. Reproducido en aislamiento; causado por el WIP de `ProvinceSeeder` (`'Vacio'`) y `storage/data/habitats.csv`. No es regresión de TASK-006.

## Riesgos residuales

- Los criterios 2 y 5 se validaron por inspección, no con test automatizado.
- La composición exacta de 3 filas se verifica por estructura Blade, no por aserción de filas.
- El árbol mezcla WIP ajeno; la atribución se basa en alcance y timestamps.

## Conclusión

`PASS WITH KNOWN ISSUES` para TASK-006. Los issues restantes son LOW/NIT o deuda ajena.
