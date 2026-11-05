# TASK-013 — Revisión independiente

- **Fecha:** 2026-11-04
- **Revisor:** `reviewer`
- **Veredicto:** `APPROVED`

## Resumen

Se revisaron el catálogo de 36 bayas con grupos, el modal de selección, las tabs por familia con nido/refugio/complementos/plantaciones, el endpoint de detalle de especie, la documentación y las correcciones solicitadas. No hay blockers, highs ni mediums pendientes.

## Hallazgos

### LOW

- `resources/views/habitats/show.blade.php`: quedan métodos/estado muertos del tablist por integrante y del panel de detalle retirado (`tabsFamilia`, `tabActivaFamilia`, `seleccionarPokemonFamilia`, `detallePokemonCache`, etc.). No se usan en el markup; se conservan como capacidad diferida documentada en el ADR. Limpieza futura.
- `resources/views/habitats/show.blade.php`: duplicación del flujo POST (CSRF, `AbortController`, timeout) entre `accionGranja` y `plantarBayaDesdeModal`. Mejora futura, no correctiva.

### NIT

- `docs/architecture.md` incluye cambios de TASK-012 en el mismo working tree; al commitear, separar por tarea.

## Checklist

- [x] Catálogo 36 con grupos 6/7/5/18.
- [x] Disponibles primero, búsqueda sin acentos y filtros por grupo.
- [x] Baya con 0 no plantable.
- [x] Plantar consume 1, refresca granja, cierra solo el modal de bayas y mantiene la granja.
- [x] Escape cierra la capa superior.
- [x] Tabs por familia con panel de nido/refugio/complementos/plantaciones.
- [x] Sin tablist interno de integrantes ni panel de especie visible.
- [x] Endpoint de detalle autorizado por cadena asignada + `evolution_chain_id`; 404 sin filtrar.
- [x] `GET /farm` conserva solo stock > 0 y añade `grupo`/`grupo_nombre`.
- [x] `stage`/`parent_id`/`species_id` aditivos en familias.
- [x] ADR-0002 y `docs/architecture.md` alineados con el addendum v1.1.
- [x] `php -l`, Pint, PHPStan (34 preexistentes) y deptrac (113 preexistentes) sin errores nuevos.
- [x] `tests/` sin cambios; sin tests ni migraciones.

## Riesgos residuales

- UI no validada en navegador; la verificación es estática.
- Código muerto del detalle de especie conservado como capacidad diferida.
- `bayasCatalogo` puede mostrar cantidades previas durante la recarga al reabrir la granja (cosmético).
- `z-[60]` depende del CSS compilado; se regeneró con `npm run build`.

## Archivos inspeccionados

Los detallados en el informe del `reviewer`: vista de granja, ADR-0002, architecture, rutas, controladores, query de detalle, presentadores, DTOs de familia, catálogo de items, inventario y bootstrap.
