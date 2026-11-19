# TASK-061 — Aprobación

- **ID de tarea:** TASK-061
- **Estado:** APROBADO
- **Plan:** v1
- **Fecha:** 2026-11-18
- **Texto literal de la aprobación:** `Aprobar plan v1`

## Alcance autorizado

1. `app/Providers/DatagridServiceProvider.php`: filtros `ability` y `move` e `idPositivo`.
2. `app/Support/Pokedex/OpcionFiltro.php`: DTO readonly `JsonSerializable`.
3. `app/Support/Pokedex/CatalogoFiltrosPokedex.php`: catálogos de habilidades y movimientos
   asignados.
4. `app/Http/Controllers/PlayerController.php`: pasar `habilidades` y `movimientos` a la
   vista.
5. `resources/views/components/ui/combobox.blade.php`: combobox accesible con buscador.
6. `resources/views/partials/combobox-filtro-script.blade.php`: factoría Alpine
   `comboboxFiltro`.
7. `resources/views/pokedex/index.blade.php`: integración de los dos filtros.
8. `database/seeders/AbilitySeeder.php`: reescritura idempotente de una pasada.
9. `storage/data/abilities_descripciones.json`: 227 descripciones por `effect_key`.
10. `docs/adr/0005-filtros-pokedex-y-descripciones-habilidades.md`: ADR.

## Exclusiones

- Sin tests: no crear, modificar ni ejecutar la suite.
- Sin migraciones ni cambios de esquema.
- Sin cambios en `src/Battle/**`, `Ability`, `Move`, `PresentadorDetalleEspecie`,
  `DatagridService`, `RelationFilter`, los 3 JSON de lotes, el CSV, `.env` ni secretos.
- Sin tocar el cambio local preexistente `.githooks/pre-commit`.
- Sin `git push` ni operaciones destructivas.
- Sin persistir filtros en URL ni corregir el bug preexistente de cierre de dropdowns.
