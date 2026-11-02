# Contexto del módulo `Reclutamiento`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Captura y evolución de pokémon. Incluye la cola de reclutamiento (`reclutables`), la creación de
`reclutados` con IVs/EVs/shiny, la evolución por caramelos de tipo y los requisitos que consume la
UI del detalle de `/equipos`.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `App/ServicioCaptura.php` | Capturas tras combate: avistado + job de captura con `ProbabilidadCaptura`. |
| `App/ServicioEvolucion.php` | Opciones evolutivas, requisitos por tipo, consumo de exp de tipo. |
| `App/Collections/PokemonCollection.php` | Colección tipada de modelos `Pokemon` para opciones. |
| `Domain/DataTransferObjects/OpcionEvolucion.php` | Opción evolutiva + requisitos. |
| `Domain/DataTransferObjects/RequisitoEvolucion.php` | Requisito por tipo. |
| `Domain/Collections/OpcionEvolucionCollection.php` | Opciones tipadas. |
| `Domain/Collections/RequisitoEvolucionCollection.php` | Requisitos + `cumple()`. |
| `Domain/ValueObjects/InventarioCaramelos.php` | Caramelos disponibles por `item_key`. |

## Decisiones de producto y funcionamiento

### Captura

1. **Probabilidad unificada cap-25** (`Src\Shared\Domain\ProbabilidadCaptura`):
   `chance = min(capture_rate, 25) / 255` (máximo ~9.8 % por derrota). El comentario histórico
   «cap-45» de `ServicioCaptura` está obsoleto; la fuente es `ProbabilidadCaptura`.
2. **Captura tras batear**: `ServicioCaptura::procesarCapturas()` marca AVISTADO con
   `ActualizarPokedexJob` y encola `CapturarPokemonJob` con la probabilidad calculada.
3. **Cola de reclutamiento**: `ReclutamientoController::recruit()` valida `reclutable_id` con
   global scope de usuario, calcula IVs con `GeneradorIvs::reclutamiento(nivelNido)` y EVs de
   granja (`ResolverBonosFamilia`), crea el reclutado con `CrearReclutado` y decrementa/elimina el
   reclutable.
4. **Shiny**: `ProbabilidadesShiny` centraliza las tasas (salvaje 1/2048, huevo 1/512). El
   reclutamiento directo no fuerza shiny; la captura de ruta shiny se resuelve en
   `OtorgarRecompensasRuta`.

### Evolución

5. **Exp de tipo** en `reclutados.exp` (JSON `{total, tipos}`; cast `App\Models\Casts\ExpReclutado`).
6. **Caramelos de tipo** en `player_inventory` con clave `tipo:{slug}` (`ItemCatalogo::keyTipo`).
7. **Umbral** = exp para subir del nivel actual al siguiente con la curva media ×10 de
   `NivelHelper` (`experienciaParaNivel`).
8. **Opciones evolutivas**: se resuelven desde `pokemon_evolution` por
   `evolves_from_species_id`; orden determinista por `minimum_level` y `evolved_species_id`.
   Una especie puede tener varias opciones (Eevee).
9. **Formas**: las regionales (Alola/Galar/Hisui/Paldea) están incluidas; mega/gmax no están en el
   seeder. No se excluyen por id.
10. **Consumo**: al evolucionar se resta el umbral de cada tipo requerido y se eliminan los tipos
    que quedan en ≤ 0 (`ExpReclutado::consumirTipos`); el reclutado cambia de `pokemon_id` y
    conserva identidad/exp total/EVs/IVs.

## Contratos y puntos de entrada

Rutas (`routes/player.php`, bajo `auth`):

```text
GET    /reclutamiento
POST   /reclutamiento/recruit
POST   /reclutamiento/discard
POST   /reclutamiento/discard-all

GET    /reclutado/{reclutado}
POST   /reclutado/{reclutado}/dar-caramelo
POST   /reclutado/{reclutado}/dar-evs
POST   /reclutado/{reclutado}/evolucionar
PATCH  /reclutado/{reclutado}/loadout
DELETE /reclutado/{reclutado}
GET    /reclutado/{reclutado}/evoluciones
GET    /reclutado/{reclutado}/movimientos

GET    /api/reclutados
GET    /api/reclutados/favoritos
POST   /api/reclutados/{reclutado}/toggle-favorito
GET    /api/reclutado/{reclutado}/capacidades
POST   /api/reclutado/{reclutado}/rol
```

- `ReclutamientoController` (cola) y `ReclutadoController` (detalle/evolución/loadout).
- `ServicioEvolucion` es estático y usa Eloquent directamente (deuda).
- Tablas: `reclutables`, `reclutados`, `pokemon`, `pokemon_evolution`, `player_inventory`.
- `DatosNuevoReclutado` es el DTO de creación; `CrearReclutado` es la vía compartida con huevos/ruta
  shiny.

## Dependencias

- `Src\Shared\Domain\ProbabilidadCaptura`, `ProbabilidadesShiny`, `NivelHelper`, `SlugTipo`.
- `Src\Pokemon\Domain\Stats\GeneradorIvs`, `StatsValue`.
- `Src\Granjas\App\ResolverBonosFamilia` — IVs/EVs iniciales.
- `App\Models\Pokemon`, `PokemonEvolution`, `Reclutado`, `PlayerInventory`, `Reclutable`.
- `App\Jobs\ActualizarPokedexJob`, `CapturarPokemonJob`.
- `App\Support\CrearReclutado`, `ItemCatalogo`, `ReclutadoSerializer`.

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Resuelto 2026-10-31] Comentario corregido a cap-25** en `ServicioCaptura` (la fuente es
  `ProbabilidadCaptura`). No reimplementar la fórmula.
- **[Media] `ExpReclutado` vive en `app/Models/Casts`** y es a la vez VO + cast. Mover el VO a
  `src/Reclutamiento/Domain` (o Shared) y dejar el cast como adaptador Eloquent.
- **[Media] `ServicioEvolucion` es estático y consulta Eloquent directamente**. Extraer un
  repositorio de evoluciones y un caso de uso `OpcionesEvolucion` para testear sin BD y reutilizar
  desde la UI/API.
- **[Media] `ServicioCaptura` usa jobs estáticos y `Pokemon::find`**. Inyectar repositorio/dispatcher
  para testear la política de captura sin jobs reales.
- **[Baja] `PokemonCollection` envuelve modelos Eloquent**, no entidades de dominio. Es aceptable
  como read model de opciones, pero no debe filtrarse a dominio.
- **[Baja] `requisitosPara()`/`requisitosDeOpciones()` mezclan consulta de inventario y cálculo**.
  Separar `InventarioCaramelos` (ya existe) de la consulta y cachear el inventario por request.

## Guía de búsqueda (grep)

- Clases: `ServicioCaptura`, `ServicioEvolucion`, `OpcionEvolucion`, `RequisitoEvolucion`,
  `InventarioCaramelos`, `DatosNuevoReclutado`, `CrearReclutado`, `ExpReclutado`.
- Constantes: `ProbabilidadCaptura::CAP`, `ProbabilidadesShiny::SALVAJE`, `HUEVO`,
  `NivelHelper::experienciaParaNivel`.
- Rutas/términos: `/reclutamiento`, `/reclutado/{id}/evolucionar`, `/reclutado/{id}/evoluciones`,
  `tipo:{slug}`, `reclutables`, `exp`, `tipos`, `shiny`.
- Tablas: `reclutables`, `reclutados`, `pokemon_evolution`, `player_inventory`.
- Tests: `tests/Feature/ReclutadoOpcionesEvolucionTest.php`, `ReclutadoEvolucionTest.php`,
  `ServicioCapturaTest`, tests de captura/reclutamiento.
