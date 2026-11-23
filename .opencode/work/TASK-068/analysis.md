# TASK-068 — Análisis

- **ID:** TASK-068
- **Fecha:** 2026-11-19
- **Estado:** ANALYZING → DESIGNING.

## 1. Dato existente

- `moves.target` (string) ya persiste el alcance; `App\Models\Move::$fillable`
  incluye `target`. No hay migración ni cambio de catálogo.
- Distribución en `storage/data/moves.json`: `normal` 492, `self` 78,
  `allAdjacentFoes` 51 (Avalancha/Ventisca), `allAdjacent` 17 (Surf/Terremoto),
  `all` 8, `filaObjetivo` 2, resto de objetivos de apoyo/lado.
- `Src\Battle\Domain\Enums\ObjetivoMovimiento` ya modela el alcance y tiene
  `label()`, `esUnSoloObjetivo()` y `esFila()`. `label()` no se usa en ninguna
  vista y habla de "adyacentes", que no describe el motor real.

## 2. Semántica real del motor

`Src\Battle\Domain\Danio\ResolutorObjetivosDanio::resolver()`:

- `ALL_ADJACENT_FOES` → todos los rivales vivos.
- `ALL_ADJACENT` y `ALL` → todos los combatientes vivos **excepto el atacante**
  (incluye aliados). Aplica igual a movimientos de estado.
- `FILA_VANGUARDIA` / `FILA_RETAGUARDIA` / `FILA_OBJETIVO` → una fila completa
  rival.
- El resto (`normal`, `self`, lados, aliados…) no es multiobjetivo de ataque.

Conclusión: el indicador debe derivarse del enum, no de la descripción del
catálogo (la de Avalancha no menciona el alcance; la de Surf sí).

## 3. Puntos de presentación sin indicador hoy

| Vista | Archivo | Payload |
|---|---|---|
| Combate (Ataques) | `resources/views/livewire/partials/moves-panel.blade.php:44-60` | `App\Livewire\Presenters\MovesPreviewPresenter::previewDe()` / `previewAliadoBase()` |
| Detalle Pokémon /equipos | `resources/views/equipos/partials/movimiento-card.blade.php:8-14` | `App\Support\ReclutadoSerializer::formatearMovimiento()` |
| Detalle especie Pokédex | `resources/views/pokedex/index.blade.php:575` | `App\Support\PresentadorDetalleEspecie::moves()` |
| Sendas | `resources/views/sendas/show.blade.php:204-212` | `App\Support\ArbolSendas::para()` |

Ninguno expone `target` ni una etiqueta de alcance. El payload de
`PresentadorDetalleEspecie` también lo consume
`Src\Habitats\Infra\Queries\DetallePokemonFamiliaQuery`, pero esa vista no pinta
movimientos; añadir campos es aditivo y no rompe contratos.

## 4. Restricciones aplicables

- `.ai/rules/frontend-minimal-logic.md`: textos y flags derivados se calculan en
  backend; la vista solo pinta.
- `.ai/rules/arrays-dto-collections.md`: los presentadores ya devuelven arrays en
  la frontera de vista (excepción tolerada); se añaden claves, no se crean
  contratos nuevos. La fuente única de etiquetas es el enum.
- Sin tests, sin migraciones, sin cambios de mecánica ni de IA.