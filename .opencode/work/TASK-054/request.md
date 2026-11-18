# TASK-054 — Petición

- **ID:** TASK-054
- **Fecha:** 2026-11-17
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección de presentación (UI) en equipos y hábitats.
- **Estado:** RECEIVED

## Texto literal

> en /equipos estan al reves los pokemon, vanguardia va a la derecha
> en habitats elimina "Entrenador de ruta" de los combates de ruta, no aplica es contra salvakes unicamente

## Interpretación inicial

1. **`/equipos`:** en el cuerpo desplegado de la tarjeta de equipo, el orden de las
   columnas está invertido respecto al layout de combate. La columna **Vanguardia** debe
   quedar a la **derecha** (y Retaguardia a la izquierda), como en el campo de batalla.
   Afecta a `resources/views/equipos/partials/panel-equipos.blade.php` (implementación de
   TASK-050 v2).
2. **Hábitats:** en los combates de ruta debe eliminarse la etiqueta/denominación
   "Entrenador de ruta", porque el combate de ruta es contra Pokémon salvajes y esa
   etiqueta no aplica. Localizar dónde se genera o presenta ese texto (vista, presenter o
   dominio) antes de decidir el alcance.

## Restricciones conocidas

- Sin tests: no crear, modificar ni ejecutar la suite.
- Sin dependencias nuevas.
- No revertir los cambios locales de otras tareas concurrentes (TASK-049, TASK-051,
  TASK-052, TASK-053) presentes en el árbol.
- No tocar backend salvo que el análisis demuestre que la etiqueta se origina en dominio
  o presentador; en ese caso se delimitará y se pedirá aprobación.
- No leer `.env` ni secretos.

## Ambigüedades a resolver por `analyst`

- ¿El orden de columnas del combate es Retaguardia | Vanguardia (vanguardia a la derecha)
  en el campo de batalla? ¿El cambio consiste solo en invertir el orden de las dos
  columnas de la tarjeta?
- ¿Dónde aparece exactamente "Entrenador de ruta" y en qué contextos? ¿Solo en combates
  de ruta o también en otros combates de hábitat? ¿Es un texto de vista o lo genera el
  backend?
