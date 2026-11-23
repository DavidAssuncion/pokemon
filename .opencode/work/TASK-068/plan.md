# TASK-068 — Plan

- **ID:** TASK-068
- **Versión:** v1
- **Estado:** AWAITING_APPROVAL
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`

## 1. Objetivo

Indicar en todas las vistas de movimientos cuándo un movimiento golpea a varios
objetivos: todos los rivales (Avalancha), todos incluidos aliados (Surf /
Terremoto) o una fila completa (Explosión / Autodestrucción). Incluye movimientos
de estado multiobjetivo. Sin cambios de mecánica.

## 2. Fuente única de etiquetas (dominio)

`src/Battle/Domain/Enums/ObjetivoMovimiento.php` — añadir:

- `esMultiobjetivo(): bool` → `ALL_ADJACENT_FOES`, `ALL_ADJACENT`, `ALL`,
  `FILA_VANGUARDIA`, `FILA_RETAGUARDIA`, `FILA_OBJETIVO`.
- `incluyeAliados(): bool` → `ALL_ADJACENT`, `ALL`.
- `etiquetaAlcance(): ?string` (texto completo para `title`):
  - `ALL_ADJACENT_FOES` → `Golpea a todos los rivales`
  - `ALL_ADJACENT` / `ALL` → `Golpea a todos los combatientes (incluye aliados)`
  - `FILA_VANGUARDIA` → `Golpea a toda la vanguardia rival`
  - `FILA_RETAGUARDIA` → `Golpea a toda la retaguardia rival`
  - `FILA_OBJETIVO` → `Golpea a toda la fila del objetivo`
  - resto → `null`.
- `etiquetaCortaAlcance(): ?string` (texto del badge):
  - `ALL_ADJACENT_FOES` → `TODOS RIVALES`
  - `ALL_ADJACENT` / `ALL` → `A TODOS`
  - `FILA_*` → `FILA COMPLETA`
  - resto → `null`.

`label()`, `esUnSoloObjetivo()` y `esFila()` no se tocan.

## 3. Payloads (aditivos, sin romper contratos)

Claves nuevas por movimiento: `objetivo` (valor crudo), `objetivo_label`,
`objetivo_corto`, `objetivo_multiple`, `objetivo_incluye_aliados`
(en `MovesPreviewPresenter` en camelCase: `objetivoLabel`, `objetivoCorto`,
`objetivoMultiple`, `objetivoIncluyeAliados`).

- `app/Livewire/Presenters/MovesPreviewPresenter.php`: en `previewDe()` y
  `previewAliadoBase()`, desde `$move->objetivo`.
- `app/Support/PresentadorDetalleEspecie.php`: en `moves()`, desde
  `ObjetivoMovimiento::tryFrom((string) $move->getAttribute('target'))`;
  actualizar los PHPDoc de shape (método, `@var`, parámetros de `moveOrder`).
- `app/Support/ReclutadoSerializer.php`: en `formatearMovimiento()`, desde
  `$move->target`.
- `app/Support/ArbolSendas.php`: en `para()`, desde `$move->target`.

## 4. Vistas

Colores (mismos tokens en las cuatro):
- incluye aliados → peligro (`bg-danger text-on-danger` en combate;
  `border-danger bg-danger-soft text-danger-ink` en Tailwind).
- solo rivales → aviso (`bg-warning text-on-warning`;
  `border-warning bg-warning-soft text-warning-ink`).
- fila → info (`bg-info-soft text-info-ink`; `border-info bg-info-soft text-info-ink`).

- `resources/views/livewire/partials/moves-panel.blade.php`: fila propia con el
  badge tras la fila de categoría/precisión (antes del bloque `modoAliado`),
  `title="{{ $move['objetivoLabel'] }}"`, solo si `objetivoMultiple`.
- `resources/views/equipos/partials/movimiento-card.blade.php`: chip en la fila
  de metadatos (tras la categoría), `x-show="mov.objetivo_multiple"`,
  `:title="mov.objetivo_label"`.
- `resources/views/pokedex/index.blade.php`: chip junto al nombre del movimiento
  (celda `mov.name`, línea 575), visible en todos los breakpoints.
- `resources/views/sendas/show.blade.php`: chip en la fila de metadatos del nodo
  (líneas 204-212), `x-show="nodo.objetivo_multiple"`.

La vista no calcula nada: solo pinta `objetivo_corto`, `objetivo_label` y los
flags recibidos.

## 5. Fuera de alcance

- Objetivos de lado/apoyo (`foeSide`, `allySide`, `allies`, `allyTeam`, `self`,
  `normal`, `scripted`, `randomNormal`, `any`, `adjacentAlly*`).
- Cambios de targeting, IA, daño o descripciones del catálogo.
- Migraciones, seeders, índices, dependencias, rutas.
- Tests: no se crean, no se modifican, no se ejecutan.

## 6. Verificación

- `php -l` en los 5 archivos PHP tocados.
- `vendor/bin/pint --dirty --format agent`.
- PHPStan (larastan) sobre los archivos del alcance; sin errores nuevos.
- Criterios de aceptación manuales:
  1. Avalancha/Ventisca muestran `TODOS RIVALES` en las 4 vistas.
  2. Surf/Terremoto muestran `A TODOS` con énfasis de peligro y tooltip
     "incluye aliados".
  3. Explosión/Autodestrucción muestran `FILA COMPLETA`.
  4. Gruñido (estado multiobjetivo) también se marca.
  5. Movimientos de un solo objetivo no muestran indicador.

## 7. Riesgos

- Tarjetas estrechas (Sendas/equipos): el chip puede envolver; se usa texto corto
  y fila de metadatos con `flex-wrap` ya existente.
- `PresentadorDetalleEspecie` alimenta también el endpoint de hábitats; los
  campos son aditivos y esa vista no pinta movimientos, sin impacto.