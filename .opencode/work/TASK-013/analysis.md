# TASK-013 — Análisis

> Resultado del `analyst`. Verificado contra el código actual.

## Resumen ejecutivo

Los contratos clave ya existen y son reutilizables: `GranjaDetalle` expone `familias`/`bayas`, `FamiliaGranja::toArray()` incluye `base + evolutions`, y `PresentadorDetallePokemon::para()` devuelve tipos, stats, hábitat y familia. Faltan tres piezas: (1) clasificación única de las 36 bayas, (2) catálogo de bayas con stock 0 para el selector, y (3) endpoint autorizado de detalle por Pokémon de familia. La opción de menor riesgo es añadir un enum `GrupoBaya` + `CatalogoItems::grupoDeBaya()`, un método `catalogoBayas()` y un endpoint bajo demanda que reutilice `PresentadorDetallePokemon`, ampliándolo de forma opt-in con habilidades/movimientos.

No conviene incrustar el detalle en `FamiliaGranja::toArray()` ni cambiar la semántica de `resumenBayas()`.

## Contratos actuales

- `GranjaDetalle` (`src/Granjas/Domain/DataTransferObjects/GranjaDetalle.php:28-38`): `id`, `habitat`, `familias`, `inventario`, `bayas`, `bonguri`.
- `BayaGranja` (`:12-28`): `clave`, `nombre`, `icono`, `cantidad`; sin grupo.
- `FamiliaGranja` (`:24-36`): `evolution_chain_id`, `base`, `evolutions`, `types`, `nido`, `refugio`, `complementos`, `ev_total`.
- `NidoGranja` (`:39-53`): plantaciones, huecos, costes, `puede_mejorar`.
- `ObtenerGranja` (`:99-109`) construye `GranjaDetalle` e inyecta `resumenBayas()`.
- `InventarioGranja::resumenBayas()` (`:72-102`) filtra `cantidad <= 0`; una consulta vía `InventarioJugador::saldos()`.
- `DTOFamiliaDisponible` (`:20-53`): `evolutionChainId`, `base`, `evolutions`, `types`; `contieneSpecies()` compara `speciesId`.
- `DTOPokemonFamilia` (`:17-45`): `id`, `name`, `icon`, `level`, `speciesId`; `toArray()` omite `speciesId`.
- `HabitatLecturaRepository::getFamiliesByHabitat()` (`:125-156`) deriva familias de `pokemon_habitat` + `pokemon.evolution_chain_id`.
- `CadenasFamiliaHabitatsTrait::cargarMiembrosFamilia()` (`:112-144`) ordena por `species_id ASC, id ASC`.
- `splitFamilyMembers()`: `base` = primer miembro por `species_id`, no necesariamente etapa 1.
- `ClaveBaya` (`:13-48`): 36 casos.
- `CatalogoItems` (`:21-28` BAYAS_BASE; `:35-41` BAYAS_ESTADISTICA; `:48-56` BAYAS_RARAS; `:186-203` construcción).
- `CatalogoRecetas` + `ClaveMejora::bayaOrigen` (`:47-58`) y `ClaveReductor::bayaOrigen` (`:43-51`) confirman el mapeo semántico.
- `PresentadorDetallePokemon::para()` (`:60-90`): tipos, stats, hábitat, familia; `:160-191` familia vía `FamiliaPokemon`.
- `DatagridServiceProvider` (`:98-106`) bloquea especie no vista salvo `unlock`.
- `routes/datagrid.php:9-10`, `routes/granjas.php:9-21`, `routes/habitats.php:6-31`; todo bajo `auth`.
- `resources/views/habitats/show.blade.php:1126-1451` modal granja; `:1387-1448` selector de baya; `:1823-2006` JS.
- `resources/views/pokedex/index.blade.php:260-466` modal de detalle reutilizable.
- `resources/views/equipos/index.blade.php:249-272` patrón de tabs.
- `app/Support/ReclutadoSerializer.php:155-201` movimientos; `:470-484` habilidades.
- `app/Models/Pokemon.php:58-85` relaciones `moves` y `abilities`.
- `FamiliaEnHabitat` (`:13-26`) validación reutilizable.

## Gaps

1. No existe grupo/categoría de baya en ningún DTO ni catálogo público.
2. `resumenBayas()` no sirve para un selector con las 36; oculta stock 0.
3. No existe endpoint de detalle autorizado por hábitat/familia.
4. `PresentadorDetallePokemon` no incluye habilidades, movimientos ni metadatos de especie.
5. El modal de granja no tiene tabs por familia ni estado de detalle/caché.
6. `DTOFamiliaDisponible` no expone `stage`/`parent_id`; el orden es por `species_id`.

## Recomendaciones

1. **Grupo de baya:** enum `GrupoBaya` (`MEDICINA`, `MEJORA`, `REDUCTOR`, `RESISTENCIA`) con `label()`; `CatalogoItems::grupoDeBaya(ClaveBaya)`: BAYAS_BASE → MEDICINA, BAYAS_RARAS → MEJORA, BAYAS_ESTADISTICA → REDUCTOR, resto → RESISTENCIA. Añadir `GrupoBaya $grupo` a `BayaGranja` y serializar `grupo`/`grupo_nombre`. No usar `CategoriaItem`.
2. **Catálogo con stock 0:** no cambiar `resumenBayas()`; añadir `InventarioGranja::catalogoBayas(int $userId): BayasGranjaCollection` con las 36 y stock 0. Exponerlo en un endpoint aparte `GET /api/habitats/{habitat}/farm/berries`, cargado al abrir el selector y cacheado en Alpine. Evita inflar cada acción de granja.
3. **Detalle de especie:** endpoint nuevo bajo demanda, nunca incrustado en `FamiliaGranja`. Ruta propuesta: `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`. Validar `pokemon.evolution_chain_id === chain` y que la cadena está asignada al hábitat (`pokemon_habitat` + `pokemon.evolution_chain_id`); no exigir que el Pokémon concreto esté en `pokemon_habitat` (las evoluciones pueden no estarlo).
4. **Información completa:** añadir opt-in habilidades, movimientos y metadatos de especie (capture_rate, base_experience, height, weight, hatch) al presentador. No cargarlos por defecto en el detalle de Pokédex. No hay `Reclutado`, así que los movimientos son learnset de especie, no loadout.
5. **Tabs:** `base + evolutions` bastan para las pestañas. Estado Alpine en el scope superior (`familiaDetalleChain`, `pokemonTabId`, `detalleCache`), no `x-data` anidado en `x-for`. Cargar detalle bajo demanda con caché. Cuidado con `@keydown.escape.window` del modal de granja: el detalle anidado debe evitar cerrar la granja.
6. **Orden de tabs:** el orden actual es por `species_id`, no por etapa; para orden evolutivo real haría falta `stage`/`parent_id` en el DTO o usar `detail.family.members`.

## Riesgos

| Nivel | Riesgo | Mitigación |
|---|---|---|
| Alto | Cambiar `resumenBayas()` a 36 | Regresión del selector y mensajes; usar método/endpoint nuevo. |
| Alto | Incrustar detalle en `FamiliaGranja` | N+1 y payload grande; endpoint bajo demanda + caché. |
| Medio | IDOR | Validar `evolution_chain_id` + `pokemon_habitat`; no aceptar ids libres. |
| Medio | Especies no vistas | Decidir regla de producto; gate Pokédex o contrato sin `visto`/`atrapado`. |
| Medio | Duplicación con Pokédex/Equipos/Exploración | Reutilizar presentador/serializer; una sola fuente `GrupoBaya`. |
| Bajo | Orden de tabs por `species_id` | Exponer `stage` o reordenar tras el detalle. |
| Bajo | Escape/backdrop y `x-if` | Guardas de teclado y estado en scope superior. |

## Preguntas bloqueantes

1. ¿El detalle debe mostrarse para especies no vistas o respetar el bloqueo de Pokédex?
2. ¿“Mejoras” = bayas raras (Liechi..Micle) y “Reductores” = estadística (Kelpsy..Tamato)?
3. ¿Las tabs deben ordenarse por etapa evolutiva real o basta el orden por `species_id`?
4. ¿“Toda su información” incluye habilidades, movimientos y metadatos, o basta el contrato actual?

## Archivos inspeccionados

Los detallados en el informe del `analyst`: DTOs de granja, `InventarioGranja`, `ObtenerGranja`, `PlantarBaya`, `FamiliaEnHabitat`, catálogo de items, DTOs de hábitats, repositorios, `PresentadorDetallePokemon`, `FamiliaPokemon`, `ReclutadoSerializer`, `DatagridServiceProvider`, modelos `Pokemon`/`Move`/`Ability`, rutas y vistas de granja/Pokédex/Equipos.
