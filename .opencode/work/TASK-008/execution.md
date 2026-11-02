# TASK-008 — Ejecución

## Preflight (2026-11-02)

- Proyecto: `/home/david/pokemon`.
- Rama: `master`, sincronizada con `origin/master`.
- `git status`: WIP local extenso (143 archivos modificados/eliminados y varios untracked, incluido `.opencode/work/`). No se revierte ni se sobrescribe.
- Colisión de artefactos: otra sesión ocupó `TASK-007` con una tarea distinta (fase evolutiva de caramelos). Nuestra tarea se renumeró a `TASK-008`; el código ya implementado no se vio afectado.

## Implementación completada

### Dominio de evolución

- `src/Pokemon/Domain/Evolution/`: `PokemonFamilia`, `AristaEvolutiva`, `MiembroFamiliaEvolutiva`, `FamiliaEvolutiva`, `ResolvedorFamiliaEvolutiva`.
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php`: delega en el nuevo resolvedor en modo compatibilidad (`multiRaiz=false`, `maxEtapas=3`), conservando API y DTO.
- Tests: `tests/Unit/Pokemon/Evolution/ResolvedorFamiliaEvolutivaTest.php`.
- `php -l` y Pint limpios; tests no ejecutados (diferidos a QA).

### Contrato y guard

- `app/Support/FamiliaPokemon.php`: carga de cadena, aristas globales y cierre cross-chain.
- `app/Support/PresentadorDetallePokemon.php`: `type_details`, `location`, `family`; conserva `types`, `stats`, `habitat_name`, `habitat_id`, `habitats`.
- `app/Providers/DatagridServiceProvider.php`: guard `visto || unlockAll` → `null` → 404; delega en el presentador.
- `tests/Feature/DatagridTest.php`: 404 no visto, `?unlock=1`, ramas/niveles, singleton, forma regional, ubicación, `type_details`.
- `php -l` y Pint limpios; tests no ejecutados.

### TipoBadges

- `src/Shared/UI/TipoBadges.php`: fuente única por slug ASCII, paleta de movimientos, API `porId/porSlug/porLabel/resolver/clases/icono/etiquetasPorId/paraJs/idsParaJs/DEFAULT`; `MAP` retirado.
- `tests/Unit/Shared/TipoBadgesTest.php`: 11 tests de contrato.
- `php -l` y Pint limpios; tests no ejecutados.

### Base frontend

- `resources/views/components/tipo-badge.blade.php`: componente reutilizable con tamaños `xs/sm/md` e icono opcional.
- `resources/views/partials/tipos-badge-script.blade.php`: helper global `tipoBadge`/`tipoBadgePorId`.
- `resources/views/layouts/app.blade.php`: incluye el partial antes de `@stack('scripts')`.
- `resources/css/app.css`: `@source "../../src";`.
- `resources/views/gimnasios/{index,show,admin}.blade.php`: adaptados a `etiquetasPorId()` sin cambio visual.
- `tests/Feature/TipoBadgeBladeTest.php`: render por slug, id, label y desconocido.
- `npm run build` correcto; CSS incluye clases de `src`.

## Estado

`COMPLETED` — implementación, revisión, QA, seguridad e informe final cerrados. Testing desactivado por indicación del desarrollador a partir de este cierre; los tests existentes quedan intactos.
