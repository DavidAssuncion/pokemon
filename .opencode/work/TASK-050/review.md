# TASK-050 — Revisión

- **Revisor:** `reviewer` independiente (sesión `ses_e15739c9bffez5sAXsbKEfqN7K`).
- **Fecha:** 2026-11-17.
- **Veredicto v1:** **CHANGES_REQUESTED** → correcciones → **APPROVED**.
- **Veredicto v2:** **APPROVED**.
- **Estado:** revisión cerrada.

## Criterios de aceptación

| # | Criterio | Resultado |
|---|---|---|
| 1 | Gimnasio en `lg`: 4 etapas en fila, líder al final; `sm` 2; móvil 1 | PASS |
| 2 | Ring, descripción y botón "¡Combatir!" intactos; combate igual | PASS |
| 3 | Modal gimnasio `max-w-6xl`, cabecera/footer fijos, cuerpo scrollable | PASS |
| 4 | Leyenda cerrada por defecto, 3 columnas en `xl`, comparación PC intacta | PASS |
| 5 | Tarjetas colapsadas, independientes, `aria-expanded` correcto | PASS |
| 6 | Header con 3 miniaturas/placeholder y sin "Composición" | PASS |
| 7 | Renombrar funciona; modal con 3 slots, vacíos deshabilitados | PASS |
| 8 | 3 PATCH en orden, estado local actualizado, persiste | PASS |
| 9 | Fallo detiene secuencia, modal abierto, error de sección; 1-2 miembros sin 422 | PASS tras corregir el cierre durante el guardado |
| 10 | No se modifican los dos archivos de TASK-049 | PASS |
| 11 | `git diff --check` limpio y solo 4 archivos autorizados | PASS |

## Hallazgos

### MEDIUM (bloqueante, corregido)

**`resources/views/equipos/index.blade.php:1788-1800` y
`resources/views/equipos/partials/modal-editar-equipo.blade.php:127-133` — cierre del
modal permitido durante `edicionGuardando`.**

- `cerrarEdicionEquipo()` no comprobaba `edicionGuardando`; Cancelar, la X, el scrim y
  Escape la invocaban.
- La promesa de `guardarEdicionEquipo` mantiene `const team`, así que al resolverse
  persistía los 3 PATCH y después cerraba el modal, aunque el usuario lo hubiera cancelado.
- **Corrección aplicada:** `if (this.edicionGuardando) return;` al inicio de
  `cerrarEdicionEquipo()` y `:disabled="edicionGuardando"` en Cancelar. Re-revisión:
  CORREGIDA.

### LOW (corregidos)

- **`edicionFeedback` muerto:** eliminado del estado, de sus asignaciones y del bloque
  `role="status"`. Re-revisión: CORREGIDA.
- **Selects deshabilitados de slots vacíos sin nombre accesible:** añadidos
  `aria-label` de postura y objeto. Re-revisión: CORREGIDA.

### NIT (no bloqueantes)

- `_patchEquipo` y `posturaEtiqueta` no figuran en el plan v1; son helpers de
  presentación justificados (eliminan triplicación). Se documentarán en el informe final.
- El error/feedback y las acciones están al final del cuerpo del modal porque
  `<x-ui.modal>` no expone un slot de footer. Desviación cosmética aceptada.

## Fortalezas

- Sin cambios en backend, rutas, casos de uso, dominio ni dependencias.
- Eliminación limpia del estado muerto (`selectedTeamId`, `composicionBadge`,
  `guardarFormacion`, `guardarPosturas`, `guardarItemSlot` y sus flags).
- `itemDe` respeta el draft con `draft !== undefined`, lo que permite limpiar el objeto
  con `''`.
- Mapas de payload solo con slots ocupados y guarda temprana sin miembros: evita 422 y
  borrados silenciosos.
- Header con botones hermanos (sin anidamiento), `aria-label`/`title` por acción.
- Scoping correcto de `<x-ui.modal>` bajo `x-data="favoritosApp()"`, con
  `<template x-if="equipoEnEdicion">` evitando nulos al cerrar.

## Evidencia

- `git status --short`: solo 3 modificados del alcance, 1 partial nuevo, TASK-049 y
  directorios de trabajo.
- `git diff --numstat`: TASK-049 sigue en +74/−44, idéntico al preflight de TASK-050.
- `git diff --check`: limpio.
- `php artisan view:cache` y `view:clear`: OK.
- `node --check` de los scripts extraídos: OK.
- Clases emitidas en `public/build/assets/app-DnhBdSYf.css`: `max-w-6xl`, `max-w-3xl`,
  `max-h-[90vh]`, `min-h-[2.5rem]`, `lg:grid-cols-4`, `sm:grid-cols-3`,
  `xl:grid-cols-3`, `rotate-180`, entre otras.
- Rutas `PATCH teams/{team}/formacion|posturas|items` confirmadas.

## Re-revisión v2

- **Fecha:** 2026-11-17.
- **Veredicto:** **APPROVED**.
- **Criterios 12-20:** PASS.
  - Padding: `leyenda-ivs-evs.blade.php:61` y `:105` con `p-2`; el md5 del partial sin
    los dos `p-2` coincide con el estado v1 revisado, lo que demuestra que no se alteró
    nada más.
  - Tarjeta: `grid grid-cols-2 gap-2`, columnas Vanguardia (primario) y Retaguardia
    (peligro), contadores, sprites de 96, detalle/quitar con bloqueo por exploración,
    "Sin Pokémon" por columna vacía.
  - `miembrosDeFormacion` solo agrupa por `formacionDe`; sin reglas de negocio.
  - Cabecera, colapso, modal de edición y criterios v1 intactos.
- **Hallazgos:** ninguno BLOCKER/HIGH/MEDIUM/LOW. Tres NIT no bloqueantes:
  - `x-cloak` opcional en los placeholders "Sin Pokémon" (ya están bajo el `x-cloak`
    del cuerpo);
  - cabeceras de columna como `<div>` sin rol de encabezado (mejora semántica opcional);
  - `miembrosDeFormacion` no figuraba en el plan v1 (sí en v2; helper de presentación).
- **Evidencia:** `git diff --check` limpio; `view:cache`/`view:clear` OK; CSS emitido
  (`app-B89k3RW1.css`) con `p-2` y `grid-cols-2`; TASK-049 intacto en +74/−44.
- **Nota de coordinación:** el árbol contiene cambios PHP de otra tarea concurrente
  (TASK-051/053) ajenos a TASK-050. El commit debe limitarse a los archivos de TASK-050.

## Riesgos residuales

- La verificación visual (320/640/768/1024/1280 px) y de teclado queda pendiente de
  comprobación manual del desarrollador.
- Dependencia funcional de los cambios locales de TASK-049 para la leyenda colapsable y
  de 3 columnas. Si se revirtieran, la leyenda perdería esas dos propiedades.
- Durante el guardado el usuario no puede abortar (por diseño); si una petición quedara
  colgada por red, el modal permanecería bloqueado hasta que `fetch` resuelva o falle.
  No hay timeout explícito, igual que en el resto de la vista.
- La auditoría de seguridad se omite por decisión explícita del desarrollador
  (ver `security.md`).

## Re-revisión (correcciones)

- **Fecha:** 2026-11-17.
- **Veredicto:** **APPROVED**.
- **Correcciones verificadas:** guarda en `cerrarEdicionEquipo()`, Cancelar deshabilitado
  durante el guardado, `edicionFeedback` eliminado sin referencias y `aria-label` en los
  selects deshabilitados de slots vacíos.
- **Regresiones:** ninguna nueva. Sin hallazgos MEDIUM/HIGH/LOW nuevos.
- **Evidencia:** `git diff --check` limpio; `view:cache`/`view:clear` OK; `node --check`
  OK; `edicionFeedback` sin referencias; TASK-049 intacto en +74/−44.
- **Riesgo aceptado:** los controles de cierre quedan inertes durante el guardado por
  diseño; el guardado dura milisegundos y no hay timeout explícito.

## Archivos modificados

**Ninguno** (revisor de solo lectura).
