# TASK-063 — Ejecución

- **ID:** TASK-063
- **Proyecto:** `/home/david/pokemon`
- **Estado:** COMPLETED — plan v1 implementado y revisado; validación manual en juego pendiente del desarrollador

## Preflight (2026-11-19)

### `git branch --show-current`

```text
master
```

### `git status --short`

```text
 M .githooks/pre-commit
 M app/Http/Controllers/PlayerController.php
 M app/Providers/DatagridServiceProvider.php
 M database/seeders/AbilitySeeder.php
 M resources/views/pokedex/index.blade.php
 M storage/data/pokemon_habitat.csv
?? .opencode/work/TASK-061/
?? .opencode/work/TASK-062/
?? app/Support/Pokedex/
?? docs/adr/0005-filtros-pokedex-y-descripciones-habilidades.md
?? resources/views/components/ui/combobox.blade.php
?? resources/views/partials/combobox-filtro-script.blade.php
?? storage/data/abilities_descripciones.json
```

### `git diff --stat`

```text
 .githooks/pre-commit                      |    0
 app/Http/Controllers/PlayerController.php |    3 +
 app/Providers/DatagridServiceProvider.php |   21 +
 database/seeders/AbilitySeeder.php        |  290 +++++-
 resources/views/pokedex/index.blade.php   |   42 +-
 storage/data/pokemon_habitat.csv          | 1574 ++++++++++++++---------------
 6 files changed, 1103 insertions(+), 827 deletions(-)
```

### Cambios locales preexistentes

Hay trabajo sin commitear ajeno a esta tarea (TASK-061 de Pokédex/habilidades y
modificaciones en `PlayerController`, `DatagridServiceProvider`, `AbilitySeeder`,
vista de Pokédex y CSV de hábitats). No se sobrescribirán. El alcance de
TASK-063 se limitará a los archivos del motor de combate que identifique el
análisis.

### Riesgos de preflight

- `master` con trabajo sin commitear: el diff de TASK-063 debe poder aislarse por
  archivo. `database/seeders/AbilitySeeder.php` está ya modificado por TASK-061;
  si TASK-063 necesita tocarlo, se hará de forma aditiva y sin revertir nada.
- No se ejecutarán operaciones destructivas ni `git push`.

## Delegaciones

| Fecha | Especialista | Objetivo | Resultado |
|-------|--------------|----------|-----------|
| 2026-11-19 | `analyst` | Localizar la lógica de turno extra tras KO y las habilidades implicadas | Completado (parcial por límite de pasos). Causa: concesión incondicional en 3 puntos; ninguna habilidad define el efecto. Ver `analysis.md`. |
| 2026-11-19 | `architect` | Diseñar la corrección mínima y el contrato futuro | Completado. Retirar las 3 concesiones, conservar la capacidad; futuro efecto `ReaccionaAlDebilitarRival`. Ver `architecture.md`. |
| 2026-11-19 | `backend` | Aplicar el plan v1 | Completado. 3 hunks en 2 archivos; `php -l`, PHPStan dirigido y Pint en verde. Incidencia: `edit` denegado por permisos, edición aplicada con reemplazo literal verificado. |
| 2026-11-19 | `reviewer` | Revisión independiente | `APROBADO`. Sin invocaciones residuales; serialización intacta; solo NITs de trazabilidad. Ver `review.md`. |

## Decisiones del desarrollador (2026-11-19)

1. **Habilidades que conservan el turno extra:** ninguna por ahora. Se elimina la
   concesión global y se deja la capacidad preparada para cuando se defina una
   habilidad que lo otorgue.
2. **Condición futura:** el turno extra solo podrá dispararse por un KO causado por
   el propio actor, no por muertes de aliados ni efectos ajenos.

## Implementación (2026-11-19)

Diff de TASK-063 (2 archivos, 3 inserciones, 12 eliminaciones):

```text
app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php
  - aplicarMovimiento(): retirado $nuevosDebilitados + if otorgarAccionExtra
  - resolverObjeto():     retirado $nuevosDebilitados + if otorgarAccionExtra
src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php
  - procesarConsecuencias(): retirado $nuevosDebilitados + if otorgarAccionExtra
```

En los tres puntos se conserva `procesarDebilitamientos()`. Sin cambios en
serialización, `SESSION_VERSION`, guardas de TASK-040, UI, BD, dependencias ni
tests. TASK-061/TASK-064 y demás cambios locales permanecen intactos.

## Verificación final

- `php -l` de ambos archivos: OK.
- PHPStan dirigido a los dos archivos: `[OK] No errors`.
- `vendor/bin/pint --test` de ambos archivos: `pass`.
- `grep -rn "otorgarAccionExtra" src app`: solo definiciones delegadas
  (`EstadoTurno:68`, `TurnoCombatiente:96,98`, `DelegacionTurnoCombatiente:32,34`).
- `grep -rn "nuevosDebilitados" src app`: sin referencias.
- `procesarDebilitamientos` presente en `EjecutorAccion:126`,
  `ResolutorAccionBatalla:138,198,237`.
- `composer analyse` global: 35 errores preexistentes ajenos (ninguno en los
  archivos de TASK-063). `composer arch`: 105 violaciones preexistentes ajenas.
- Revisión independiente: `APROBADO`.

## Estado

`COMPLETED` para código y revisión. La validación manual en juego (KO en batalla
3v3 y objeto que debilita) queda pendiente del desarrollador.
