# TASK-068 — Ejecución

- **ID:** TASK-068
- **Proyecto:** `/home/david/pokemon`
- **Plan:** v1 (aprobado)
- **Estado:** IMPLEMENTED — validación manual pendiente del desarrollador

## Preflight

- Rama: `master`.
- `config/sendas.php` aparece modificado en el árbol de trabajo **antes de esta
  tarea** (`costes` 4 => 512, 5 => 1024). No se ha tocado ni revertido: es WIP
  ajeno a TASK-068.
- Sin cambios en dependencias, migraciones ni tests.

## Archivos modificados

### Dominio

- `src/Battle/Domain/Enums/ObjetivoMovimiento.php`
  - `esMultiobjetivo()`, `incluyeAliados()`, `etiquetaAlcance()`,
    `etiquetaCortaAlcance()`.

### Presentación (payloads)

- `app/Livewire/Presenters/MovesPreviewPresenter.php` — campos en `previewDe()`
  y `previewAliadoBase()`.
- `app/Support/PresentadorDetalleEspecie.php` — campos en `moves()` y PHPDoc de
  shape actualizados.
- `app/Support/ReclutadoSerializer.php` — campos en `formatearMovimiento()`.
- `app/Support/ArbolSendas.php` — campos en el nodo de Senda.

### Vistas

- `resources/views/livewire/partials/moves-panel.blade.php` — badge propio en
  las tarjetas de Ataques.
- `resources/views/equipos/partials/movimiento-card.blade.php` — chip en la
  fila de metadatos.
- `resources/views/pokedex/index.blade.php` — chip junto al nombre del
  movimiento.
- `resources/views/sendas/show.blade.php` — chip en la fila de metadatos.

## Ajustes sobre el plan durante la verificación

1. **`ALL` excluido del indicador.** Los 8 movimientos con target `all` del
   catálogo son de campo/tiempo (Día Soleado, Danza Lluvia, Tormenta de Arena,
   Granizo, Trick Room, Zona Mágica, Paisaje Nevado, Hidrochorro). Marcarlos
   como "A TODOS" sugería daño colateral inexistente. `allAdjacent` y
   `allAdjacentFoes` siguen marcados.
2. **Campo `objetivo_es_fila` añadido** para que la vista elija el color info sin
   replicar la regla del enum.
3. **Tooltips con verbo "Afecta"** en lugar de "Golpea", porque el indicador
   también cubre estados multiobjetivo (Gruñido, Danza Caos).

## Verificación ejecutada

```text
php -l (5 PHP) .......................... OK
vendor/bin/pint --dirty --format agent ... pass
PHPStan (larastan, L6) sobre 5 PHP ...... [OK] No errors
php artisan view:cache + view:clear ...... OK
```

Comprobación funcional read-only con datos reales:

- `Panpour`: Malicioso/Ventisca/Viento Hielo/Voz Cautivadora → `TODOS RIVALES`;
  Surf → `A TODOS`; Día Soleado (`all`) → sin indicador.
- `Bulbasaur`: Gruñido/Hoja Afilada/Dulce Aroma/Disparo Demora/Viento Cortante →
  `TODOS RIVALES`; Día Soleado → sin indicador.

## Criterios de aceptación

| # | Criterio | Estado |
|---|---|---|
| 1 | Avalancha/Ventisca muestran `TODOS RIVALES` | Cumplido (payload verificado) |
| 2 | Surf/Terremoto muestran `A TODOS` con peligro y tooltip de aliados | Cumplido (payload verificado) |
| 3 | Explosión/Autodestrucción muestran `FILA COMPLETA` | Cumplido (enum verificado) |
| 4 | Estados multiobjetivo (Gruñido) se marcan | Cumplido (payload verificado) |
| 5 | Movimientos de un solo objetivo no muestran indicador | Cumplido |
| 6 | Campos de campo/tiempo (`all`) no muestran indicador | Cumplido (ajuste 1) |

Validación visual en navegador pendiente por el desarrollador; si no se refleja,
ejecutar `npm run build` o `npm run dev`.