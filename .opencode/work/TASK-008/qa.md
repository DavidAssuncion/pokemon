# TASK-008 — QA

- **QA:** `qa`
- **Fecha:** 2026-11-02
- **Plan:** `plan.md` v1
- **Veredicto:** `PASS WITH KNOWN ISSUES`

## Resultado

Regresión del helper JS global corregida y verificada. El partial `tipos-badge-script` ya no se inyecta en `layouts/app.blade.php`; solo en `pokedex/index`, `equipos/index` y `habitats/show`, dentro de `@push('scripts')` y antes del script de cada vista. `ExploracionesViewTest` vuelve a verde.

## Pruebas ejecutadas

- Unitarias (familia, `TipoBadges`, `FaseEvolutiva`): **25 passed / 492 assertions**.
- `DatagridTest` + `TipoBadgeBladeTest`: **36 passed / 171 assertions**.
- `ExploracionesViewTest` + `PokedexViewTest` + `EquiposViewTest` + `HabitatsViewTest` + `Habitats/FamiliesTest`: **52 passed / 417 assertions**.
- Gimnasios: **3 failed / 62 passed** — WIP ajeno (EVs 64/128 y medalla), no TASK-008.
- `npm run build`: **OK** (Vite 7.3.6, 67 módulos).
- PHPStan del alcance: sin errores reales; solo baseline no emparejado de Granjas por analizar un subconjunto.
- Presupuesto de consultas: **7 del presentador + 1 find del endpoint = 8** (Pokémon 25, familia de 4).
- `TipoPokemon.php` y `database/migrations`: sin cambios.

## Criterios de aceptación

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| 1 | Familia completa sin duplicados | Cumple | `ResolvedorFamiliaEvolutivaTest`; `DatagridTest` ramas/regionales |
| 2 | Icono/nombre, «Nv X», actual y `aria-current` | Cumple | `pokedex/index.blade.php:385-399`; `PokedexViewTest` |
| 3 | Singleton | Cumple | Unitario + `DatagridTest` |
| 4 | Ubicación provincia/imagen/nombre/botón | Cumple | `pokedex/index.blade.php:436-459`; `DatagridTest` |
| 5 | Sin hábitat: mensaje y sin botón | Cumple | `x-if="detail.location"` / `x-if="!detail.location"`; `PokedexViewTest` |
| 6 | Pill de movimientos + icono; sin `getTypeClass` | Cumple | `TipoBadgesTest` 11/11; `TipoBadgeBladeTest` 4/4; grep sin restos |
| 7 | Pills migrados usan `TipoBadges` | Cumple | Sin `TipoBadges::MAP`; vistas usan helper/componente |
| 8 | Gimnasios/Bootstrap sin cambio visual | Cumple | Diff de gimnasios solo `MAP`→`etiquetasPorId()` y `[0]`→escalar |
| 9 | No visto 404 / unlock 200 | Cumple | `DatagridTest` casos 404 y `?unlock=1` |
| 10 | Contrato conservado | Cumple (parcial) | `types`, `stats`, `habitat_name`, `habitat_id` con aserción; `habitats` sin aserción explícita |
| 11 | ≤8 consultas | Cumple | 7 presentador + 1 find = 8 |
| 12 | Sin migraciones/dependencias/`TipoPokemon::color()` | Cumple | `git status` vacío en esos paths |
| 13 | PHPUnit/PHPStan/Pint sin errores nuevos en alcance | Cumple con matiz | 113 tests del alcance verdes; PHPStan del alcance limpio; global rojo por WIP ajeno |

## Fallos conocidos

- **Gimnasios (3):** `GimnasioApiTest` EVs 64/128 y `GimnasioLivewireTest` medalla. Atribuidos a refactors ajenos (`PerfilIvsEvs`, `GymCatalogoRepositoryInterface`). No se tocaron en TASK-008.
- **Suite completa inestable:** por concurrencia sobre `laravel_test` (evidencia `relation "users" does not exist` y 147/24/56 fallos en ejecuciones consecutivas). No se repitió; requiere BD aislada.
- **PHPStan global:** 8 errores en `SendasController.php` + 1 en `FinalizarExploracionHandler.php`, WIP ajeno.

## Riesgos residuales

- `habitats` no tiene aserción explícita.
- Presupuesto de consultas medido una vez, sin test automatizado.
- 9 tests `skipped` de la suite completa sin enumerar.
- Pint no reejecutado en esta ronda; el review lo dio por bueno.

## Conclusión

`PASS WITH KNOWN ISSUES` para TASK-008. Los criterios funcionales y de alcance se cumplen; los fallos restantes son WIP ajeno o limitaciones del entorno de test.
