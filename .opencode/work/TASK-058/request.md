# TASK-058 — Petición

- **ID:** TASK-058
- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección de dos fallos funcionales (reclutamiento y expediciones).
- **Estado:** RECEIVED

## Texto literal

> En reclutamiento no puedo descartar o reclutar, en Expediciones se queda Pendiente de procesar, deberia leerse el evento al abrir el modal o detectarse que ya termino

## Fallo 1 — Reclutamiento

En `/reclutamiento` (y en el modal de la navbar) los botones **Reclutar** y
**Descartar** no completan la acción. No se especifica el síntoma exacto: puede ser
que no ocurra nada, que aparezca un error o que la lista no se actualice.

## Fallo 2 — Expediciones

Al abrir el panel o el detalle de una expedición cuyo tiempo ya terminó, el estado
sigue siendo **«Pendiente de procesar»** (`pendiente_resolucion`) en lugar de
resolverse. El desarrollador propone dos vías:

- procesar/leer el evento al abrir el modal, o
- detectar en la consulta que la expedición ya terminó.

## Restricciones

- No implementar sin plan aprobado.
- No tocar los cambios locales preexistentes de TASK-049 a TASK-057 ni el tema.
- No leer `.env` ni secretos.
- No crear, modificar ni ejecutar tests.
- Sin dependencias nuevas.
