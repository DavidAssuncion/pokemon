# Shared

Infraestructura transversal sin dependencias de módulos. Todo lo reutilizable entre
módulos vive aquí.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/Collection.php` | Colección base `Countable` + `IteratorAggregate` (add, filter, sort, map, reduce, sum…) |
| `Collections/IntCollection.php`, `StringCollection.php` | Colecciones de escalares |
| `Domain/Collections/ItemModalCollection.php` | Items de modal de recompensas |
| `Domain/DataTransferObjects/` | `PokemonResumen`, `ItemResuelto`, `ItemModal` |
| `Domain/ValueObjects/MapaConteo.php` | Mapa `string => int` tipado |
| `Domain/Exceptions/` | `DominioExcepcion`, `RecursoNoExiste`, `ViolacionReglaNegocio`, `PermisoDenegado` |
| `Bus/` | `Command`, `CommandHandler`, `CommandBus`, `UnitOfWork` |
| `Tipos/` | Enum `TipoPokemon`, `TiposCollection`, `TypeChart` (matriz 18×18) |
| `Domain/` | `ClasificadorOfensivaDefensiva`, `IvsRangoEntrenador`, `NivelHelper`, `SlugTipo`, `ProbabilidadCaptura`, `ProbabilidadesShiny`, `DistribuidorStatsValor`, `EscaladorNivelRival`, `RangoIvs` |
| `UI/TipoBadges.php` | Helpers de badges de tipo |

## Reglas

- No importar clases de otros módulos.
- Las colecciones extienden `Src\Shared\Domain\Collection` y declaran `public string $type`.
- Los mapas de dominio usan `MapaConteo`; los decimales de batalla, `MapaFactores`.
- `TypeChart` solo expone `getEffectiveness()`; la matriz es privada.
- Los DTOs exponen `toArray()` solo como frontera.

## Uso típico

```php
$tipos = new TiposCollection([TipoPokemon::FUEGO, TipoPokemon::VOLADOR]);
$tipos->contiene(TipoPokemon::FUEGO);      // bool
$tipos->toList();                          // frontera

$stats = new DatosStatsCollection([$s1, $s2, $s3]);
$formacion = (new ClasificadorOfensivaDefensiva())->generarFormacion($stats);
$posicion = $formacion->en(0);
```

## Tests

`tests/Unit/Shared/`, `tests/Unit/CollectionTest.php` y tests de tipos/efectividad.
