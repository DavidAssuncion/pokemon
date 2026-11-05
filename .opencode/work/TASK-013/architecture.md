# TASK-013 — Diseño técnico

> Diseño del `architect` para el modal de selección de bayas y las tabs de familia con detalle completo.

## 1. Resumen y decisiones

Se implementan dos read-models independientes, sin migraciones y sin cambiar la semántica de `resumenBayas()`:

1. **Catálogo de bayas con stock 0** servido por `GET /api/habitats/{habitat}/farm/berries`, con clasificación única `GrupoBaya` en `Shared\Domain\Items`.
2. **Detalle de especie de familia** bajo demanda por `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`, autorizado por cadena asignada al hábitat, reutilizando `PresentadorDetallePokemon` mediante `PresentadorDetalleEspecie`.
3. **Tabs de familia** con orden por `stage` real, exponiendo `stage`/`parent_id`/`species_id` en `DTOPokemonFamilia`.
4. **Modal de bayas separado** (`z-[60]`) abierto desde `Plantar`; al elegir baya se planta, se cierra solo ese modal y la granja se refresca.

Sin F6/F7, sin tests, sin commit/push.

## 2. Clasificación de bayas

Enum `GrupoBaya` con `label()` y `orden()`:

| Grupo | Fuente | Nº |
|---|---|---|
| `MEDICINA` | `BAYAS_BASE` | 6 |
| `MEJORA` | `BAYAS_RARAS` | 7 |
| `REDUCTOR` | `BAYAS_ESTADISTICA` | 5 |
| `RESISTENCIA` | resto (bayas de tipo) | 18 |

`CatalogoItems::grupoDeBaya(ClaveBaya): GrupoBaya` es la única fuente de verdad. `BayaGranja` incorpora `GrupoBaya $grupo`; `toArray()` añade `grupo` y `grupo_nombre` (aditivo).

## 3. Catálogo con stock 0

`InventarioGranja::catalogoBayas(int $userId): BayasGranjaCollection` recorre las 36 `ClaveBaya::cases()`, lee saldos en una sola llamada y devuelve cantidad 0 cuando no hay fila. **No modifica `resumenBayas()`**.

Caso de uso `ObtenerCatalogoBayas::handle(int $userId, int $habitatId): BayasGranjaCollection` valida el hábitat con `AsegurarGranja::para()` y devuelve la colección. Endpoint: `GET /api/habitats/{habitat}/farm/berries` → `{ "bayas": [...] }`.

## 4. Detalle de especie

Endpoint `GET /api/habitats/{habitat}/families/{chain}/pokemon/{pokemon}`:

1. Validar que `chain` está asignada al hábitat (`pokemon_habitat` join `pokemon.evolution_chain_id`). Si no: 404.
2. Cargar `Pokemon` por `id` y `evolution_chain_id = chain`. Si no: 404.
3. Leer `pokedex` del usuario para `visto`/`atrapado` (información, no bloqueo).
4. Delegar en `PresentadorDetalleEspecie::para($pokemon, $visto, $atrapado)`.

`PresentadorDetalleEspecie` carga `abilities` y `moves`, llama a `PresentadorDetallePokemon::para()` (con overrides opcionales) y añade `abilities`, `moves` y `species`. El detalle normal de Pokédex no cambia.

Limitación: `ability_pokemon` no guarda `hidden`; se expone `slot`/`source` sin inventar la bandera. Las formas regionales con cadena propia quedan fuera salvo que su cadena esté asignada al hábitat.

### Shape JSON

Reutiliza el shape de `PresentadorDetallePokemon` (id, name, visto, atrapado, types, type_details, stats, habitat, family) y añade:

```json
{
  "abilities": [
    {"id": 65, "name": "Espesura", "effect": "...", "slot": 1, "source": "poke_habilidades"}
  ],
  "moves": [
    {
      "slug": "tackle", "name": "Placaje", "type_id": 1,
      "tipo_nombre": "Normal", "tipo_slug": "normal",
      "category": "fisico", "category_label": "Físico",
      "base_power": 40, "accuracy": 100,
      "level": 1, "source": "level-up"
    }
  ],
  "species": {
    "species_id": 1,
    "evolution_chain_id": 1,
    "capture_rate": 45,
    "base_experience": 64,
    "height": 7,
    "weight": 69,
    "hatch": 20
  }
}
```

- `moves` ordenados: level-up primero, luego `level` asc, luego `name`.
- `abilities` ordenadas por `slot`.
- `species` omite claves nulas.
- `visto`/`atrapado` nunca bloquean la respuesta.

## 5. Tabs de familia

`MiembroCadena` incorpora `parentId`; `DTOPokemonFamilia` incorpora `stage` y `parentId` (con defaults compatibles) y `toArray()` añade `stage`, `parent_id`, `species_id`. `CadenasFamiliaHabitatsTrait` pasa ambos valores en los dos builders.

La vista ordena `base + evolutions` por `stage` asc, `species_id` asc, `id` asc. `base` sigue siendo el menor `species_id` por compatibilidad; la tab inicial es la de menor `stage`.

## 6. UI

- **Modal de bayas:** hermano del modal de granja, `z-[60]`, buscador, chips de categoría, lista con icono/nombre/cantidad/grupo, disponibles primero. Click en baya con stock → planta.
- **Tabs:** dentro de cada tarjeta de familia, reemplazan el sprite único. Panel de detalle inline debajo, cargado bajo demanda con caché por `chainId/pokemonId`.
- **Escape:** el modal de granja usa `cerrarCapaSuperior()`: si el modal de bayas está abierto lo cierra; si no, cierra la granja. El modal de bayas no registra su propio `.window`.
- **Refresco:** tras plantar, la respuesta completa de granja actualiza `granja` y sincroniza cantidades del catálogo local; no se hace otra petición.

## 7. Contratos y firmas

```php
enum GrupoBaya: string { case MEDICINA='medicina'; case MEJORA='mejora'; case REDUCTOR='reductor'; case RESISTENCIA='resistencia'; public function label(): string; public function orden(): int; }

CatalogoItems::grupoDeBaya(ClaveBaya $baya): GrupoBaya

BayaGranja::__construct(string $clave, string $nombre, string $icono, int $cantidad, GrupoBaya $grupo)

InventarioGranja::catalogoBayas(int $userId): BayasGranjaCollection
ObtenerCatalogoBayas::handle(int $userId, int $habitatId): BayasGranjaCollection

DTOPokemonFamilia::__construct(int $id, string $name, string $icon, ?int $level, int $speciesId, int $stage = 1, ?int $parentId = null)

PresentadorDetallePokemon::para(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array
PresentadorDetalleEspecie::para(Pokemon $pokemon, ?bool $visto = null, ?bool $atrapado = null): array
DetallePokemonFamiliaQuery::obtener(int $habitatId, int $chainId, int $pokemonId, int $userId): array
```

## 8. Archivos

**Crear:**
- `src/Shared/Domain/Items/GrupoBaya.php`
- `src/Granjas/App/ObtenerCatalogoBayas.php`
- `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php`
- `src/Habitats/Infra/Controllers/DetallePokemonFamiliaController.php`
- `app/Support/PresentadorDetalleEspecie.php`

**Modificar:**
- `src/Shared/Domain/Items/CatalogoItems.php`
- `src/Granjas/Domain/DataTransferObjects/BayaGranja.php`
- `src/Granjas/App/InventarioGranja.php`
- `src/Granjas/Infra/Controllers/GranjaController.php`
- `routes/granjas.php`
- `src/Habitats/Domain/DataTransferObjects/MiembroCadena.php`
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php`
- `src/Habitats/Presentation/DTOPokemonFamilia.php`
- `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php`
- `app/Support/PresentadorDetallePokemon.php`
- `routes/habitats.php`
- `resources/views/habitats/show.blade.php`

**Documentación:**
- `docs/adr/0002-catalogo-bayas-y-detalle-especie.md`
- `docs/architecture.md`

## 9. Orden de implementación

1. `GrupoBaya` + `CatalogoItems::grupoDeBaya`.
2. `BayaGranja` + `catalogoBayas` + `ObtenerCatalogoBayas` + endpoint/ruta.
3. `MiembroCadena.parentId` + `ResolvedorCadenasEvolutivas` + `DTOPokemonFamilia.stage/parentId` + trait.
4. `PresentadorDetallePokemon` overrides + `PresentadorDetalleEspecie` + query + controlador + ruta.
5. Blade: modal de bayas + JS.
6. Blade: tabs + panel + caché.
7. Documentación y verificación.

## 10. Criterios de aceptación

1. Catálogo con exactamente 36 bayas; reparto 6/7/5/18.
2. Disponibles (cantidad > 0) primero; orden estable.
3. Buscador por nombre sin distinguir mayúsculas/acentos; filtros por grupo.
4. Baya con cantidad 0 no plantable.
5. Al plantar: consume 1, refresca granja, cierra modal de bayas y mantiene granja.
6. Escape cierra solo el modal superior.
7. Tabs ordenadas por `stage`, luego `species_id`; `aria-selected`.
8. Detalle cacheado por Pokémon.
9. Detalle con sprite, tipos, stats, hábitat, familia, habilidades, learnset y metadatos.
10. Learnset scrollable con nombre, tipo, categoría, potencia, precisión y nivel/source.
11. Endpoint de detalle: 404 si cadena no asignada o Pokémon de otra cadena.
12. Especie no vista: detalle completo con `visto=false`, sin bloqueo.
13. `GET /datagrid/pokemon/{id}/detalle` conserva su contrato sin abilities/moves/species.
14. `GET /api/habitats/{id}/families` y `GET /farm` siguen siendo consumibles (claves aditivas).
15. Pint y análisis estático sin errores nuevos.
16. Sin tests ni migraciones.

## 11. Riesgos

| Nivel | Riesgo | Mitigación |
|---|---|---|
| Alto | Cambiar `resumenBayas()` a 36 | Método/endpoint nuevos; no tocar el existente. |
| Alto | IDOR en detalle | Validar cadena asignada + `evolution_chain_id`; 404 sin filtrar. |
| Medio | `stage` fallback 3 | Orden `stage`, `species_id`, `id`; documentar. |
| Medio | Doble clic al plantar | Botón deshabilitado sin stock + flag `bayasPlantando`. |
| Medio | Doble cierre por Escape | Un único handler `.window` + `cerrarCapaSuperior()`. |
| Bajo | `ability_pokemon` sin `hidden` | Exponer `slot`/`source`; no inventar. |
| Bajo | Especies sin movimientos/habilidades | Estados vacíos explícitos. |
| Bajo | `FamiliaPokemon` carga todas las aristas | Uso bajo demanda y caché; optimización posterior si hace falta. |

## 12. Verificación

- `vendor/bin/pint --dirty --format agent`
- `php -l` de archivos nuevos/modificados
- `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
- `vendor/bin/deptrac analyse --no-progress`
- `php artisan route:list --path=api/habitats`
- `php artisan view:cache` / `view:clear`
- `npm run build` si se añaden clases Tailwind nuevas
- `grep` de `new BayaGranja` y `new DTOPokemonFamilia` para constructores actualizados
- Sin tests
