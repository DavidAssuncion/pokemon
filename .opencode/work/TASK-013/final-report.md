# TASK-013 — Informe final

- **ID:** TASK-013
- **Estado:** `COMPLETED`
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon`
- **Rama:** `task-011`
- **Alcance:** modal de selección de bayas, catálogo de 36 bayas con grupos y tabs por familia en la granja.
- **Aprobación:** plan v1 + addendum v1.1, registrada en `approval.md`.
- **Cambios:** working tree, **sin commit y sin push**.

---

## 1. Resultado

- **Modal de bayas independiente** (`z-[60]`) con:
  - categorías **Medicinas, Mejoras, Reductores y Resistencias**;
  - **disponibles primero** y agotadas después;
  - **buscador** por nombre/categoría sin distinguir mayúsculas ni acentos;
  - plantado directo en el hueco y refresco de la granja.
- **Tabs por familia** en el modal de granja, como el patrón de provincias:
  - una tab por familia del hábitat;
  - al pulsar, se muestra el panel con **nido, refugio, complementos y plantaciones** de esa familia;
  - solo una familia visible a la vez.
- **Catálogo completo de 36 bayas** con stock 0 y grupo, servido en endpoint propio; `resumenBayas()` sigue devolviendo solo stock > 0.
- **Endpoint de detalle de especie** (`GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`) con tipos, stats, hábitat, familia, habilidades, learnset y metadatos. Está disponible pero **no conectado a la UI actual** (capacidad diferida).
- **Documentación:** ADR-0002 y `docs/architecture.md` actualizados con el addendum v1.1.

## 2. Archivos principales

### Backend

- `src/Shared/Domain/Items/GrupoBaya.php` (nuevo)
- `src/Shared/Domain/Items/CatalogoItems.php` (`grupoDeBaya`)
- `src/Granjas/Domain/DataTransferObjects/BayaGranja.php` (`grupo`, `grupo_nombre`)
- `src/Granjas/App/InventarioGranja.php` (`catalogoBayas`)
- `src/Granjas/App/ObtenerCatalogoBayas.php` (nuevo)
- `src/Granjas/Infra/Controllers/GranjaController.php` (`bayas`)
- `routes/granjas.php` (`GET /api/habitats/{habitat}/farm/berries`)
- `src/Habitats/Domain/DataTransferObjects/MiembroCadena.php` (`parentId`)
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php`
- `src/Habitats/Presentation/DTOPokemonFamilia.php` (`stage`, `parent_id`, `species_id`)
- `src/Habitats/Presentation/DTOFamiliaDisponible.php` / `ColeccionPokemonFamilia.php` (PHPDoc)
- `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php`
- `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php` (nuevo)
- `src/Habitats/Infra/Controllers/DetallePokemonFamiliaController.php` (nuevo)
- `routes/habitats.php` (`GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`)
- `app/Support/PresentadorDetallePokemon.php` (overrides opcionales)
- `app/Support/PresentadorDetalleEspecie.php` (nuevo)

### Frontend

- `resources/views/habitats/show.blade.php`:
  - modal de bayas con buscador, chips y disponibles primero;
  - tabs por familia y panel de nido/refugio/complementos/plantaciones;
  - sincronización del catálogo tras plantar, cosechar y replantar;
  - cierre en capas (`cerrarCapaSuperior`).

### Documentación

- `docs/adr/0002-catalogo-bayas-y-detalle-especie.md` (nuevo)
- `docs/architecture.md`

## 3. Verificación

| Comprobación | Resultado |
|---|---|
| `php -l` de archivos nuevos/modificados | Sin errores. |
| `phpstan analyse` | 34 errores preexistentes; ninguno nuevo en TASK-013. |
| `deptrac analyse` | 113 violaciones preexistentes; ninguna nueva. |
| `pint --test --dirty` | `pass`. |
| `php artisan view:cache` / `view:clear` | OK. |
| `npm run build` | OK. |
| `route:list --path=farm` | 8 rutas, incluida `/farm/berries`. |
| `route:list --path=families` | 5 rutas, incluida la de detalle. |
| Catálogo | 36 bayas; grupos 6/7/5/18. |
| `resumenBayas()` | Solo cantidades > 0; `GET /farm` conserva claves y añade `grupo`. |
| Familias | `base`/`evolutions` con `stage`, `parent_id`, `species_id`. |
| Detalle de especie | 200 con abilities/moves/species; 404 para otra cadena o hábitat inexistente. |
| `tests/` | Sin cambios. No se ejecutaron tests. |

## 4. Revisión y seguridad

- **Revisión independiente:** `APPROVED`.
  - LOW: código muerto del tablist por integrante/panel de especie retirado, conservado como capacidad diferida.
  - LOW: duplicación del flujo POST entre `accionGranja` y `plantarBayaDesdeModal`.
  - NIT: el working tree mezcla cambios de TASK-011/012; separar al commitear.
- **Seguridad:** `APPROVED`.
  - Sin CRITICAL/HIGH.
  - LOW: endpoint de detalle sin rate limiting.
  - LOW: `GET /farm/berries` puede crear la granja (efecto idempotente por usuario).
  - INFO: especies no vistas devuelven datos estáticos por decisión D8.
  - Advisories de dependencias preexistentes.

## 5. Riesgos residuales

- **UI no validada en navegador:** falta comprobar visualmente el modal de bayas, el buscador con acentos, las tabs por familia y el cierre con Escape.
- **Código muerto diferido:** métodos/estado del detalle de especie (`tabsFamilia`, `detallePokemonCache`, etc.) no se usan; limpiar en una tarea posterior.
- **Catálogo durante recarga:** al reabrir la granja se conserva la lista anterior mientras llega la nueva; puede mostrar cantidades obsoletas un instante.
- **Rate limiting:** el detalle de especie no tiene throttle.
- **Dependencias:** Laravel 12.67.0, Livewire 4.4.2, Symfony 7.4.17 y CommonMark 2.10.0 con advisories preexistentes.
- **Sin tests:** por política del proyecto; verificación estática y manual.

## 6. Próximos pasos

1. Probar en navegador: abrir granja, cambiar tabs de familia, ver nido/refugio/plantaciones, abrir modal de bayas, buscar “bariba”, plantar una baya con stock y comprobar el refresco; cosechar/replantar y verificar cantidades.
2. Commit de TASK-013 separado de TASK-011/012; no se hizo push.
3. Limpieza futura: retirar métodos/estado muertos del detalle de especie o conectarlos a una UI posterior.
4. Hardening: añadir `throttle` al endpoint de detalle y valorar separar la creación de granja del GET de catálogo.
5. Actualizar dependencias con advisories en una tarea aparte.
