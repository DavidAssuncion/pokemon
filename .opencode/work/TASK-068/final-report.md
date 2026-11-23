# TASK-068 — Informe final

- **ID:** TASK-068
- **Título:** Indicador de alcance multiobjetivo en movimientos
- **Proyecto:** `/home/david/pokemon`
- **Estado:** IMPLEMENTED — validación visual pendiente
- **Plan:** v1 aprobado por el desarrollador
- **Fecha:** 2026-11-19

## Resultado

Los movimientos que afectan a varios objetivos muestran ahora un indicador en
las cuatro vistas donde se listan:

| Vista | Indicador |
|---|---|
| Combate (Ataques) | Badge `TODOS RIVALES` / `A TODOS` / `FILA COMPLETA`; color de peligro si incluye aliados |
| Detalle de Pokémon (`/equipos`) | Chip en la fila de metadatos |
| Detalle de especie (Pokédex) | Chip junto al nombre del movimiento |
| Sendas | Chip en la fila de metadatos |

Etiquetas y tooltips:

- `allAdjacentFoes` (Avalancha, Ventisca, Gruñido…) → `TODOS RIVALES` /
  "Afecta a todos los rivales".
- `allAdjacent` (Surf, Terremoto, Danza Caos…) → `A TODOS` (peligro) /
  "Afecta a todos los combatientes (incluye aliados)".
- `filaObjetivo` (Explosión, Autodestrucción) → `FILA COMPLETA`.
- `all` (Día Soleado, Danza Lluvia, Trick Room…) → **sin indicador**: son
  movimientos de campo/tiempo, no ataques que golpeen a todos.
- Movimientos de un solo objetivo → sin indicador.

## Cambios

- `src/Battle/Domain/Enums/ObjetivoMovimiento.php`: métodos de alcance y
  etiquetas (fuente única).
- Payloads aditivos en `MovesPreviewPresenter`, `PresentadorDetalleEspecie`,
  `ReclutadoSerializer` y `ArbolSendas`.
- Badges/chips en `moves-panel`, `movimiento-card`, `pokedex/index` y
  `sendas/show`.
- Artefactos de tarea en `.opencode/work/TASK-068/`.

Sin migraciones, sin cambios de mecánica, sin tests, sin dependencias nuevas.

## Verificación

- `php -l` de los 5 PHP: OK.
- Pint (`--dirty`): pass.
- PHPStan L6 dirigido: sin errores.
- `php artisan view:cache` + `view:clear`: OK.
- Comprobación read-only con datos reales (Panpour/Bulbasaur): Surf `A TODOS`,
  Avalancha/Ventisca/Gruñido `TODOS RIVALES`, Día Soleado sin indicador.

## Pendiente

- Validación visual en navegador. Si no se refleja, ejecutar `npm run build` o
  `npm run dev`.
- Revisión independiente por `reviewer` no invocada en esta sesión; se deja
  autorrevisión en `review.md`.
- No se ha creado commit (sin pedido explícito).

## Nota de preflight

`config/sendas.php` estaba modificado en el árbol de trabajo antes de esta
tarea (costes de tramos 4-5). No se ha tocado ni revertido.