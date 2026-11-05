# TASK-013 — Ejecución

## Estado

`COMPLETED`

## Preflight

- Rama: `task-011`.
- Working tree con F1 + F2–F5 de TASK-012 sin commitear; `.githooks/pre-commit` y `MVP.txt` preexistentes fuera de alcance.
- No se toca `tests/`.

## Historial

- 2026-11-04: tarea creada a partir de la petición de modal de bayas y tabs de familia.

- 2026-11-04: `analyst` entrega análisis; guardado en `analysis.md`. Se inicia diseño.

- 2026-11-04: `architect` entrega diseño; guardado en `architecture.md`. Plan v1 redactado; pendiente de aprobación explícita.

- 2026-11-04: plan v1 aprobado con «Aprobar plan v1 (TASK-013)». Inicia implementación.

## Implementación

- Backend bloque 1: `GrupoBaya`, `CatalogoItems::grupoDeBaya`, `BayaGranja` con grupo, `catalogoBayas()` (36), `ObtenerCatalogoBayas`, endpoint `/farm/berries`.
- Backend bloque 2: `MiembroCadena.parentId`, `stage/parent_id/species_id` en `DTOPokemonFamilia`, `PresentadorDetalleEspecie`, `DetallePokemonFamiliaQuery`, controlador y endpoint `/families/{chain}/pokemon/{pokemon}`.
- Frontend A: modal de bayas separado con buscador, categorías, disponibles primero.
- Frontend B: tabs de familia ordenadas por etapa, panel de detalle con tipos, stats, hábitat, familia, habilidades, learnset y metadatos.
- Corrección deptrac: controlador de detalle sin base `Controller`.

## Verificación estática

- `php -l`: sin errores.
- `phpstan`: 34 errores preexistentes; sin nuevos.
- `deptrac`: 113 violaciones preexistentes; sin nuevas.
- `pint --test --dirty`: pass.
- `view:cache`/`view:clear`: OK.
- `npm run build`: OK.
- Catálogo: 36 bayas, grupos 6/7/5/18; `resumenBayas` sigue solo con stock > 0.
- `GET /farm`: `bayas` conserva claves y añade `grupo`/`grupo_nombre`; familias exponen `stage`/`parent_id`/`species_id`.
- Detalle de especie: abilities, moves y species presentes; 404 para Pokémon de otra cadena y hábitat inexistente.
- `tests/`: sin cambios.


## Ajuste UI (2026-11-04)

- El desarrollador eligió **tabs por familia** (no por integrante). Cada tab muestra nido/refugio/complementos/plantaciones de la familia seleccionada.
- Se retira de la UI el panel de detalle de especie; el endpoint queda disponible para una fase posterior.
- Pendiente: implementar tabs por familia, corregir PHPDoc de `ColeccionPokemonFamilia`, re-verificar y re-revisar.


## Cierre

- Revisión independiente: `APPROVED`.
- Auditoría de seguridad: `APPROVED`.
- Documentación: ADR-0002 y `docs/architecture.md` alineados con tabs por familia.
- Informe final: `final-report.md`.
- Sin commit ni push; cambios en el working tree.
