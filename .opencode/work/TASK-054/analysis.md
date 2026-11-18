# TASK-054 — Análisis

- **Analista:** `analyst` (sesión `ses_e14c622c9ffezsZs2gmQH9gDmS`).
- **Fecha:** 2026-11-17.
- **Nota:** el analista alcanzó el límite de pasos antes de emitir el informe con formato
  completo; se registran a continuación sus hallazgos y evidencias, sin alterar sus
  conclusiones. No modificó archivos.

## A) Orden Vanguardia/Retaguardia en `/equipos`

- **A1 — Ubicación.** `resources/views/equipos/partials/panel-equipos.blade.php:147`
  `<div class="grid grid-cols-2 gap-2">`; bloque Vanguardia en `148-193` (cabecera
  `bg-primary-soft` en `150-153`); bloque Retaguardia en `195-240` (cabecera
  `bg-danger-soft` en `197-200`). No hay posicionamiento absoluto que dependa del bloque
  vecino; solo la «✕» usa `absolute` relativa a su propia tarjeta.
- **A2 — Layout de combate.** `resources/views/livewire/partials/battle-field.blade.php`
  `21-30`: columna 1 = jugador **Retaguardia**; `32-41`: columna 2 = jugador
  **Vanguardia**; `43-52` rival vanguardia; `54-63` rival retaguardia. No hay
  `display:reversed` ni `order` en `resources/css/combate.css:463-522`. Por tanto, en
  combate la Vanguardia queda a la derecha.
- **A3 — Cambio mínimo.** Intercambiar físicamente los dos bloques en el DOM (mover
  `195-240` antes de `148-193`). No hay reglas CSS ni `order-*` activas en ese grid, ni JS
  que lea el orden de hijos. Alternativa con `order-1`/`order-2` descartada por empeorar
  el orden de lectura accesible.
- **A4 — Coherencia.** `modal-editar-equipo.blade.php:39-56` y
  `habitats/partials/modal-formacion.blade.php:30-36` son toggles por slot, no columnas
  posicionales. `gimnasios/show.blade.php:85-88` mantiene el orden 1→4 con el líder a la
  derecha. Ninguno requiere cambios.

## B) "Entrenador de ruta"

- **B5 — Aparición única.** `resources/views/habitats/partials/columna-derecha.blade.php:234`:
  `<p class="text-2xs uppercase tracking-wide text-ink-subtle">Entrenador de ruta</p>`.
  Sin variantes en `EntrenadorDeRuta`, `entrenador_ruta`, `src`, `app`, `lang` ni
  `resources/js`. Solo reaparece en la vista compilada de `storage/framework/views`.
- **B6 — Contexto.** Es el encabezado del bloque de clase base del hábitat en **modo
  ruta** (`x-show="modo === 'ruta'"`, línea 220), con sprite (`spriteUrl(claseBaseHabitat())`,
  `221-232`) y nombre de clase (`spriteAlt(claseBaseHabitat()) || 'Entrenador'`, `235`).
  El modo `entrenadores` tiene su propio panel independiente (`301-351`, «Entrenador N» en
  `341`). Es texto literal Blade; no hay dominio, presenter ni traducción implicados.
- **B7 — Cambio.** El desarrollador decidió en TASK-054 eliminar el **bloque completo**
  (`219-237`): rótulo, sprite y nombre de clase. Sustituir por etiqueta neutra o dejar
  solo el rótulo fueron opciones descartadas.
- **B8 — Backend.** No aplica. El cambio es 100 % presentación.

## C) Restricciones y alcance

- **C9 — Archivos a modificar:**
  1. `resources/views/equipos/partials/panel-equipos.blade.php` (solo el cuerpo del grid,
     `147-241`).
  2. `resources/views/habitats/partials/columna-derecha.blade.php` (bloque `219-237`).
  3. `resources/views/habitats/show.blade.php` — el helper `claseBaseHabitat()`
     (`97-102`) queda sin uso tras retirar el bloque; se elimina como limpieza
     consecuente.
  - **No tocar:** `battle-field.blade.php`, `combate.css`, `equipos/index.blade.php`,
    `modal-editar-equipo.blade.php`, `modal-formacion.blade.php`,
    `gimnasios/show.blade.php`, `src/`, `app/`, `routes/`,
    `resources/data/entrenadores_sprites.json`, assets de entrenadores.
- **C10 — Dependencias concurrentes:**
  - **TASK-051** está en `REVIEWING` (implementación completada) y su ejecución incluye
    `panel-equipos.blade.php`; riesgo de conflicto si la revisión pide correcciones.
  - **TASK-053** introdujo el bloque «Entrenador de ruta» y el sprite de ruta; su decisión
    queda reemplazada por la elección explícita del desarrollador en TASK-054.
  - TASK-049/050/052 no tocan estos archivos (050 sí `panel-equipos`, ya implementado).
  - Todo el árbol está sin commitear; se edita sobre el estado actual sin revertir nada.

## Riesgos

- **Alto:** colisión con TASK-051 en `panel-equipos.blade.php` si su revisión solicita
  cambios mientras TASK-054 edita.
- **Medio:** eliminar el bloque completo deja el panel de ruta solo con niveles; es lo
  solicitado, pero cambia el diseño introducido en TASK-053.
- **Bajo:** la caché de vistas compiladas mantiene el texto antiguo hasta
  `view:clear`/recompilación.
- **Bajo:** retirar `claseBaseHabitat()` podría afectar a usos no detectados; `grep`
  confirma que solo se usaba en el bloque eliminado.
