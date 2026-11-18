# TASK-054 — Plan v1

- **ID:** TASK-054
- **Versión:** v1
- **Fecha:** 2026-11-17
- **Proyecto:** `/home/david/pokemon`
- **Estado:** AWAITING_APPROVAL

## Objetivo

Aplicar dos correcciones de presentación solicitadas por el desarrollador:

1. En `/equipos`, invertir el orden de las columnas de la tarjeta desplegada:
   **Retaguardia a la izquierda y Vanguardia a la derecha**, como en el campo de combate.
2. En `/habitats`, en **modo ruta** (combate contra Pokémon salvajes), eliminar el bloque
   completo de clase base del hábitat: rótulo "Entrenador de ruta", sprite y nombre de
   clase. El modo `entrenadores` no se toca.

## Alcance

### 1. Inversión de columnas en la tarjeta de equipo

- Archivo: `resources/views/equipos/partials/panel-equipos.blade.php`.
- Mover el bloque completo de **Retaguardia** (`195-240`) antes del bloque de
  **Vanguardia** (`148-193`) dentro del `div.grid.grid-cols-2` (línea 147).
- El contenido de cada bloque no cambia: cabecera con nombre y contador, sprites de 96,
  nombre, `Slot N · postura`, objeto, botón de quitar con bloqueo por exploración, acceso
  al detalle y estado "Sin Pokémon".
- Los comentarios Blade `{{-- Vanguardia --}}` y `{{-- Retaguardia --}}` viajan con sus
  bloques.
- No se usan `order-*` ni CSS nuevo: se corrige el orden real del DOM, que es también el
  orden de lectura para tecnologías de asistencia.
- La cabecera de la tarjeta, el colapso, el modal de edición y los helpers de
  `equipos/index.blade.php` no se tocan.

### 2. Retirada del bloque de clase base en hábitats (modo ruta)

- Archivo: `resources/views/habitats/partials/columna-derecha.blade.php`.
- Eliminar el bloque `219-237`:
  - comentario `<!-- Clase base del hábitat (solo ruta) -->`;
  - `div` con `x-show="modo === 'ruta'"` que muestra el sprite;
  - rótulo "Entrenador de ruta";
  - nombre de clase (`spriteAlt(claseBaseHabitat()) || 'Entrenador'`).
- El panel de niveles de ruta (`238` en adelante) permanece intacto y pasa a ser el
  primer contenido de la tarjeta.
- Archivo: `resources/views/habitats/show.blade.php`.
- Eliminar el helper `claseBaseHabitat()` (`97-102`), que queda sin uso. `spriteUrl()` y
  `spriteAlt()` se conservan porque se usan en el modo entrenadores y en otras vistas.
- No se toca el modo `entrenadores`, sus tarjetas ni el botón de combate contra
  entrenador.

## Fuera de alcance

- Backend, rutas, controladores, dominio, migraciones, datos y dependencias.
- `battle-field.blade.php`, `combate.css`, `equipos/index.blade.php`,
  `modal-editar-equipo.blade.php`, `modal-formacion.blade.php`, `gimnasios/show.blade.php`.
- Cambios en los sprites de entrenadores, manifiesto o assets de TASK-053.
- Tests: no se crean, modifican ni ejecutan.
- Revertir o reformatear cambios locales de TASK-049, TASK-051, TASK-052 o TASK-053.

## Archivos y responsables

| Archivo | Acción | Responsable | Líneas |
|---|---|---|---|
| `resources/views/equipos/partials/panel-equipos.blade.php` | Intercambiar bloques Retaguardia/Vanguardia | `frontend` | 147-241 |
| `resources/views/habitats/partials/columna-derecha.blade.php` | Eliminar bloque de clase base en modo ruta | `frontend` | 219-237 |
| `resources/views/habitats/show.blade.php` | Eliminar helper `claseBaseHabitat()` sin uso | `frontend` | 97-102 |

Sin `backend`, `database`, `architect` ni `documenter`. `reviewer` revisará el diff.
`security` no aplica (solo presentación, sin entradas ni datos sensibles).

## Orden de ejecución y dependencias

1. Confirmar que TASK-051 (en `REVIEWING`) no está escribiendo en
   `panel-equipos.blade.php`; si su revisión pide correcciones, coordinar antes.
2. `panel-equipos.blade.php`: mover el bloque de Retaguardia antes del de Vanguardia.
3. `columna-derecha.blade.php`: eliminar el bloque de clase base.
4. `habitats/show.blade.php`: eliminar `claseBaseHabitat()`.
5. `php artisan view:cache` y `view:clear`.
6. `git diff --check`, `git status --short`, `git diff --stat`.
7. Revisión independiente.

## Criterios de aceptación verificables

1. En `/equipos`, con la tarjeta desplegada, la columna **Retaguardia** aparece a la
   izquierda y **Vanguardia** a la derecha, igual que en el campo de batalla.
2. Cada columna conserva su contenido y comportamiento: cabecera con nombre y contador,
   sprite de 96, nombre, `Slot N · postura`, objeto, quitar con bloqueo por exploración,
   apertura del detalle y "Sin Pokémon" cuando está vacía.
3. La cabecera de la tarjeta (3 miniaturas, nombre, badges, chevron y acciones), el
   colapso y el modal de edición siguen intactos.
4. En `/habitats`, modo ruta, no aparece "Entrenador de ruta", ni el sprite de clase, ni
   el nombre de clase; la tarjeta muestra directamente el panel de niveles.
5. El modo `entrenadores` conserva sus tarjetas y etiquetas sin cambios.
6. `claseBaseHabitat` no tiene referencias en el código; `spriteUrl` y `spriteAlt`
   mantienen sus usos actuales.
7. No hay cambios en archivos fuera de los tres autorizados (aparte de los cambios
   preexistentes de otras tareas).
8. `git diff --check` limpio.
9. `php artisan view:cache` y `view:clear` terminan sin error.
10. Sin tests.

## Verificación requerida

Sin tests.

- `php artisan view:cache` y `php artisan view:clear`.
- `git diff --check`, `git status --short`, `git diff --stat`.
- `git diff` de los tres archivos para confirmar que solo se mueve/elimina lo previsto.
- `grep -rn "Entrenador de ruta" resources/views` sin resultados.
- `grep -rn "claseBaseHabitat" resources/views` sin resultados.
- Comprobación manual:
  - `/equipos` con formaciones 3-0, 2-1, 1-2 y 0-3; tarjeta colapsada y desplegada;
  - `/habitats/{id}` en modo ruta y en modo entrenadores;
  - 320 px y escritorio; teclado/foco.

## Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| TASK-051 en revisión puede pedir cambios en `panel-equipos.blade.php` | Conflicto de edición | Verificar que su implementación está cerrada; si hay correcciones, aplicarlas antes o después, nunca en paralelo |
| El orden DOM cambia el orden de lectura de lectores de pantalla | Cambio deliberado | Es el orden visual correcto solicitado; las cabeceras de columna identifican cada grupo |
| Eliminar `claseBaseHabitat()` rompe un uso no detectado | Error JS | `grep` confirma un único uso en el bloque eliminado; comprobar de nuevo tras el cambio |
| Eliminar el bloque contradice la decisión de TASK-053 | Regresión de diseño | Decisión explícita del desarrollador en TASK-054; registrar en el informe |
| Caché de vistas con el texto antiguo | El cambio no se ve | `view:clear` y recarga |

## Rollback

- Restaurar el orden original de los bloques y el bloque de clase base, y reinsertar
  `claseBaseHabitat()`.
- No hay cambios de datos, esquema, dependencias ni assets; no requiere migración ni
  build.

## Aprobación

Este plan no autoriza implementación hasta que el desarrollador responda con una
aprobación explícita que mencione la versión (`Aprobar plan v1` o equivalente).
