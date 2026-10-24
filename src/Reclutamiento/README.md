# Reclutamiento

Captura de pokémon y evolución por caramelos de tipo. Incluye la resolución de
opciones evolutivas y los requisitos que consume la UI.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `App/ServicioCaptura.php` | Reglas de captura/reclutamiento |
| `App/ServicioEvolucion.php` | Evolución: opciones, requisitos, consumo de exp de tipo |
| `App/Collections/PokemonCollection.php` | Colección tipada de modelos Pokemon (opciones) |
| `Domain/DataTransferObjects/OpcionEvolucion.php` | Opción evolutiva + requisitos |
| `Domain/DataTransferObjects/RequisitoEvolucion.php` | Requisito por tipo |
| `Domain/Collections/OpcionEvolucionCollection.php` | Opciones tipadas |
| `Domain/Collections/RequisitoEvolucionCollection.php` | Requisitos + `cumple()` |
| `Domain/ValueObjects/InventarioCaramelos.php` | Caramelos disponibles por `item_key` |

## Puntos de entrada

- `ServicioEvolucion::opcionesEvolucion(Pokemon)` → `PokemonCollection`
  (orden determinista por `minimum_level`, `evolved_species_id`).
- `ServicioEvolucion::siguienteEvolucion(Pokemon)` → `?Pokemon`.
- `ServicioEvolucion::requisitosDeOpciones(Reclutado, userId)` →
  `OpcionEvolucionCollection` (una sola query de inventario).
- `ServicioEvolucion::tiposRequeridos(?Pokemon)` → `StringCollection`.
- `ServicioEvolucion::consumirExpTipo(...)` → actualiza `reclutados.exp`.

## Reglas

- La exp de tipo vive en el JSON `reclutados.exp` (cast `ExpReclutado`).
- Los caramelos viven en `player_inventory` con clave `tipo:{slug}`.
- Umbral = exp para subir del nivel actual al siguiente (curva media ×10).
- Una especie puede tener varias evoluciones (Eevee); el orden es determinista.
- Las formas alternas (mega/gmax) no están en el seeder; las regionales sí.

## Tests

`tests/Feature/ReclutadoOpcionesEvolucionTest.php`, `ReclutadoEvolucionTest.php` y
tests de captura.
