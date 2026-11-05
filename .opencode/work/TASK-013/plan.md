# TASK-013 — Plan v1 — Modal de bayas y tabs de familia

- **ID:** TASK-013
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-04
- **Proyecto:** `/home/david/pokemon` — Laravel 12 / PHP 8.4 / Alpine 3.16
- **Base:** `request.md`, `analysis.md`, `architecture.md`.

---

## 1. Objetivo

1. Añadir un **modal de selección de baya** independiente del modal de granja, con:
   - categorías **Medicinas, Mejoras, Reductores y Resistencias**;
   - **disponibles primero** (cantidad > 0) y después las agotadas;
   - **buscador** por nombre;
   - plantado directo en el hueco elegido.
2. Mostrar los **Pokémon de cada familia como tabs** en el modal de granja; al pulsar una tab, cargar y mostrar su **información completa**: sprite, tipos, stats, hábitat, familia, habilidades, learnset y metadatos de especie.

## 2. Alcance

### Incluido

- Enum `GrupoBaya` y clasificación única en `CatalogoItems`.
- `BayaGranja` con `grupo`/`grupo_nombre`.
- `InventarioGranja::catalogoBayas()` con las 36 bayas (incluidas 0).
- Endpoint `GET /api/habitats/{habitat}/farm/berries`.
- `stage`/`parent_id` en `DTOPokemonFamilia` y `parentId` en `MiembroCadena`.
- Endpoint `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`.
- `PresentadorDetallePokemon` con overrides opcionales y `PresentadorDetalleEspecie` opt-in.
- Modal de bayas, tabs de familia, panel de detalle y caché en `resources/views/habitats/show.blade.php`.
- ADR y actualización de `docs/architecture.md`.
- Verificación estática, revisión y seguridad.

### Fuera de alcance

- F6 (equipables con coste/iconos) y F7 (botín de combate).
- Cambiar `resumenBayas()` o el contrato actual de plantación/cosecha.
- Incrustar el detalle de especie en `FamiliaGranja::toArray()`.
- Mostrar habilidades ocultas: el esquema `ability_pokemon` no guarda `hidden`.
- Formas regionales con cadena propia no asignada al hábitat.
- Tests: no se crean, actualizan ni ejecutan.
- Commit, push, migraciones o cambios de dependencias.

## 3. Decisiones

| ID | Decisión |
|---|---|
| D1 | Categorías: Medicinas = BAYAS_BASE; Mejoras = BAYAS_RARAS; Reductores = BAYAS_ESTADISTICA; Resistencias = bayas de tipo. |
| D2 | El catálogo de 36 bayas se sirve en endpoint aparte; `resumenBayas()` no cambia. |
| D3 | El modal de bayas es hermano del modal de granja, `z-[60]`; un único handler de Escape cierra la capa superior. |
| D4 | Click en baya con stock planta directamente; baya con 0 no es plantable. |
| D5 | Tabs ordenadas por `stage`, luego `species_id`, luego `id`. |
| D6 | Detalle bajo demanda con caché por `chainId/pokemonId`; no se incrusta en el payload de granja. |
| D7 | El detalle incluye habilidades, learnset y metadatos; `PresentadorDetallePokemon` no cambia para Pokédex. |
| D8 | Especies no vistas devuelven detalle completo con `visto=false`; no se bloquea. |
| D9 | Autorización del detalle: cadena asignada al hábitat + `pokemon.evolution_chain_id = chain`; IDs libres → 404. |

## 4. Diseño backend

### 4.1 Bayas

- `GrupoBaya` (`src/Shared/Domain/Items`): `MEDICINA`, `MEJORA`, `REDUCTOR`, `RESISTENCIA`; `label()` y `orden()`.
- `CatalogoItems::grupoDeBaya(ClaveBaya)`: usa las listas privadas existentes; el resto son resistencias.
- `BayaGranja`: añade `GrupoBaya $grupo`; `toArray()` añade `grupo` y `grupo_nombre`.
- `InventarioGranja::catalogoBayas(int $userId)`: 36 `ClaveBaya::cases()`, una consulta de saldos, cantidad 0 si no hay fila.
- `ObtenerCatalogoBayas::handle(int $userId, int $habitatId)`: valida hábitat con `AsegurarGranja::para()`.
- `GranjaController::bayas(int $habitat)`: devuelve `{ "bayas": [...] }`.
- Ruta: `GET /api/habitats/{habitat}/farm/berries` con `whereNumber('habitat')`.

### 4.2 Tabs de familia

- `MiembroCadena`: añade `?int $parentId = null`.
- `ResolvedorCadenasEvolutivas`: rellena `parentId` desde `FamiliaEvolutiva::porId(...)->parentId`.
- `DTOPokemonFamilia`: añade `int $stage = 1` y `?int $parentId = null` (defaults compatibles); `toArray()` añade `stage`, `parent_id`, `species_id`.
- `CadenasFamiliaHabitatsTrait`: pasa `stage` y `parentId` en los dos builders.
- La vista ordena `[base, ...evolutions]` por `stage`, `species_id`, `id`.

### 4.3 Detalle de especie

- `DetallePokemonFamiliaQuery::obtener(int $habitatId, int $chainId, int $pokemonId, int $userId): array`:
  1. Validar cadena asignada al hábitat (`pokemon_habitat` join `pokemon.evolution_chain_id`); si no, 404.
  2. `Pokemon` por `id` + `evolution_chain_id = chain`; si no, 404.
  3. Leer `pokedex` del usuario (`visto`, `atrapado`).
  4. `PresentadorDetalleEspecie::para($pokemon, $visto, $atrapado)`.
- `PresentadorDetalleEspecie`: carga `abilities` y `moves`; compone `PresentadorDetallePokemon::para()` con overrides y añade `abilities`, `moves`, `species`.
- `PresentadorDetallePokemon::para(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null)`: overrides opcionales, sin cambiar el comportamiento actual.
- `DetallePokemonFamiliaController`: controlador delgado; 404 vía `RecursoNoExiste`.
- Ruta: `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}` con `whereNumber` en los tres parámetros.

## 5. Diseño UI

### 5.1 Modal de bayas

- Estado Alpine: `showBayaModal`, `bayaBusqueda`, `bayaGrupoActivo`, `bayasCatalogo`, `bayasCatalogoCargado`, `bayasPlantando`, `slotPlantando`.
- Abrir desde `abrirSelectorBaya(chainId, slot)`: guarda el hueco, carga el catálogo si no está cargado y muestra el modal.
- Buscador `x-model="bayaBusqueda"`; chips de grupo; lista ordenada con disponibles primero.
- Cada baya: icono, nombre, cantidad, grupo; click con stock > 0 → `plantarBayaDesdeModal(baya)`.
- Al plantar: usa el endpoint existente, refresca `granja`, sincroniza cantidades y cierra solo el modal de bayas.
- `cerrarCapaSuperior()`: si `showBayaModal`, lo cierra; si no, cierra la granja. El modal de bayas no registra su propio `@keydown.escape.window`.

### 5.2 Tabs y detalle de familia

- En cada tarjeta de familia, tabs `[base, ...evolutions]` con sprite/nombre; `role="tablist"`, `aria-selected`.
- Estado: `familiaTabActiva`, `detallePokemon`, `detallePokemonCache`, `detallePokemonLoading`, `detallePokemonError`.
- Al pulsar una tab: si hay caché, muestra; si no, fetch al endpoint y guarda.
- Panel de detalle: cabecera con sprite/nombre/tipos; stats; hábitat; familia; habilidades; learnset scrollable; metadatos.
- Estados de carga/error/vacío explícitos.

## 6. Archivos

### Crear

| Archivo | Responsable |
|---|---|
| `src/Shared/Domain/Items/GrupoBaya.php` | backend |
| `src/Granjas/App/ObtenerCatalogoBayas.php` | backend |
| `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php` | backend |
| `src/Habitats/Infra/Controllers/DetallePokemonFamiliaController.php` | backend |
| `app/Support/PresentadorDetalleEspecie.php` | backend |
| `docs/adr/0002-catalogo-bayas-y-detalle-especie.md` | documenter |

### Modificar

| Archivo | Responsable |
|---|---|
| `src/Shared/Domain/Items/CatalogoItems.php` | backend |
| `src/Granjas/Domain/DataTransferObjects/BayaGranja.php` | backend |
| `src/Granjas/App/InventarioGranja.php` | backend |
| `src/Granjas/Infra/Controllers/GranjaController.php` | backend |
| `routes/granjas.php` | backend |
| `src/Habitats/Domain/DataTransferObjects/MiembroCadena.php` | backend |
| `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php` | backend |
| `src/Habitats/Presentation/DTOPokemonFamilia.php` | backend |
| `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php` | backend |
| `app/Support/PresentadorDetallePokemon.php` | backend |
| `routes/habitats.php` | backend |
| `resources/views/habitats/show.blade.php` | frontend |
| `docs/architecture.md` | documenter |

## 7. Orden de ejecución

1. Backend: `GrupoBaya` + `CatalogoItems::grupoDeBaya`.
2. Backend: `BayaGranja` + `catalogoBayas` + `ObtenerCatalogoBayas` + endpoint/ruta.
3. Backend: `MiembroCadena.parentId` + resolvedor + `DTOPokemonFamilia.stage/parentId` + trait.
4. Backend: `PresentadorDetallePokemon` overrides + `PresentadorDetalleEspecie` + query + controlador + ruta.
5. Frontend: modal de bayas + JS.
6. Frontend: tabs + panel de detalle + caché.
7. Documentación (ADR + architecture).
8. Verificación estática, revisión y seguridad.
9. Informe final.

Dependencias: 1 → 2; 3 independiente; 4 depende de 3 para el orden de tabs (el endpoint puede ir en paralelo); 5 y 6 dependen de 2 y 4; 6 depende de 5 para el estado del modal.

## 8. Criterios de aceptación

1. Catálogo con exactamente 36 bayas; reparto 6/7/5/18; sin grupos desconocidos.
2. Disponibles primero, orden estable; buscador y filtros por grupo.
3. Baya con cantidad 0 no plantable.
4. Al plantar: consume 1, refresca granja, cierra modal de bayas y mantiene granja.
5. Escape cierra solo la capa superior.
6. Tabs ordenadas por `stage`, `species_id`, `id`; `aria-selected`.
7. Detalle cacheado por Pokémon.
8. Detalle con sprite, tipos, stats, hábitat, familia, habilidades, learnset y metadatos.
9. Learnset scrollable con nombre, tipo, categoría, potencia, precisión y nivel/source.
10. Endpoint de detalle: 404 si cadena no asignada o Pokémon de otra cadena.
11. Especie no vista: detalle completo con `visto=false`, sin bloqueo.
12. `GET /datagrid/pokemon/{id}/detalle` conserva su contrato sin abilities/moves/species.
13. `GET /api/habitats/{id}/families` y `GET /farm` siguen siendo consumibles (claves aditivas).
14. Pint, PHPStan y deptrac sin errores nuevos.
15. Sin tests ni migraciones.

## 9. Verificación

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/deptrac analyse --no-progress
php -l <archivos nuevos/modificados>
php artisan route:list --path=api/habitats
php artisan view:cache && php artisan view:clear
npm run build
grep -rn "new BayaGranja\|new DTOPokemonFamilia" src app
```

Comprobaciones manuales: 36 bayas, reparto 6/7/5/18, disponibles primero, búsqueda, plantado, tabs ordenadas, caché, 404 de autorización, detalle de especie no vista. Sin tests.

## 10. Riesgos y mitigaciones

| Nivel | Riesgo | Mitigación |
|---|---|---|
| Alto | Cambiar `resumenBayas()` a 36 | Método/endpoint nuevos; no tocar el existente. |
| Alto | IDOR en detalle | Validar cadena asignada + `evolution_chain_id`; 404 sin filtrar. |
| Medio | `stage` fallback 3 | Orden `stage`, `species_id`, `id`; documentar. |
| Medio | Doble clic al plantar | Botón deshabilitado sin stock + flag `bayasPlantando`. |
| Medio | Doble cierre por Escape | Un único handler `.window` + `cerrarCapaSuperior()`. |
| Bajo | `ability_pokemon` sin `hidden` | Exponer `slot`/`source`; no inventar. |
| Bajo | Especies sin movimientos/habilidades | Estados vacíos explícitos. |
| Bajo | `FamiliaPokemon` carga todas las aristas | Uso bajo demanda y caché; optimización posterior. |

## 11. Rollback

- Revertir los archivos nuevos y modificados de TASK-013.
- No hay migraciones.
- El modal de granja puede volver al selector `<select>` anterior.
- No se hace `git reset --hard`, `git clean` ni `git checkout --` sin aprobación.

## 12. Aprobación

Este plan no autoriza implementación hasta una aprobación explícita que mencione la versión, por ejemplo:

```text
Aprobar plan v1 (TASK-013)
```

La aprobación se registrará en `approval.md` con el texto literal, la fecha y el alcance autorizado.

---

## Addendum v1.1 — Ajuste de UI aprobado (2026-11-04)

- **Decisión del desarrollador:** las tabs del modal de granja son **por familia**, no por integrante.
- Cada tab representa una familia del hábitat; al pulsarla se muestra el panel con **nido, refugio, complementos y plantaciones** de esa familia, siguiendo el patrón de tabs de `habitats/index.blade.php` (provincias).
- El detalle de especie (`GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`) se mantiene como endpoint disponible, pero **no se muestra en la UI actual** de tabs por familia.
- El modal de bayas, el catálogo de 36 y el resto del plan v1 no cambian.
