# TASK-049 — Revisión

- **Revisor:** `reviewer` independiente.
- **Fecha:** 2026-11-16.
- **Veredicto:** **APPROVED** (tras corregir el hallazgo LOW de los botones).

## Criterios de aceptación

| # | Criterio | Resultado |
|---|---|---|
| 1 | Modal `max-w-6xl`, `max-h-[90vh]`, cuerpo scrollable, botones visibles | PASS |
| 2 | Leyenda cerrada por defecto, `<summary>` accesible, chevron | PASS |
| 3 | 3 columnas desde `xl`, 1 por debajo, sin scroll de página | PASS |
| 4 | Gimnasio: `max-w-md`, apilado, leyenda expandida | PASS |
| 5 | Estado de carga/error/perfil en el summary | PASS |
| 6 | `npm run build` y clases en el CSS emitido | PASS |
| 7 | Solo 2 archivos; sin tests ni backend | PASS |
| 8 | Verificación visual manual 360/768/1024/1440, teclado, red lenta | PENDIENTE (desarrollador) |

## Hallazgos

- **LOW 1 (resuelto):** los botones del footer se estiraban a ~546 px con el modal de
  1152 px. Ahora el footer es `flex-col` en móvil y `sm:flex-row sm:justify-end` en
  escritorio; los botones son `w-full` en móvil y `sm:w-auto sm:px-6` en escritorio.
  Verificado en el CSS emitido (`sm:flex-row`, `sm:justify-end`, `sm:w-auto`, `sm:px-6`).
- **LOW 2 (abierto, no bloqueante):** la indentación de los cierres del condicional
  `@if ($colapsable)` en `leyenda-ivs-evs.blade.php:149-152` es mejorable. El DOM
  compilado está balanceado y `php artisan view:cache` pasa.
- **NIT 3:** la rama de gimnasio envuelve el `x-for` en un `div.grid` de una columna;
  el espaciado visual es equivalente (`gap-3` = `space-y-3`).
- **NIT 4:** `$columnas >= 3` solo distingue 1 vs 3; no hay otros llamadores.
- **NIT 5:** doble `aria-live` (summary + cuerpo) puede duplicar anuncios con la
  leyenda abierta; se acepta como mitigación del estado cerrado.

## Evidencia

- `git status --short`: solo los dos archivos autorizados + `.opencode/work/TASK-049/`.
- `git diff --stat`: 2 archivos, +72/−42; `git diff --check` limpio.
- `npm run build`: OK; CSS `public/build/assets/app-DYqH_YGd.css`.
- Clases compiladas: `max-w-6xl`, `max-h-[90vh]`, `xl:grid-cols-3`,
  `group-open:rotate-180`, `[&::-webkit-details-marker]:hidden`, `sm:flex-row`,
  `sm:justify-end`, `sm:w-auto`, `sm:px-6`.
- `php artisan view:cache`: OK; `php artisan view:clear`: OK.
- `gimnasios/show.blade.php:222` sigue con `@include('partials.leyenda-ivs-evs')`
  sin parámetros y su panel sigue en `max-w-md` (`:153`).

## Riesgos residuales

- La verificación visual real (360/640/768/1024/1280/1440, teclado y red lenta) no
  se ha ejecutado; queda pendiente de la comprobación del desarrollador.
- El estado abierto/cerrado de la leyenda no persiste al cerrar y reabrir el modal.
- `aria-live` duplicado con la leyenda abierta (NIT).
