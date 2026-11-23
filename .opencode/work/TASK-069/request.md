# TASK-069 — Petición

- **ID:** TASK-069
- **Fecha:** 2026-11-19
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** revisión y refactor de `src/` y `app/`.
- **Estado:** IMPLEMENTING.

## Texto literal

> revisa src y app en busca de refactorizaciones, eliminar codigo innecesario o
> repetido, bugs, encapsulamiento necesario, clases extensas...

> Falta autorización de administración en gimnasios, Soft-lock de batalla cuando
> la IA no encuentra acción son correctos, adelante con todos los F

## Alcance autorizado

Plan v1 presentado en la revisión: fases F1 (bugs), F2 (código muerto),
F3 (duplicación), F4 (encapsulamiento), F5 (clases extensas) y F6 (calidad
estática), limitado a los archivos no modificados por la sesión concurrente de
TASK-068.

## Restricciones

- No tocar: `app/Livewire/Presenters/MovesPreviewPresenter.php`,
  `app/Support/ArbolSendas.php`, `app/Support/PresentadorDetalleEspecie.php`,
  `app/Support/ReclutadoSerializer.php`, `src/Battle/Domain/Enums/ObjetivoMovimiento.php`
  ni los Blade modificados por TASK-068.
- No eliminar `src/Avistamientos/**` sin confirmación explícita (módulo WIP).
- No modificar `tests/` ni ejecutarlos.
- No `git push`, no operaciones destructivas de git.
