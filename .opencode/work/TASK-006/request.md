# TASK-006 — Correcciones UI: nombre del reclutado en hábitat y layout de recompensas de combate

## Petición literal

> El reclutado no se borro, revisa el error. Las recompensas de un combate deben tener el mismo formato que en las exploraciones, columna de capturas a 1/3 y otra de 2/3 con 3 filas de caramelos (si hubiese captura) si no, full width

## Interpretación operativa

1. **«Reclutado eliminado» en el hábitat**: el reclutado no se ha borrado. La vista de hábitat muestra ese texto cuando `$exp->reclutado->nombre` es `null`, aunque la relación `reclutado` exista. Hay que usar el nombre del Pokémon como fallback y reservar «Reclutado eliminado» para cuando la relación sea realmente nula.
2. **Recompensas de combate**: el modal de recompensas de ruta debe usar el mismo formato que el grid de Exploraciones:
   - Si hay capturas: columna de capturas a 1/3 y columna de caramelos a 2/3.
   - La columna de caramelos muestra 3 filas: familia, EV y tipo.
   - Si no hay capturas: la columna de caramelos ocupa el ancho completo.
   - Si hay capturas pero no caramelos: capturas a ancho completo.

## Contexto

- Proyecto `/home/david/pokemon`, Laravel 12 / PHP 8.4, rama `master`, WIP extenso.
- El texto «Reclutado eliminado» está en `resources/views/habitats/show.blade.php:160`.
- El modal de recompensas de combate está en `resources/views/livewire/combate.blade.php` (rama `layout === 'ruta'`).
- El presentador de recompensas es `app/Livewire/Presenters/BatallaVistaPresenter.php` (`rewardsVista()`).
- El grid de Exploraciones usa `_caramelo` y columnas de capturas/caramelos.

## Restricciones

- No cambiar la regla de recompensas ni el cálculo de capturas/caramelos.
- No tocar migraciones ni dependencias.
- No revertir WIP ajeno.
- Mantener el estilo Tailwind/Bootstrap existente.
- Tests al cierre del módulo.

## Criterios de aceptación preliminares

- Un reclutado con `nombre=null` y Pokémon existente muestra el nombre del Pokémon, no «Reclutado eliminado».
- «Reclutado eliminado» solo aparece si la relación `reclutado` es realmente nula.
- El modal de recompensas de ruta muestra capturas 1/3 + caramelos 2/3 cuando hay capturas; caramelos full width si no hay capturas.
- Los caramelos se agrupan en 3 filas (familia/EV/tipo).
- Tests de hábitat y combate en verde.
